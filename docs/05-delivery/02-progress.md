# Progress Tracker

> **اولین فایلی که هر سشن جدید می‌خواند.** در پایان هر Task و هر سشن به‌روز می‌شود.
> وضعیت‌ها: ⬜ انجام نشده · 🟨 در حال انجام · ✅ انجام‌شده · ⛔ مسدود · ⏭️ رد شده (با دلیل)

## ▶️ از اینجا ادامه بده (Resume Here)
| | |
|---|---|
| **فاز فعلی** | **M1** کاتالوگ و زمان‌بندی (M0 تمام شد) |
| **Task بعدی** | T1.5 — Availability API + Cache |
| **Task در حال انجام** | — |
| **آخرین کار انجام‌شده** | T1.4: `AvailabilityCalculator` در `Scheduling\Domain\Availability` (خالص). شبکه شروع از نیمه‌شب محلی، Buffer داخل ساعت کاری، Extra، ظرفیت با مدل «جلسه» (فقط پیوستن به همان جلسه؛ منبع جلسه‌ها را می‌شمارد)، min_notice و max_advance، ترتیب `least_busy` یا `priority`. `LocalDay` برای تبدیل دقیقه محلی به UTC، با گپ DST. تست‌ها: جدول سناریو، مقایسه تصادفی با brute force (300 seed، با mutation تأیید شد) و بنچمارک (حدود 4ms در برابر بودجه 50ms). `composer check` سبز (580 تست Unit) (2026-09-26). قبلی — T1.3: ماژول `Scheduling` شامل Domain (`ScheduleRule` با روز هفته 0 = شنبه، `ScheduleException` از نوع off، extra و blocked، `Holiday`، و `DayPlan` برای دقیقه‌های کاری و مسدود یک مالک در یک روز)، migration `CreateSchedulingTables` (سه جدول)، Repositoryهای Wpdb، دیتاست `assets/holidays/1405.json` (26 روز از تقویم رسمی) و migration `ImportHolidays(1405)`. `Name` و `Slug` به `Shared\Domain` منتقل شدند و `Row` به `Kernel\Database`. `composer check` سبز (546 تست Unit). **CI سبز روی شاخه wip: Integration 114 تست در 4 ترکیب (run 36221089129)** (2026-09-26). قبلی — T1.2: Repositoryهای Catalog (یک interface برای هر Aggregate، حذف نرم)، `CatalogService` (Use Caseهای Admin با بررسی دوباره capability `manage_catalog` از port `Authorizer` و بررسی ارجاع‌ها)، `CatalogApi` با `CatalogReader` (`offer()` و `location()`، فقط آیتم فعال)، REST Admin برای 6 منبع (list، create، read، PUT و delete)، `Shared\Domain\NotFound` و `Forbidden` در Router، `Db::getResults()`، `docs/api.md` و نوع‌های TS. `composer check` سبز (516 تست Unit). **CI سبز روی شاخه wip: Integration 108 تست در 4 ترکیب (run 36189399823)** (2026-09-26). قبلی — T1.1: ماژول `Catalog` با Domain (`Location`، `Staff`، `BookableResource`، `ServiceCategory`، Aggregate `Service` با `Variant`، `ServiceStaff` و `ResourceRequirement`، `Extra`، و VOهای `Name`، `Color` و `Slug`)، `Service::terms()` برای قیمت و مدت اختصاصی پرسنل، و migration `CreateCatalogTables` (9 جدول). `composer check` سبز (455 تست Unit)، `test:rename` سبز. **Integration فقط روی CI اجرا می‌شود و هنوز push نشده** (2026-09-25). قبلی — T0.11: pnpm workspace (`shared`، `admin`، `widget`)، wp-scripts 35، ESLint، Stylelint، Vitest، size-limit، `AdminModule` با صفحه Admin، rename برای JS، و jobهای CI `test-js` و `build`. CI سبز (run 36176578809) (2026-09-25). قبلی — T0.9: `Kernel\Settings` (گروه‌های typed، یک option برای هر گروه)، `SecretStore` (XChaCha20-Poly1305 با کلید از `wp_salt('auth')`، ثابت wp-config با اولویت)، `Kernel\Log` (`Logger` روی جدول `logs` با ماسک PII و Retention، `Pii`)، `Capabilities` (`Module::capabilities()`، هر جفت یک‌بار). `composer check` سبز (383 تست Unit). CI سبز (run 36152711861) (2026-09-25). قبلی — T0.8: `Kernel\Rest` (`Router` مرز خطا با Envelope و `request_id`، `RateLimiter` روی جدول `rate_limits` با migration خود Kernel، `ClientIp`، `Pagination`، `ApiError`)، `Kernel\RequestId`. CI سبز (run 36149430470) (2026-09-25) |
| **Blockerها** | — (PHP 8.3 و Composer نصب شدند. Docker محلی لازم نیست و تست‌های MySQL در CI اجرا می‌شوند). CI سبز است و `gh` لاگین است (`gh run list`، `gh run watch`) |
| **کار کاربر (اختیاری)** | (1) نصب pluginهای `php-lsp`، `typescript-lsp` و `security-guidance` از Manage plugins در VS Code. (2) انتقال پروژه به مسیری بدون فاصله |
| **نکته برای سشن بعد** | اگر `php` یا `composer` پیدا نشد، PATH را refresh کن (dev-environment §2). قبل و بعد از کار `composer check` را اجرا کن. **T0.10:** اگر CI قرمز بود، اول همان را رفع کن و بعد T0.10 را ✅ کن. محتمل‌ترین نقاط شکست: unit روی PHP 8.4 (deprecation)، ترکیب WP 6.6 با PHP 8.4 در integration، و `wp-env start`. نکات wp-env در implementation-notes §2.2 است. قبل از push، `actionlint` را اجرا کن (dev-environment §5). پوشش را با `phpdbg -d memory_limit=-1` اندازه بگیر (dev-environment §1). توکن‌های Identity را لیترال و چسبیده به حرف ننویس (implementation-notes §2.1). قبل از هر commit، `composer test:rename` را هم اجرا کن. تابع‌های سراسری همیشه fully-qualified نوشته می‌شوند (`\add_action()`). **T0.7:** SQL فقط از `Db` و با placeholder (`%i` برای جدول). تست‌های Integration که commit واقعی لازم دارند از `TestCase` و trait `RealDatabase` استفاده می‌کنند، نه `WP_UnitTestCase` (implementation-notes §5). اگر CI قرمز بود، محتمل‌ترین نقطه‌ها `TransactionTest` (KILL و lock wait) و فرض‌های wpdb است. **T0.8:** route فقط با `Router::add()` روی `rest_api_init`؛ متن Exception در `error_log` نمی‌رود (PII)؛ Logger در T0.9 جایگزین `error_log` در `Router` و `Plugin` می‌شود و `RequestId` را از Container می‌گیرد؛ جدول `logs` دومین migration owner `kernel` است (implementation-notes §6). **T0.9:** بعد از push، CI را ببین (`gh run watch`). محتمل‌ترین نقاط شکست در Integration: ستون `JSON` و `JSON_EXTRACT`، مقدار `autoload` (`on`/`off` یا `yes`/`no`)، و `CapabilitiesTest` (WP_Roles در حافظه). secret فقط از `SecretStore`، لاگ فقط از `Logger` با پیام ثابت و داده در context (implementation-notes §6.1). ماژول جدید باید `capabilities()` را پیاده کند. **T0.11:** قبل از commit JS، `pnpm lint` و `pnpm test` را اجرا کن. `pnpm format` را بزن، نه قالب‌بندی دستی. CSS بدون `style.css` (implementation-notes §7.1). **T1.1:** بعد از push، CI را ببین. `CreateCatalogTablesTest` تنها تست جدید Integration است (نوع ستون‌ها و ایندکس `service_group`). Entityهای Catalog را فقط با سازنده بساز، چون invariantها آنجا بررسی می‌شوند (implementation-notes §4.3). **T1.2:** Integration محلی اجرا نمی‌شود. قبل از commit روی `main`، کار را روی شاخه `wip/…` push کن و `gh workflow run CI --ref <branch>` بزن. تله‌های REST (default در schema، ترتیب `get_param`، validation قبل از permission) در implementation-notes §4.4 است. T1.3 از `CatalogApi::location()` برای timezone شعبه استفاده می‌کند. **T1.5:** ورودی calculator را از `CatalogApi::offer()`، `DayPlan` (با `LocalDay::utc()`، intersect با ساعات شعبه) و یک کوئری Batch روی `occupancies` بساز. `Occupancy` باید `variantId` و `staffId` را داشته باشد (مدل جلسه، implementation-notes §4.6). پرسنل و منبع شعبه‌های دیگر را فیلتر کن. گام از `Offer::slotStepMin` یا تنظیم سراسری است. جدول `occupancies` در T2.1 ساخته می‌شود، پس در T1.5 یا جدول را زودتر بساز یا Busy را از یک Port بگیر. **تله charset:** جدولی که SQL خام با متن فارسی می‌گیرد ستون `ascii` نداشته باشد (implementation-notes §4.5). **تا پایان اسفند 1405 باید `1406.json` و `ImportHolidays(1406)` اضافه شود** |

