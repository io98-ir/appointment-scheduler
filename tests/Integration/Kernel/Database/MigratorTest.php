<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Kernel\Database;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\DbException;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Database\Migrator;
use Vaqtyar\Kernel\Options;
use Vaqtyar\Kernel\Tables;

/**
 * Real DDL, the InnoDB check against information_schema, and GET_LOCK.
 */
final class MigratorTest extends TestCase
{
    use RealDatabase;

    private Db $db;
    private Migrator $migrator;

    /** @var ArrayObject<int, string> */
    private ArrayObject $ran;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->realDb();
        $this->migrator = new Migrator($this->db);
        $this->ran = new ArrayObject();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    public function testCreatesInnoDbTablesAndRecordsTheVersion(): void
    {
        self::assertTrue($this->migrator->migrate(['test' => [$this->createItems(), $this->addNote()]]));

        self::assertSame('InnoDB', $this->engineOf(Tables::name('test_items')));
        self::assertTrue($this->hasNoteColumn());
        self::assertSame(['test' => 2], \get_option(Options::key('db_versions')));
    }

    public function testASecondRunRunsNothing(): void
    {
        $migrations = ['test' => [$this->createItems()]];
        $this->migrator->migrate($migrations);
        $this->migrator->migrate($migrations);

        self::assertSame(['create items'], $this->ran->getArrayCopy());
        self::assertTrue($this->migrator->isCurrent($migrations));
    }

    public function testMigrationsAreIdempotent(): void
    {
        // A failure after DDL means the migration runs again from the start.
        $this->createItems()->up($this->db);
        $this->addNote()->up($this->db);
        $this->createItems()->up($this->db);
        $this->addNote()->up($this->db);

        self::assertSame('InnoDB', $this->engineOf(Tables::name('test_items')));
        self::assertTrue($this->hasNoteColumn());
    }

    public function testRefusesAnExistingTableThatIsNotInnoDb(): void
    {
        $this->db->execute(
            'CREATE TABLE %i (id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (id)) ENGINE=MyISAM',
            Tables::name('test_items')
        );

        try {
            $this->migrator->migrate(['test' => [$this->createItems()]]);
        } catch (DbException $e) {
            self::assertStringContainsString('MyISAM', $e->getMessage());
            self::assertFalse(\get_option(Options::key('db_versions')));

            return;
        }
        self::fail('A MyISAM table was accepted.');
    }

    public function testDoesNotRunWhileAnotherConnectionHoldsTheLock(): void
    {
        $other = $this->otherConnection();
        $name = $other->real_escape_string('.' . Tables::name('migrate'));
        $other->query("SELECT GET_LOCK(SHA1(CONCAT(DATABASE(), '{$name}')), 0)");

        try {
            self::assertFalse($this->migrator->migrate(['test' => [$this->createItems()]]));
            self::assertSame([], $this->ran->getArrayCopy());
        } finally {
            // Released explicitly: close() returns before the server has ended
            // the session, so a GET_LOCK with timeout 0 right after may still
            // find the lock held (flaky on CI, run 36149811656).
            $other->query("SELECT RELEASE_LOCK(SHA1(CONCAT(DATABASE(), '{$name}')))");
            $other->close();
        }

        // The other request has finished.
        self::assertTrue($this->migrator->migrate(['test' => [$this->createItems()]]));
        self::assertSame(['create items'], $this->ran->getArrayCopy());
    }

    public function testReadsTheVersionsFromTheDatabaseUnderTheLock(): void
    {
        // This request has the (missing) option cached, as every request does.
        self::assertFalse(\get_option(Options::key('db_versions')));

        // Meanwhile another request ran the migration and saved the version.
        $other = $this->otherConnection();
        $other->query(\sprintf(
            "INSERT INTO `%s` (option_name, option_value, autoload) VALUES ('%s', '%s', 'yes')",
            $this->wpdb()->options,
            $other->real_escape_string(Options::key('db_versions')),
            $other->real_escape_string(\serialize(['test' => 1]))
        ));
        $other->close();

        $this->migrator->migrate(['test' => [$this->createItems()]]);

        self::assertSame([], $this->ran->getArrayCopy());
    }

    private function createItems(): Migration
    {
        return new class ($this->ran) implements Migration {
            /**
             * @param ArrayObject<int, string> $ran
             */
            public function __construct(private readonly ArrayObject $ran)
            {
            }

            public function up(Db $db): void
            {
                $this->ran->append('create items');
                $db->createTable(
                    Tables::name('test_items'),
                    'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (id)'
                );
            }
        };
    }

    private function addNote(): Migration
    {
        return new class implements Migration {
            public function up(Db $db): void
            {
                $table = Tables::name('test_items');
                $exists = $db->getVar(
                    'SELECT 1 FROM information_schema.COLUMNS'
                    . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                    $table,
                    'note'
                );
                if (null === $exists) {
                    $db->execute('ALTER TABLE %i ADD COLUMN note VARCHAR(20) NULL', $table);
                }
            }
        };
    }

    private function hasNoteColumn(): bool
    {
        return '1' === $this->db->getVar(
            'SELECT 1 FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
            Tables::name('test_items'),
            'note'
        );
    }

    private function engineOf(string $table): ?string
    {
        return $this->db->getVar(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
            $table
        );
    }

    private function cleanUp(): void
    {
        $this->dropTable(Tables::name('test_items'));
        \delete_option(Options::key('db_versions'));
    }
}
