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
  - لیست ماژول‌ها باید یک‌جا در سطح بالای فایل اصلی تعریف شود تا هم boot و هم activation (migration و capability) از آن استفاده کنند. مثال سازگار با PHP 7.0: `$vaqtyar_modules = static function () { return array(new XModule(), …); };`. (در T0.7 انجام شد: `register_activation_hook` ← `Plugin::activate()` و `plugins_loaded` ← `Plugin::boot()`، هر دو با همین لیست.)
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
  - `phpunit.xml.dist` فقط suite `unit` دارد. Integration در `phpunit-integration.xml.dist` است (§2.2).
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

## 2.2 Integration و CI (T0.10)
- **wp-env 11:** محیط `tests` جدا (`env.tests` و `testsEnvironment`) منسوخ شده، **ولی اگر کلید `testsEnvironment` نباشد هنوز روشن است** (README می‌گوید پیش‌فرض `false` است، ولی کد `!== false` را بررسی می‌کند). پس `"testsEnvironment": false` در `.wp-env.json` لازم است. وگرنه MariaDB، WordPress و CLI دوم ساخته می‌شوند و هر خرابی آن‌ها `start` را می‌شکند. تست‌ها در `cli` اجرا می‌شوند. نسخه wp-env در CI روی major 11 ثابت است (`npx @wordpress/env@11`)، چون هنوز `package.json` نداریم (T0.11).
- **کتابخانه تست WP** از خود wp-env می‌آید (`$WP_TESTS_DIR` = `/wordpress-phpunit`، هم‌نسخه با core). پکیج `wp-phpunit/wp-phpunit` نصب نمی‌شود، چون نسخه‌اش با core ماتریس (6.6) نمی‌خواند. برای PHPStan فقط `php-stubs/wordpress-tests-stubs` در `scanFiles` است.
- **تله prefix جدول:** wp-env فایل `wp-tests-config.php` را از `wp-config.php` سایت می‌سازد، با همان prefix `wp_`. کتابخانه تست جدول‌ها را drop و دوباره نصب می‌کند، پس اجرای تست سایت dev را پاک می‌کرد. `tests/Integration/wp-tests-config.php` همان فایل را include می‌کند و prefix را `wptests_` می‌گذارد (از طریق ثابت `WP_TESTS_CONFIG_FILE_PATH`). این فایل در پروسه جدای install هم include می‌شود، پس به bootstrap وابسته نیست.
- افزونه با `tests_add_filter('muplugins_loaded', …)` بارگذاری می‌شود، یعنی **قبل از** `plugins_loaded`، مثل نصب واقعی.
- `vendor/` روی host (runner) با PHP 8.1 نصب و در containerها mount می‌شود. PHP container از `WP_ENV_PHP_VERSION` و core از `WP_ENV_CORE` می‌آید. WP 6.6 یعنی `WordPress/WordPress#6.6-branch` (آخرین patch).
- در config Integration، deprecationها Exception نمی‌شوند، چون core روی PHP جدیدتر deprecation خودش را دارد. suite Unit روی PHP 8.1 و 8.4 deprecationهای کد ما را می‌گیرد.
- **jobهای CI** (`.github/workflows/ci.yml`): `quality` (lint، stan، deptrac)، `unit` (PHP 8.1 و 8.4)، `legacy-syntax` (`php -l` روی PHP 7.0 برای سه فایل bootstrap؛ `php -l` قبل از 8.3 فقط یک فایل می‌گیرد)، `rename`، `integration` (PHP {8.1، 8.4} × WP {6.6، latest}). jobهای `concurrency` (T2.2)، `test-js` و `build` (T0.11) با اولین محتوای واقعی‌شان اضافه می‌شوند.
- **تله PHPStan محلی و CI:** job `quality` در CI روی PHP 8.1 اجرا می‌شود و PHPStan محلی روی PHP 8.3. با نسخه یکسان PHPStan (2.2.15)، نتیجه‌ها می‌توانند متفاوت باشند. مثلاً `str_replace(\range('0', '9'), …)` فقط در CI خطا گرفت (`range` عدد int برمی‌گرداند، نه رشته). گذاشتن `phpVersion: 80100` این تفاوت را برطرف نکرد. **مرجع نهایی CI است.** محلی سبز بودن یعنی «احتمالاً سبز».
- **تله PHP 8.4 + WP 6.6:** در مسیر reconnect، `wpdb::check_connection()` تابع `mysqli_ping()` را صدا می‌زند که روی PHP 8.4 deprecation چاپ می‌کند (باگ core). چون `failOnRisky` روشن است، این خروجی تست را قرمز می‌کند. در تستی که این مسیر را اجرا می‌کند، `E_DEPRECATED` فقط در همان تست خاموش می‌شود.
- **تله phpcs:** اضافه کردن `<rule ref="X">` برای exclude کردن یک فایل، اگر X قبلاً در ruleset نبوده، آن sniff را برای کل کد **روشن** می‌کند. قبل از exclude، بررسی کن sniff اصلاً فعال است.
- **تله PowerShell:** `composer require "pkg:^1.0"` از `composer.bat` رد می‌شود و cmd علامت `^` را حذف می‌کند (constraint می‌شود `1.0`). constraint را بعداً در `composer.json` اصلاح کن و `composer update --lock` بزن، یا از Git Bash اجرا کن.

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

