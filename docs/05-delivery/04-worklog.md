# Worklog

> گزارش زمانی همه کارها. **جدیدترین ورودی بالاست.** این فایل Append-only است: ورودی‌های قبلی ویرایش نمی‌شوند، فقط اصلاحیه اضافه می‌شود.
>
> قالب هر ورودی:
> ```
> ## YYYY-MM-DD — سشن N — <عنوان کوتاه>
> **Taskها:** T…
> **انجام شد:** …
> **تصمیم‌ها و فرض‌ها:** …  (هر فرضی که کاربر باید بداند)
> **تأیید:** دستورهایی که اجرا شد و نتیجه (مثلاً `composer test` ← 124 passed)
> **مشکلات و باقیمانده:** …
> **قدم بعدی:** …
> **Commitها:** <hash> <message>
> ```

---

## 2026-09-25 — سشن 7 (ادامه) — CI برای T0.9 و تست flaky
**Taskها:** T0.9، T0.7
**انجام شد:** push `84764c3` به درخواست کاربر. run قبلی (36149811656، commit `f54f8c8` که فقط سند بود) در Integration (PHP 8.4، WP 6.6) قرمز شده بود: `MigratorTest::testDoesNotRunWhileAnotherConnectionHoldsTheLock`. علت یک race در خود تست است: `mysqli::close()` قبل از پایان session در سرور برمی‌گردد و `GET_LOCK(…, 0)` قفل را هنوز گرفته می‌بیند. تست حالا قفل را قبل از close با `RELEASE_LOCK` آزاد می‌کند. تله در implementation-notes §5 ثبت شد.
**تأیید:** run 36152711861 روی `84764c3` ← هر 9 job سبز. Integration روی {8.1، 8.4} × {6.6، latest} ← `OK (52 tests, 164 assertions)`، یعنی هر 9 تست جدید T0.9 از اولین اجرا پاس شدند. Unit روی 8.1 و 8.4 ← 383 تست OK. رفع تست flaky با push بعدی در CI دیده می‌شود.
**قدم بعدی:** T0.11.
**Commitها:** `test(kernel): release the migration lock explicitly in the lock test`
---

## 2026-09-25 — سشن 7 — T0.9 Settings، SecretStore، Logger، Caps
**Taskها:** T0.9
**انجام شد:**
- `src/Kernel/Settings/`: `SettingsGroup` (interface)، `Settings` (get/save، یک option برای هر گروه)، `GeneralSettings` (تقویم و ارقام).
- `src/Kernel/SecretStore.php`: ثابت wp-config با اولویت، رمزنگاری AEAD با نام option به‌عنوان AD، و لاگ خطا وقتی مقدار قابل رمزگشایی نیست.
- `src/Kernel/Log/`: `Logger`، `LogLevel`، `Pii`، و `CreateLogsTable` (دومین migration با owner `kernel`).
- `src/Kernel/Capabilities.php` و `Module::capabilities()`.
- `Plugin`: ثبت `Logger`، `Settings`، `SecretStore` و `DateFormatter` در Container، و اجرای grant در `activate()` و در `boot()` بعد از migration.
- `Router`: خطای 500 با `Logger` ثبت می‌شود. `Db::inTransaction()`.
- تست‌ها:
  - Unit: `PiiTest`، `LoggerTest`، `SettingsTest`، `SecretStoreTest`، `CapabilitiesTest`، `PluginTest`، و fixture `LargeSettings`.
  - Integration: `LoggerTest`، `SettingsTest`، `SecretStoreTest`، `CapabilitiesTest`، و به‌روزرسانی `RouterTest` (خطای 500 حالا در جدول `logs` بررسی می‌شود) و `PaginationTest`.
- اسناد: implementation-notes §6.1 (جدید)، §5 و §6، و data-model (`logs`).

**تصمیم‌ها و فرض‌ها:**
- **`SettingsGroup` یک interface است، با یک پیاده‌سازی واقعی.** دلیل: مرز هر ماژول برای تنظیمات خودش است (Catalog، Booking، Payments، Notifications، White-label). principles §0 را بازبینی کردم. بدون آن، `Settings::get()` نمی‌تواند typed باشد.
- **کلید SecretStore از `wp_salt('auth')` مشتق می‌شود، نه مستقیم از `AUTH_KEY`.** این هم AUTH_KEY را دارد (ADR-014) و هم سایتی را که AUTH_KEY تعریف نکرده پوشش می‌دهد.
- **خرابی Migration همچنان به `error_log` می‌رود، نه Logger** (برخلاف یادداشت T0.7). دلیل: DB همان چیزی است که شکسته و notice مدیر را به لاگ PHP می‌فرستد.
- **Retention لاگ:** 30 روز (ثابت) و prune بعد از هر نوشتن، مثل `RateLimiter`. Job جدا یا تنظیمات برای آن ساخته نشد (principles §0).
- **ماسک PII در Logger با الگو است:** ایمیل و رشته‌های 8 رقمی یا بیشتر. نام و آدرس تشخیص داده نمی‌شوند و این در سند آمده است. timestamp یونیکس هم ماسک می‌شود.
- **نقش‌های خود افزونه** (Manager، Receptionist، Staff) ساخته نشدند، چون هنوز هیچ capability واقعی وجود ندارد. با M1 تا M3 ساخته می‌شوند. Kernel خودش capability ندارد.
- `SecretStore::mask()` به توصیه reviewer حذف شد و با صفحه تنظیمات secretها می‌آید.

**Review:** subagent `reviewer` هفت مورد پیدا کرد، هیچ‌کدام blocker نبود:
- رفع شد: خط fallback در `error_log` context را نداشت. برای 500 در REST این یعنی از دست رفتن کلاس، فایل و خط.
- رفع شد: ثابت عددی wp-config (terminal id) بی‌صدا null می‌شد.
- رفع شد: کلیدهای آرایه context ماسک نمی‌شدند.
- رفع شد: شماره داخل پرانتز، مثل `(0912) 123 4567`، ماسک نمی‌شد.
- رفع شد: prune داخل تراکنش رزرو قفل ردیف می‌گرفت. حالا با `Db::inTransaction()` رد می‌شود.
- مستند شد: `GeneralSettings` تا اولین ذخیره یک کوئری در هر درخواست دارد (Onboarding در T6.1 آن را ذخیره می‌کند).
- حذف شد: `SecretStore::mask()` بدون مصرف‌کننده.

**تأیید:**
- `composer check` ← exit 0:
  - phpcs: پاک
  - PHPStan: No errors
  - deptrac: 0 violation در هر دو فایل. هر 25 مورد uncovered داخل stubهای وردپرس است.
  - PHPUnit: OK (383 tests, 11926 assertions)
- `composer test:rename` ← OK.
- **Integration محلی اجرا نشد** (Docker محلی نداریم). 9 تست جدید و تغییر `RouterTest` فقط در CI بررسی می‌شوند.

**مشکلات و باقیمانده:** نتیجه CI دیده نشده است.
**قدم بعدی:** push و بررسی CI. سپس T0.11.
**Commitها:** `feat(kernel): settings, secret store, logger and capabilities (T0.9)`
---

