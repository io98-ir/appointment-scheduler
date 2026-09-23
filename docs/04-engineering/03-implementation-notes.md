# مهندسی: یادداشت‌های فنی پیش از کدنویسی

> نکات دقیق و «تله‌ها»یی که هنگام کدنویسی باید رعایت شوند. این‌ها در سایر اسناد نیامده‌اند یا جزئیات اجرایی‌اند.
> هر تله جدیدی که کشف شد اینجا اضافه شود.

## 1. فایل اصلی افزونه و Bootstrap (T0.1)
- **فایل اصلی (`vaqtyar.php`) و `Requirements` نباید سینتکس PHP 8 داشته باشند.** روی PHP قدیمی باید parse شوند تا پیام خطای مناسب نمایش داده شود و Fatal رخ ندهد. پس بدون typed property، بدون `match`، بدون named args، بدون `?->` و بدون `enum`. سازگاری تا PHP 7.0 کافی است. بقیه `src/` از PHP 8.1 استفاده می‌کند.
- ترتیب در فایل اصلی:
  1. گارد `defined('ABSPATH') || exit;`
  2. ثابت‌ها (`*_FILE`، `*_VERSION`)
  3. بررسی Requirements. اگر رد شد: `admin_notices` و `return`
  4. `vendor/autoload.php`. اگر نبود: notice «composer install اجرا نشده» و `return`
  5. `require vendor/woocommerce/action-scheduler/action-scheduler.php`. **باید در فایل اصلی و قبل از `plugins_loaded` باشد.** Action Scheduler خودش نسخه‌ها را بین افزونه‌ها هماهنگ می‌کند.
  6. `register_activation_hook`، `register_deactivation_hook` و `register_uninstall_hook` یا `uninstall.php`، همه در scope سطح بالای فایل
  7. `add_action('plugins_loaded', [Plugin::class, 'boot'], 5)`
- **ابزار کیفیت روی فایل‌های سازگار با PHP 7.0 (برای T0.2):** `vaqtyar.php`، `uninstall.php` و `src/Kernel/Requirements.php` نمی‌توانند visibility برای const یا نوع برگشتی `void` داشته باشند. در `tools/phpcs.xml` sniffهای `PSR12.Properties.ConstantVisibility` و return type hint اسلوومت را برای همین فایل‌ها exclude کن، و sniff `PHPCompatibility` با `testVersion 7.0-` را فقط روی همین سه فایل اجرا کن. **فایل را به سینتکس جدید «اصلاح» نکن.**
- `uninstall.php` هم باید روی PHP قدیمی parse شود، چون WP آن را حتی وقتی Requirements رد شده اجرا می‌کند.
- **نسخه در دو جا تعریف می‌شود:** `Version:` در header و ثابت `VERSION`. اسکریپت release برابری این دو را بررسی می‌کند.
- **Header** شامل این موارد است:
  - `Requires at least: 6.6`
  - `Requires PHP: 8.1`
  - `License: GPL-2.0-or-later`
  - `Text Domain: vaqtyar`
  - `Domain Path: /languages`
  - `Update URI: false` تا وقتی سیستم آپدیت خودمان را نداریم. این جلوی جایگزین‌شدن افزونه با افزونه هم‌نام در wp.org را می‌گیرد.

## 2. Composer
- `"name": "io98-ir/vaqtyar"` (توکن `vaqtyar` را rename عوض می‌کند)، `"type": "wordpress-plugin"`، `"license": "GPL-2.0-or-later"`.
- **`"config": {"platform": {"php": "8.1.0"}}`**: وابستگی‌ها با PHP 8.1 سازگار resolve می‌شوند، حتی اگر PHP محلی 8.3 باشد. **فراموش نشود.**
- `allow-plugins` برای `dealerdirect/phpcodesniffer-composer-installer` تنظیم می‌شود.
- اسکریپت‌ها: `lint`، `lint:fix`، `stan`، `deptrac`، `test:unit`، `test:integration`، `check`.
- **مسیر Repo پرانتز دارد:** در فایل‌های `.neon` (PHPStan) مسیر مطلق را حتماً در quote بگذار، وگرنه Nette آن را Statement تفسیر می‌کند و خطای `expandIncludedFile()` می‌دهد. مسیر نسبی (`%currentWorkingDirectory%` یا نسبت به فایل neon) بهتر است.
- Build تولیدی: `composer install --no-dev --optimize-autoloader --classmap-authoritative`.