## 4.2 Jalali و DateFormatter (T0.6)
- **`Shared\Domain\Jalali`:** سرویس خالص و بدون state (`fromGregorian`، `toGregorian`، `isLeapYear`، `daysInMonth`).
  - الگوریتم Borkowski است (همان jalaali-js): چرخه 33 ساله با سال‌های شکست. API از سال 1 تا 3176 پشتیبانی می‌کند، چون الگوریتم به نوروز سال بعد هم نیاز دارد.
  - در بازه 1300 تا 1500 روزبه‌روز (حدود 73 هزار روز)، در هر دو جهت و در وضعیت کبیسه، با ICU (`@calendar=persian`) یکسان است.
  - **ICU از سال 1634 به بعد متفاوت است.** ICU قاعده ساده 33 ساله را به کار می‌برد. پس خروجی ما را در آن سال‌ها با ICU «اصلاح» نکن.
  - آزمون پیوستگی روی همه سال‌های 1 تا 3176 بررسی می‌کند که هر سال دقیقاً روز بعد از پایان سال قبل شروع شود و طولش با وضعیت کبیسه بخواند. این آزمون شاخه‌های اصلاحی را که ICU به آن‌ها نمی‌رسد پوشش می‌دهد.
  - `ext-intl` در require-dev است تا آزمون ICU بی‌صدا skip نشود.
  - تاریخ در ذخیره‌سازی و منطق همیشه میلادی است (`LocalDate`). Jalali فقط برای ورودی و نمایش است.
  - **تله port:** در تبدیل روزی که قبل از نوروز سال میلادی است، leap **سال اولیه** استفاده می‌شود، نه سال قبل. آزمون ICU این اشتباه را گرفت.
- **`Shared\DateFormatter`:** تنها جایی است که تاریخ به متن تبدیل می‌شود. تقویم (`Calendar`) و ارقام (`Digits`) از تنظیمات می‌آیند (T0.9). منطقه زمانی همیشه صریح پاس داده می‌شود (سایت یا شعبه).
  - نام ماه‌های شمسی با `_x('Farvardin', 'Jalali month', …)` ترجمه‌پذیرند. **تا وقتی فایل fa_IR ساخته نشده (T6.3)، نام انگلیسی آوانگاری‌شده نمایش داده می‌شود.**
  - تاریخ بلند میلادی از `wp_date('j F Y')` می‌آید تا ترجمه هسته وردپرس برای نام ماه به کار برود.
  - `digits()` برای هر عددی که نمایش داده می‌شود کاربرد دارد، مثلاً پول در Formatter بعدی.
  - **Bidi:** ارقام فارسی در Unicode کلاس AN دارند. `/` چند رشته AN را به یک run تبدیل می‌کند ولی `-` این کار را نمی‌کند، پس «۲۰۲۶-۰۹-۲۴» برعکس نمایش داده می‌شود. به همین دلیل تاریخ عددی با ارقام فارسی همیشه `/` دارد.
  - تاریخ و زمانی که کنار هم در صفحه LTR با ارقام فارسی می‌آیند، ممکن است جابه‌جا نمایش داده شوند. رفع این مورد کار UI است (`<bdi>`). در متن ذخیره‌شده یا پیامک کاراکتر نامرئی نمی‌گذاریم.

## 4.3 Catalog Domain (T1.1)
- Entityها `final` با propertyهای `readonly` هستند و هر invariant در سازنده بررسی می‌شود. `id` تا ذخیره‌شدن `null` است. ویرایش یعنی ساختن نمونه جدید با همان id (Repository در T1.2). خطای ورودی `InvalidValue` با کد است (مثل `invalid_name`، `default_variant`، `duplicate_staff`) و Router آن را به 422 تبدیل می‌کند.
- Value Objectهای ماژول: `Name` (یک خط، 1 تا 191 کاراکتر، بدون کاراکتر کنترلی، چون در پیامک و subject ایمیل می‌رود)، `Color` (فقط `#rrggbb` کوچک، چون در style inline نوشته می‌شود) و `Slug` (کلید گروه منبع و تقویم تعطیلات).
- **`Service` تنها Aggregate چندبخشی است** (Variantها، `ServiceStaff` و `ResourceRequirement`) و یک‌جا ذخیره می‌شود. دقیقاً یک Variant پیش‌فرض دارد.
- **معنای `ServiceStaff`:** ردیف بدون Variant یعنی پرسنل همه Variantها را ارائه می‌دهد، و ردیف با Variant یعنی فقط همان را. `Service::terms($variantId, $staffId)` مدت و قیمت را فیلد به فیلد از اولین منبعی که مقدار دارد برمی‌دارد: ردیف همان Variant، ردیف سراسری، خود Variant. اگر پرسنل تخصیص نداشته باشد، `null` برمی‌گرداند. **ردیف سراسری فقط وقتی قیمت یا مدت دارد که خدمت یک Variant داشته باشد** (`override_needs_variant`)، چون یک قیمت یا مدت برای ویزیت 30 و 60 دقیقه‌ای تفاوت Variantها را از بین می‌برد. PriceCalculator (T2.3، مرحله 2) و Availability (T1.4، مدت) از همین استفاده می‌کنند.
- تخصیص فقط به Variant ذخیره‌شده (با id) ممکن است. پس UI اول Variant جدید را ذخیره می‌کند و بعد قیمت اختصاصی آن را.
- **idها و `sort` در سازنده بررسی می‌شوند** (trait `GuardsStoredNumbers`: id مثبت، sort بین 0 و 1,000,000). wpdb حالت strict در MySQL را خاموش می‌کند، پس مقدار بیرون از بازه ستون بی‌صدا clamp می‌شود (id منفی ← 0) و خطا نمی‌دهد.
- **Timezone شعبه** فقط از `DateTimeZone::listIdentifiers()` است (نام‌های منطقه‌ای و `UTC`). offset ثابت (`+03:30`، `Etc/GMT-3`، `EST`) و aliasهای قدیمی (`Iran`) رد می‌شوند.
- کلاس منبع `BookableResource` نام دارد، چون `resource` در PHP کلمه soft-reserved است و PHPCompatibility آن را رد می‌کند.
- سقف‌ها: مدت، Buffer و گام اسلات تا 1440 دقیقه. ظرفیت خدمت و منبع تا 1000. تعداد Extra و منبع لازم تا 100. متن‌های TEXT تا 65535 بایت (نه کاراکتر). قیمت منفی در Variant، Extra و قیمت اختصاصی پذیرفته نمی‌شود، چون تخفیف کار Price rule و کوپن است.
- `search_name` پرسنل را Repository در T1.2 پر می‌کند. نرمال‌سازی فارسی با Customers (T2.7) مشترک است و وقتی دومین مصرف‌کننده آمد جدا می‌شود.