## 2026-09-25 — سشن 6 (ادامه) — CI برای T0.8
**Taskها:** T0.8
**انجام شد:** push `6513c8e` و بستن T0.8 در Tracker.
**تأیید:** run 36149430470 ← هر 9 job سبز شدند. Integration روی {8.1، 8.4} × {6.6، latest} ← `OK (43 tests, 149 assertions)`، یعنی هر 24 تست جدید (Router، Pagination، RateLimiter روی MySQL واقعی) از اولین اجرا پاس شدند.
**قدم بعدی:** T0.9.
**Commitها:** `docs: close T0.8 in the tracker`
---

## 2026-09-25 — سشن 6 — T0.8 REST base
**Taskها:** T0.8
**انجام شد:**
- `src/Kernel/Rest/`:
  - `Router`: ثبت route زیر `Identity::REST_NAMESPACE`، مرز خطا، Envelope و `request_id`. `Router::ANYONE` فقط روی GET و همراه rate limit پذیرفته می‌شود.
  - `ApiError` و `RateLimit`.
  - `RateLimiter`: fixed window در جدول `rate_limits`، با یک upsert اتمی و `LAST_INSERT_ID(expr)`.
  - `ClientIp`: `REMOTE_ADDR`، فیلتر `rest/client_ip`، IPv6 با /64.
  - `Pagination`.
  - `CreateRateLimitsTable`.
- `Kernel\RequestId`.
- `Plugin`: migrationهای خود Kernel با owner `kernel` (`Plugin::KERNEL_ID`، در `ModuleRegistry` رزرو شده) و ثبت `Clock`، `RequestId`، `RateLimiter` و `Router` در Container. `Db::lastInsertId()`.
- تست‌ها:
  - Unit: `RateLimitTest`، `ClientIpTest`، `RateLimiterTest`، و به‌روزرسانی `PluginTest` و `ModuleRegistryTest`. `FakesWpdb` حالا `%i` و `get_charset_collate` را می‌شناسد. `tests/Fixtures/FixedClock`.
  - Integration: `RouterTest` (نمونه، 401، 403، 422، 404 با details، 500 بدون نشت متن و PII، 429 با `Retry-After`، شمارش جدا برای هر client و هر route، رد route عمومی ناامن)، `PaginationTest`، `RateLimiterTest` (MySQL واقعی، prune).
- اسناد: implementation-notes §6 (Router، RateLimiter، ClientIp، Pagination، جداول Kernel، تست REST، تله PHPStan) و data-model (`rate_limits`). در phpcs، sniff `CamelCapsMethodName` برای `tests/Integration` خاموش شد (`set_up` در WP_UnitTestCase).

**تصمیم‌ها و فرض‌ها:**
- **«Controller پایه» ← `Router` (composition)** به‌جای کلاس abstract. Controllerها کلاس `final` ساده‌اند و وابستگی‌هایشان را از constructor خودشان می‌گیرند.
- خطای schema در `args` همان 400 `rest_invalid_param` وردپرس می‌ماند. 422 برای قاعده دامنه (`InvalidValue`) است.
- Rate limit بعد از permission و داخل callback شمرده می‌شود (فقط آنجا هدر `Retry-After` ممکن است). endpointی که باید تلاش رد‌شده را بشمارد (OTP) خودش `RateLimiter` را صدا می‌زند.
- `RateLimiter` از `Transaction` استفاده نمی‌کند (`START TRANSACTION` تراکنش تست WP را commit می‌کند) و Retry برای deadlock ندارد (چرخه قفل ممکن نیست، implementation-notes §6).
- سایت پشت CDN باید فیلتر `rest/client_ip` را پیاده کند. گزینه تنظیمات برای آن تصمیم باز است.

**Review:** subagent `reviewer` شش مورد پیدا کرد و هر شش بررسی شد:
- رفع شد: متن Exception در `error_log` (نشت PII) حذف شد.
- رفع شد: `SELECT LAST_INSERT_ID()` جدا (با drop-inهای HyperDB یا LudicrousDB fail-open می‌شد) با `Db::lastInsertId()` جایگزین شد.
- رفع شد: فیلتر خراب IP دیگر 500 نمی‌دهد.
- رفع شد: `page` سقف گرفت (سرریز offset).
- رفع شد: `WP_Error` برگشتی از callback هم Envelope می‌گیرد.
- نگه داشته شد و مستند شد: شمرده‌نشدن درخواست‌هایی که permission ردشان کرده.

**تأیید:** `composer check` ← lint و stan (No errors)، deptrac (0 violation در هر دو فایل)، unit (322 tests OK). `composer test:rename` ← OK. **Integration فقط در CI اجرا می‌شود** (نتیجه در ورودی بعدی).
**مشکلات و باقیمانده:** —
**قدم بعدی:** بررسی CI، سپس T0.9.
**Commitها:** `feat(kernel): rest router, error envelope, rate limiter and pagination (T0.8)`
---

## 2026-09-25 — سشن 5 (ادامه) — اولین CI واقعی و رفع آن
**Taskها:** T0.10، T0.7
**انجام شد:** کاربر `gh auth login` زد. اولین اجرای CI (run 36144097562) در سه job قرمز بود و هر سه رفع شد:
- **PHPStan، و به همین دلیل rename:** `str_replace(\range('0', '9'), …)` در `DateFormatter` (از T0.6). `range` عدد int برمی‌گرداند و با یک لیست لیترال جایگزین شد. PHPStan محلی روی PHP 8.3 این را نمی‌گیرد، حتی با `phpVersion: 80100` و بدون cache (امتحان شد). CI روی 8.1 آن را گرفت.
- **Integration PHP 8.4 + WP 6.6:** هر 19 تست پاس شدند، ولی `mysqli_ping()` در مسیر reconnect خود core، روی PHP 8.4 deprecation چاپ کرد. تست risky شد و `failOnRisky` job را شکست داد. در همان یک تست `E_DEPRECATED` خاموش شد.

**تأیید:** run 36145578720 روی `0888e32` ← هر 9 job سبز: quality، unit روی 8.1 و 8.4، legacy-syntax روی 7.0، rename، integration روی {8.1، 8.4} × {6.6، latest}. پس تست‌های Integration مربوط به T0.7 (lock wait واقعی، KILL، MyISAM، GET_LOCK و cache) روی MySQL واقعی پاس شدند. `composer check` محلی ← OK (300 tests).
**مشکلات و باقیمانده:** GitHub اعلام کرده برچسب `ubuntu-latest` از 2026-10-19 به Ubuntu 26 منتقل می‌شود. فعلاً اقدامی لازم نیست.
**قدم بعدی:** T0.8.
**Commitها:** `0888e32 fix(ci): string digits in DateFormatter, core deprecation in reconnect test`
---

## 2026-09-25 — سشن 5 (ادامه) — push و بستن T0.10 و T0.7
**Taskها:** T0.10، T0.7
**انجام شد:** commitهای `9ddb825` (T0.10) و `0ce43dc` (T0.7) به `origin/main` push شدند (`4c93fc7..0ce43dc`).
**تصمیم‌ها و فرض‌ها:** به درخواست کاربر هر دو Task ✅ شدند، **بدون دیدن نتیجه CI**. `gh` لاگین نیست و repo خصوصی است.
**تأیید:** فقط موفقیت push دیده شد. هیچ‌کدام از jobهای CI (integration، unit روی 8.4 و legacy-syntax) از اینجا دیده نشده‌اند.
**مشکلات و باقیمانده:** اگر CI قرمز باشد، رفعش قبل از T0.8 انجام می‌شود. محتمل‌ترین نقاط در ورودی قبلی آمده‌اند.
**قدم بعدی:** T0.8.
**Commitها:** —
---

