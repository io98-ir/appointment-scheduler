# Progress Tracker

> **اولین فایلی که هر سشن جدید می‌خواند.** در پایان هر Task و هر سشن به‌روز می‌شود.
> وضعیت‌ها: ⬜ انجام نشده · 🟨 در حال انجام · ✅ انجام‌شده · ⛔ مسدود · ⏭️ رد شده (با دلیل)

## ▶️ از اینجا ادامه بده (Resume Here)
| | |
|---|---|
| **فاز فعلی** | برنامه‌ریزی تمام شد ← شروع **M0** |
| **Task بعدی** | **T0.1** — اسکلت Repo (ر.ک. [01-roadmap.md](01-roadmap.md#m0--زیربنا)) |
| **Task در حال انجام** | — |
| **آخرین کار انجام‌شده** | اتصال به GitHub، نصب 14 Skill، نصب باینری‌های LSP، و نوشتن اسناد محیط، ابزار Agent و تله‌های فنی (2026-09-23) |
| **Blockerها** | ⛔ **PHP 8.3 و Composer نصب نیستند.** T0.1 را می‌شود نوشت، ولی قابل تأیید نیست. ⛔ **Docker نصب نیست.** بدون آن تست Integration، همزمانی و E2E ممکن نیست (از T0.7). دستورهای نصب در [../04-engineering/02-dev-environment.md](../04-engineering/02-dev-environment.md) آمده است |
| **کار کاربر** | (1) نصب PHP، Composer و Docker، یا اجازه نصب با winget. (2) نصب pluginهای `php-lsp`، `typescript-lsp` و `security-guidance` از Manage plugins در VS Code با Project scope. (3) اختیاری: انتقال پروژه به مسیری بدون فاصله |
| **نکته برای سشن بعد** | قبل از T0.1 دوباره `php -v`، `composer -V` و `docker -v` را چک کن. اول [../04-engineering/03-implementation-notes.md](../04-engineering/03-implementation-notes.md) §1 و §2 را بخوان: فایل اصلی بدون سینتکس PHP 8، و `config.platform.php = 8.1.0` |

## خلاصه Milestoneها
| Milestone | وضعیت | پیشرفت |
|---|---|---|
| M(-1) تحقیق، معماری و اصول | ✅ | 100% |
| M0 زیربنا | ⬜ | 0/11 |
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
| P.5 | GitHub remote، Skillها، محیط، ADR-017 و 018، تله‌های فنی | ✅ | این commit |
| P.6 | نصب PHP، Composer و Docker (کار کاربر) | ⛔ | منتظر کاربر |
| T0.1 | اسکلت Repo | ⬜ | |
| T0.2 | ابزار کیفیت PHP | ⬜ | |
| T0.3 | Kernel | ⬜ | |
| T0.4 | Helperهای نام + rename.php | ⬜ | |
| T0.5 | Shared Value Objects + IntervalSet | ⬜ | |
| T0.6 | Jalali + DateFormatter | ⬜ | |
| T0.7 | Db، Transaction، Migrator | ⬜ | |
| T0.8 | REST base | ⬜ | |
| T0.9 | Settings، SecretStore، Logger، Caps | ⬜ | |
| T0.10 | wp-env + CI | ⬜ | |
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
