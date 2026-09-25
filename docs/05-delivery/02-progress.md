# Progress Tracker

> **اولین فایلی که هر سشن جدید می‌خواند.** در پایان هر Task و هر سشن به‌روز می‌شود.
> وضعیت‌ها: ⬜ انجام نشده · 🟨 در حال انجام · ✅ انجام‌شده · ⛔ مسدود · ⏭️ رد شده (با دلیل)

## ▶️ از اینجا ادامه بده (Resume Here)
| | |
|---|---|
| **فاز فعلی** | **M0** زیربنا |
| **Task بعدی** | بررسی CI برای T0.11، سپس T1.1 — Catalog Domain |
| **Task در حال انجام** | — |
| **آخرین کار انجام‌شده** | T0.11: pnpm workspace (`shared`، `admin`، `widget`)، wp-scripts 35، ESLint، Stylelint، Vitest، size-limit، `AdminModule` با صفحه Admin، rename برای JS، و jobهای CI `test-js` و `build` (2026-09-25). قبلی — T0.9: `Kernel\Settings` (گروه‌های typed، یک option برای هر گروه)، `SecretStore` (XChaCha20-Poly1305 با کلید از `wp_salt('auth')`، ثابت wp-config با اولویت)، `Kernel\Log` (`Logger` روی جدول `logs` با ماسک PII و Retention، `Pii`)، `Capabilities` (`Module::capabilities()`، هر جفت یک‌بار). `composer check` سبز (383 تست Unit). CI سبز (run 36152711861) (2026-09-25). قبلی — T0.8: `Kernel\Rest` (`Router` مرز خطا با Envelope و `request_id`، `RateLimiter` روی جدول `rate_limits` با migration خود Kernel، `ClientIp`، `Pagination`، `ApiError`)، `Kernel\RequestId`. CI سبز (run 36149430470) (2026-09-25) |
| **Blockerها** | — (PHP 8.3 و Composer نصب شدند. Docker محلی لازم نیست و تست‌های MySQL در CI اجرا می‌شوند). CI سبز است و `gh` لاگین است (`gh run list`، `gh run watch`) |
| **کار کاربر (اختیاری)** | (1) نصب pluginهای `php-lsp`، `typescript-lsp` و `security-guidance` از Manage plugins در VS Code. (2) انتقال پروژه به مسیری بدون فاصله |
| **نکته برای سشن بعد** | اگر `php` یا `composer` پیدا نشد، PATH را refresh کن (dev-environment §2). قبل و بعد از کار `composer check` را اجرا کن. **T0.10:** اگر CI قرمز بود، اول همان را رفع کن و بعد T0.10 را ✅ کن. محتمل‌ترین نقاط شکست: unit روی PHP 8.4 (deprecation)، ترکیب WP 6.6 با PHP 8.4 در integration، و `wp-env start`. نکات wp-env در implementation-notes §2.2 است. قبل از push، `actionlint` را اجرا کن (dev-environment §5). پوشش را با `phpdbg -d memory_limit=-1` اندازه بگیر (dev-environment §1). توکن‌های Identity را لیترال و چسبیده به حرف ننویس (implementation-notes §2.1). قبل از هر commit، `composer test:rename` را هم اجرا کن. تابع‌های سراسری همیشه fully-qualified نوشته می‌شوند (`\add_action()`). **T0.7:** SQL فقط از `Db` و با placeholder (`%i` برای جدول). تست‌های Integration که commit واقعی لازم دارند از `TestCase` و trait `RealDatabase` استفاده می‌کنند، نه `WP_UnitTestCase` (implementation-notes §5). اگر CI قرمز بود، محتمل‌ترین نقطه‌ها `TransactionTest` (KILL و lock wait) و فرض‌های wpdb است. **T0.8:** route فقط با `Router::add()` روی `rest_api_init`؛ متن Exception در `error_log` نمی‌رود (PII)؛ Logger در T0.9 جایگزین `error_log` در `Router` و `Plugin` می‌شود و `RequestId` را از Container می‌گیرد؛ جدول `logs` دومین migration owner `kernel` است (implementation-notes §6). **T0.9:** بعد از push، CI را ببین (`gh run watch`). محتمل‌ترین نقاط شکست در Integration: ستون `JSON` و `JSON_EXTRACT`، مقدار `autoload` (`on`/`off` یا `yes`/`no`)، و `CapabilitiesTest` (WP_Roles در حافظه). secret فقط از `SecretStore`، لاگ فقط از `Logger` با پیام ثابت و داده در context (implementation-notes §6.1). ماژول جدید باید `capabilities()` را پیاده کند. **T0.11:** قبل از commit JS، `pnpm lint` و `pnpm test` را اجرا کن. `pnpm format` را بزن، نه قالب‌بندی دستی. CSS بدون `style.css` (implementation-notes §7.1) |

## خلاصه Milestoneها
| Milestone | وضعیت | پیشرفت |
|---|---|---|
| M(-1) تحقیق، معماری و اصول | ✅ | 100% |
| M0 زیربنا | ✅ | 11/11 |
| M1 کاتالوگ و زمان‌بندی | ⬜ | 0/5 |
| M2 هسته رزرو | ⬜ | 0/8 |
| M3 Admin | ⬜ | 0/6 |
| M4 سمت مشتری | ⬜ | 0/5 |
| M5 پرداخت و اعلان | ⬜ | 0/5 |
| M6 انتشار 1.0 | ⬜ | 0/6 |