## 5. دیتابیس
- `$wpdb->get_charset_collate()` در `CREATE TABLE` استفاده شود (`Db::createTable()` این کار را می‌کند).
- Migrator از `CREATE TABLE IF NOT EXISTS` و `ALTER` صریح استفاده می‌کند، **نه** `dbDelta` (ر.ک. data-model §3).
- **`Kernel\Database\Db` (T0.7)** تنها راه کد ما به `$wpdb` است:
  - `execute()` و `getVar()` پارامتر `literal-string` دارند (PHPStan). مقدار با `%s` یا `%d` و **نام جدول با `%i`** (از WP 6.2) وارد SQL می‌شود. پس هیچ رشته‌ای از بیرون کد به SQL چسبانده نمی‌شود. اگر PHPStan خطای `literal-string` داد، راه‌حل placeholder است، نه cast.
  - `insert()` و `update()` برای `int` فرمت `%d` و برای `string` فرمت `%s` می‌سازند. `null` به `NULL` تبدیل می‌شود و در شرط `update` به `IS NULL`. `float` پذیرفته نمی‌شود (پول int است).
  - هر خطا `DbException` می‌دهد، حتی وقتی wpdb بی‌صدا `false` برمی‌گرداند (مقدار بلندتر از ستون یا charset نامعتبر). **پیام Exception متن خطای MySQL را ندارد**، چون MySQL مقدار را نقل می‌کند (`Duplicate entry '0912…'`). متن در `$e->detail` و شماره خطا در `$e->errno` است.
  - در طول هر فراخوانی، نمایش خطای wpdb خاموش است (`hide_errors()`) و بعد به حالت قبل برمی‌گردد. `error_log` خود wpdb سر جایش است.
  - `errno` از `mysqli_errno()` روی `$wpdb->dbh` خوانده می‌شود. `dbh` در stubها protected است و از `__get()` قدیمی wpdb خوانده می‌شود.
  - `Db::createTable()` بعد از `CREATE`، موتور جدول را از `information_schema` می‌خواند و اگر InnoDB نبود (جایگزینی بی‌صدای MySQL، یا جدولی که از قبل MyISAM بوده) `DbException` با پیام روشن می‌دهد. DDL قابل prepare نیست، پس نام از `Tables::name()` و تعریف ستون‌ها `literal-string` است.