## 2026-09-25 — سشن 5 — T0.7 Db، Transaction، Migrator
**Taskها:** T0.7
**انجام شد:**
- `src/Kernel/Database/`:
  - `Db`: wrapper نهایی روی `wpdb`. شامل `execute`، `getVar`، `insert`، `update`، `createTable`، و `begin`/`commit`/`rollBack`.
  - `DbException`: همراه با `errno`، `detail` و `isRetryable()`.
  - `Transaction::run()`: Retry روی 1213 و 1205، حداکثر 3 بار.
  - interface `Migration`.
  - `Migrator`: شامل `isCurrent` و `migrate`.
- `Module::migrations()`.
- `Plugin::activate()`. `Plugin::boot()` قبل از boot ماژول‌ها migrate می‌کند و `Db` و `Transaction` را به‌صورت singleton در Container ثبت می‌کند.
- `vaqtyar.php`: لیست مشترک ماژول‌ها (`$vaqtyar_modules`) و `register_activation_hook` در سطح بالای فایل. هر دو سازگار با PHP 7.0 هستند.
- تست‌ها:
  - Unit: `FakesWpdb` (Mockery روی `wpdb`)، `DbTest`، `TransactionTest`، `MigratorTest`، و گسترش `PluginTest`.
  - Integration: `RealDatabase`، `DbTest`، `TransactionTest` (lock wait واقعی با connection دوم، و KILL connection)، `MigratorTest` (DDL واقعی، InnoDB، MyISAM، GET_LOCK، cache)، و `ActivationTest`.
- اسناد: implementation-notes §1 و §5 (بخش‌های جدید Db، Transaction، Migrator و تله‌ها)، data-model §3، architecture §5 (استثنای Migration در boot)، و docblock `Tables`.

**تصمیم‌ها و فرض‌ها:**
- **`literal-string` + `%i`:** PHPStan برای `wpdb::prepare()` رشته literal می‌خواهد. به‌جای ignore، همین قید به `Db::execute/getVar` منتقل شد و نام جدول با `%i` (WP 6.2+) وارد می‌شود. DDL قابل prepare نیست، پس `CREATE TABLE` و بررسی InnoDB داخل `Db::createTable()` است. در نتیجه `Migration` یک interface ساده شد، نه کلاس پایه.
- نسخه هر ماژول = تعداد migrationهای اجراشده (لیست فقط اضافه‌شدنی).
- ADR-004 «تا 3 بار Retry» به معنای 4 تلاش پیاده شد.
- `GET_LOCK` بدون انتظار (timeout 0). درخواستی که قفل را نگرفت با schema فعلی ادامه می‌دهد.
- در فعال‌سازی شبکه‌ای، فقط سایت جاری migrate می‌شود. بقیه سایت‌ها در اولین درخواست خودشان migrate می‌شوند.
- `getRow`/`getResults` ساخته نشدند، چون مصرف‌کننده‌ای ندارند (principles §0). با اولین Repository اضافه می‌شوند.
- migration Kernel (`logs`، `rate_limits`) با T0.8 و T0.9 می‌آید. الان هیچ migration واقعی وجود ندارد و `$vaqtyar_modules` خالی است.

**تأیید:**
- `composer check` ← exit 0:
  - phpcs: پاک
  - PHPStan: No errors
  - deptrac: 0 violation (uncovered جدید `Db` ← `wpdb`، هم‌نوع مورد `Requirements`)
  - PHPUnit: OK (300 tests, 11775 assertions)
- `composer test:rename` ← OK.
- Mutation دستی:
  - `MAX_RETRIES = 2` ← `TransactionTest` شکست خورد.
  - حذف خواندن دوباره زیر قفل و حذف پاک‌کردن cache ← `MigratorTest` شکست خورد.
- **Integration محلی اجرا نشد** (Docker یا MySQL نداریم) و CI هنوز دیده نشده.
- Subagent `reviewer` سه مورد واقعی پیدا کرد و هر سه رفع شد:
  1. خواندن دوباره نسخه زیر قفل از cache `alloptions` می‌آمد و عملاً بی‌اثر بود. حالا قبل از خواندن، cache پاک می‌شود. تست Unit با cache شبیه‌سازی‌شده و تست Integration اضافه شد.
  2. Migration ناموفق در boot کل سایت را از کار می‌انداخت. حالا گرفته می‌شود و این کارها انجام می‌شود: `error_log`، admin notice، و boot نشدن ماژول‌ها. در activation همچنان Exception پرتاب می‌شود.
  3. reconnect بی‌صدای wpdb روی 2006 وسط تراکنش. حالا بررسی `thread_id` انجام می‌شود و `connectionLost` بدون Retry پرتاب می‌شود. ریسک باقیمانده برای T2.2 در implementation-notes §5 ثبت شد.
  - reviewer هم WP core را روی این ماشین نداشت و فرض‌های wpdb را از روی دانسته‌هایش بررسی کرد.

**مشکلات و باقیمانده:**
- T0.10 و T0.7 تا دیده شدن CI روی 🟨 می‌مانند.
- فرض‌هایی که فقط CI تأیید می‌کند:
  - خطای KILL در mysqlnd (2006 یا 2013)
  - `wpdb::__get('dbh')`
  - رفتار `process_fields` روی مقدار بلند
  - `SET autocommit = 1` بعد از `WP_UnitTestCase`

**قدم بعدی:** push و دیدن CI (کاربر: `gh auth login`). بعد T0.8.
**Commitها:** `feat(kernel): db wrapper, transaction and migrator (T0.7)`

---

## 2026-09-25 — سشن 4 — T0.10 wp-env + CI
**Taskها:** T0.10
**انجام شد:**
- `.github/workflows/ci.yml` با این jobها:
  - `quality`: lint، stan و deptrac
  - `unit`: روی PHP 8.1 و 8.4
  - `legacy-syntax`: `php -l` روی PHP 7.0 برای `vaqtyar.php`، `uninstall.php` و `Requirements.php`
  - `rename`: `composer test:rename`
  - `integration`: روی wp-env، با ماتریس PHP {8.1، 8.4} × WP {6.6، latest}
- `phpunit-integration.xml.dist`، `tests/Integration/bootstrap.php`، `tests/Integration/wp-tests-config.php` (prefix جدا به نام `wptests_`) و `BootstrapTest`. این تست Requirements را روی MySQL واقعی و بارگذاری Action Scheduler از فایل اصلی بررسی می‌کند.
- اسکریپت `composer test:integration`.
- `php-stubs/wordpress-tests-stubs` (dev) برای PHPStan.
- `.wp-env.json`: `env.tests` منسوخ حذف و `"testsEnvironment": false` اضافه شد.
- اسناد:
  - implementation-notes §2.2 (جدید)
  - dev-environment §5
  - roadmap: job `concurrency` به T2.2 و jobهای `test-js` و `build` به T0.11 منتقل شدند

