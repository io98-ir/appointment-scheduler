# مهندسی: محیط توسعه

> وضعیت بررسی‌شده در 2026-09-23 روی سیستم کاربر (Windows 11 Pro). هر تغییر در محیط اینجا به‌روز شود.

## 1. وضعیت ابزارها
| ابزار | وضعیت | نسخه | لازم برای |
|---|---|---|---|
| Git | ✅ | 2.52 | همه‌چیز |
| Node.js | ✅ | 24.12 | Build JS، Skill scripts |
| npm | ✅ | 11.6 | |
| pnpm | ✅ | 9.15 | JS workspace (T0.11) |
| Python | ✅ | 3.13 | Skill `webapp-testing` (Playwright Python) |
| intelephense | ✅ (نصب شد) | npm global | LSP برای PHP (plugin `php-lsp`) |
| typescript + typescript-language-server | ✅ (نصب شد) | npm global | LSP برای TS (plugin `typescript-lsp`) |
| **PHP** | ✅ (2026-09-24، winget) | 8.3.33 ZTS x64 | Composer، PHPUnit، PHPStan، PHPCS. مسیر: `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_…\php.exe`، فایل `php.ini` از قالب development ساخته شد. extensionهای intl، sodium، mbstring، openssl، curl، mysqli، pdo_mysql، zip، fileinfo و gd فعال‌اند. `memory_limit=1G` |
| **Composer** | ✅ (نصب رسمی با بررسی امضای SHA384) | 2.10.3 | `%LOCALAPPDATA%\Composer\bin\composer.bat` (در PATH کاربر) |
| **Docker Desktop** | ⏭️ **به تصمیم کاربر روی این سیستم نصب نمی‌شود** | | بستر آماده است: `.wp-env.json`. تست Integration، Concurrency و E2E **در GitHub Actions** اجرا می‌شوند (runnerها Docker دارند). روی هر سیستمی که Docker دارد: `npx wp-env start` |
| GitHub CLI (`gh`) | ✅ نصب شد، ⬜ **لاگین نشده** | 2.101.0 | کاربر باید `gh auth login` را اجرا کند تا وضعیت CI از اینجا دیده شود |
| Coverage driver (pcov/xdebug) | ❌ | | Coverage فقط در CI اندازه‌گیری می‌شود |
| WP-CLI | ❌ | | داخل wp-env موجود است (`npx wp-env run cli wp …`). نصب محلی لازم نیست |
| winget | ✅ | | نصب ابزارها |

## 2. نصب‌های لازم (با تأیید کاربر)
```powershell
winget install --id PHP.PHP.8.3 -e          # سپس php.ini: فعال‌سازی extensionها (پایین)
winget install --id Composer.Composer -e
winget install --id Docker.DockerDesktop -e  # نیاز به WSL2 و احتمالاً Restart
winget install --id GitHub.cli -e            # اختیاری
```
**Extensionهای لازم PHP** در `php.ini`:
- `mbstring`, `intl`, `sodium`, `openssl`, `curl`, `mysqli`, `pdo_mysql`, `zip`, `fileinfo`
- `xdebug` یا `pcov` برای Coverage

> وضعیت 2026-09-24: PHP و Composer و gh نصب شدند. Docker طبق تصمیم کاربر محلی نصب نمی‌شود.
> **نکته شل:** بعد از نصب، PATH در سشن‌های ابزار قدیمی به‌روز نمی‌شود. اگر `php` یا `composer` پیدا نشد، اول این را اجرا کن: `$env:Path = [Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [Environment]::GetEnvironmentVariable('Path','User')`. در غیر این صورت از مسیر کامل استفاده کن.

**بدون Docker چه چیزی ممکن است:** تست Unit در Domain و Application، PHPStan، PHPCS، Deptrac و Build JS.
**بدون Docker چه چیزی ممکن نیست:** تست Integration با MySQL، **تست همزمانی** (قفل InnoDB) و E2E.
WordPress Playground از SQLite استفاده می‌کند و **برای تست قفل ردیف مناسب نیست**.