- **`Transaction::run(callable)`:** تراکنش تودرتو رد می‌شود (`KernelException`)، چون `START TRANSACTION` دوم در MySQL تراکنش اول را بی‌صدا commit می‌کند. روی 1213 و 1205 کل کار دوباره اجرا می‌شود (حداکثر 3 Retry، یعنی 4 تلاش، با مکث تصادفی 5 تا 20 میلی‌ثانیه ضرب در شماره تلاش). **پس callable باید هر چیزی را که بر اساسش تصمیم می‌گیرد داخل تراکنش بخواند و اثر جانبی بیرون از DB نداشته باشد.** اگر ROLLBACK هم شکست بخورد، `DbException` با خطای اصلی به‌عنوان previous پرتاب می‌شود.
- **تله reconnect در wpdb:** روی خطای 2006 (server has gone away)، `wpdb::query()` بی‌صدا دوباره وصل می‌شود و همان statement را روی connection جدید اجرا می‌کند. آن connection تراکنش ندارد و autocommit است، پس قفل‌های `FOR UPDATE` و بررسی تداخل از دست رفته‌اند. `Db` شناسه connection (`thread_id`) را هنگام `START TRANSACTION` نگه می‌دارد و بعد از هر فراخوانی داخل تراکنش مقایسه می‌کند. اگر عوض شده باشد، `DbException::connectionLost()` (2006، **بدون Retry**) می‌دهد. **ریسک باقیمانده برای T2.2:** statementی که wpdb تکرار کرده ممکن است commit شده باشد (مثلاً یک ردیف `occupancies`). این در جهت امن است (اسلات بی‌دلیل اشغال می‌ماند، نه Double booking)، و ردیف Hold با انقضا پاک می‌شود. ولی کد رزرو نباید فرض کند که Exception یعنی «هیچ چیز نوشته نشده».
- **Migrator:**
  - `Migration` یک interface با `up(Db)` است. هر ماژول با `Module::migrations()` لیست مرتب خودش را می‌دهد. **لیست فقط اضافه‌شدنی است**، چون نسخه ذخیره‌شده همان تعداد migrationهای اجراشده است.
  - نسخه‌ها در option `db_versions` (autoload) هستند، پس بررسی «به‌روز است؟» در هر درخواست کوئری ندارد. اگر ماژولی migration نداشته باشد، boot اصلاً به DB دست نمی‌زند.
  - قفل با `GET_LOCK(SHA1(CONCAT(DATABASE(), '.' + نام پیشونددار سایت)), 0)` گرفته می‌شود، یعنی بدون انتظار. درخواستی که قفل را نگرفت migration را رد می‌کند و ادامه می‌دهد. نسخه‌ها زیر قفل دوباره خوانده می‌شوند و بعد از هر migration ذخیره می‌شوند.
  - **تله cache:** `db_versions` autoload است و `get_option()` آن را از cache `alloptions` (یا `notoptions`) می‌خواند که ابتدای درخواست پر شده است. پس زیر قفل، قبل از خواندن دوباره، `alloptions`، `notoptions` و خود کلید از cache حذف می‌شوند. وگرنه درخواستی که بعد از پایان کار درخواست دیگر قفل را گرفته، migration را دوباره اجرا می‌کند.
  - **Migration ناموفق در boot** (نبود مجوز ALTER، نبود InnoDB، …) شرط سرور است، نه باگ. پس برخلاف بقیه Exceptionهای boot، گرفته می‌شود: پیام عمومی در `error_log` ثبت می‌شود (بدون `detail`)، یک admin notice به کاربر `activate_plugins` نمایش داده می‌شود، و **هیچ ماژولی boot نمی‌شود**. در `activate()` Exception عبور می‌کند تا فعال‌سازی با پیام روشن رد شود. **این مسیر عمداً `error_log` می‌ماند، نه Logger** (T0.9): چیزی که شکسته خود DB است (شاید همان جدول `logs`)، و notice مدیر را به لاگ PHP می‌فرستد که بدون افزونه هم خواندنی است.
  - نسخه ذخیره‌شده بزرگ‌تر از لیست (downgrade افزونه) خطا نیست و کاری انجام نمی‌شود.
  - در multisite و فعال‌سازی شبکه‌ای، فقط سایت جاری در activation migrate می‌شود. بقیه سایت‌ها در اولین درخواستشان (مسیر boot) migrate می‌شوند.
  - ALTER باید idempotent باشد: قبلش وجود ستون یا ایندکس را از `information_schema` بررسی کن.
- **تله تست Integration:** `WP_UnitTestCase` در `set_up` دستور `SET autocommit = 0` را اجرا می‌کند و هیچ‌وقت برش نمی‌گرداند. همچنین با فیلتر `query`، `CREATE TABLE` را به `CREATE TEMPORARY TABLE` تبدیل می‌کند و جدول موقت در `information_schema.TABLES` دیده نمی‌شود. پس تست‌های Db، Transaction و Migrator از `PHPUnit\Framework\TestCase` ارث می‌برند، از trait `RealDatabase` استفاده می‌کنند (که `autocommit = 1` می‌گذارد) و خودشان جدول‌ها و option را پاک می‌کنند.
- **تله تست قفل با connection دوم:** `mysqli::close()` قبل از پایان session در سرور برمی‌گردد. پس `GET_LOCK(…, 0)` بلافاصله بعد از آن گاهی قفل را هنوز گرفته می‌بیند (flaky در CI، run 36149811656). قفل را قبل از close با `RELEASE_LOCK` صریحاً آزاد کن.
- **تله Bash tool:** heredoc با ترکیب `'` و `"` و backtick در Bash tool گاهی خطای parse می‌دهد. برای ویرایش‌های چندخطی از Edit یا اسکریپت PHP در scratchpad استفاده کن.
- تراکنش: `START TRANSACTION`، `COMMIT` و `ROLLBACK` از طریق `$wpdb->query`. بعد از هر query باید `$wpdb->last_error` بررسی شود و در صورت خطا Exception پرتاب شود.
- **شماره خطاهای MySQL برای Retry:** 1213 (deadlock) و 1205 (lock wait timeout). با `mysqli_errno($wpdb->dbh)` خوانده می‌شوند.
- `SET SESSION innodb_lock_wait_timeout = 5` فقط داخل مسیر رزرو اجرا شود.
- در تست Integration، WP هر تست را داخل تراکنش اجرا و Rollback می‌کند. **پس تست همزمانی و تست تراکنش باید خارج از آن اجرا شوند** (testsuite جدا، یا درخواست HTTP واقعی روی wp-env).