**تصمیم‌ها و فرض‌ها:**
- کتابخانه تست WP از خود wp-env می‌آید (`$WP_TESTS_DIR`، هم‌نسخه با core). `wp-phpunit/wp-phpunit` نصب نشد، چون نسخه‌اش در lock ثابت است و با WP 6.6 ماتریس نمی‌خواند.
- jobهای `concurrency`، `test-js` و `build` ساخته نشدند، چون محتوایی ندارند (principles §0). job خالی فقط سبز دروغین می‌دهد.
- wp-env بدون `package.json` با `npx @wordpress/env@11` روی major 11 ثابت شد. در T0.11 به devDependency منتقل می‌شود.
- Actionها فقط `actions/checkout`، `actions/setup-node` و `shivammathur/setup-php` هستند. برای composer از action شخص ثالث استفاده نشد.
- در config Integration، deprecation به Exception تبدیل نمی‌شود، چون core خودش deprecation دارد. suite Unit روی 8.4 کد ما را پوشش می‌دهد.

**تأیید:**
- `composer check` ← exit 0:
  - phpcs: پاک
  - PHPStan: No errors
  - deptrac: 0 violation (1 uncovered که از قبل بود: `Requirements` ← `wpdb`)
  - PHPUnit: OK (256 tests, 11686 assertions)
- `composer test:rename` ← OK (فایل‌های جدید هم در کپی بودند، چون untracked هم کپی می‌شود).
- `actionlint` روی workflow ← پاک.
- **Integration محلی اجرا نشد** (Docker نداریم). **نتیجه CI هنوز دیده نشده.** repo خصوصی است و `gh` لاگین نیست.
- Subagent `reviewer`: همه فرض‌های wp-env را با سورس 11.16.0 و کتابخانه تست WP (6.6.9 و trunk) بررسی کرد. دو مورد پیدا کرد:
  - `testsEnvironment` بدون کلید هنوز روشن است (برخلاف README). اصلاح شد.
  - خط‌شکستگی در `phpcs.xml`. اصلاح شد.

**مشکلات و باقیمانده:**
- تا وقتی CI دیده نشود، T0.10 روی 🟨 می‌ماند.
- unit روی PHP 8.4 هرگز اجرا نشده است (محلی 8.3 داریم).

**قدم بعدی:** دیدن نتیجه CI (کاربر یا `gh auth login`)، رفع اگر لازم بود، ✅ کردن T0.10، سپس T0.7.
**Commitها:** `ci(kernel): wp-env integration suite and github actions (T0.10)`

## 2026-09-24 — سشن 3 — T0.6 Jalali + DateFormatter
**Taskها:** T0.6
**انجام شد:**
- `src/Shared/Domain/Jalali.php` (خالص): `fromGregorian`، `toGregorian`، `isLeapYear`، `daysInMonth`. الگوریتم port شده Borkowski/jalaali-js است و از سال 1 تا 3176 پشتیبانی می‌کند.
- `src/Shared/DateFormatter.php`: `date`، `longDate`، `time`، `dateTime` و `digits`. تقویم با enum `Calendar` و ارقام با enum `Digits` انتخاب می‌شوند.
  - نام ماه‌های شمسی با `_x(…, 'Jalali month', …)` ترجمه‌پذیرند.
  - تاریخ بلند میلادی از `wp_date('j F Y')` می‌آید.
- `ext-intl` به require-dev اضافه شد (lock: فقط hash و `platform-dev` تغییر کرد).
- اسناد: implementation-notes §4.2 (جدید)، دستور phpdbg با `memory_limit=-1`.

**تصمیم‌ها و فرض‌ها:**
- Jalali در `Shared\Domain` است (PHP خالص)، چون Domain، مثلاً نمای ماه Availability، ممکن است به مرز ماه شمسی نیاز داشته باشد. نام ماه و قالب‌بندی در `Shared` است، چون i18n و `wp_date` لازم دارد.
- **تا وقتی فایل fa_IR ساخته نشده (T6.3)، نام ماه شمسی به‌صورت انگلیسی آوانگاری‌شده (Mehr) نمایش داده می‌شود.**
- تاریخ عددی با ارقام فارسی همیشه `/` دارد، به خاطر bidi. کنار هم آمدن تاریخ و زمان در صفحه LTR کار UI است و در متن کاراکتر نامرئی نمی‌گذاریم.
- ICU از سال 1634 به بعد با الگوریتم ما متفاوت است. ICU مرجع درستی در آن سال‌ها نیست.

**تأیید:**
- ابتدا تست‌ها قرمز بودند. آزمون ICU اشتباه port را گرفت: در شاخه قبل از نوروز، leap سال قبل به‌جای سال اولیه استفاده شده بود. بعد از اصلاح سبز شدند.
- `composer check` ← exit 0:
  - phpcs: پاک
  - PHPStan: No errors
  - deptrac: 0 violation
  - PHPUnit: OK (256 tests, 11686 assertions)
- آزمون ICU 72: همه روزهای 1300 تا 1500 در هر دو جهت و وضعیت کبیسه همه سال‌ها، بدون اختلاف.
- آزمون پیوستگی سال‌های 1 تا 3176: شروع هر سال، طول 365 یا 366، و round trip.
- پوشش با phpdbg: `Jalali` برابر 71 از 71 خط، `DateFormatter` برابر 28 از 29 خط. خط باقیمانده شاخه `false` در `wp_date` است.
- `composer test:rename` ← OK.
- Subagent `reviewer`: در سال‌های واقعی باگ درستی پیدا نکرد و کل بازه 1 تا 3177 را مستقل بررسی کرد. 6 مورد دیگر هم اصلاح شد:
  - قرارداد مرز سال 3177
  - آزمون شاخه‌های اصلاحی
  - bidi در تاریخ میلادی با ارقام فارسی
  - ارجاع §7 که باید §9 باشد
  - ادعای بیش از حد دقت در docblock
  - skip بی‌صدای آزمون ICU (با `ext-intl` حل شد)

**مشکلات و باقیمانده:** reviewer لیست رسمی سال‌های کبیسه ایران را از حافظه با خروجی ما مطابقت داد. منبع رسمی در repo نیست.
**قدم بعدی:** T0.10 (wp-env + CI)، قبل از T0.7.
**Commitها:** `feat(shared): jalali calendar and date formatter (T0.6)`

## 2026-09-24 — سشن 3 — T0.5 Value Objectهای Shared
**Taskها:** T0.5
**انجام شد:**
- `src/Shared/Domain/` شامل این موارد است:
  - `InvalidValue` (با `errorCode`)
  - `Money` + `Currency` (فقط IRR) + `Rounding` (`Down`، `Up`، `HalfUp`)
  - `PhoneNumber` (E.164)
  - `Email`
  - `LocalDate`، `LocalTime` (با `24:00`) و `TimeRange` (نیمه‌باز، UTC، ثانیه کامل)
  - `IntervalSet` (union، subtract، intersect و covers با ادغام خطی)
  - `Ulid` (ساخت خالص با `fromParts`)
  - `Clock` (interface)
- `src/Shared/SystemClock.php`.
- `tools/Rename/Shell::run()` حالا خروجی زیرفرایند را از pipe می‌خواند و relay می‌کند. این همان مشکل «پیام گم‌شده» در T0.4 بود.
- اسناد:
  - implementation-notes §4.1 (جدید)
  - dev-environment: پوشش با `phpdbg`، تله `python -`، تله خروجی زیرفرایند

