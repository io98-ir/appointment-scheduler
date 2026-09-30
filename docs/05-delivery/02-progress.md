# Progress Tracker

> **اولین فایلی که هر سشن جدید می‌خواند.** در پایان هر Task و هر سشن به‌روز می‌شود.
> وضعیت‌ها: ⬜ انجام نشده · 🟨 در حال انجام · ✅ انجام‌شده · ⛔ مسدود · ⏭️ رد شده (با دلیل)

## ▶️ از اینجا ادامه بده (Resume Here)
| | |
|---|---|
| **فاز فعلی** | **M6** تمام شد؛ نسخه **1.0.0** ساخته شده است. CI به دستور مالک (2026-09-30) کنار گذاشته شد، پس آنچه فقط در CI قابل اجراست **اجرا نشده** (فهرست در «Task بعدی») |
| **Task در حال انجام** | — (هیچ) |
| **Task بعدی** | برنامه‌ی 1.0 تمام است. **اجرا/تأییدنشده (چون CI کنار گذاشته شد):** jobهای `plugin-check`، `uninstall`، `package`؛ تست‌های Integration `UpgradeTest`، `StatusRestTest`، `EmailChannelTest` و همه Integration T5.3 تا T6.6 (ووکامرس/HPOS، `SetupRestTest`)؛ E2E `golden.spec.ts` و `setup.spec.ts`؛ نصب و فعال‌سازی `dist/vaqtyar-1.0.0.zip` روی وردپرس واقعی (به‌ویژه 7.x)؛ تست زنده چهار سرویس‌دهنده پیامک و دو درگاه با کلید واقعی. **پیش از فروش این‌ها را دست‌کم یک بار دستی یا با CI اجرا کن (به‌علاوه‌ی Integrationهای 1.1 و دیدن آیکن و زبان در وردپرس واقعی).** بعد از 1.1، به ترتیب اولویت ([05-parity-audit.md](../01-research/05-parity-audit.md) §3): بیعانه و پرداخت اجباری، تأیید دستی به‌ازای خدمت، پنجره‌ی رزرو هر خدمت، تأیید پرداخت آفلاین/استرداد در رابط، پرداخت مانده، لیست انتظار. بک‌لاگ بعد از 1.0: تأیید پرداخت آفلاین و ثبت استرداد در رابط (فعلاً API)، جابه‌جایی نوبت توسط پرسنل در رابط، پرداخت مانده در پنل مشتری، سقف سراسری OTP و سقف Hold فعال (07-security-review.md §3)، ویراستار فارسی ترجمه و ممیزی دستی کنتراست/کیبورد (06-i18n-a11y-perf-report.md)، 1406.json تعطیلات تا پایان اسفند 1405 |
| **آخرین کار انجام‌شده** | **نسخه 1.1.0 (2026-10-01، ADR-020)**: رابط Admin مینیمال و سبک به سبک io98.ir با پالت رنگی پویا (شش پالت + رنگ دلخواه، حالت تاریک)؛ **تقویم شمسی/میلادی، ارقام و زبان (مانند سایت، فارسی، English)** در Settings و ویزارد، فیلد تاریخ شمسی (تایپی + شبکه ماه) در همه‌ی فرم‌های Admin و ویجت؛ نام «وقت یار» در فارسی و «Vaqtyar» در انگلیسی (و **بارگذاری ترجمه‌ی PHP که قبلاً اصلاً انجام نمی‌شد**)؛ اعتبار سازنده io98 (هدر افزونه، ردیف افزونه، فوتر، آیکن منوی «io98»)؛ **قوانین رزرو در رابط** (`/booking-rules`)، افزودن به تقویم (.ics)، صفحه‌ی تشکر، CSV مشتریان، شورت‌کد هر خدمت؛ [بازبینی برابری با مرجع‌ها](../01-research/05-parity-audit.md)؛ **راهنمای مصور مرحله‌ای** در `docs/guide` (25 عکس از رابط واقعی با `pnpm guide:shoot`). **تأییدشده محلی:** `composer check` (1050 تست Unit)، `pnpm lint`، `vitest` (207)، `build`، `size` (ویجت 16.6KB، admin 44.6KB)، `i18n:check` (593 رشته). **اجرا نشده:** Integration جدید (`BookingRulesRestTest`، `/general` در `SetupRestTest`، `thanks` در `EmbedsTest`) و رفتار `Localization` روی وردپرس واقعی (سایت فارسی + افزونه انگلیسی و برعکس). **قبلی: آماده‌سازی انتشار 1.0.0 (2026-09-30)**: نسخه 1.0.0 در چهار جا، `Tested up to: 7.1`، صفحه **Notifications** در Admin برای ویرایش قالب‌ها و کد الگوی پیامک (دیگر فقط API نیست)، راهنمای فارسی به‌روز، 557 رشته ترجمه‌شده؛ و پیش‌تر **T6.6 (zip ساخته و محلی تأیید شد)**: `composer build:zip` با `tools/Release/Packager.php` (فهرست مجاز، vendor بدون dev، بررسی یکی‌بودن نسخه)، `readme.txt`، `license.txt`، `CHANGELOG.md`، `user-guide-fa.md`، `docs/hooks.md`، jobهای CI `package` و `plugin-check` روی پوشه‌ی zip؛ و پیش‌تر **T6.5 (نوشته شد، منتظر CI)**: `uninstall.php` که خالی بود حالا با انتخاب صریح مالک همه داده را پاک می‌کند (`Kernel\Uninstaller`، سوییچ در System status)، job `uninstall`، تست ارتقا، E2E طلایی؛ و پیش‌تر **T6.4 (بخش امنیت)**: چک‌لیست §7 روی کل کد، رفع شمارش تلاش OTP (UPDATE شرطی) و اجبار `text/plain` در ایمیل، `composer audit`/`pnpm audit`؛ و پیش‌تر **T6.3** (ترجمه کامل fa_IR با 527 رشته، ممیزی axe و رفع `role=grid` تقویم ویجت؛ منتظر CI) و پیش‌تر **T6.2** (Site Health، صفحه System Status و خاموش‌کردن `notifications` و `widget`؛ منتظر CI) و پیش‌تر **T6.1** (برند، ویزارد نصب و تنظیم پیامک/درگاه در UI؛ منتظر CI) و پیش‌تر **T5.5** (پیامک: چهار سرویس‌دهنده، Failover، پترن، OTP؛ منتظر CI) و پیش‌تر **T5.3** (ووکامرس) و **T5.4** (اعلان‌ها). قبل از آن با CI سبز: **T4.2 تا T4.5** (Hold تا تأیید، OTP، پنل مشتری، shortcode و بلوک)، **T5.1** (هسته پرداخت) و **T5.2** (Zarinpal، Zibal، تطبیق، استرداد دستی، اتصال Booking و ویجت به پرداخت آنلاین). **CI سبز روی main: run 36552515145** (هر 13 job، 2026-09-29). ویجت 15.5KB gz |
| **Blockerها** | ندارد. GitHub Actions به‌خاطر billing اجرا نمی‌شود و مالک گفته CI را ولش کنیم؛ کد و تست‌ها محلی (`composer check`، `pnpm lint/test/build/size/i18n:check`، `composer build:zip`) سبزند |
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
| M6 انتشار 1.0 | ✅ | 6/6 (با قید: چیزهایی که فقط در CI یا روی وردپرس واقعی اجرا می‌شوند تأیید نشده‌اند) |

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
| T6.3 | ترجمه، a11y، کارایی | ✅ | `languages/` (pot، po، mo، JED)، `tools/i18n`، `pnpm i18n:check` و job `i18n` در CI، تست‌های axe برای Admin و ویجت. گزارش: `06-i18n-a11y-perf-report.md`. **منتظر CI** |
| T6.4 | امنیت + Plugin Check | ✅ | بازبینی امنیتی کامل، F1 (OTP) و F2 (ایمیل) رفع شدند، ریسک‌های پذیرفته‌شده ثبت شد: [07-security-review.md](07-security-review.md). **Plugin Check هیچ‌بار اجرا نشده** (job نوشته شده، CI کنار گذاشته شد) |
| T6.5 | E2E کامل + تست ارتقا + uninstall | ✅ | `Uninstaller` + `DataSettings` + `PUT /data` + سوییچ UI، job `uninstall`، `UpgradeTest`، `golden.spec.ts` (implementation-notes §4.24). **هیچ‌کدام از تست‌های Integration/E2E/uninstall تازه اجرا نشده‌اند** |
| T6.6 | Build و مستندات انتشار | ✅ | `dist/vaqtyar-0.1.0.zip`: 549 فایل، 0.7MB، فقط افزونه با vendor تولیدی؛ `php -l` و autoload روی پوشه مرحله‌ای تأیید شد. `readme.txt`، `CHANGELOG.md`، `user-guide-fa.md`، `docs/hooks.md` (implementation-notes §4.25). **Plugin Check و job `package` هنوز در CI اجرا نشده‌اند**؛ نسخه هنوز `0.1.0` است |

## تصمیم‌های باز
| # | موضوع | وضعیت |
|---|---|---|
| 1 | نام نهایی محصول | **بسته شد (2026-09-30، مالک): نام «Vaqtyar» (وقت‌یار) می‌ماند.** ADR-000 همچنان امکان تعویض را نگه می‌دارد |
| 2 | کانال فروش و سیستم لایسنس | باز است. قبل از M6 لازم است. لایسنس کد GPL است (ADR-017) |