## جزئیات Taskها
| ID | عنوان | وضعیت | Commit / یادداشت |
|---|---|---|---|
| P.1 | تحقیق رقبا و اکوسیستم | ✅ | docs/01-research |
| P.2 | Stack، معماری، مدل داده، ADRها | ✅ | docs/03-architecture |
| P.3 | اصول مهندسی + ضد Overengineering | ✅ | docs/04-engineering |
| P.4 | Roadmap، Tracker، Worklog، Agent workflow | ✅ | `727973a` |
| P.5 | GitHub remote، Skillها، محیط، ADR-017 و 018، تله‌های فنی | ✅ | `8104685` |
| P.6 | نصب PHP 8.3، Composer و gh + `.wp-env.json` (Docker فقط در CI) | ✅ | 2026-09-24 |
| T0.1 | اسکلت Repo | ✅ | `feat(kernel): repo skeleton with requirements check (T0.1)`. بررسی InnoDB به T0.7 منتقل شد |
| T0.2 | ابزار کیفیت PHP | ✅ | `chore(tooling): phpcs, phpstan, deptrac, phpunit configs (T0.2)`. Integration config به T0.10 منتقل شد |
| T0.3 | Kernel | ✅ | `feat(kernel): container, module registry and boot (T0.3)`. Context نوع درخواست را حدس نمی‌زند (architecture §5) |
| T0.4 | Helperهای نام + rename.php | ✅ | `feat(kernel): naming helpers and rename tool (T0.4)` |
| T0.5 | Shared Value Objects + IntervalSet | ✅ | `feat(shared): value objects, interval set and clock (T0.5)` |
| T0.6 | Jalali + DateFormatter | ✅ | `feat(shared): jalali calendar and date formatter (T0.6)` |
| T0.10 | wp-env + CI | ✅ | `9ddb825` `ci(kernel): wp-env integration suite and github actions (T0.10)`. محلی سبز است. **CI سبز (run 36145578720، commit `0888e32`).** jobهای concurrency، test-js و build به T2.2 و T0.11 منتقل شدند |
| T0.7 | Db، Transaction، Migrator | ✅ | `0ce43dc` `feat(kernel): db wrapper, transaction and migrator (T0.7)`. Unit و `composer check` سبز است. **Integration روی CI سبز است (4 ترکیب PHP × WP، run 36145578720)** |
| T0.8 | REST base | ✅ | `6513c8e` `feat(kernel): rest router, error envelope, rate limiter and pagination (T0.8)`. «Controller پایه» ← `Router` (composition). **CI سبز: Integration 43 تست در 4 ترکیب (run 36149430470)** |
| T0.9 | Settings، SecretStore، Logger، Caps | ✅ | `84764c3` `feat(kernel): settings, secret store, logger and capabilities (T0.9)`. **CI سبز: Integration 52 تست در 4 ترکیب (run 36152711861)** |
| T0.11 | JS workspace | ✅ | `feat(admin): js workspace with admin shell and widget mount (T0.11)`. `pnpm lint`، `pnpm test` (38)، `pnpm build`، `pnpm size`، `composer check` و `test:rename` (با JS) سبز. **CI هنوز دیده نشده** |
| T1.1 | Catalog Domain | ⬜ | |
| T1.2 | Catalog REST CRUD | ⬜ | |
| T1.3 | Scheduling + تعطیلات | ⬜ | |
| T1.4 | AvailabilityCalculator | ⬜ | |
| T1.5 | Availability API + Cache | ⬜ | |
| T2.1 | Booking Migrations | ⬜ | |
| T2.2 | Locker + Hold + تست همزمانی | ⬜ | |
| T2.3 | PriceCalculator | ⬜ | |
| T2.4 | Appointment + Confirm | ⬜ | |
| T2.5 | Policy + Cancel/Reschedule | ⬜ | |
| T2.6 | فیلدهای سفارشی | ⬜ | |
| T2.7 | Customers | ⬜ | |
| T2.8 | Admin Queries | ⬜ | |
| T3.1 | Admin Shell | ⬜ | |
| T3.2 | صفحات کاتالوگ | ⬜ | |
| T3.3 | تقویم Admin | ⬜ | |
| T3.4 | لیست و جزئیات نوبت | ⬜ | |
| T3.5 | مشتریان، Policy، قیمت، فیلدها | ⬜ | |
| T3.6 | داشبورد و گزارش | ⬜ | |
| T4.1 | ویجت: انتخاب و تقویم | ⬜ | |
| T4.2 | ویجت: Hold تا تأیید | ⬜ | |
| T4.3 | OTP | ⬜ | |
| T4.4 | پنل مشتری | ⬜ | |
| T4.5 | Shortcode و Block | ⬜ | |
| T5.1 | Payments core | ⬜ | |
| T5.2 | Zarinpal، Zibal، تطبیق | ⬜ | |
| T5.3 | ووکامرس | ⬜ | |
| T5.4 | Notifications core | ⬜ | |
| T5.5 | SMS Providers | ⬜ | |
| T6.1 | White-label + Onboarding | ⬜ | |
| T6.2 | Site Health + Status | ⬜ | |
| T6.3 | ترجمه، a11y، کارایی | ⬜ | |
| T6.4 | امنیت + Plugin Check | ⬜ | |
| T6.5 | E2E کامل + تست ارتقا | ⬜ | |
| T6.6 | Build و مستندات انتشار | ⬜ | |

## تصمیم‌های باز
| # | موضوع | وضعیت |
|---|---|---|
| 1 | نام نهایی محصول | باز است. **مانع کار نیست** (ADR-000: قابل تعویض با `rename.php` تا پیش از انتشار) |
| 2 | کانال فروش و سیستم لایسنس | باز است. قبل از M6 لازم است. لایسنس کد GPL است (ADR-017) |