**تصمیم‌ها و فرض‌ها:**
- **پوشش با `phpdbg` محلی اندازه‌گیری می‌شود.** نصب pcov یا xdebug لازم نیست.
- `Currency` فقط IRR دارد (ADR-010). بررسی ارز با `@phpstan-ignore` حفظ شد. reviewer تأیید کرد که با اضافه‌شدن ارز دوم، این ignoreها خطای «unmatched» می‌دهند.
- شماره بدون کد کشور ایرانی فرض می‌شود و کشور دیگر فقط با `+` یا `00` پذیرفته می‌شود. فرم «+98 (0) 912…» پذیرفته می‌شود.
- `IdGenerator` Port با اولین مصرف‌کننده (Appointment، M2) ساخته می‌شود (§0). `Ulid::milliseconds()` به‌خاطر نداشتن مصرف‌کننده حذف شد.
- بنچمارک Availability (بودجه 50ms) مربوط به T1.4 است و از تست IntervalSet حذف شد، چون تست نباید به زمان واقعی وابسته باشد.

**تأیید:**
- ابتدا تست‌ها قرمز بودند، سپس سبز شدند.
- `composer check` ← exit 0:
  - phpcs: پاک
  - PHPStan: No errors
  - deptrac: 0 violation
  - PHPUnit: OK (227 tests, 1904 assertions)
- تست تصادفی IntervalSet: 300 seed در مقایسه با مدل bitmap. reviewer هم 20,000 seed با اعداد منفی را بدون هیچ اختلافی اجرا کرد.
- پوشش با `phpdbg`: `Shared\Domain` برابر 184 از 185 خط (99.5%). خط باقیمانده بررسی ارز است که با یک ارز قابل اجرا نیست.
- ULID با نمونه رسمی spec (`01ARZ3NDEKTSV4RRFFQ69G5FAV`) مقایسه شد، که بخش‌هایش جداگانه با Python decode شده بودند.
- `composer test:rename` ← OK. rename کپی سالم ماند (227 tests) و خروجی حالا کامل و مرتب است.
- Subagent `reviewer`:
  - **1 باگ واقعی:** شماره‌های `+980…` که یک رقم کم داشتند پذیرفته می‌شدند و ممکن بود شماره‌ای نامعتبر در `customers.phone` (UNIQUE) ذخیره شود. اصلاح شد و 4 تست اضافه شد.
  - 4 مورد جزئی: بنچمارک وابسته به زمان، نام تست‌ها، متد بدون مصرف‌کننده، و یادداشت کهنه در tracker. همه اصلاح شدند.

**مشکلات و باقیمانده:** —
**قدم بعدی:** T0.6 (Jalali + DateFormatter).
**Commitها:** `feat(shared): value objects, interval set and clock (T0.5)`

## 2026-09-24 — سشن 3 — T0.4 Helperهای نام + rename.php
**Taskها:** T0.4
**انجام شد:**
- `src/Kernel/`:
  - `Tables::name()`: پیشوند سایت در هر فراخوانی از `$wpdb` خوانده می‌شود (multisite). سقف 64 کاراکتر.
  - `Options::key()`: سقف 191 کاراکتر.
  - `Hooks::name()`: `{hook_prefix}/{module}/{event}`.
  - `Caps::name()`.
  - هر ورودی با `[a-z][a-z0-9_]*` (و modifier `D`) اعتبارسنجی می‌شود و در غیر این صورت `KernelException` می‌دهد.
- `tools/Rename/` (namespace `Vaqtyar\Tools\Rename` در autoload-dev):
  - `RenameSpec`: اعتبارسنجی و مشتق‌کردن شناسه‌ها از slug.
  - `Renamer`: plan و apply.
  - `RenamePlan`، `RenameCommand`، `Shell` (git و composer بدون shell)، `RenameException`.
- CLI: `tools/rename.php` با `--dry-run`.
- `tools/test-rename.php` و `composer test:rename`: کپی، `git init` و commit، `composer install`، rename، و `composer check` روی کپی.
- phpcs و PHPStan حالا `tools/` را هم پوشش می‌دهند. EscapeOutput برای `tools/` exclude شد، چون CLI است و در بسته نهایی قرار نمی‌گیرد.
- تست‌ها:
  - `NamesTest`: 43 مورد با dataProvider.
  - `RenameSpecTest`.
  - `RenamerTest`: روی یک افزونه جعلی با توکن‌های ساختگی `AcmeBook` و `ZetaTool`، تا بعد از rename واقعی repo هم معنی‌دار بماند.
- اسناد: implementation-notes §2.1 (جدید)، جزئیات اجرا در ADR-000، dev-environment §5، roadmap (T0.10: `test:rename` در CI؛ T0.11: lock file در JS).

**تصمیم‌ها و فرض‌ها:**
- **`const_prefix`، `hook_prefix`، `text_domain` و `rest_namespace` از slug مشتق می‌شوند** و slug فقط `a-z0-9` است. این تصمیم ADR-000 را عوض نمی‌کند، فقط قید به آن اضافه می‌کند، و در ADR-000 به‌عنوان «جزئیات اجرا» ثبت شد.
- جایگزینی فقط توکن کامل را عوض می‌کند. هر اثری از توکن قدیمی که عوض نشود، rename را **قبل از نوشتن** رد می‌کند.
- اجرای واقعی فقط روی working tree تمیز مجاز است. lock fileها، `docs/`، `.claude/` و `CLAUDE.md` دست نمی‌خورند.
- Helperها static هستند، چون ADR-000 همین شکل را تعیین کرده است. state ندارند.

**تأیید:**
- `composer check` ← exit 0:
  - phpcs: پاک
  - PHPStan level 9 (حالا با `tools/`): No errors
  - deptrac: هر دو config با 0 violation
  - PHPUnit: OK (116 tests, 219 assertions)
- `composer test:rename` ← OK. کپی به `renamecheck` rename شد (38 ویرایش و یک جابه‌جایی)، اثری از توکن قدیمی نماند و `composer check` روی کپی سبز شد (116 tests).
- CLI روی repo واقعی:
  - dry-run با نام جدید ← 38 ویرایش، بدون هیچ تغییری روی دیسک.
  - اجرا روی tree کثیف ← رد شد.
  - بدون `--name` ← رد شد، با فهرست باقیمانده‌ها.
  - slug نادرست (`nobat-yar`) ← رد شد.
- Subagent `reviewer`: 7 یافته، همه بازتولید و اصلاح شدند:
  - بدون `--name`، rename بعد از نوشتن شکست می‌خورد.
  - شکست وسط `apply()` به «class not found» می‌رسید و tree ناسازگار می‌ماند.
  - `$1` در نام نمایشی خروجی را خراب می‌کرد.
  - hash در lock file با پیشوند کوتاه برخورد می‌کرد.
  - بررسی تداخل، نام قدیمی را در کل فایل‌ها پاک می‌کرد.
  - regex اعتبارسنجی newline انتهایی را می‌پذیرفت (`$` بدون `D`).
  - نام سه تست رفتار را توصیف نمی‌کرد.

**مشکلات و باقیمانده:**
- در اولین اجرای ناموفق `test:rename`، پیام خطای rename در خروجی دیده نشد. نه من توانستم بازتولیدش کنم و نه reviewer. آزمایش مستقیم نشان می‌دهد stdout و stderr زیرفرایند منتقل می‌شوند.
- معیار «lint و test سبز می‌مانند» برآورده شد. deptrac و PHPStan هم روی کپی سبز بودند.