## خلاصه Milestoneها
| Milestone | وضعیت | پیشرفت |
|---|---|---|
| M(-1) تحقیق، معماری و اصول | ✅ | 100% |
| M0 زیربنا | ✅ | 11/11 |
| M1 کاتالوگ و زمان‌بندی | 🟨 | 4/5 |
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
| T0.11 | JS workspace | ✅ | `abab1d6` `feat(admin): js workspace with admin shell and widget mount (T0.11)` + `6dab2b0` (رفع CI). **CI سبز: هر 11 job، Integration 56 تست در 4 ترکیب (run 36176578809)** |
| T1.1 | Catalog Domain | ✅ | `feat(catalog): domain entities and catalog tables (T1.1)`. Integration تست migration در CI بررسی می‌شود. انحراف‌های schema در data-model §2 (یادداشت زیر Catalog) |
| T1.2 | Catalog REST CRUD | ✅ | `feat(catalog): repositories, admin rest crud and catalog api (T1.2)`. **CI سبز: Integration 108 تست در 4 ترکیب (run 36189399823، روی شاخه wip قبل از squash)**. نقش‌های افزونه هنوز ساخته نشدند (implementation-notes §4.4) |
| T1.3 | Scheduling + تعطیلات | ✅ | `feat(scheduling): schedules, exceptions, holidays and the 1405 dataset (T1.3)`. **CI سبز: Integration 114 تست در 4 ترکیب (run 36221089129، روی شاخه wip قبل از squash)**. REST برنامه‌ها با UI در T3.2 و T3.5 ساخته می‌شود و `resource_day_locks` در T2.2 |
| T1.4 | AvailabilityCalculator | ✅ | `feat(scheduling): availability calculator (T1.4)`. فقط Domain و Unit، پس Integration ندارد. «سقف روزانه» به T2.5 (Policy `booking_window`) منتقل شد |
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
