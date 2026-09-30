# Progress Tracker

> **اولین فایلی که هر سشن جدید می‌خواند.** در پایان هر Task و هر سشن به‌روز می‌شود.
> وضعیت‌ها: ⬜ انجام نشده · 🟨 در حال انجام · ✅ انجام‌شده · ⛔ مسدود · ⏭️ رد شده (با دلیل)

## ▶️ از اینجا ادامه بده (Resume Here)
| | |
|---|---|
| **فاز فعلی** | **M6** انتشار در جریان است (T6.1 و T6.2 نوشته شدند) |
| **Task در حال انجام** | — (T5.3 تا T6.2 نوشته و محلی بررسی شدند، منتظر CI؛ Integration تازه T6.2: `StatusRestTest`؛ اگر سرخ بود اول همان را رفع کن با `gh run list`. Integration تازه: ووکامرس/HPOS، `NotificationStoreTest` (شامل قالب‌های پیامکی و `sms_patterns`)). باقی‌مانده‌های T5.5: صفحه Admin تنظیم پیامک و ویرایش پترن‌ها با T6.1؛ تست زنده هر چهار سرویس‌دهنده با کلید واقعی پیش از انتشار (IPPanel روی API قدیمی است). باقی‌مانده‌های T5.4: صفحه Admin قالب‌ها؛ اعلان رویداد confirmed. باقی‌مانده‌های T5.2: `payment_status` نوبت با استرداد عوض نمی‌شود؛ مبلغ استرداد در لغو صفر است؛ UI هشدار `booking/needs_attention`؛ صفحه تنظیم merchant (با T6.1)؛ تست زنده با کلید واقعی |
| **Task بعدی** | **T6.3** — ترجمه کامل fa_IR، بررسی en، ممیزی a11y (axe) و بودجه کارایی. پیش از آن CI مربوط به T5.3 تا T6.2 را بررسی کن (billing گیتهاب)؛ اگر سرخ بود اول رفع کن، به‌ویژه Integration ووکامرس، `SetupRestTest`، `StatusRestTest` و E2E `setup.spec.ts` که هیچ‌کدام هنوز اجرا نشده‌اند. باقی‌مانده‌های T6.1: صفحه ویرایش قالب‌های اعلان و پترن‌های پیامک در UI؛ رنگ هدر Admin بعد از ذخیره با reload اعمال می‌شود. باقی‌مانده‌های T6.2: با خاموش‌بودن `notifications` بخش پیامک Settings خطا می‌دهد (UI پنهانش نمی‌کند) |
| **آخرین کار انجام‌شده** | **T6.2** (Site Health، صفحه System Status و خاموش‌کردن `notifications` و `widget`؛ منتظر CI) و پیش‌تر **T6.1** (برند، ویزارد نصب و تنظیم پیامک/درگاه در UI؛ منتظر CI) و پیش‌تر **T5.5** (پیامک: چهار سرویس‌دهنده، Failover، پترن، OTP؛ منتظر CI) و پیش‌تر **T5.3** (ووکامرس) و **T5.4** (اعلان‌ها). قبل از آن با CI سبز: **T4.2 تا T4.5** (Hold تا تأیید، OTP، پنل مشتری، shortcode و بلوک)، **T5.1** (هسته پرداخت) و **T5.2** (Zarinpal، Zibal، تطبیق، استرداد دستی، اتصال Booking و ویجت به پرداخت آنلاین). **CI سبز روی main: run 36552515145** (هر 13 job، 2026-09-29). ویجت 15.5KB gz |
| **Blockerها** | CI اجرا نمی‌شود: GitHub می‌گوید پرداخت حساب ناموفق است یا سقف هزینه پر شده (run 36555809046، 2026-09-29، Billing & plans). تا رفع آن T5.3 فقط محلی تأیید شده (Integration ووکامرس هنوز اجرا نشده) |
| **قانون کار (از کاربر، 2026-09-29)** | **سریع پیش برو:** کد را دسته‌ای بنویس و پشت سر هم تست نگیر؛ `composer check`/`pnpm test`/`build`/`test:rename` را فقط آخر یک دسته یا با CI اجرا کن. reviewer را اجرا نکن. بعد از هر بخش `02-progress.md` را به‌روز کن |
| **تله‌های فعلی** | (۱) `Db::getResults()` فقط SQL literal می‌گیرد؛ SQL را داخل هر متد Repository بنویس، نه در متد کمکی. (۲) فایل‌های repo را با Edit ویرایش کن؛ اسکریپت Python روی ویندوز CRLF می‌نویسد و `\a` را BEL می‌کند (phpcs می‌گیرد). داخل heredoc ابزار Bash هم دو backslash پشت سر هم یکی می‌شود و در Python به بایت BEL تبدیل می‌شود (در سشن 24 باعث fatal در همه jobهای Integration شد). کد PHP را فقط با Write/Edit بنویس و بعد از هر اسکریپت، فایل‌های تغییرکرده را برای بایت کنترلی اسکن کن. (۳) `X-WP-Nonce` کهنه هر درخواست مهمان را 403 می‌کند: ویجت قبل از POST nonce تازه می‌گیرد. (۴) دکمه‌های فرم را نام یکتا بده (مثل «Add field»)، وگرنه لوکیتورهای E2E می‌شکنند. (۵) تا پایان اسفند 1405 باید `1406.json` و `ImportHolidays(1406)` اضافه شود. جزئیات بیشتر: `docs/04-engineering/03-implementation-notes.md` |
| **کار کاربر (اختیاری)** | انتقال پروژه به مسیری بدون فاصله؛ نصب pluginهای `php-lsp` و `typescript-lsp` |