**قدم بعدی:** T0.5 (Shared Value Objects + IntervalSet).
**Commitها:** `feat(kernel): naming helpers and rename tool (T0.4)`

## 2026-09-24 — سشن 3 — T0.3 Kernel
**Taskها:** T0.3
**انجام شد:**
- `src/Kernel/`:
  - `Identity`: ثابت‌های `NAME`، `SLUG`، `PREFIX`، `HOOK_PREFIX` و `REST_NAMESPACE`.
  - `Container` (ADR-011): `set`، `singleton`، `has` و `get` با شناسه class-string. ثبت تکراری، نوع نادرست و وابستگی چرخه‌ای Exception می‌دهند.
  - `Module` (interface) و `Context` (container، مسیر فایل اصلی، نسخه).
  - `ModuleRegistry`: id یکتا و حفظ ترتیب.
  - `Plugin`: ابتدا `register` همه ماژول‌ها، سپس `boot` همه.
  - `KernelException`.
- `vaqtyar.php`: روی `plugins_loaded` با اولویت 5، `(new Plugin(VAQTYAR_FILE, VAQTYAR_VERSION))->boot()` اجرا می‌شود. هنوز ماژولی وجود ندارد.
- تست‌ها:
  - `ContainerTest` (9)، `ModuleRegistryTest` (3)، `PluginTest` (3) با ماژول نمونه در `tests/Unit/Kernel/Fixtures`.
  - `IdentityTest` (3): ثابت‌ها با `identity.json`، header و نام فایل اصلی، ثابت‌های `*_VERSION` و `*_FILE`، و namespace در composer.
- اسناد: architecture §5 (Composition root، Context، Exception در boot، hook Add-on)، implementation-notes §1 (Container و تله Activation) و §6 (پیام Exception و Plugin Check)، roadmap T0.7.

**تصمیم‌ها و فرض‌ها:**
- **Composition root = فایل اصلی.** Kernel به هیچ ماژولی وابسته نیست و config فعلی deptrac بدون تغییر درست است.
- **Context نوع درخواست (admin، rest، …) را حدس نمی‌زند** و با این تصمیم طرح اولیه architecture §5 عوض شد:
  - در `plugins_loaded` هنوز `REST_REQUEST` تعریف نشده است.
  - ماژول‌ها کار هر درخواست را با hook خود WP محدود می‌کنند.
  - ADR لازم نبود، چون این مورد فقط در سند معماری بود و ADR نداشت. reviewer هم تأیید کرد.
- Exception در boot گرفته نمی‌شود. recovery mode وردپرس افزونه را متوقف می‌کند.
- hook ثبت ماژول برای Add-onها ساخته نشد، چون هنوز Add-on واقعی وجود ندارد (§0).
- `Plugin::boot` از static به متد instance تغییر کرد (principles §3: static فقط برای named constructor).
- **`EscapeOutput.ExceptionNotEscaped` در phpcs exclude شد.**
  - Domain نمی‌تواند `esc_html()` صدا بزند و پیام Exception متن ساده است.
  - **Plugin Check این sniff را گزارش می‌کند** (از سورس plugin-check بررسی شد).
  - اگر wp.org کانال فروش شد (تصمیم باز 2)، این سیاست قبل از T6.4 بازبینی می‌شود.
- **تله Activation:** در درخواست فعال‌سازی، `plugins_loaded` زودتر رخ داده است، پس boot اجرا نمی‌شود. لیست مشترک ماژول‌ها و activation hook در سطح بالای فایل اصلی به T0.7 سپرده شد، چون اولین نیاز واقعی همان‌جاست.

**تأیید:**
- ابتدا تست‌ها قرمز بودند (16 خطا، کلاس‌ها وجود نداشتند)، سپس سبز شدند.
- `composer check` ← exit 0:
  - phpcs: هر دو ruleset پاک
  - PHPStan level 9: No errors
  - deptrac: هر دو config با 0 violation
  - PHPUnit: OK (36 tests, 88 assertions)
- WordPress Playground ← افزونه فعال بود، `plugins_loaded=1` بود، `Kernel\Plugin` و `Container` بارگذاری شدند، Action Scheduler بارگذاری شد و `debug.log` خالی بود:
  - PHP 8.1.34 + WP 6.6.9
  - PHP 8.4.25 + WP 7.1.2 (دوباره بعد از اصلاحات reviewer)
- Subagent `reviewer`: باگی در درستی یا امنیت پیدا نکرد. 5 یافته داشت:
  - تله Activation
  - Plugin Check و ExceptionNotEscaped
  - دو جمله نادرست در architecture
  - متد static
  - نام دو تست

  همه اصلاح یا مستند شدند.

**مشکلات و باقیمانده:** `gh` لاگین نشده، پس سورس plugin-check از raw.githubusercontent خوانده شد.
**قدم بعدی:** T0.4 (Helperهای نام + `rename.php`).
**Commitها:** `feat(kernel): container, module registry and boot (T0.3)`

## 2026-09-24 — سشن 3 — T0.2 ابزار کیفیت PHP
**Taskها:** T0.2
**انجام شد:**
- `tools/phpcs.xml`:
  - PSR-12
  - از WPCS: `Security`، `DB.PreparedSQL*`، `WP.I18n` (text domain `vaqtyar`)، `WP.EnqueuedResources` و `PrefixAllGlobals` (بدون `DynamicHooknameFound`، چون نام hook از `Hooks::name()` می‌آید)
  - PHPCompatibilityWP برای 8.1+
  - Slevomat: strict types، type hintها، unused uses و **FullyQualifiedGlobalFunctions**
  - `Squiz.PHP.GlobalKeyword`
- `tools/phpcs-legacy.xml`: PHPCompatibility 7.0+ فقط روی سه فایل bootstrap.
- `tools/phpstan.neon`: level 9 + phpstan-wordpress روی `src/`، `tests/`، `vaqtyar.php` و `uninstall.php`.
- `tools/deptrac-layers.yaml`:
  - قوانین لایه‌های hexagonal
  - layer `WordPress` از روی wordpress-stubs و Action Scheduler
  - layer `Superglobals`
  - layer `Unclassified` برای جلوگیری از دور زدن قوانین
- `tools/deptrac-modules.yaml`: ایزوله‌سازی 7 ماژول (دسترسی فقط از طریق `Contracts`) + `Unclassified`.
- `phpunit.xml.dist` با suite `unit`.
- اسکریپت‌های composer: `lint`، `lint:fix`، `stan`، `deptrac`، `test`، `test:unit` و `check`.
- phpcbf تابع‌های سراسری `Requirements.php` و تستش را به شکل `\fn()` درآورد. `declare(strict_types=1)` به `vaqtyar.php` و `uninstall.php` اضافه شد (از PHP 7.0 مجاز است).
- اسناد: implementation-notes §2 (تله‌های ابزار)، dev-environment §5 (دستورها و مشکل بک‌اسلش در ابزار Bash)، roadmap (T0.2، T0.3، T0.7 و T0.10).