**Smoke محلی با WordPress Playground (بدون Docker):** برای دیدن فعال‌سازی واقعی افزونه روی نسخه‌های مختلف PHP و WP کافی است (T0.1 همین‌طور تأیید شد):
```
MSYS_NO_PATHCONV=1 npx -y @wp-playground/cli@latest run-blueprint --blueprint=./bp.json   --mount-dir "J:/New folder (2)/extention_php" /wordpress/wp-content/plugins/vaqtyar   --mount-dir "<scratch>/out" /out
```
- نسخه PHP و WP را با `preferredVersions` داخل Blueprint بده. `run-blueprint` پرچم `--php` را نادیده می‌گیرد.
- گام `runPHP` فقط کد inline (string) می‌پذیرد. نتیجه را در فایلی زیر `/out` بنویس.
- در Git Bash بدون `MSYS_NO_PATHCONV=1`، مسیر `/wordpress/...` به `C:/Program Files/Git/...` تبدیل می‌شود.
- `wpdb::db_server_info()` در Playground مقدار `8.0.38-mysql-on-sqlite-…` برمی‌گرداند.

## 3. نکات Windows و این Repo
- **مسیر پروژه فاصله و پرانتز دارد** (`J:\New folder (2)\extention_php`). همیشه مسیرها را در quote بگذار. اگر ابزاری با مسیر مشکل داشت (مثلاً mount در Docker یا اسکریپت‌های shell)، اول همین را بررسی کن. پیشنهاد به کاربر: انتقال به مسیری بدون فاصله، مثل `J:\dev\appointment-scheduler`.
- **Shell:** ابزار PowerShell روی نسخه 5.1 است: `&&` کار نمی‌کند و here-string هنگام pipe به native command مشکل دارد. برای پیام commit از فایل استفاده کن (`git commit -F <file>`). Bash (Git Bash) هم در دسترس است.
- **بک‌اسلش در ابزار Bash:** heredoc، `sed` و `printf` در ابزار Bash گاهی بک‌اسلش را حذف یا تفسیر می‌کنند (مثلاً `\\` به `\` و `\a` به کاراکتر bell). فایلی را که بک‌اسلش دارد، مثل namespaceهای PHP یا regex در YAML، با ابزار Write یا Edit بنویس و بعد با `od -c` بررسی کن.
- **پایان خط:** `.gitattributes` با `eol=lf` تنظیم شده است. فایل‌ها را با LF بنویس.
- **نام پوشه در برابر slug:** پوشه Repo `extention_php` است و Remote `appointment-scheduler`. slug فنی افزونه (`vaqtyar`) از Identity می‌آید. خروجی zip در Build همیشه در پوشه‌ای به نام slug قرار می‌گیرد (T6.6). در wp-env، پوشه افزونه با `"plugins": ["."]` mount می‌شود و نام پوشه در WP همان basename است. این در توسعه اشکالی ندارد.

## 4. Git و GitHub
- Remote: `origin` ← https://github.com/io98-ir/appointment-scheduler (شاخه `main`، tracking تنظیم شده).
- هویت commit: همان git config کاربر.
- سیاست push: ر.ک. [../05-delivery/03-agent-workflow.md](../05-delivery/03-agent-workflow.md) §2.9.

## 5. دستورهای استاندارد
| دستور | کار |
|---|---|
| `composer lint` | PHPCS با دو ruleset: `tools/phpcs.xml` (PSR-12، WPCS امنیتی و i18n، Slevomat، PHPCompatibilityWP برای 8.1+) و `tools/phpcs-legacy.xml` (سازگاری PHP 7.0 فقط برای سه فایل bootstrap) |
| `composer lint:fix` | PHPCBF |
| `composer stan` | PHPStan level 9 (`tools/phpstan.neon`) روی `src/`، `tests/` و فایل‌های bootstrap |
| `composer deptrac` | دو config: `tools/deptrac-layers.yaml` (لایه‌ها) و `tools/deptrac-modules.yaml` (ایزوله‌بودن ماژول‌ها) |
| `composer test` / `composer test:unit` | PHPUnit بدون WP (`phpunit.xml.dist`، suite `unit`) |
| `composer test:integration` | PHPUnit روی wp-env. در T0.10 اضافه می‌شود |
| `composer check` | lint، stan، deptrac و test:unit. حدود 30 ثانیه (اجرای اول deptrac به‌خاطر stubهای WP کندتر است) |
| `pnpm build` / `pnpm test` / `pnpm lint` | JS (از T0.11) |
| `npx wp-env start` | محیط محلی WP (http://localhost:8888) |

> **Cacheها:** `.phpstan.cache/` و `.deptrac.cache` در ریشه ساخته می‌شوند و در `.gitignore` هستند.