## خلاصه Milestoneها
| Milestone | وضعیت | پیشرفت |
|---|---|---|
| M(-1) تحقیق، معماری و اصول | ✅ | 100% |
| M0 زیربنا | ✅ | 11/11 |
| M1 کاتالوگ و زمان‌بندی | ✅ | 5/5 |
| M2 هسته رزرو | ✅ | 8/8 |
| M3 Admin | ✅ | 6/6 |
| M4 سمت مشتری | ✅ | 5/5 |
| M5 پرداخت و اعلان | ✅ | 5/5 (T5.3 تا T5.5 منتظر CI) |
| M6 انتشار 1.0 | 🟨 | 2/6 (T6.1 و T6.2 منتظر CI) |

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
| T1.5 | Availability API + Cache | ✅ | `feat(scheduling): availability api and cache (T1.5)`. **CI سبز: Integration 125 تست در 4 ترکیب، p95 زیر 40ms (run 36264007776، روی شاخه wip قبل از squash)**. جدول `occupancies` از T2.1 جلو آمد. min_notice و max_advance فعلاً سراسری‌اند (Policy در T2.5) |
| T2.1 | Booking Migrations | ✅ | `feat(booking): booking tables migration (T2.1)`. **CI سبز: Integration 129 تست در 4 ترکیب (run 36266758573، روی شاخه wip قبل از squash)**. Policy سراسری `service_id = 0` |
| T2.2 | Locker + Hold + تست همزمانی | ✅ | `feat(booking): holds with day locks and POST /holds (T2.2)`. **CI سبز (run 36278605604): Integration 135 تست در 4 ترکیب؛ concurrency: پرسنل مشخص 1 موفق و 29 پاسخ 409، «فرقی نمی‌کند» 2 موفق (دو پرسنل) و 28 پاسخ 409.** قفل‌ها روی روز UTC. ردیف‌های قفل قبل از تراکنش ساخته می‌شوند (رفع deadlock) |
| T2.3 | PriceCalculator | ✅ | `feat(booking): price calculator and hold price quote (T2.3)`. **CI سبز (run 36278605604)**. قیمت در Hold snapshot می‌شود. کوپن در `POST /holds`؛ شمردن `used` در T2.4 |
| T2.4 | Appointment + Confirm | ✅ | `feat(booking): appointment state machine and confirm (T2.4)`. **CI سبز (run 36302887258)**. `POST /bookings` فعلاً فقط Admin |
| T2.5 | Policy + Cancel/Reschedule | ✅ | `feat(booking): policies, cancel, reschedule and no-show (T2.5)`. **CI سبز (run 36304495986)**. deposit، approval و booking_window به بعد موکول شدند (implementation-notes §4.12) |
| T2.6 | فیلدهای سفارشی | ✅ | `feat(booking): custom fields and booking answers (T2.6)`. **CI سبز (run 36322900163)**. CRUD فیلدها با T3.5 (implementation-notes §4.13) |
| T2.7 | Customers | ✅ | `feat(customers): customers module with admin crud and search (T2.7)`. **CI سبز (run 36326611539)**. OTP و نشست با T4.3، UI با T3.5. محدودیت multisite در implementation-notes §4.14 |
| T2.8 | Admin Queries | ✅ | `feat(booking): admin appointment list, detail and calendar queries (T2.8)`. **CI سبز (run 36341868373)**. EXPLAIN در `AppointmentQueriesTest`. سقف تقویم 2000 آیتم (implementation-notes §4.15) |
| T3.1 | Admin Shell | ✅ f89944a | منو، ErrorBoundary، snackbar، TanStack Query، dark mode. CI سبز (run 36352743077) |
| T3.2 | صفحات کاتالوگ | ✅ | `feat(scheduling): admin api for weekly schedules and exceptions`، `feat(admin): catalog screens with weekly hours, time off and e2e`. ADR-019. **CI سبز (run 36409232141)** شامل E2E |
| T3.3 | تقویم Admin | ✅ | 2026-09-28 |
| T3.4 | لیست و جزئیات نوبت | ✅ | `feat(admin): appointment list and detail with approve, complete and notes (T3.4)`. ADR-019 (بدون DataViews). **CI سبز (run 36449635744)** |
| T3.5 | مشتریان، Policy، قیمت، فیلدها | ✅ | `94ce3bb`..`18d2823` و `23f4748`. کوپن و قیمت زمانی هم Repository جدا از `WpdbPricingReader` دارند. E2E در `settings.spec.ts`. انتخاب خدمت در فرم کوپن/قیمت زمانی فقط با API ممکن است (UI بعداً). **CI سبز (run 36544899242)** |
| T3.6 | داشبورد و گزارش | ✅ | `23f4748`. درآمد = `price_total` نوبت‌های confirmed و completed (پرداخت واقعی با M5). CSV سمت کلاینت. **CI سبز (run 36544899242)** |
| T4.1 | ویجت: انتخاب و تقویم | ✅ | `33035a6`. `GET /catalog` عمومی. ویجت 11KB gz. **CI سبز (run 36544899242)** |
| T4.2 | ویجت: Hold تا تأیید | ✅ | `42b39eb` + رفع lint. **CI سبز (run 36552515145)** |
| T4.3 | OTP | ✅ | `4d0f662` + رفع lint/تست. تنظیم `require_phone_verification` پیش‌فرض خاموش. **CI سبز (run 36552515145)** |
| T4.4 | پنل مشتری | ✅ | `60198eb`. پرداخت مانده هنوز نیست. **CI سبز (run 36552515145)** |
| T4.5 | Shortcode و Block | ✅ | `eb4d25e`. ماژول `Widget`. **CI سبز (run 36552515145)** |
| T5.1 | Payments core | ✅ | `684dd67`. **CI سبز (run 36552515145)** |
| T5.2 | Zarinpal، Zibal، تطبیق | ✅ | `37107d2`. HTTP Mock ضبط‌شده؛ استرداد فقط دستی. اتصال Booking همین‌جا انجام شد (implementation-notes §4.17). **CI سبز (run 36552515145)** شامل 5 سناریوی Integration |
| T5.3 | ووکامرس | ✅ | `WooCommerceGateway` روی Port `WcOrders`؛ سفارش pending با fee line، HPOS، IRR و IRT، settle با status change و بازگشت مشتری. پیش‌فرض خاموش (`WooCommerceSettings`). **منتظر CI** (implementation-notes §4.18) |
| T5.4 | Notifications core | ✅ | ماژول `Notifications`: قالب، trigger، audience، یادآوری با Action Scheduler، ساعات سکوت، dedup، لاگ، Email و REST قالب‌ها/لاگ. **منتظر CI** (implementation-notes §4.19) |
| T5.5 | SMS Providers | ✅ | `SmsProvider` با چهار Adapter (کاونگار، IPPanel، SMS.ir، ملی‌پیامک)، Failover، پترن هر قالب، OTP، `GET/PUT /sms` و `POST /sms/test`. **منتظر CI** (implementation-notes §4.20) |
| T6.1 | White-label + Onboarding | ✅ | `Brand`، `BrandSettings`، `SetupService`، `PaymentSettingsService`، ویزارد `#/setup` و فرم‌های Settings. **منتظر CI و E2E** (implementation-notes §4.21) |
| T6.2 | Site Health + Status | ✅ | `Switchable` و `ModuleCatalog` در Kernel، `HealthEvaluator`، `StatusService`، `GET /status`، `PUT /modules/{id}`، تست‌های Site Health و صفحه `#/status`. **منتظر CI** (implementation-notes §4.22) |
| T6.3 | ترجمه، a11y، کارایی | ⬜ | |
| T6.4 | امنیت + Plugin Check | ⬜ | |
| T6.5 | E2E کامل + تست ارتقا | ⬜ | |
| T6.6 | Build و مستندات انتشار | ⬜ | |

## تصمیم‌های باز
| # | موضوع | وضعیت |
|---|---|---|
| 1 | نام نهایی محصول | باز است. **مانع کار نیست** (ADR-000: قابل تعویض با `rename.php` تا پیش از انتشار) |
| 2 | کانال فروش و سیستم لایسنس | باز است. قبل از M6 لازم است. لایسنس کد GPL است (ADR-017) |