**تصمیم‌ها و فرض‌ها:**
- **تابع‌های سراسری همیشه fully-qualified نوشته می‌شوند.** Deptrac فراخوانی بی‌پیشوند داخل namespace را تشخیص نمی‌دهد (با fixture آزموده شد).
- **deptrac دو config دارد**، چون کلاسی که در دو layer باشد با قوانین «ماژول × لایه» درست بررسی نمی‌شود.
- **config و bootstrap تست Integration به T0.10 منتقل شد**، چون به WP test library و MySQL نیاز دارد. پیامد: **T0.10 قبل از T0.7 انجام می‌شود** (ردیف T0.10 در tracker جابه‌جا شد و T0.7 در roadmap پیش‌نیاز گرفت).
- **شکاف:** PHPCompatibility 9.3 سینتکس PHP 8 (`?->`، `match`، named args) را نمی‌شناسد. `php -l` روی PHP 7.0 واقعی به CI در T0.10 اضافه شد.
- Kernel فعلاً به هیچ ماژولی دسترسی ندارد. محل composition root در T0.3 تصمیم‌گیری می‌شود.
- جهت گراف وابستگی ماژول‌ها (architecture §3) هنوز اجبار نمی‌شود و در M1 اضافه می‌شود.
- `tools/rename.php` (T0.4) باید `tools/*.xml|yaml|neon` را هم بازنویسی کند (namespace و text domain در configها لیترال هستند).

**تأیید:**
- `composer check` (Bash و PowerShell) ← exit 0:
  - phpcs: هر دو ruleset پاک
  - PHPStan: No errors
  - deptrac: هر دو config با 0 violation
  - PHPUnit: OK (18 tests, 20 assertions)
- fixtureهای موقت (بعد از بررسی حذف شدند):
  - deptrac این موارد را گرفت: Domain ← `\add_action`، `\get_option`، `use function wp_die`، `WP_Error`، `$GLOBALS`، `as_enqueue_async_action`، `ActionScheduler`، کلاس Application و کلاس Unclassified. همچنین Booking ← `Catalog\Domain`، Contracts ← Domain، و ماژول فهرست‌نشده (Coupons) ← `Booking\Domain`.
  - Booking ← `Catalog\Contracts` مجاز شناخته شد.
  - violation در `composer deptrac` باعث exit 1 شد.
  - phpcs این موارد را گرفت: `echo $_GET`، SQL بدون prepare، text domain اشتباه، `global $wpdb`، تابع سراسری بدون prefix. نام hook پویا پذیرفته شد.
  - phpcs-legacy این موارد را گرفت: const visibility، nullable، `void` و `object`. `?->` و `match` را نگرفت (شکاف بالا).
- Subagent `reviewer`: 6 یافته. 1 تا 5 اصلاح شدند:
  - PrefixAllGlobals روی hook پویا
  - Unclassified
  - `global $wpdb`
  - Action Scheduler
  - ترتیب roadmap

  یافته 6 (گراف ماژول‌ها) به M1 موکول و در implementation-notes ثبت شد.

**مشکلات و باقیمانده:**
- ابزار Bash این محیط بک‌اسلش را در heredoc، `sed` و `printf` خراب می‌کند. در dev-environment ثبت شد.
- اجرای کامل `composer check` حدود 30 ثانیه طول می‌کشد (تحلیل stubهای WP در deptrac).

**قدم بعدی:** T0.3 (Kernel).
**Commitها:** `chore(tooling): phpcs, phpstan, deptrac, phpunit configs (T0.2)`

## 2026-09-24 — سشن 2 — T0.1 اسکلت Repo
**Taskها:** T0.1
**انجام شد:**
- `vaqtyar.php` (header، ثابت‌های `VAQTYAR_VERSION` و `VAQTYAR_FILE`، بررسی Requirements، autoload و Action Scheduler) و `uninstall.php` (فقط گارد). هر دو با سینتکس PHP 7.0.
- `src/Kernel/Requirements.php` (سینتکس PHP 7.0) بررسی می‌کند:
  - PHP 8.1 و WP 6.6 (پسوند `-RC1` مثل core نادیده گرفته می‌شود)
  - MySQL 5.7 یا MariaDB 10.4 از `db_server_info()` (بدون کوئری، با پشتیبانی از پیشوند `5.5.5-`)
  - extension `mbstring` و وجود `vendor/`
  - اگر رد شود، notice در `admin_notices` و `network_admin_notices` فقط برای `activate_plugins` نمایش داده می‌شود. ترجمه داخل callback بارگذاری می‌شود.
- `identity.json` طبق ADR-000، و `.editorconfig` (PHP با 4 فاصله، composer با فاصله، بقیه tab).
- `composer.json`:
  - Production: فقط `woocommerce/action-scheduler` ^3.9
  - Dev: PHPUnit 9.6، polyfills، Brain Monkey، Mockery، PHPStan 2 + phpstan-wordpress، PHPCS + WPCS 3 + PHPCompatibilityWP + Slevomat، Deptrac 2
  - `platform.php = 8.1.0`
  - `composer.lock` هم commit شد.
- 18 تست Unit برای Requirements.
- اسناد: implementation-notes (تله‌های T0.2 و neon)، dev-environment (Smoke با Playground)، architecture §5، roadmap T0.7.

**تصمیم‌ها و فرض‌ها:**
- **بررسی InnoDB از Requirements حذف و به Migrator در T0.7 منتقل شد.** این بررسی کوئری لازم دارد و نباید روی هر درخواست اجرا شود. Architecture §5 و roadmap اصلاح شدند.
- اگر Requirements رد شود، افزونه در WP «فعال» می‌ماند ولی هیچ کاری نمی‌کند و فقط notice نشان می‌دهد. خودش را غیرفعال نمی‌کند. خود WP هم فعال‌سازی روی PHP پایین‌تر از `Requires PHP` را رد می‌کند.
- هوک‌های activation و deactivation و `plugins_loaded → Plugin::boot` هنوز اضافه نشده‌اند (T0.3 و T0.7)، چون کد خالی اضافه نمی‌کنیم.
- Author و Plugin URI در header خالی مانده‌اند، تا وقتی نام و کانال فروش قطعی شود.
- اسکریپت‌های composer و configها (`phpcs.xml`، `phpstan.neon`، `phpunit.xml`) در T0.2 ساخته می‌شوند.

**تأیید:**
- `phpunit --bootstrap vendor/autoload.php tests/Unit` ← OK (18 tests, 20 assertions)
- PHPStan level 9 + phpstan-wordpress (config موقت) ← No errors
- `phpcs --standard=PHPCompatibility --runtime-set testVersion 7.0-` روی 3 فایل bootstrap ← پاک
- WPCS `Security.EscapeOutput`، `WP.I18n` و `PrefixAllGlobals` ← پاک
- WordPress Playground CLI (`run-blueprint`):
  - PHP 8.1 + WP 6.6.9، و PHP 8.4 + WP 7.1.2 ← `activate_plugin` ok، Action Scheduler بارگذاری شد، `debug.log` خالی
  - PHP 8.0 و 7.4 ← WP فعال‌سازی را با پیام خودش رد کرد. با فعال‌سازی اجباری، notice ما نمایش داده شد، Fatal رخ نداد و `debug.log` خالی بود.
- Subagent `reviewer`: مشکل مسدودکننده‌ای نبود. 4 یافته (بررسی نسخه DB و InnoDB، ترجمه notice، پوشش پیام‌های wp و vendor در تست، و `.editorconfig`) همه اصلاح شدند.

