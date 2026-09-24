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
  7. `add_action('plugins_loaded', static function () { (new \Vaqtyar\Kernel\Plugin(VAQTYAR_FILE, VAQTYAR_VERSION))->boot(new XModule(), …); }, 5)`. فایل اصلی Composition root است (architecture §5). closure بدون `: void` نوشته می‌شود، چون `void` از PHP 7.1 است.
- **تله Activation (برای T0.7):**
  - در درخواستی که افزونه را فعال می‌کند، `plugins_loaded` قبل از include شدن فایل اصلی رخ داده است. پس closure بالا اجرا نمی‌شود: Container ساخته نمی‌شود و هیچ `boot()` ای اجرا نمی‌شود.
  - در نتیجه `register_activation_hook` نباید داخل `boot()` ماژول باشد و باید در سطح بالای فایل اصلی بماند (مورد 6).
  - لیست ماژول‌ها باید یک‌جا در سطح بالای فایل اصلی تعریف شود تا هم boot و هم activation (migration و capability) از آن استفاده کنند. مثال سازگار با PHP 7.0: `$vaqtyar_modules = static function () { return array(new XModule(), …); };`. این تغییر در T0.7 انجام می‌شود، چون اولین نیاز واقعی همان‌جاست.
- **Container (T0.3):**
  - شناسه همیشه نام کلاس یا interface است (`class-string`)، تا `get()` نوع درست برگرداند.
  - PHPStan اتصال نادرست را تشخیص نمی‌دهد، چون T را از هر دو آرگومان استنتاج می‌کند (مثلاً `set(Foo::class, fn () => new Bar())`). بررسی `instanceof` در زمان اجرا تنها محافظ است.
  - ثبت دوباره یک شناسه Exception می‌دهد. override سرویس نداریم.
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
- **ابزار کیفیت (T0.2):**
  - **تابع‌های سراسری همیشه fully-qualified نوشته می‌شوند** (`\add_action()`، `\strlen()`). Deptrac فراخوانی بی‌پیشوند داخل namespace را به `Vaqtyar\...\add_action` تفسیر می‌کند و در نتیجه تابع WP داخل Domain دیده نمی‌شود. sniff `SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions` این قاعده را اجباری می‌کند و `composer lint:fix` خودکار اصلاحش می‌کند.
  - Deptrac تابع‌ها و کلاس‌های WP را فقط وقتی می‌شناسد که تعریفشان تحلیل شود. به همین دلیل `vendor/php-stubs/wordpress-stubs` در paths قرار دارد و layer `WordPress` همان stubها است.
  - قوانین لایه و قوانین ماژول در **دو فایل جدا** هستند (`deptrac-layers.yaml` و `deptrac-modules.yaml`). اگر کلاسی در دو layer باشد، کافی است یکی از آن layerها مجاز نباشد تا violation ثبت شود. به همین دلیل ترکیب «ماژول × لایه» در یک فایل درست کار نمی‌کند.
  - هر دو فایل deptrac یک layer به نام `Unclassified` دارند که هر کلاس `Vaqtyar\` خارج از بقیه layerها را می‌گیرد و وابستگی به آن را ممنوع می‌کند. بدون آن، کلاس بیرون از همه layerها فقط «uncovered» حساب می‌شود و راه دور زدن قوانین است. **ماژول یا پوشه جدید (غیر از Domain، Application، Infrastructure، Presentation، Contracts و `<Name>Module`) یعنی باید config deptrac به‌روز شود.**
  - Action Scheduler (`as_*` و `ActionScheduler*`) جزو layer `WordPress` است، پس Domain و Application نمی‌توانند مستقیم Job ثبت کنند (ADR-005: از طریق Port).
  - `global $wpdb` با `Squiz.PHP.GlobalKeyword` ممنوع است. شکل `$GLOBALS['wpdb']` را deptrac می‌بیند.
  - **هنوز اجبار نمی‌شود:** جهت گراف وابستگی ماژول‌ها (architecture §3). الان همه Contracts یک layer هستند و هر ماژولی می‌تواند Contracts هر ماژول دیگری را ببیند. با اولین ماژول‌ها در M1، Contracts هر ماژول جدا شود و ruleset از جدول §3 نوشته شود.
  - در regex داخل YAML تک‌کوتیشن، `\\` یعنی یک بک‌اسلش واقعی در namespace و `\w` همان کلاس کاراکتر است. مثال: `'#^Vaqtyar\\Modules\\\w+\\Domain\\#'`.
  - **شکاف:** PHPCompatibility 9.3 سینتکس PHP 8 (`?->`، `match`، named args، `enum`) را نمی‌شناسد. تضمین قطعی سازگاری PHP 7.0 سه فایل bootstrap با `php -l` روی PHP 7.0 واقعی در CI است (T0.10).
  - `phpunit.xml.dist` فعلاً فقط suite `unit` دارد. config و bootstrap Integration به WP test library و MySQL نیاز دارند و در T0.10 ساخته می‌شوند.
- **مسیر Repo پرانتز دارد:** در فایل‌های `.neon` (PHPStan) مسیر مطلق را حتماً در quote بگذار، وگرنه Nette آن را Statement تفسیر می‌کند و خطای `expandIncludedFile()` می‌دهد. مسیر نسبی (`%currentWorkingDirectory%` یا نسبت به فایل neon) بهتر است.
- Build تولیدی: `composer install --no-dev --optimize-autoloader --classmap-authoritative`.

## 2.1 نام‌ها و Rename (T0.4)
- **Helperهای نام** در `src/Kernel`:
  - `Tables::name('appointments')` ← `{$wpdb->prefix}vqy_appointments`
  - `Options::key('db_versions')`
  - `Hooks::name('booking/appointment_confirmed')` ← `vaqtyar/booking/appointment_confirmed`
  - `Caps::name('manage_bookings')`
  - REST namespace مستقیم از `Identity::REST_NAMESPACE` خوانده می‌شود.
- هر ورودی اعتبارسنجی می‌شود (`[a-z][a-z0-9_]*`) و ورودی نادرست `KernelException` می‌دهد، چون نام جدول بدون prepare وارد SQL می‌شود. سقف طول: 64 برای نام کامل جدول و 191 برای option.
- `Tables::name()` پیشوند سایت را در **هر فراخوانی** از `$wpdb` می‌خواند، چون `switch_to_blog()` در multisite آن را عوض می‌کند. در ثابت یا property نگهش ندار.
- **Rename:** `php tools/rename.php --name="…" --slug=… --namespace=… --prefix=… [--dry-run]`. گزینه‌ای که داده نشود مقدار فعلی را نگه می‌دارد.
  - **`const_prefix`، `hook_prefix`، `text_domain` و `rest_namespace` از slug مشتق می‌شوند** (`SLUG`، `slug`، `slug`، `slug/v1`). با این کار هر توکن در rename بعدی بی‌ابهام به عقب نگاشت می‌شود. در slug فقط `a-z0-9` مجاز است و `-` یا `_` نه، چون slug در شناسه‌های PHP (ثابت‌ها و prefix سراسری) هم استفاده می‌شود.
  - جایگزینی حساس به حالت حروف است (`Namespace`، `SLUG`، `slug`، `PREFIX`، `prefix`).
  - جایگزینی **فقط توکن کامل** را عوض می‌کند، یعنی جایی که حرف یا رقمی به آن نچسبیده باشد. `_` و `\` و `/` مرز حساب می‌شوند، پس `VAQTYAR_VERSION` و `wp_vqy_x` عوض می‌شوند. ولی `VaqtyarModule` یا `vqy` داخل یک hash عوض نمی‌شود و در بررسی باقیمانده، **قبل از هر نوشتنی**، باعث رد rename می‌شود. پس توکن را هرگز چسبیده به حرف ننویس.
  - پیشوند باید 3 تا 6 کاراکتر باشد.
  - نام نمایشی فقط در سه جای مشخص عوض می‌شود: `identity.json`، `Identity::NAME` و `Plugin Name` در header. اگر نام قدیمی شامل slug باشد (مثل «Vaqtyar») و `--name` داده نشود، rename رد می‌شود.
  - لیست فایل‌ها از `git ls-files` می‌آید. `docs/`، `.claude/`، `CLAUDE.md`، lock fileها (`composer.lock`، `pnpm-lock.yaml`، …) و فایل‌های باینری دست نمی‌خورند. hash داخل lock fileها ممکن است تصادفاً شامل پیشوند باشد.
  - **اجرای واقعی فقط روی working tree تمیز مجاز است** تا با `git checkout . && git clean -fd` بشود برگشت. dry-run این شرط را ندارد.
  - **توکن جدید نباید از قبل در کد وجود داشته باشد.** در غیر این صورت rename بعدی نمی‌تواند آن را از توکن ما تشخیص دهد و ابزار rename را رد می‌کند. به همین دلیل **هیچ‌جای کد توکن تستی را لیترال ننویس.** `tools/test-rename.php` آن را از چند تکه می‌سازد.
  - بعد از rename، `composer update --lock` (نام پکیج در content-hash قفل است) و `dump-autoload` اجرا می‌شوند. در پایان هیچ توکن قدیمی (با هر حالت حروف) نباید مانده باشد.
- **`composer test:rename`** یک کپی موقت می‌سازد، rename می‌کند و روی کپی `composer check` می‌گیرد (ADR-000). حدود 40 ثانیه طول می‌کشد و در CI اجرا می‌شود (T0.10).

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

## 4.1 Value Objectهای Shared (T0.5)
- همه در `src/Shared/Domain` هستند (PHP خالص). خطای ورودی `InvalidValue` است با `errorCode` (مثل `invalid_phone`). Presentation این کد را به پیام ترجمه‌شده و 422 تبدیل می‌کند. پیام Exception هیچ‌وقت ورودی کاربر را ندارد.
- **Money:** عدد صحیح در کوچک‌ترین واحد ارز (ریال).
  - سرریز int در PHP بی‌صدا به float تبدیل می‌شود. پس `add`، `subtract`، `multiply` و `percent` در این حالت Exception می‌دهند.
  - `percent()` همیشه یک `Rounding` صریح می‌گیرد: `Down` (به سمت صفر)، `Up` (دور از صفر) یا `HalfUp`.
  - فعلاً فقط `Currency::IRR` وجود دارد. بررسی یکسان‌بودن ارز با `@phpstan-ignore` نگه داشته شده و با اضافه‌شدن ارز دوم، PHPStan خودش یادآوری می‌کند.
- **PhoneNumber:** خروجی E.164 است. ورودی ارقام فارسی و عربی، فاصله، `-`، پرانتز و علامت‌های bidi را می‌پذیرد. شماره بدون کد کشور ایرانی فرض می‌شود و کشور دیگر فقط با `+` یا `00` پذیرفته می‌شود.
- **Email:** trim می‌شود و domain به حروف کوچک تبدیل می‌شود. مقایسه case-insensitive است.
- **LocalDate و LocalTime:** بدون منطقه زمانی.
  - `LocalTime` مقدار `24:00` را به‌عنوان پایان روز می‌پذیرد.
  - `dayOfWeek()` طبق ISO است (1 = دوشنبه تا 7 = یکشنبه). شنبه‌اول بودن هفته ایرانی فقط مسئله نمایش است.
- **TimeRange:** بازه نیمه‌باز `[start, end)`، در UTC و با ثانیه کامل. نوبت‌های پشت‌سرهم تداخل ندارند.
- **IntervalSet:** بازه‌های نیمه‌باز روی اعداد صحیح (timestamp یا دقیقه) که همیشه مرتب، جدا از هم و ادغام‌شده نگه داشته می‌شوند. `covers(s, e)` همان بررسی «Fit» در Availability است.
- **Ulid:** ساختنش خالص است: `Ulid::fromParts($time, random_bytes(10))`. Port تولید شناسه (`IdGenerator`، principles §3) با اولین مصرف‌کننده، یعنی Appointment در M2، ساخته می‌شود.
- **Clock:** interface در Domain است و `Vaqtyar\Shared\SystemClock` پیاده‌سازی آن است. در Domain و Application از `new DateTimeImmutable()` یا `time()` برای «الان» استفاده نکن.
- **پوشش:** با `phpdbg` محلی اندازه‌گیری می‌شود (dev-environment §1). مقدار T0.5 برای `Shared\Domain`: 184 از 185 خط (99.5%). تنها خط پوشش‌نیافته بررسی ارز است که با یک ارز قابل اجرا نیست.

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
- **پیام Exception متن ساده است و هرگز بدون escape در HTML چاپ نمی‌شود.** sniff `EscapeOutput.ExceptionNotEscaped` در `tools/phpcs.xml` به همین دلیل exclude شده است:
  - REST پیام را در JSON برمی‌گرداند.
  - Exception غیرمنتظره به یک پیام عمومی تبدیل می‌شود و متن اصلی فقط در لاگ ثبت می‌شود.
  - هر جایی از wp-admin که پیام نمایش داده می‌شود، هنگام render از `esc_html()` استفاده می‌کند.

  **Plugin Check این sniff را گزارش می‌کند.** `Late_Escaping_Check` کل `WordPress.Security.EscapeOutput` را بدون استثنا اجرا می‌کند (از سورس plugin-check بررسی شد، 2026-09-24). پس هر `throw` که آرگومان متغیر دارد در Plugin Check خطا می‌گیرد. Domain نمی‌تواند `esc_html()` صدا بزند، پس راه‌حل همگانی نداریم. **اگر wp.org کانال فروش شد (تصمیم باز 2)، این سیاست قبل از T6.4 بازبینی می‌شود.** گزینه این است که پیام Exception در Domain متغیر نداشته باشد و جزئیات در property نگه داشته شود.
- در پیام Exception ورودی کاربر گذاشته نشود. با `WP_DEBUG_DISPLAY`، PHP پیام Exception گرفته‌نشده را خام چاپ می‌کند.

## 7. Assets
- Assetها فقط در صفحات لازم enqueue می‌شوند. در Admin با بررسی `$hook_suffix`. در Front با بررسی وجود block یا shortcode (`has_block()`، یا ثبت lazy در render callback).
- dependency و version همیشه از `build/*.asset.php` خوانده می‌شوند.

## 8. Git و انتشار
- یک commit برای هر Task، روی `main`. پیام Conventional Commits به انگلیسی با ID تسک، و خط `Co-Authored-By` طبق سیاست جاری.
- **Push:** در پایان هر `/wrap` و هر وقت کاربر بخواهد (ر.ک. agent-workflow §2.9).
- CI در GitHub Actions با هر push اجرا می‌شود (از T0.10). وضعیت CI بدون `gh` از اینجا دیده نمی‌شود، پس از کاربر بپرس یا `gh` نصب شود.