## 6. REST
- Routeها روی `rest_api_init` ثبت می‌شوند. Namespace از `Identity`.
- `permission_callback` **همیشه** تعریف می‌شود. برای endpoint عمومی: callback ای که nonce یا توکن را بررسی کند، یا `__return_true` **فقط برای GET عمومی** (مثل availability)، همراه با rate limit.
- **`Kernel\Rest\Router` (T0.8)** به‌جای کلاس پایه Controller است. Controller یک کلاس `final` ساده است که روی `rest_api_init` متد `$router->add($path, $methods, $callback, $permission, $args, ?RateLimit)` را صدا می‌زند. Router از Container (singleton) گرفته می‌شود.
  - Router مرز خطاست. callback و `permission_callback` هر دو داخل `try` اجرا می‌شوند و هر Exception به Envelope تبدیل می‌شود: `{"code","message","data":{"status","details","request_id"}}`. این شکل همان شکل `WP_Error` در REST است، پس کلاینت‌های وردپرس (`apiFetch`) هم آن را می‌فهمند. `details` در JSON همیشه object است، حتی خالی.
  - نگاشت: `ApiError` ← status، code و details خودش (پیام را Controller ترجمه‌شده می‌دهد). `InvalidValue` ← 422 با `errorCode` و پیام عمومی ترجمه‌شده. هر چیز دیگر ← 500 `internal_error` با پیام عمومی. Exception با `Logger` (کانال `rest`) ثبت می‌شود: کلاس، کد، فایل، خط و متن **ماسک‌شده** (§6.1)، زیر همان `request_id` که کلاینت می‌بیند. `WP_Error` ای که callback برگرداند هم به Envelope تبدیل می‌شود (status از data آن، وگرنه 500).
  - `permission_callback` فقط با `true` عبور می‌کند. در غیر این صورت `rest_forbidden` با `rest_authorization_required_code()` (401 برای مهمان و 403 برای کاربر بدون مجوز)، مثل خود وردپرس.
  - `Router::ANYONE` (همان `'__return_true'`) فقط روی `GET` و همراه `RateLimit` پذیرفته می‌شود. در غیر این صورت `add()` خطای `KernelException` می‌دهد. closure ای که `true` برمی‌گرداند این بررسی را دور می‌زند، پس Reviewer آن را چک می‌کند.
  - خطاهایی که وردپرس **قبل از** کد ما تولید می‌کند (route ناموجود، و پارامتری که schema در `args` را رد می‌کند: 400 `rest_invalid_param`) شکل خود وردپرس را دارند و `request_id` ندارند. یعنی خطای نوع و بازه در schema 400 است و خطای قاعده دامنه 422.
  - **ترتیب اجرا در وردپرس:** validation و sanitize آرگومان‌ها ← `permission_callback` ← callback. Rate limit داخل callback و بعد از permission شمرده می‌شود، چون فقط callback می‌تواند هدر `Retry-After` بفرستد (`WP_Error` برگشتی از permission هدر ندارد). درخواستی که پارامترش نامعتبر است، یا permission ردش کرده، شمرده نمی‌شود. این آگاهانه است: nonce و توکن نشست با brute force شکستنی نیستند. اگر endpointی لازم داشت تلاش‌های رد‌شده را بشمارد (مثل OTP در T4.3)، `RateLimiter` را خودش مستقیم صدا می‌زند و `ApiError::tooManyRequests()` پرتاب می‌کند.
- **`RateLimiter`:** شمارنده Fixed window در جدول `rate_limits`. بسته به نتیجه، `attempt($key, RateLimit)` مقدار 0 یا ثانیه‌های باقیمانده تا پایان پنجره را برمی‌گرداند.
  - شمارش یک statement اتمی است: `INSERT … ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(hits + 1)`. MySQL برای ردیف جدید 1 و برای ردیف به‌روزشده 2 ردیف affected گزارش می‌کند. در حالت دوم شمارش از `Db::lastInsertId()` خوانده می‌شود. wpdb بعد از همان INSERT مقدار `mysqli_insert_id()` را در `insert_id` می‌گذارد، که برای `LAST_INSERT_ID(expr)` همان expr است. پس مقدار درخواست همزمان دیگر خوانده نمی‌شود. **`SELECT LAST_INSERT_ID()` جدا ممنوع است:** drop-inهای read/write split (HyperDB و LudicrousDB) SELECT بدون جدول را به replica می‌فرستند، نتیجه 0 می‌شود و limiter همه را عبور می‌دهد. transient استفاده نمی‌شود، چون خواندن و بعد نوشتن در آن اتمی نیست.
  - **خارج از تراکنش صدا زده شود،** تا قفل ردیف فقط یک statement طول بکشد. از `Transaction` هم استفاده نمی‌کند: `START TRANSACTION` داخل تست `WP_UnitTestCase` تراکنش خود تست را بی‌صدا commit می‌کند.
  - bucket یک HMAC-SHA256 با `wp_salt('nonce')` از «طول پنجره + کلید» است. پس کلید می‌تواند شماره موبایل یا IP باشد و در DB ذخیره نمی‌شود. hash بدون کلید برای شماره موبایل با brute force برگشت‌پذیر است.
  - پنجره‌ها به طولشان تراز می‌شوند (`floor(now / window) * window`). وقتی یک bucket پنجره جدید باز می‌کند (hits = 1)، تا 100 ردیف منقضی (`expires_at <= now`) در کل جدول حذف می‌شود. پس حجم جدول حدوداً برابر کلاینت‌های فعال است و Job جدا لازم نیست.
  - Deadlock: upsert فقط قفل PK یک ردیف را می‌گیرد و prune ردیف‌های منقضی را، که با ردیف پنجره جاری مشترک نیستند. چرخه قفل ممکن نیست، پس Retry ندارد. **اگر خطای 1213 از این مسیر در لاگ دیده شد، Retry اضافه شود.**