**مشکلات و باقیمانده:**
- نمایش ترجمه فارسی notice تأیید نشد، چون هنوز فایل `.mo` وجود ندارد.
- تست روی MySQL واقعی در CI انجام می‌شود (T0.10).

**قدم بعدی:** T0.2 (ابزار کیفیت PHP).
**Commitها:** `feat(kernel): repo skeleton with requirements check (T0.1)`

## 2026-09-24 — سشن 1 (بخش 4): نصب ابزارها
**Taskها:** P.6
**انجام شد:**
- PHP 8.3.33 با winget نصب شد. `php.ini` ساخته شد و 10 extension لازم فعال شد.
- Composer 2.10.3 با installer رسمی نصب شد (در winget وجود نداشت). امضای SHA384 بررسی شد.
- GitHub CLI 2.101 نصب شد (لاگین نشده).
- `.wp-env.json` ساخته شد: PHP 8.1، پورت‌های 8888 و 8889.
- طبق تصمیم کاربر، **Docker محلی نصب نشد**. تست‌های MySQL (Integration و Concurrency) و E2E در GitHub Actions اجرا می‌شوند (T0.10 به‌روز شد).

**تصمیم‌ها و فرض‌ها:**
- انتقال پوشه پروژه انجام نشد: انتقال وسط سشن مسیر کاری جاری را می‌شکند، پس به کاربر پیشنهاد داده شد که خودش انجام دهد.
- نصب pluginهای Claude Code و `gh auth login` فقط از طرف کاربر ممکن است.

**تأیید:**
- `php -v` ← 8.3.33
- `php -m` ← شامل intl، sodium، mbstring، …
- `composer -V` ← 2.10.3
- `gh --version` ← 2.101.0

**قدم بعدی:** T0.1.

## 2026-09-23 — سشن 1 (بخش 3): GitHub، Skillها، محیط و یادداشت‌های پیش از کد
**Taskها:** P.5 (و P.6 باز است)
**انجام شد:**
- Remote `origin` به https://github.com/io98-ir/appointment-scheduler وصل و `main` push شد. یک Remote تکراری با همان URL (`appointment-scheduler`) حذف شد.
- **14 Skill** با `npx skills add --copy` در سطح پروژه نصب شد (ثبت در `skills-lock.json`):
  - 9 مورد از WordPress/agent-skills
  - `frontend-design` و `webapp-testing` از Anthropic
  - `test-driven-development`، `systematic-debugging` و `verification-before-completion` از superpowers
  - اسکریپت‌های Skillها از نظر امنیتی بررسی شدند: هیچ فراخوانی شبکه ندارند.
- `intelephense`، `typescript` و `typescript-language-server` با npm global نصب شدند (پیش‌نیاز pluginهای LSP).
- اسناد جدید:
  - `04-engineering/02-dev-environment.md` (وضعیت ابزارها و نصب‌ها)
  - `04-engineering/03-implementation-notes.md` (تله‌های فنی)
  - `05-delivery/05-agent-tooling.md` (Skillها، اولویت، تعارض‌ها)
- **ADR-017:** لایسنس GPL-2.0-or-later. مدل فروش بر پایه آپدیت و پشتیبانی است.
- **ADR-018:** PHPUnit 9.6 + Polyfills، چون تست‌سوئیت WP نسخه PHPUnit 10 را پشتیبانی نمی‌کند و PHPUnit 11 به PHP 8.2 نیاز دارد.
- سیاست push به‌روز شد: push در `/wrap` انجام می‌شود و force-push هرگز.

**تصمیم‌ها و فرض‌ها:**
- Skillهای superpowers که با روند ما تعارض داشتند (brainstorming، writing-plans، using-superpowers) عمداً نصب نشدند.
- Pluginها از این محیط قابل نصب نیستند (CLI `claude` روی PATH نیست). کاربر باید از Manage plugins در VS Code نصبشان کند.

**تأیید:**
- `git push -u origin main` ← `* [new branch] main -> main`
- `npx skills add` ← 14 مورد ✓
- `Get-Command intelephense` و `typescript-language-server` ← OK

**مشکلات و باقیمانده:** ⛔ PHP، Composer و Docker نصب نیستند (ر.ک. dev-environment §2).
**قدم بعدی:** کاربر ابزارها را نصب کند، سپس T0.1.
**Commitها:** `chore: connect GitHub, install agent skills, pre-coding notes`

## 2026-09-23 — سشن 1 (بخش 2) — بازنگری طبق نظر کارفرما + زیرساخت روند کار
**Taskها:** P.3، P.4
**انجام شد:**
- `git init` (شاخه `main`).
- **ADR-000:** نام قابل تعویض طراحی شد: `identity.json` + Helperهای نام + `tools/rename.php` + White-label برای نام نمایشی.
- ADR-008 (PSR-12 + Sniffهای امنیتی WPCS) طبق تأیید کارفرما `Accepted` شد.
- **ADR-016:** محصول عمومی است و حوزه تخصصی ندارد. موارد Overengineering حذف شدند: Ledger، Outbox، Command Bus، league/container و Strauss، OpenAPI، Zustand، Eris و Infection، رمزنگاری بالینی.
- ماژول‌ها از 15 به 7 کاهش یافتند. مدل داده ساده شد (جدول واحد `occupancies`).
- اسناد product-scope، tech-stack، architecture، booking-engine و data-model بازنویسی شدند. principles بخش §0 ضد Overengineering گرفت.
- ساخته شد: `docs/05-delivery/` (roadmap با 46 Task، progress tracker، agent workflow، worklog)، Skillهای `/resume`، `/next-task` و `/wrap`، و Subagent `reviewer`.

**تصمیم‌ها و فرض‌ها:**
- نام کاری `Vaqtyar` / `vqy` حفظ شد، چون توکن منحصربه‌فرد برای rename امن لازم است.
- ثبت Job در Action Scheduler داخل تراکنش جایگزین Outbox شد (ADR-005).
- Reschedule همان رکورد نوبت را به‌روز می‌کند و رکورد جدید نمی‌سازد.

**تأیید:** فقط اسناد تغییر کردند. کدی وجود ندارد.
**قدم بعدی:** T0.1 (اسکلت Repo). اول بررسی نسخه‌های PHP، Composer، Node، pnpm و Docker روی سیستم.
**Commitها:** `727973a docs: research, architecture, principles and delivery workflow` (commit اولیه). `.gitattributes` با `eol=lf` هم اضافه شد.

## 2026-09-23 — سشن 1 (بخش 1) — تحقیق و معماری اولیه
**Taskها:** P.1، P.2
**انجام شد:**
- تحقیق روی Medino، Bookly، نوبت‌پلاس، نوبت‌نگار و Booknetic Custom Duration، به‌علاوه Booknetic core، Amelia، LatePoint و پذیرش24.
- بررسی اکوسیستم ایران (پیامک پترن، درگاه‌ها، شاپرک، تحریم، نبود DST، تعطیلات قمری).
- بررسی WP 6.9/7.0، Action Scheduler و جداول سفارشی.
- نوشته شد: `docs/01-research/*`، `docs/02-product`، `docs/03-architecture/*` (نسخه اولیه)، `docs/04-engineering/01-principles.md`، `CLAUDE.md`.

**قدم بعدی:** تأیید تصمیم‌های باز توسط کارفرما (انجام شد در بخش 2).
