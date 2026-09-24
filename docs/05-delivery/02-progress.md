# Progress Tracker

> **اولین فایلی که هر سشن جدید می‌خواند.** در پایان هر Task و هر سشن به‌روز می‌شود.
> وضعیت‌ها: ⬜ انجام نشده · 🟨 در حال انجام · ✅ انجام‌شده · ⛔ مسدود · ⏭️ رد شده (با دلیل)

## ▶️ از اینجا ادامه بده (Resume Here)
| | |
|---|---|
| **فاز فعلی** | **M0** زیربنا |
| **Task بعدی** | **T0.4** — Helperهای نام + `tools/rename.php` (ر.ک. [01-roadmap.md](01-roadmap.md#m0--زیربنا)) |
| **Task در حال انجام** | — |
| **آخرین کار انجام‌شده** | T0.3: Kernel. شامل Identity، Container، Module، Context، ModuleRegistry و Plugin. فایل اصلی Composition root است و boot روی `plugins_loaded` در Playground تأیید شد (2026-09-24) |
| **Blockerها** | — (PHP 8.3 و Composer نصب شدند. Docker محلی لازم نیست و تست‌های MySQL در CI اجرا می‌شوند) |
| **کار کاربر (اختیاری)** | (1) `gh auth login` تا وضعیت CI از اینجا دیده شود. (2) نصب pluginهای `php-lsp`، `typescript-lsp` و `security-guidance` از Manage plugins در VS Code. (3) انتقال پروژه به مسیری بدون فاصله |
| **نکته برای سشن بعد** | اگر `php` یا `composer` پیدا نشد، PATH را refresh کن (dev-environment §2). قبل و بعد از کار `composer check` را اجرا کن. برای T0.4: Identity ثابت‌های `PREFIX` و `HOOK_PREFIX` را دارد. `rename.php` باید `identity.json`، `Identity.php`، header فایل اصلی، نام فایل اصلی، ثابت‌های `VAQTYAR_*`، namespace در composer و کد، text domain و `tools/*.xml|yaml|neon` را عوض کند. `IdentityTest` هماهنگی این‌ها را بررسی می‌کند. تابع‌های سراسری همیشه fully-qualified نوشته می‌شوند (`\add_action()`). **ترتیب: T0.10 قبل از T0.7 انجام می‌شود** |

## خلاصه Milestoneها
| Milestone | وضعیت | پیشرفت |
|---|---|---|
| M(-1) تحقیق، معماری و اصول | ✅ | 100% |
| M0 زیربنا | 🟨 | 3/11 |
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
| T0.4 | Helperهای نام + rename.php | ⬜ | |
| T0.5 | Shared Value Objects + IntervalSet | ⬜ | |
| T0.6 | Jalali + DateFormatter | ⬜ | |
| T0.10 | wp-env + CI | ⬜ | |
| T0.7 | Db، Transaction، Migrator | ⬜ | |
| T0.8 | REST base | ⬜ | |
| T0.9 | Settings، SecretStore، Logger، Caps | ⬜ | |
| T0.11 | JS workspace | ⬜ | |
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
