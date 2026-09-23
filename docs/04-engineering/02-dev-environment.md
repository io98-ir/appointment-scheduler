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
| **PHP** | ❌ **نصب نیست** | لازم: 8.3 (حداقل 8.1) | Composer، PHPUnit، PHPStan، PHPCS، `rename.php` ← **T0.1 به بعد** |
| **Composer** | ❌ **نصب نیست** | 2.x | وابستگی‌ها و autoload ← **T0.1** |
| **Docker Desktop** (WSL2) | ❌ **نصب نیست** | | `wp-env` ← تست Integration، Concurrency و E2E (**T0.7 به بعد، و T0.10**) |
| GitHub CLI (`gh`) | ❌ | | اختیاری: مشاهده CI و PR |
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

**بدون Docker چه چیزی ممکن است:** تست Unit در Domain و Application، PHPStan، PHPCS، Deptrac و Build JS.
**بدون Docker چه چیزی ممکن نیست:** تست Integration با MySQL، **تست همزمانی** (قفل InnoDB) و E2E.
WordPress Playground از SQLite استفاده می‌کند و **برای تست قفل ردیف مناسب نیست**.

## 3. نکات Windows و این Repo
- **مسیر پروژه فاصله و پرانتز دارد** (`J:\New folder (2)\extention_php`). همیشه مسیرها را در quote بگذار. اگر ابزاری با مسیر مشکل داشت (مثلاً mount در Docker یا اسکریپت‌های shell)، اول همین را بررسی کن. پیشنهاد به کاربر: انتقال به مسیری بدون فاصله، مثل `J:\dev\appointment-scheduler`.
- **Shell:** ابزار PowerShell روی نسخه 5.1 است: `&&` کار نمی‌کند و here-string هنگام pipe به native command مشکل دارد. برای پیام commit از فایل استفاده کن (`git commit -F <file>`). Bash (Git Bash) هم در دسترس است.
- **پایان خط:** `.gitattributes` با `eol=lf` تنظیم شده است. فایل‌ها را با LF بنویس.
- **نام پوشه در برابر slug:** پوشه Repo `extention_php` است و Remote `appointment-scheduler`. slug فنی افزونه (`vaqtyar`) از Identity می‌آید. خروجی zip در Build همیشه در پوشه‌ای به نام slug قرار می‌گیرد (T6.6). در wp-env، پوشه افزونه با `"plugins": ["."]` mount می‌شود و نام پوشه در WP همان basename است. این در توسعه اشکالی ندارد.

## 4. Git و GitHub
- Remote: `origin` ← https://github.com/io98-ir/appointment-scheduler (شاخه `main`، tracking تنظیم شده).
- هویت commit: همان git config کاربر.
- سیاست push: ر.ک. [../05-delivery/03-agent-workflow.md](../05-delivery/03-agent-workflow.md) §2.9.

## 5. دستورهای استاندارد (از T0.2 به بعد تعریف می‌شوند)
| دستور | کار |
|---|---|
| `composer lint` | PHPCS |
| `composer stan` | PHPStan level 9 |
| `composer deptrac` | قوانین لایه‌ها |
| `composer test:unit` | PHPUnit بدون WP |
| `composer test:integration` | PHPUnit روی wp-env |
| `composer check` | همه موارد بالا |
| `pnpm build` / `pnpm test` / `pnpm lint` | JS |
| `npx wp-env start` | محیط محلی WP (http://localhost:8888) |
