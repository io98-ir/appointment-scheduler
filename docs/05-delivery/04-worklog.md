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