- **`ClientIp`:** فقط `REMOTE_ADDR`. هدرهای `X-Forwarded-For` را خود کلاینت می‌سازد و با آن‌ها هر کس bucket خودش را انتخاب می‌کند. IPv6 با شبکه /64 شمرده می‌شود و IPv4-mapped به‌عنوان IPv4. **سایت پشت CDN (ArvanCloud، Cloudflare)** برای همه بازدیدکننده‌ها IP پروکسی را می‌بیند و باید فیلتر `Hooks::name('rest/client_ip')` را پیاده کند. خروجی فیلتر هم با `FILTER_VALIDATE_IP` بررسی می‌شود. اگر فیلتر خراب مقدار نامعتبر برگرداند، کلاینت «unknown» حساب می‌شود و خطای 500 رخ نمی‌دهد. گزینه تنظیمات برای آن (انتخاب هدر پروکسی مورد اعتماد) هنوز تصمیم باز است.
- **`Pagination`:** `Pagination::ARGS` در `args` route ادغام می‌شود (`page` بین 1 و `MAX_PAGE` = 1,000,000 تا offset به float سرریز نکند، `per_page` بین 1 و 100، پیش‌فرض 20) و وردپرس بازه را اجرا می‌کند. `Pagination::fromRequest()` ← `offset()` ← `response($items, $total)` که هدرهای `X-WP-Total` و `X-WP-TotalPages` را می‌گذارد. route بدون `ARGS` خطای `KernelException` می‌دهد.
- **جداول خود Kernel** (`rate_limits` و بعداً `logs`) با owner `kernel` (`Plugin::KERNEL_ID`) در Migrator ثبت می‌شوند و قبل از ماژول‌ها اجرا می‌شوند. هیچ ماژولی نمی‌تواند این id را داشته باشد. در نتیجه boot همیشه Migrator را صدا می‌زند، ولی وقتی schema به‌روز است فقط option autoload را می‌خواند و کوئری ندارد.
- **تست Integration برای REST:** در `set_up`، `$GLOBALS['wp_rest_server'] = null` بگذار و routeها را با `add_action('rest_api_init', …)` اضافه کن. سرور و routeها در اولین `rest_do_request()` یا `rest_get_server()` ساخته می‌شوند. ثبت route بیرون از `rest_api_init` باعث `_doing_it_wrong` می‌شود. کلاس‌های `WP_UnitTestCase` متدهای `set_up()` و `tear_down()` دارند (`setUp()` در آن‌ها final است). sniff `CamelCapsMethodName` برای `tests/Integration` خاموش است.
- **تله PHPStan:** بعد از `assertSame(x, $obj->method())`، PHPStan فراخوانی یکسان بعدی را هم x فرض می‌کند (`alreadyNarrowedType`)، حتی با `@phpstan-impure`. نتیجه‌ها را در آرایه جمع کن و یک‌جا مقایسه کن.
- `args` با `type`، `required`، `sanitize_callback` و `validate_callback`. Validation دامنه‌ای در Value Objectها انجام می‌شود.
- **پیام Exception متن ساده است و هرگز بدون escape در HTML چاپ نمی‌شود.** sniff `EscapeOutput.ExceptionNotEscaped` در `tools/phpcs.xml` به همین دلیل exclude شده است:
  - REST پیام را در JSON برمی‌گرداند.
  - Exception غیرمنتظره به یک پیام عمومی تبدیل می‌شود و متن اصلی فقط در لاگ ثبت می‌شود.
  - هر جایی از wp-admin که پیام نمایش داده می‌شود، هنگام render از `esc_html()` استفاده می‌کند.

  **Plugin Check این sniff را گزارش می‌کند.** `Late_Escaping_Check` کل `WordPress.Security.EscapeOutput` را بدون استثنا اجرا می‌کند (از سورس plugin-check بررسی شد، 2026-09-24). پس هر `throw` که آرگومان متغیر دارد در Plugin Check خطا می‌گیرد. Domain نمی‌تواند `esc_html()` صدا بزند، پس راه‌حل همگانی نداریم. **اگر wp.org کانال فروش شد (تصمیم باز 2)، این سیاست قبل از T6.4 بازبینی می‌شود.** گزینه این است که پیام Exception در Domain متغیر نداشته باشد و جزئیات در property نگه داشته شود.
- در پیام Exception ورودی کاربر گذاشته نشود. با `WP_DEBUG_DISPLAY`، PHP پیام Exception گرفته‌نشده را خام چاپ می‌کند.

## 6.1 Settings، SecretStore، Logger، Capabilities (T0.9)
- **Settings:** هر گروه یک کلاس `final` با propertyهای `readonly` و typed است که `SettingsGroup` را پیاده می‌کند (`name()`، `autoload()`، `fromStored()`، `toStored()`) و در یک option ذخیره می‌شود: `Options::key('settings_' . name)`. `Settings::get(GeneralSettings::class)` نمونه typed برمی‌گرداند.
  - `fromStored()` **هرگز Exception نمی‌دهد:** هر مقدار ناموجود یا نامعتبر (نسخه قدیمی، option دست‌کاری‌شده) جداگانه به پیش‌فرض خودش برمی‌گردد. Validation سختگیرانه ورودی کاربر کار REST تنظیمات است (T3.5 و T6.1).
  - `Settings` چیزی cache نمی‌کند: `get_option()` خودش cache دارد و نسخه نگه‌داشته‌شده بعد از `switch_to_blog()` کهنه می‌شد.
  - `autoload` در `update_option()` فقط وقتی مقدار عوض شود اعمال می‌شود. اگر گروهی در نسخه بعد autoload خود را عوض کرد، migration لازم دارد.
  - گروه موجود: `GeneralSettings` (تقویم و ارقام، autoload). `DateFormatter` در Container از همین ساخته می‌شود. **تا اولین ذخیره (Onboarding در T6.1) option وجود ندارد** و بدون object cache هر خواندن یک کوئری است.
  - Application به `Settings` دسترسی ندارد (deptrac)؛ Infrastructure مقدار لازم را به آن پاس می‌دهد.