## 3. i18n: تله‌های WP 6.7+
- **هیچ `__()` قبل از هوک `init` صدا زده نشود.** از WP 6.7 به بعد، این کار notice «_load_textdomain_just_in_time was called incorrectly» می‌دهد. پیام‌های Requirements هم از این قاعده مستثنی نیستند: ترجمه را داخل callback `admin_notices` انجام بده.
- ترجمه‌های داخل پوشه `languages/` با `load_plugin_textdomain()` روی `init` بارگذاری می‌شوند.
- JS: `wp_set_script_translations($handle, 'vaqtyar', $path)`. فایل‌های JSON با `wp i18n make-json` ساخته می‌شوند.
- text-domain همیشه **لیترال** است (ابزار `wp i18n make-pot` متغیر را نمی‌فهمد) و `rename.php` آن را عوض می‌کند.

## 4. زمان
- هرگز `date_default_timezone_set()` صدا زده نشود. وردپرس timezone پیش‌فرض PHP را UTC نگه می‌دارد.
- از `current_time()`، `time()` و `new DateTimeImmutable()` مستقیم در Domain و Application استفاده نشود. فقط `Clock`.
- `wp_timezone()` فقط در Infrastructure (برای timezone پیش‌فرض سایت) استفاده شود. Timezone هر Location صریح است.
- ستون `DATETIME` در UTC ذخیره می‌شود و مقدار با فرمت `Y-m-d H:i:s` نوشته و خوانده می‌شود. timezone ضمنی MySQL نداریم، پس `NOW()` در SQL ممنوع است و زمان همیشه از PHP پاس داده می‌شود.

## 5. دیتابیس
- `$wpdb->get_charset_collate()` در `CREATE TABLE` استفاده شود.
- Migrator از `CREATE TABLE IF NOT EXISTS` و `ALTER` صریح استفاده می‌کند، **نه** `dbDelta` (ر.ک. data-model §3).
- تراکنش: `START TRANSACTION`، `COMMIT` و `ROLLBACK` از طریق `$wpdb->query`. بعد از هر query باید `$wpdb->last_error` بررسی شود و در صورت خطا Exception پرتاب شود.
- **شماره خطاهای MySQL برای Retry:** 1213 (deadlock) و 1205 (lock wait timeout). با `mysqli_errno($wpdb->dbh)` خوانده می‌شوند.
- `SET SESSION innodb_lock_wait_timeout = 5` فقط داخل مسیر رزرو اجرا شود.
- در تست Integration، WP هر تست را داخل تراکنش اجرا و Rollback می‌کند. **پس تست همزمانی و تست تراکنش باید خارج از آن اجرا شوند** (testsuite جدا، یا درخواست HTTP واقعی روی wp-env).

## 6. REST
- Routeها روی `rest_api_init` ثبت می‌شوند. Namespace از `Identity`.
- `permission_callback` **همیشه** تعریف می‌شود. برای endpoint عمومی: callback ای که nonce یا توکن را بررسی کند، یا `__return_true` **فقط برای GET عمومی** (مثل availability)، همراه با rate limit.
- `args` با `type`، `required`، `sanitize_callback` و `validate_callback`. Validation دامنه‌ای در Value Objectها انجام می‌شود.

## 7. Assets
- Assetها فقط در صفحات لازم enqueue می‌شوند. در Admin با بررسی `$hook_suffix`. در Front با بررسی وجود block یا shortcode (`has_block()`، یا ثبت lazy در render callback).
- dependency و version همیشه از `build/*.asset.php` خوانده می‌شوند.

## 8. Git و انتشار
- یک commit برای هر Task، روی `main`. پیام Conventional Commits به انگلیسی با ID تسک، و خط `Co-Authored-By` طبق سیاست جاری.
- **Push:** در پایان هر `/wrap` و هر وقت کاربر بخواهد (ر.ک. agent-workflow §2.9).
- CI در GitHub Actions با هر push اجرا می‌شود (از T0.10). وضعیت CI بدون `gh` از اینجا دیده نمی‌شود، پس از کاربر بپرس یا `gh` نصب شود.
