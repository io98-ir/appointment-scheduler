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
| **PHP** | ✅ | 8.3.33 (winget) | فقط در PowerShell دیده می‌شود، نه در PATH ابزار Bash (Git Bash)؛ `php` در Bash هم کار می‌کند چون همان نصب winget است |
| **Composer** | ✅ | 2.10.3 | **فقط در PowerShell**؛ در ابزار Bash در PATH نیست (`composer: command not found`). دستورهای `composer …` را با ابزار PowerShell اجرا کن |
| **Docker Desktop** | ⏭️ **به تصمیم کاربر روی این سیستم نصب نمی‌شود** | | بستر آماده است: `.wp-env.json`. تست Integration، Concurrency و E2E **در GitHub Actions** اجرا می‌شوند (runnerها Docker دارند). روی هر سیستمی که Docker دارد: `npx wp-env start` |
| GitHub CLI (`gh`) | ✅ نصب و لاگین (`NimaM048`) | 2.101.0 | `gh run list`، `gh run watch` روی این دستگاه کار می‌کند |
| Coverage driver | ✅ `phpdbg` (همراه PHP) | | pcov و xdebug نصب نیستند. پوشش محلی: `phpdbg -qrr -d memory_limit=-1 vendor/bin/phpunit --testsuite unit --coverage-text` (phpdbg کنار `php.exe` است؛ آزمون 73 هزار روزه Jalali با سقف 1G حافظه کم می‌آورد) |
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

> وضعیت 2026-09-24: PHP، Composer و gh نصب شدند. Docker طبق تصمیم کاربر محلی نصب نمی‌شود.
> **اصلاحیه 2026-09-28:** یک سشن قبلی نوشته بود پروژه به دستگاه دیگری (`D:\PycharmProjects\appointment_php`) منتقل شده و PHP/Composer آنجا نصب نیستند. این با مسیر واقعی Repo (`J:\New folder (2)\extention_php`، همان‌جا که Remote و `.git` هستند) و وضعیت واقعی این سشن نمی‌خواند: PHP 8.3.33 و Composer 2.10.3 روی همین دستگاه نصب‌اند و `composer check` مستقیماً همین‌جا اجرا و سبز شد (820 تست Unit، T3.5 بخش Policy). یادداشت «دستگاه جدید» ظاهراً اشتباه یا مال یک محیط دیگر بوده؛ نادیده گرفته شود. **PHP و Composer را از این پس، مثل قبل، محلی هم تأیید کن** (`composer check` قبل و بعد از هر Task)، نه فقط با CI.
> **نکته شل:** بعد از نصب، PATH در سشن‌های ابزار قدیمی به‌روز نمی‌شود. اگر `php` یا `composer` پیدا نشد، اول این را اجرا کن: `$env:Path = [Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [Environment]::GetEnvironmentVariable('Path','User')`. در غیر این صورت از مسیر کامل استفاده کن.

**بدون Docker چه چیزی ممکن است:** تست Unit در Domain و Application، PHPStan، PHPCS، Deptrac و Build JS (`composer check`، `pnpm lint`/`test`/`build`/`size`، همه محلی).
**بدون Docker چه چیزی ممکن نیست:** تست Integration با MySQL، **تست همزمانی** (قفل InnoDB) و E2E؛ این‌ها فقط در CI (`gh run watch`) تأیید می‌شوند.
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
- **`python -` یا `python` بدون فایل در ابزار Bash** تا timeout منتظر می‌ماند و گیر می‌کند. از `python -c "…"` استفاده کن، یا اسکریپت را در فایل بنویس.
- **خروجی زیرفرایند:** وقتی خروجی به فایل redirect شده، زیرفرایندی که stdout و stderr را به ارث برده ممکن است خروجی والد را بازنویسی کند. `tools/Rename/Shell::run()` به همین دلیل خروجی را از pipe می‌خواند و relay می‌کند.
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
| `composer test:integration` | PHPUnit روی WP و MySQL واقعی (`phpunit-integration.xml.dist`). فقط داخل wp-env: `npx @wordpress/env@11 start` و بعد `npx @wordpress/env@11 run cli --env-cwd=wp-content/plugins/<نام پوشه> composer test:integration`. محلی Docker نداریم، پس در CI اجرا می‌شود |
| `composer check` | lint، stan، deptrac و test:unit. حدود 30 ثانیه (اجرای اول deptrac به‌خاطر stubهای WP کندتر است) |
| `composer test:rename` | rename یک کپی موقت و `composer check` روی آن (ADR-000)، حدود 40 ثانیه |
| `php tools/rename.php --dry-run …` | تغییر شناسه فنی (implementation-notes §2.1) |
| `pnpm build` / `pnpm test` / `pnpm lint` | JS (از T0.11) |
| `npx @wordpress/env@11 start` | محیط محلی WP (http://localhost:8888) |
| `actionlint .github/workflows/ci.yml` | بررسی workflow قبل از push (`pip install actionlint-py`؛ فایل اجرایی در `%APPDATA%\Python\Python313\Scripts`) |

> **Cacheها:** `.phpstan.cache/` و `.deptrac.cache` در ریشه ساخته می‌شوند و در `.gitignore` هستند.