- **SecretStore:** `get/set/isDefinedInConfig`. ثابت `{SLUG}_{NAME}` در `wp-config.php` اولویت دارد (نام از `Identity::SLUG` ساخته می‌شود، پس با rename عوض می‌شود). ثابت عددی (terminal id بدون کوتیشن) به متن تبدیل می‌شود. ثابت خالی یا غیر رشته‌ای null است و لاگ می‌شود. `set()` روی secretی که ثابت دارد `KernelException` می‌دهد. مقدار خالی یعنی حذف.
  - رمزنگاری: XChaCha20-Poly1305 (AEAD) با nonce تصادفی 24 بایتی، و **نام option به‌عنوان Additional Data**، تا ciphertext کپی‌شده زیر نام دیگر باز نشود. قالب ذخیره `v1:` + base64(nonce + ciphertext)، autoload خاموش.
  - کلید: BLAKE2b از `'secret-store:' . wp_salt('auth')` (یعنی AUTH_KEY و AUTH_SALT؛ اگر تعریف نشده باشند، مقدار تصادفی ذخیره‌شده وردپرس). **عوض‌کردن salt همه secretها را غیرقابل‌خواندن می‌کند:** `get()` مقدار null می‌دهد و خطا با نام secret (نه مقدار) لاگ می‌شود.
  - `sodium_*` همیشه هست: وردپرس sodium_compat را همراه دارد.
  - ماسک UI (`••••1234`، principles §7) با صفحه تنظیمات secretها ساخته می‌شود (M5)، نه الان (principles §0).
- **Logger:** `error/warning/info($channel, $message, $context)` در جدول `logs` با `request_id`. **هرگز Exception نمی‌دهد.**
  - `Pii::mask()` روی پیام، مقدارها **و کلیدهای** context اجرا می‌شود: ایمیل ← `***@domain`، و هر رشته 8 رقم یا بیشتر (لاتین، فارسی، عربی؛ با فاصله، `-` و پرانتز بینشان) ← `***` + 4 رقم آخر. تاریخ ISO دست نمی‌خورد، ولی timestamp یونیکس ماسک می‌شود. **نام و آدرس تشخیص داده نمی‌شوند:** آن‌ها را در پیام و context نگذار.
  - `Throwable` در context به کلاس، کد، متن ماسک‌شده، فایل و خط تبدیل می‌شود. enum به مقدارش، object به نام کلاس، عمق بیش از 5 به `[too deep]`، و JSON بزرگ‌تر از 16KB به `{"truncated":true}`. پیام بعد از ماسک به 1000 کاراکتر کوتاه می‌شود (ماسک قبل از برش، تا نیمه شماره باقی نماند).
  - اگر نوشتن در جدول شکست بخورد (جدول هنوز ساخته نشده، DB قطع است)، همان خط ماسک‌شده همراه context در `error_log` می‌رود.
  - Retention سی‌روزه: بعد از هر نوشتن، تا 100 ردیف قدیمی حذف می‌شود، **جز داخل تراکنش** (`Db::inTransaction()`)، چون قفل ردیف‌های حذف‌شده تا commit فراخواننده می‌ماند و کنار قفل‌های رزرو یال جدید deadlock می‌سازد.
  - خطی که داخل `Transaction` نوشته شود با rollback آن از بین می‌رود. شکست را بعد از تراکنش و در catch لاگ کن.
  - Application به Logger دسترسی ندارد (deptrac). اگر Use Caseی لازم داشت، Port در Contracts یا SharedDomain با اولین مصرف‌کننده ساخته می‌شود.
- **Capabilities:** هر ماژول با `Module::capabilities()` نقشه «نام کوتاه ← نقش‌های پیش‌فرض» می‌دهد (اگر دو ماژول یک capability را نام ببرند، نقش‌ها جمع می‌شوند). `Capabilities::grant()` در `activate()` و در `boot()` بعد از migration موفق اجرا می‌شود (بعد از update، activation اجرا نمی‌شود).
  - هر جفت «capability، نقش» **فقط یک‌بار** داده می‌شود و در option autoload `granted_caps` ثبت می‌شود. پس capabilityی که مدیر سایت از نقشی گرفت، با update برنمی‌گردد، و درخواستی که چیز جدیدی ندارد کوئری ندارد. نقشی که هنوز وجود ندارد رد می‌شود و بعد از ساخته‌شدن، در اولین درخواست capability را می‌گیرد.
  - نقش‌ها و option برای هر سایت جدا هستند. در multisite هر سایت در اولین درخواست خودش به‌روز می‌شود.
  - **نقش‌های خود افزونه** (Manager، Receptionist، Staff؛ product-scope §2) با اولین capabilityهای واقعی‌شان ساخته می‌شوند (M1 تا M3). حذف capabilityها در uninstall هم با همان کار می‌آید.
  - **تله تست:** `WP_User` ساخته‌شده capabilityهایش را هنگام بارگذاری نگه می‌دارد. در تست بعد از `grant()`، `user_can()` را با id صدا بزن. `WP_Roles` در حافظه با rollback تست برنمی‌گردد، پس تست capability را در `tear_down` پس بگیرد.

## 7. Assets
- Assetها فقط در صفحات لازم enqueue می‌شوند. در Admin با بررسی `$hook_suffix`. در Front با بررسی وجود block یا shortcode (`has_block()`، یا ثبت lazy در render callback).
- dependency و version همیشه از `build/*.asset.php` خوانده می‌شوند.

