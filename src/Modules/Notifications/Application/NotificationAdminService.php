<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

use Vaqtyar\Modules\Notifications\Domain\Template;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\Page;

/**
 * The admin's view of notifications: templates to edit and the log of what
 * was sent. Each call checks the capability again after the REST permission
 * callback (architecture §12).
 *
 * @phpstan-import-type Entry from NotificationLog
 */
final class NotificationAdminService
{
    public const CAPABILITY = 'manage_notifications';

    /**
     * @param list<string> $channels the ids of the registered channels.
     */
    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly TemplateRepository $templates,
        private readonly NotificationLog $log,
        private readonly array $channels,
    ) {
    }

    /**
     * @return list<Template>
     */
    public function templates(): array
    {
        $this->authorize();

        return $this->templates->all();
    }

    /**
     * @throws InvalidValue unknown_channel; NotFound template_not_found for an id that is not stored.
     */
    public function save(Template $template): Template
    {
        $this->authorize();
        if (!\in_array($template->channel, $this->channels, true)) {
            throw new InvalidValue('unknown_channel', 'No channel has this id.');
        }
        if (null !== $template->id && null === $this->templates->find($template->id)) {
            throw new NotFound('template_not_found', 'No template has this id.');
        }

        return $this->templates->save($template);
    }

    /**
     * @throws NotFound template_not_found
     */
    public function delete(int $id): void
    {
        $this->authorize();
        if (null === $this->templates->find($id)) {
            throw new NotFound('template_not_found', 'No template has this id.');
        }
        $this->templates->delete($id);
    }

    /**
     * @return Page<Entry>
     */
    public function log(int $offset, int $limit): Page
    {
        $this->authorize();

        return $this->log->page($offset, $limit);
    }

    private function authorize(): void
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
    }
}