## 7.1 JS workspace (T0.11)
- **ساختار:** pnpm workspace با سه پکیج: `packages/shared` (بدون فریم‌ورک: `ApiClient`، `api-types`، jalali، money، digits، `SLUG`)، `packages/admin` (React هسته WP به‌صورت external، shell و hash router) و `packages/widget` (Preact، `mount(el, config)`). Build با `@wordpress/scripts` 35 و `webpack.config.js` با دو entry ← `build/admin.*` و `build/widget.*`، هر کدام با `.asset.php`.
  - نسخه 35 انتخاب شد، چون 36 به Node 24.15 یا بالاتر نیاز دارد (محلی 24.12، CI روی 22). TypeScript روی 5.9 است، نه 7 (کامپایلر native جدید)، چون ابزار WP هنوز آن را پشتیبانی نمی‌کند.
  - **CSS را `style.css` نام‌گذاری نکن:** wp-scripts آن را در chunk جدای `style-{entry}.css` می‌ریزد. نام فایل همان entry است (`admin.css`، `widget.css`). فایل‌های `*-rtl.css` هم ساخته می‌شوند ولی enqueue نمی‌شوند، چون Logical properties هر دو جهت را پوشش می‌دهند.
- **JSX ویجت برای Preact:** `babel.config.js` برای `packages/widget` پلاگین JSX با `importSource: 'preact'` را اضافه می‌کند. این پلاگین قبل از JSX خود preset اجرا می‌شود. در `build/widget.asset.php` فقط `wp-i18n` هست و React نیست. tsconfig ویجت `jsxImportSource: preact` دارد و Vitest هم در project ویجت (`oxc.jsx`) همین را دارد.
- **Identity در JS:** `packages/shared/src/identity.ts` فایل `identity.json` را import می‌کند، پس rename نیاز به ویرایش JS ندارد. id المنت Admin برابر `{slug}-admin` و attribute ویجت `data-{slug}-widget` است. کلاس‌های CSS `vqy-*`، text-domain و نام پکیج‌ها (`@vaqtyar/*`) لیترال‌اند و rename آن‌ها را عوض می‌کند.
- **`ApiClient`** به‌جای `@wordpress/api-fetch` است، تا ویجت هم همان کلاینت را داشته باشد. `baseUrl` از `rest_url(Identity::REST_NAMESPACE)` می‌آید (هر دو شکل pretty و `?rest_route=`). Envelope خطا ← `ApiError` (`code`، `status`، `details`، `requestId`). کدهای خودمان `network_error` (status 0) و `invalid_response` هستند. **nonce فقط برای کاربر لاگین‌شده فرستاده شود:** وردپرس nonce مهمان را هم بررسی می‌کند و nonce کهنه در صفحه cache‌شده هر درخواست مهمان را 403 می‌کند (تصمیم برای T4.x).
- **Lint:** `eslint.config.cjs` (flat، روی config خود wp-scripts). تست‌ها ابزارشان را از devDependencies ریشه import می‌کنند. `stylelint.config.cjs`: `csstools/use-logical`، رنگ hard-code ممنوع (hex، نام، `rgb()` و مانند آن)، و کلاس BEM. `pnpm lint` = ESLint + Stylelint + `tsc` برای هر پکیج. قالب‌بندی با `pnpm format` (wp-prettier).
- **بودجه (`.size-limit.json`، gzip):** ویجت (JS + CSS) کمتر از 40KB، Admin کمتر از 150KB. **`wp-i18n` و `wp-hooks`** که ویجت از هسته می‌گیرد در این عدد نیستند. با enqueue واقعی ویجت (T4.5) باید در بودجه حساب شوند.
- **صفحه Admin:** `Modules\Admin\Presentation\AdminPage` یک menu با capability `access_admin` (به administrator) ثبت می‌کند و `build/admin.js` را فقط روی hook suffix خودش enqueue می‌کند (dependency و version از `admin.asset.php`). بدون build، به‌جای صفحه خالی notice «pnpm build» نشان می‌دهد. تست Integration با fixture `tests/Integration/Fixtures/built-plugin` کار می‌کند، پس suite به `pnpm build` نیاز ندارد.
- **Rename و JS:** `rename.php` بعد از rename، `pnpm install --no-frozen-lockfile` و `pnpm format` را اجرا می‌کند. install لازم است، چون نام پکیج‌ها در `pnpm-lock.yaml` هست (که rename به آن دست نمی‌زند). format لازم است، چون طول توکن جدید جای شکست خط Prettier را عوض می‌کند (`test:rename` این را گرفت). در CI، pnpm قفل را پیش‌فرض frozen می‌کند، پس `--no-frozen-lockfile` صریح است. `test:rename` روی کپی `pnpm lint`، `pnpm test` و `pnpm build` را هم اجرا می‌کند.
- **CI:** jobهای `test-js` (lint و test) و `build` (build و size) اضافه شدند. job `rename` حالا Node و pnpm دارد. zip و Plugin Check در job `build` به Task انتشار (M6) موکول شدند.
- **تله CI:** `actions/setup-node@v5` با دیدن `packageManager` در `package.json` خودکار cache pnpm را روشن می‌کند و در jobی که pnpm نصب نکرده (integration) با «Unable to locate executable file: pnpm» می‌شکند (run 36176335757). در چنین jobی `package-manager-cache: false` بگذار.

## 8. Git و انتشار
- یک commit برای هر Task، روی `main`. پیام Conventional Commits به انگلیسی با ID تسک، و خط `Co-Authored-By` طبق سیاست جاری.
- **Push:** در پایان هر `/wrap` و هر وقت کاربر بخواهد (ر.ک. agent-workflow §2.9).
- CI در GitHub Actions با هر push اجرا می‌شود (از T0.10). وضعیت CI بدون `gh` از اینجا دیده نمی‌شود، پس از کاربر بپرس یا `gh` نصب شود.
