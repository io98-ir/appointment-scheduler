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

## 2026-09-29 — سشن 25 — T5.3: ووکامرس به‌عنوان درگاه
**Taskها:** T5.3 (منتظر CI)
**انجام شد:** `WooCommerceGateway` روی Port `WcOrders` (Application) با `WcOrderStore` (CRUD سفارش ووکامرس، سازگار با HPOS)، `WooCommerceHooks` (اعلام HPOS، settle با `woocommerce_order_status_changed`، بازگشت مشتری به callback خودمان)، `WooCommerceSettings` (پیش‌فرض خاموش) و اتصال در `PaymentsModule`. تست Unit (10 تست، Fake) و Integration با ووکامرس واقعی (5 تست؛ بدون ووکامرس skip). CI: نصب WC 9.3.3 فقط در job Integration با `.wp-env.override.json`؛ `bootstrap.php` آن را load و نصب می‌کند و HPOS را روشن می‌کند. `php-stubs/woocommerce-stubs` به devDependencies و PHPStan اضافه شد.
**تصمیم‌ها و فرض‌ها:** (۱) سفارش pending حکم نیست (awaiting می‌ماند) تا پرداخت دیرتر گم نشود. (۲) فقط IRR و IRT. (۳) پیش‌فرض خاموش تا نصب ووکامرس رفتار رزروها را عوض نکند. (۴) ووکامرس فقط در job Integration نصب می‌شود تا onboarding آن E2E را نشکند. (۵) نسخه 9.3.3 حدسی است برای سازگاری با WP 6.6؛ اگر CI نصب را رد کرد نسخه را عوض کن.
**تأیید:** محلی: `composer lint`، `stan`، `deptrac`، `test:unit` ← 895 تست سبز. Integration فقط CI (اولین اجرای ووکامرس و HPOS آنجاست؛ `WC_Install::install()` در `setup_theme` بدون تأیید نوشته شده).
**مشکلات و باقیمانده:** سبز شدن CI؛ سفارش pending رهاشده؛ اطلاعات صورت‌حساب؛ استرداد به ووکامرس.
**قدم بعدی:** بررسی CI ← T5.4.
**Commitها:** `c6636eb`.

---

## 2026-09-29 — سشن 24 (ادامه 7) — T5.2: درگاه‌ها، تطبیق، استرداد و اتصال Booking
**Taskها:** T5.2 (منتظر CI)، رفع CI سرخ T4.3 تا T5.1
**انجام شد:** `ZarinpalGateway` و `ZibalGateway` روی Port `JsonHttp`؛ `callbackAuthority` در Port درگاه؛ `PaymentService::settleCallback` و `reconcile`؛ `RefundService` با `POST /payments/refunds`؛ `OnlinePayments` (Contract `PaymentsApi`)؛ در Booking: `UnpaidAppointments`، `OnlineCheckout`، `Actor::system()`، `Appointment::paymentStatus()` (دیگر public readonly نیست)، Job انقضا، شنونده `payments/succeeded` با `booking/needs_attention`، `GET /payment-options` و `pay_online` در `POST /book`؛ callback با `return` به صفحه سایت برمی‌گردد. ویجت: دکمه «پرداخت آنلاین»، هدایت به درگاه، بنر نتیجه. مستندات: `api.md` و implementation-notes §4.17.
**تصمیم‌ها و فرض‌ها:** (۱) مشتری انتخاب می‌کند «آنلاین» یا «در محل»؛ اگر درگاه آنلاینی هست هر دو دکمه دیده می‌شود، و «اجباری بودن پرداخت» تنظیم بعدی است (Policy deposit موکول شده بود). (۲) پرداخت دیرهنگام به نوبت منقضی‌شده اعمال نمی‌شود، بلکه به کارمند گزارش می‌شود، چون تصمیم پول با انسان است. (۳) استرداد با API درگاه ساخته نشد. (۴) merchant id فقط با `SecretStore` یا ثابت wp-config؛ UI با onboarding.
**تأیید:** محلی: `composer lint` (هر دو استاندارد)، PHPStan، Deptrac (دو فایل)، `phpunit --testsuite unit` ← 885 تست، `vitest run` ← 143 تست، `pnpm typecheck` و `pnpm build` و `pnpm size` (ویجت 15.46KB gz). Integration نه (فقط CI). **علت CI سرخ قبلی:** اسکریپت Python با `\\a` در تست بایت BEL نوشته بود ← `HoldsTest` parse نمی‌شد ← fatal 255 در هر چهار ترکیب. درس: داخل Bash tool دو backslash پشت سر هم یکی می‌شود؛ برای کد PHP از Write/Edit استفاده کن، نه Python heredoc.
**مشکلات و باقیمانده:** CI برای T4.2 تا T5.2؛ `payment_status` با استرداد؛ UI هشدار `needs_attention`؛ صفحه تنظیم درگاه؛ تست زنده با کلید واقعی.
**قدم بعدی:** پس از سبز شدن CI ← T5.3.
**Commitها:** T5.2 در commit بعدی.

---

## 2026-09-29 — سشن 24 (ادامه 6) — T5.1: هسته پرداخت
**Taskها:** T5.1 (منتظر CI)، رفع CI سرخ T4.4/T4.5
**انجام شد:** ماژول `Payments` با Port درگاه، Registry، `PaymentService` (Failover، settle idempotent)، `OfflineGateway`، جدول‌های `payments` و `refunds`، routeهای callback و تأیید آفلاین، و رویداد `payments/succeeded`. رفع CI: باگ تست (`null ?? 'set'` همیشه `'set'` می‌دهد)، import بلااستفاده، دو خط بلند.
**تصمیم‌ها و فرض‌ها:** استعلام درگاه بیرون از قفل ردیف است تا HTTP زیر تراکنش نباشد؛ `settle` با `UPDATE … WHERE status = awaiting_callback` جلوی تصادم دو callback را می‌گیرد. Booking و Payments فقط با action به هم می‌رسند. `refunds` همین حالا ساخته شد تا این ماژول migration دوم نخواهد. اتصال به Booking عمداً به T5.2 رفت چون بدون درگاه واقعی قابل امتحان نیست.
**تأیید:** تست محلی گرفته نشد؛ CI ابزار تأیید است.
**مشکلات و باقیمانده:** CI سبز؛ اتصال Booking؛ تست Integration ریپازیتوری.
**قدم بعدی:** T5.2.
**Commitها:** T5.1 در commit بعدی.

---

## 2026-09-29 — سشن 24 (ادامه 5) — T4.5: Shortcode و بلوک
**Taskها:** T4.5 (منتظر CI)
**انجام شد:** ماژول `Widget` (`WidgetModule`، `Presentation/Embeds`): shortcodeهای `[vaqtyar_booking]` و `[vaqtyar_panel]`، بلوک‌های سمت‌سرور با ویژگی‌های service، variant، location، staff، calendar و digits؛ enqueue شرطی؛ `assets/blocks.js` برای ویرایشگر. deptrac و `vaqtyar.php` به‌روز شدند.
**تصمیم‌ها و فرض‌ها:** ویرایشگر بلوک بدون build و بدون global ساخته شد تا وابستگی پروژه بیشتر نشود (`@wordpress/blocks` و همراهانش external وردپرس‌اند). نام بلوک‌ها از attribute `data-blocks` تگ اسکریپت خوانده می‌شود، پس نام برند در JS نیست. بدون `build/` مهمان چیزی نمی‌بیند.
**تأیید:** تست محلی گرفته نشد؛ CI ابزار تأیید است.
**مشکلات و باقیمانده:** CI سبز برای T4.2 تا T4.5. اگر `rename.php` فایل `assets/blocks.js` را نبیند، text-domain آن پس از rename عوض نمی‌شود (CI بررسی می‌کند).
**قدم بعدی:** M5 (T5.1).
**Commitها:** T4.5 در commit بعدی.

---

## 2026-09-29 — سشن 24 (ادامه 4) — T4.4: پنل مشتری
**Taskها:** T4.4 (منتظر CI)، رفع CI سرخ T4.3
**انجام شد:** `AppointmentService::decisions()` (Policy لغو و جابجایی بدون قفل و بدون تغییر)؛ `CustomerPanel` (نوبت‌های مشتری از روی نشست شماره، لغو و جابجایی از راه `AppointmentService` با `Actor::customer` که خودش مالکیت را می‌سنجد)؛ `PanelRoutes` زیر `/my/appointments`؛ `CustomerApi::customerOfSession`. ویجت: `Panel.tsx`، `mountPanel`، گزینه `session` در `ApiClient`، و `status` در `useFetch` برای خروج خودکار با 401 و 403.
**تصمیم‌ها و فرض‌ها:** نشست در `sessionStorage` نگه داشته می‌شود (تا بسته شدن تب، 29 دقیقه). «پرداخت مانده» ساخته نشد چون پرداخت تا M5 نیست. پنل همیشه OTP می‌خواهد، مستقل از تنظیم `require_phone_verification`، چون داده شخصی نشان می‌دهد. مشتری Override ندارد.
**تأیید:** بنا به درخواست کاربر تست محلی گرفته نشد؛ CI ابزار تأیید است. CI T4.3 قرمز بود و رفع شد.
**مشکلات و باقیمانده:** سبز شدن CI.
**قدم بعدی:** T4.5.
**Commitها:** T4.4 در commit بعدی.

---

## 2026-09-29 — سشن 24 (ادامه 3) — T4.3: OTP، نشست شماره و captcha
**Taskها:** T4.3 (منتظر CI)، T4.2 (رفع lint)
**انجام شد:** Customers: migration `CreateOtpCodesTable`؛ پورت‌های `OtpStore` (با `WpdbOtpStore`) و `OtpSender` (با `HookOtpSender`)؛ `OtpService`؛ `PhoneSessions` و `Captcha` (بدون ذخیره، امضا با `wp_salt('auth')`)؛ `OtpRoutes`. `CustomerApi::forBooking` توکن نشست را می‌گیرد و وقتی `LoginSettings::requirePhoneVerification` روشن است شماره را با آن تطبیق می‌دهد (`phone_not_verified`). `POST /book` پارامتر `session_token` گرفت. ویجت: `PhoneCheck.tsx` (captcha، ارسال کد، تأیید) داخل `BookingFlow`. تست‌ها: `OtpServiceTest` (Unit)، دو تست Integration در `HoldsTest`، یک تست Vitest.
**تصمیم‌ها و فرض‌ها:** کد هرگز در پاسخ یا لاگ نیست؛ فقط action `customers/otp` (تا فرستنده‌های پیامک T5.5). تنظیم پیش‌فرض خاموش است چون بدون کانال ارسال، روشن‌کردنش رزرو را قفل می‌کند. captcha بدون ذخیره فقط جلوی اسکریپت ساده را می‌گیرد؛ سد اصلی rate limit هر کلاینت و هر شماره است. برای رزرو شمارش حدس‌ها قبل از مقایسه ثبت می‌شود تا حدس‌های موازی از سقف رد نشوند.
**تأیید:** بنا به درخواست کاربر تست محلی گرفته نشد؛ CI ابزار تأیید است. CI T4.2: Integration سبز، lint قرمز که رفع شد.
**مشکلات و باقیمانده:** سبز شدن CI برای T4.2 و T4.3.
**قدم بعدی:** T4.4.
**Commitها:** T4.3 در commit بعدی.

---

## 2026-09-29 — سشن 24 (ادامه 2) — T4.1 تکمیل و T4.2: Hold تا تأیید در ویجت
**Taskها:** T4.1 (✅)، T4.2 (در حال انجام)
**انجام شد:** T4.1: `PublicMenu` (Catalog Application) و `GET /catalog` عمومی با rate limit؛ ویجت Preact: انتخاب خدمت، Variant، شعبه، پرسنل؛ تقویم ماه شمسی یا میلادی (شنبه اول، `month.ts`)؛ رنگ روزها؛ ساعت‌های خالی؛ «اولین نوبت خالی»؛ رویداد `vqy:slot`. T4.2: `InvalidValue` جزئیات گرفت و Router آن را در envelope می‌گذارد؛ `AnswerValidator` کلید فیلد خطادار را برمی‌گرداند (`data.details.field_key`)؛ `CustomerApi::forBooking` (پیدا یا ساخت مشتری با شماره؛ مشتری موجود را تغییر نمی‌دهد)؛ `BookingService::confirmAsGuest` و منبع `widget`؛ `GuestBookingRoutes` با `GET /nonce`، `GET /service-fields` و `POST /book`؛ ویجت `BookingFlow.tsx` (Hold با nonce تازه، تایمر، خلاصه قیمت، کوپن، فرم با فیلدهای سفارشی و `show_if`، صفحه موفقیت با کد پیگیری).
**تصمیم‌ها و فرض‌ها:** شماره تلفن مهمان هنوز تأیید نمی‌شود؛ T4.3 (OTP) آن را الزامی می‌کند. کوپن قبل از ساخت Hold گرفته می‌شود، چون Hold دوم برای همان اسلات با اولی تصادم می‌کند و route آزادسازی Hold وجود ندارد. E2E مرورگری T4.2 تا T4.5 ممکن نیست (صفحه‌ای برای mount ویجت نیست)؛ با Integration (`HoldsTest`) و Vitest پوشش داده شد.
**تأیید:** بر اساس درخواست کاربر، تست‌ها دسته‌ای اجرا می‌شوند. CI سبز برای T3.6 و T4.1 (run 36544899242). کد T4.2 هنوز روی CI اجرا نشده.
**مشکلات و باقیمانده:** اجرای CI برای T4.2 و رفع خطاهای احتمالی. اسکریپت Python فایل‌ها را CRLF می‌نویسد؛ Edit استفاده شود.
**قدم بعدی:** T4.3 (OTP).
**Commitها:** `33035a6` (T4.1)؛ T4.2 در commit بعدی.

---

## 2026-09-29 — سشن 24 (ادامه) — T3.5 تکمیل و T3.6: داشبورد و گزارش
**Taskها:** T3.5 (تکمیل با E2E)، T3.6
**انجام شد:** T3.6: `ReportService` (Application) روی port `ReportQuery` با `WpdbReportQuery` (چهار GROUP BY روی `appointments`: وضعیت، روز، خدمت، پرسنل؛ فیلتر دقیق روی `local_date` و بازه `start_at` ایندکس‌دار با یک روز حاشیه)، REST `GET /reports/summary` (`ReportRoutes`). «رزرو‌شده» یعنی `confirmed` و `completed`؛ درآمد مجموع `price_total` همان‌ها؛ نرخ لغو = لغو‌شده‌ها ÷ (رزرو‌شده + no_show + لغو‌شده). UI: `DashboardPage` (امروز، 7 روز، 30 روز، نرخ لغو، نمودار میله‌ای 7 روز و نوبت‌های تأییدشده باقی‌مانده امروز) در `/`، و `ReportsPage` (بازه، جدول هر روز/خدمت/پرسنل، خروجی CSV از `GET /appointments` تا 5000 ردیف با BOM و خنثی‌سازی سلول‌های شبیه فرمول) در `/reports`. E2E T3.5 و T3.6 در `tests/e2e/specs/settings.spec.ts` (فیلد سراسری، کوپن، قیمت زمانی، تعطیلات، داشبورد، گزارش و دانلود CSV).
**تصمیم‌ها و فرض‌ها:** CSV سمت کلاینت ساخته می‌شود تا route تازه با پاسخ غیر JSON لازم نباشد. «امروز» و «7 روز» تاریخ مرورگر ادمین‌اند. انتخاب خدمت در فرم کوپن و قیمت زمانی به UI اضافه نشد (API دارد)؛ برای T3.5 این را یک بهبود بعدی حساب کردم، نه شرط پایان، چون معیار Task «E2E» بود. درآمد فعلاً مبلغ نوبت است نه پرداخت‌شده (با M5 اصلاح می‌شود).
**تأیید:** `composer check` (828 Unit) سبز. `pnpm lint` سبز. `pnpm test` ← 122 passed (20 فایل). `pnpm build` و `pnpm size` (admin 33.68KB gz) سبز. `ReportRestTest` و E2E فقط با CI.
**مشکلات و باقیمانده:** یک اسکریپت Python من فایل `BookingModule.php` را با CRLF نوشت و phpcs گرفت (رفع شد؛ برای ویرایش فایل‌های repo از Edit استفاده کن یا `newline=''` بده).
**قدم بعدی:** پس از CI سبز، T3.5 و T3.6 ✅ و M3 تمام؛ بعد M4 (T4.1 ویجت).
**Commitها:** (بعد از commit پر می‌شود)

---

## 2026-09-29 — سشن 24 — T3.5 (بخش ۶): قیمت زمانی (Price rule) در Admin
**Taskها:** T3.5 (در حال انجام؛ این بار فقط ردیف‌های `price_rules` از نوع `time`)
**انجام شد:** Domain: `TimeRuleDefinition` (id، service_id، priority، active، به‌علاوه `TimeRule` موجود که فقط قاعده را می‌سنجد و همه invariantهای بازه، درصد و تاریخ را خودش دارد) و `TimeRuleRepository`. Infrastructure: `WpdbTimeRuleRepository` جدا از `WpdbPricingReader` (دست‌نخورده) که config JSON را دقیقاً به شکلی که reader مستند کرده می‌نویسد؛ ردیف خراب از لیست حذف می‌شود. Application: `TimeRuleAdminService` (capability `manage_bookings`، خدمت باید در کاتالوگ باشد). Presentation: `TimeRuleRoutes` با `GET|POST /time-rules` و `PUT|DELETE /time-rules/{id}`. Admin UI: `packages/admin/src/timeRules/TimeRules.tsx` (لیست + فرم افزودن با چک‌باکس روزهای هفته) در `/settings`. `docs/api.md` و نوع TS `TimeRule` اضافه شدند.
**تصمیم‌ها و فرض‌ها:** `<input type="time">` نمی‌تواند `24:00` تولید کند؛ API آن را می‌پذیرد ولی UI فقط تا `23:59` می‌رود. فرم UI همیشه `service_id: null` می‌فرستد (قاعده مخصوص یک خدمت فقط با API). ردیف‌های `price_rules` غیر از `time` هیچ‌وقت خوانده یا حذف نمی‌شوند (`AND type = 'time'` در همه کوئری‌ها).
**تأیید:** `composer check` (821 Unit، phpcs، PHPStan 9، Deptrac) سبز. `pnpm lint` سبز. `pnpm test` ← 114 passed (18 فایل). `pnpm build` و `pnpm size` (admin 31.91KB gz) سبز. `TimeRuleRestTest` (Integration) فقط با CI اجرا می‌شود. subagent `reviewer` اجرا نشد؛ بازبینی دستی.
**مشکلات و باقیمانده:** T3.5: انتخاب خدمت در فرم کوپن و قیمت زمانی (API دارد، UI ندارد)، و E2E کامل (مشتریان، Policy، تعطیلات، فیلدها، کوپن و قیمت زمانی فقط Vitest دارند).
**قدم بعدی:** E2E T3.5 (Playwright روی wp-env)، بعد انتخاب خدمت در UI، بعد T3.5 ✅ و T3.6.
**Commitها:** (بعد از commit پر می‌شود)

---

## 2026-09-29 — سشن 24 — T3.5 (بخش ۵): کوپن‌ها در Admin
**Taskها:** T3.5 (در حال انجام؛ این بار فقط کوپن. Price rule از نوع زمانی می‌ماند)
**انجام شد:** Domain: `CouponRepository` (در `Domain\Pricing`، کنار `Coupon`؛ `all`، `find`، `findByCode`، `save`، `delete`). Infrastructure: `WpdbCouponRepository` جدا از `WpdbPricingReader` (مسیر خواندن و شمارش رزرو، دست‌نخورده ماند)؛ `used` هرگز نوشته نمی‌شود؛ کلید تکراری 1062 به `Conflict coupon_code_taken` تبدیل می‌شود. Application: `CouponAdminService` (capability همان `manage_bookings`؛ کد 1 تا 64 نویسه، `valid_to` بعد از `valid_from`، `max_uses` ≥ 1، `service_ids` خالی رد می‌شود و هر خدمت باید در کاتالوگ باشد، کد تکراری بدون توجه به حرف بزرگ و کوچک 409). Presentation: `CouponRoutes` با `GET|POST /coupons` و `PUT|DELETE /coupons/{id}`. Admin UI: `packages/admin/src/coupons/Coupons.tsx` (لیست + فرم افزودن، بدون ویرایش درجا؛ بازه به‌صورت `datetime-local` در منطقه زمانی مرورگر و ارسال به‌صورت UTC) در `/settings` زیر Policy و فیلدها. `docs/api.md` و نوع TS `Coupon` اضافه شدند.
**تصمیم‌ها و فرض‌ها:** اعتبارسنجی بازه، سقف و لیست خدمت‌ها در Service است، نه سازنده `Coupon`، چون `WpdbPricingReader` همان سازنده را روی ردیف ذخیره‌شده صدا می‌زند و سخت‌تر کردنش می‌تواند رزرو را به‌خاطر یک ردیف قدیمی بشکند. فرم UI محدودکردن کوپن به چند خدمت را ندارد (API دارد، UI فعلاً همیشه `service_ids: null` می‌فرستد)؛ انتخاب خدمت به بخش بعدی موکول شد.
**تأیید:** `composer check` (821 Unit، phpcs، PHPStan 9، Deptrac) محلی سبز. `pnpm lint` سبز. `pnpm test` ← 112 passed (17 فایل). `pnpm build`، `pnpm size` (admin 31.35KB gz) و `composer test:rename` سبز. **CI سبز روی main (run 36535342752): هر ۱۳ job شامل Integration در ۴ ترکیب (با `CouponRestTest`)، Concurrency، Rename و E2E** (2026-09-29). subagent `reviewer` اجرا نشد (کاربر درخواست agent را رد کرد)؛ بازبینی دستی.
**مشکلات و باقیمانده:** T3.5: Price rule (نوع time) و E2E کامل (مشتریان، Policy، تعطیلات، فیلدها و کوپن فقط Vitest دارند)، و انتخاب خدمت در فرم کوپن.
**قدم بعدی:** Price rule زمانی (`WpdbTimeRuleRepository` جدا از `WpdbPricingReader`)، بعد E2E T3.5.
**Commitها:** `3131f5e feat(booking): admin CRUD for coupons (T3.5 part 5)`

---

## 2026-09-28 — سشن 23 — T3.5 (بخش ۴): فیلدهای سفارشی در Admin
**Taskها:** T3.5 (در حال انجام؛ این بار فقط فیلدهای سفارشی)
**انجام شد:** Domain تازه در Booking (implementation-notes §4.13): `FieldScope` (`global`/`service`)، `FieldDefinition` (id، scope، service_id، ترکیب‌شده با `Field` موجود که فقط پاسخ رزرو را اعتبارسنجی می‌کند)، `FieldSetValidator` که هر ذخیره را در برابر «مجموعه در دسترس» آن فیلد می‌سنجد (سراسری‌ها، به‌علاوه فیلدهای همان خدمت برای یک فیلد `service`؛ فقط سراسری‌ها برای یک فیلد `global`، چون یک فیلد سراسری همه خدمت‌ها را هم‌زمان تحت تأثیر قرار می‌دهد و بررسی همه خدمت‌ها عملی نیست) و کلید تکراری یا `show_if` نامعتبر (خودارجاعی، فیلد ناموجود، یا `sort` بزرگ‌تر/مساوی؛ مساوی هم رد می‌شود چون ترتیب تضمین‌شده نیست) را رد می‌کند. Infra: `FieldRepository`/`WpdbFieldRepository` جدا از `WpdbFieldReader` (مسیر خواندن زمان رزرو، دست‌نخورده ماند)، CRUD تخت با `id`. Application: `FieldAdminService` (capability همان `manage_bookings`). Presentation: `FieldRoutes` با `GET|POST /fields` و `PUT|DELETE /fields/{id}`، در `BookingModule` سیم‌کشی شد. Admin UI: `packages/admin/src/fields/Fields.tsx` (لیست + فرم افزودن inline، مثل `TimeOff.tsx`؛ بدون ویرایش درجا، فقط حذف و ساخت دوباره)، زیر صفحه هر خدمت (`ServicesPage`'s `ItemEditor.after`، `scope=service`) و در `/settings` (`scope=global`). `docs/api.md` و نوع TS `FieldDefinition` اضافه شدند.
**تصمیم‌ها و فرض‌ها:** ویرایش درجا عمداً ساخته نشد (مثل `TimeOff`)، چون در بودجه این سشن اولویت CRUD کامل روی بک‌اند و اعتبارسنجی مجموعه در دسترس بود، نه UI کامل؛ حذف-و-ساخت-دوباره برای این نسخه کافی است. گزینه‌های فیلد `select` با یک ورودی متنی جداشده با کاما گرفته می‌شود، نه ویرایشگر چندردیفی مثل `RefundTiers`، برای سادگی. اعتبارسنجی `show_if` عمداً محافظه‌کارانه است: یک فیلد هم‌سطح (`sort` برابر) رد می‌شود چون ترتیب دو ردیف با `sort` یکسان در `WpdbFieldReader` فقط با `id` (که یک فیلد تازه هنوز ندارد) مشخص می‌شود، نه تضمینی.
**تأیید:** `pnpm lint` (js، style، typecheck) سبز. `pnpm test` ← 110 passed (16 فایل). `pnpm build` سبز. `pnpm size` ← admin 30.77KB gz (بودجه 150KB). **`composer check` محلی اجرا نشد** (PHP نصب نیست روی این کپی از repo، برخلاف کپی دیگر که سشن Policy گزارش کرد). **CI سبز روی main (run 36486745772) پس از سه push: هر ۱۳ job شامل Integration در ۴ ترکیب، Concurrency، Rename و E2E** (2026-09-28).
**مشکلات و باقیمانده:** push اول به دو مشکل خورد: (۱) دکمه «افزودن» تازه `Fields.tsx` متن «Add» داشت که در صفحه ویرایش خدمت با دکمه مشابه بخش Add-on (`ExtraForm`) هم‌نام شد؛ E2E موجود (`catalog.spec.ts`) از یک لوکیتور بدون scope برای «Add» استفاده می‌کرد و با «strict mode violation: resolved to 2 elements» شکست. رفع: دکمه به «Add field» تغییر کرد (مثل «Add a tier» در `RefundTiers`، الگوی موجود برای همین مشکل). (۲) phpcs سه خط تست بلندتر از 120 کاراکتر داشت (warning، ولی این پروژه warning را هم fail می‌کند). push دوم به PHPStan سطح 9 خورد: یک متد کمکی خصوصی `private function list(string $sql, ...)` که SQL را به `Db::getResults()` پاس می‌داد، `$sql` را دیگر `literal-string` نمی‌دید (نوع PHPStan که این پروژه برای جلوگیری از SQL injection الگویی می‌خواهد)؛ رفع با inline کردن SQL مستقیم در `globalFields()` و `forService()`، مثل هر `WpdbXxxRepository` دیگر در این codebase (هیچ‌کدام چنین wrapperای ندارند، که این یک الگوی مستند نشده اما رعایت‌شده بود). هر دو مشکل فقط با CI دیده شدند، نه محلی (نه PHP نه Playwright/wp-env روی این دستگاه). باقی‌مانده T3.5: Price rule و کوپن (Domain/REST هنوز ساخته نشده)، به‌علاوه E2E کامل (مشتریان، Policy، تعطیلات و فیلدها فعلاً فقط Vitest دارند).
**قدم بعدی:** ادامه T3.5: Price rule و کوپن admin CRUD (implementation-notes میگوید `WpdbPricingReader` دست نخورد؛ `WpdbCouponRepository`/`WpdbTimeRuleRepository` جدا بساز).
**Commitها:** `e4e1a42 feat(booking): admin CRUD for custom fields (T3.5 part 4)`، `5b01424 fix(booking): distinguish the fields "Add" button and shorten long test lines`، `f2e700a fix(booking): inline the fields repository's SQL literals for PHPStan`

---

## 2026-09-28 — سشن 22 — T3.5 (بخش ۳): صفحه تعطیلات در Admin
**Taskها:** T3.5 (در حال انجام؛ این بار فقط تعطیلات)
**انجام شد:** `HolidayService` تازه در ماژول Scheduling (نه Booking): `holidays()` با محدودیت بازه ۳۶۶ روزه مثل `ScheduleService::exceptions`، `save()` که همیشه با منبع `manual` می‌نویسد (دیتاست سالانه فقط با `ImportHolidays` نوشته می‌شود)، `delete()` با 404 اگر روز موجود نباشد. `HolidayRoutes`: `GET /holidays`، `POST /holidays` (upsert روی کلید calendar+date)، `DELETE /holidays/{calendar}/{date}`. Capability به‌جای وابستگی به Booking (که Deptrac اجازه نمی‌دهد)، یک رشته لیترال تکراری `manage_bookings` است؛ Booking این Capability را از قبل به administrator می‌دهد، پس نیازی به ثبت دوباره در `SchedulingModule::capabilities()` نبود. Admin UI: `packages/admin/src/holidays/HolidaysPage.tsx` دقیقاً روی الگوی `TimeOff.tsx` (لیست + فرم افزودن inline)، با یک فیلد متنی برای کلید calendar چون شعبه‌ها آن را به‌صورت متن آزاد ذخیره می‌کنند، نه یک select از گزینه‌های شناخته‌شده. ورودی منو `/holidays`. `docs/api.md` و `Holiday` در `api-types.ts` اضافه شدند. تست‌های Integration (`HolidayRestTest`، به سبک `ScheduleRestTest`) و Vitest (`holidays.test.tsx`، به سبک `customers.test.tsx` با fake fetch).
**تصمیم‌ها و فرض‌ها:** تست حذف در Vitest فقط وضعیت سرور را بعد از حذف بررسی می‌کند، نه بازتاب فوری DOM؛ همین الگو در `screens.test.tsx` (حذف شعبه) از قبل استفاده شده بود، چون invalidateQueries و رفرش لیست در چرخه‌های act/flush تضمین‌شده نیستند. کلید calendar پیش‌فرض `"ir"` است اما هر مقداری قابل تایپ است؛ تقویم ناشناخته فقط لیست خالی برمی‌گرداند، نه خطا.
**تأیید:** `pnpm lint` (js، style، typecheck) سبز. `pnpm test` ← 107 passed (15 فایل، بعد از rebase روی کار موازی Policy). `pnpm build` سبز. `pnpm size` ← admin 29.98KB gz (بودجه 150KB). **`composer check` محلی اجرا نشد** (PHP نصب نیست روی این کپی از repo). **CI سبز روی main (run 36482424380): هر ۱۳ job، شامل Integration در ۴ ترکیب، Concurrency، Rename و E2E** (2026-09-28).
**مشکلات و باقیمانده:** push اول با `fetch first` رد شد چون سشن دیگری هم‌زمان بخش Policy را روی `main` push کرده بود (commitهای `e2aee8f`..`c2f1d58`)؛ `rebase` روی `origin/main` بدون conflict بود (فایل‌های PHP کاملاً جدا در Booking/Scheduling، فقط چند فایل JS مشترک به‌خوبی merge شدند). این کپی از repo روی `D:\PycharmProjects\appointment_php\appointment-scheduler` است، نه `J:\New folder (2)\extention_php` که یادداشت سشن Policy آن را مسیر «واقعی» خواند و agent `reviewer`/skillهای پروژه را دارد؛ این سشن نه reviewer داشت نه آن skillها (فقط نسخه عمومی `next-task`)، فقط با بازبینی دستی دقیق پیش رفت. بقیه T3.5: فیلدهای سفارشی، Price rule و کوپن، به‌علاوه E2E کامل (مشتریان، Policy و تعطیلات فعلاً فقط Vitest دارند).
**قدم بعدی:** ادامه T3.5: فیلدهای سفارشی یا Price rule/کوپن (هر کدام زودتر آماده شد)، طبق یادداشت‌های `02-progress.md`.
**Commitها:** `fc7ef08 feat(admin): holiday-calendar screen and admin API (T3.5 part 2)`

---

## 2026-09-28 — سشن 21 — T3.5 (بخش ۲): Policy لغو/جابجایی در Admin
**Taskها:** T3.5 (در حال انجام؛ فقط بخش Policy)
**انجام شد:** Domain: `CancellationPolicy::fromConfig()`/`toConfig()` و `ReschedulePolicy::fromConfig()`/`toConfig()` روی پیکربندی JSON جدول `policies` (all-or-nothing: `refund` خراب حتی کنار `notice_hours` سالم کل ردیف را رد می‌کند). `WpdbPolicyReader` (مسیر خواندن زمان رزرو) رفاکتور شد تا این متدها را صدا بزند، بدون تغییر رفتار قابل مشاهده (فقط یک ناسازگاری قبلی بین بررسی `notice_hours` که throw می‌کرد و `refund` که silently خالی می‌شد، یکدست شد). Infra: `PolicyRepository` (interface جدید) و `WpdbPolicyRepository` (upsert با delete-then-insert در یک `Transaction::run()`، مثل `WpdbScheduleRuleRepository::replace()`؛ عمداً از `WpdbPolicyReader` جدا نگه داشته شد). Application: `PolicyAdminService` (capability همان `BookingService::CAPABILITY`، بدون capability جدید؛ اعتبارسنجی `service_id` غیرصفر با `CatalogApi::isStored('service', …)` که تازه اضافه شد). Presentation: `PolicyRoutes` با `GET|PUT|DELETE /policies/{type}/{service_id}` (owner-keyed مثل `ScheduleRoutes`)، در `BookingModule` سیم‌کشی شد. Admin UI در `packages/admin/src/policies/`: `Policies.tsx` (فرم لغو و جابجایی، هر دو با toggle «بدون مهلت» و برای لغو یک ویرایشگر پلکان استرداد) زیر صفحه هر خدمت (`ServicesPage`'s `ItemEditor.after`) و صفحه تازه `/settings` برای Policy سراسری (`service_id=0`، جایگزین Placeholder قبلی). `docs/api.md` بخش «Policy لغو و جابجایی» گرفت.
**تصمیم‌ها و فرض‌ها:** طبق یادداشت سشن قبل، از الگوی owner-keyed `ScheduleRoutes` پیروی شد، نه CRUD تخت مثل `CustomerRoutes`، چون کلید طبیعی جدول `UNIQUE(type, service_id)` است نه یک `id` عددی مستقل. `WpdbPolicyRepository` عمداً از `WpdbPolicyReader` جدا ماند تا مسیر خواندن حیاتی رزرو دست‌نخورده بماند. لیبل فیلد «مهلت» در فرم لغو (`Cancellation deadline...`) و جابجایی (`Reschedule deadline...`) عمداً از لیبل فیلد هر پله استرداد («Hours before the start») متفاوت شد تا برای screen reader و تست دوپهلو نباشد. کشف نکته مهم محیطی: برخلاف یادداشت سشن قبل، **PHP 8.3.33 و Composer 2.10.3 روی همین دستگاه (`J:\New folder (2)\extention_php`) نصب‌اند** (فقط در PowerShell، نه ابزار Bash)؛ یادداشت «به دستگاه جدید منتقل شد» ظاهراً اشتباه بوده. `02-dev-environment.md` و این فایل اصلاح شدند.
**تأیید:** `composer check` محلی ← 821 Unit OK (PHPCS، PHPStan سطح 9، هر دو Deptrac). `composer test:rename` محلی ← سبز. `pnpm lint` (js، style، typecheck)، `pnpm test` ← 105 passed (شامل `policies.test.tsx` تازه) و `pnpm build` سبز؛ `pnpm size` ← admin 29.7KB gz (بودجه 150KB)، widget بدون تغییر. Subagent `reviewer` دو یافته واقعی داد؛ هر دو رفع شد (زیر). **CI سبز روی main (run 36469031550)، هر ۱۳ job شامل Integration در ۴ ترکیب، E2E (admin)، Concurrency و Rename** (2026-09-28؛ یک تلاش اول به Integration خورد، رفع شد، زیر).
**مشکلات و باقیمانده:** reviewer یافته اول: تنگ‌کردن رفتار `WpdbPolicyReader` برای یک شکل خاص (ورودی) — یک تست regression در `PolicyConfigTest` و یادداشت در docblock اضافه شد تا این تفاوت عمدی مستند بماند. یافته دوم: `WpdbPolicyRepository::find*` بدون try/catch یک ردیف خراب را با 422 غیرقابل‌بازیابی روی صفحه Admin می‌کرد؛ رفع شد (مثل reader، ردیف خراب یعنی «تنظیم نشده»، نه خطا). حین نوشتن تست JS یک باگ واقعی در خود fake server تست پیدا و رفع شد (نه در کد محصول): `new Response('', {status: 204})` طبق Fetch spec روی وضعیت null-body خطا می‌دهد؛ باید `null` باشد نه رشته خالی. **CI اول قرمز شد:** `PolicyRestTest::testABrokenRefundTierIs422` انتظار 422 داشت، ولی Schema خود REST (`percent`: 0 تا 100) دقیقاً همان محدوده `RefundTier` را از قبل اعمال می‌کند، پس وردپرس با 400 `rest_invalid_param` رد می‌کند، قبل از رسیدن به Domain (architecture §9). تست به `testATierOutOfSchemaRangeIs400` تغییر نام و انتظار درست کرد؛ فقط محلی (لینت/استن) قابل بررسی بود، نه با اجرای واقعی Integration (بدون Docker)، پس این خطا فقط با CI دیده شد. باقی‌مانده T3.5: فیلدهای سفارشی، Price rule و کوپن (Domain/REST هنوز ساخته نشده) و تعطیلات (فقط REST admin و UI لازم است، Domain آماده است).
**قدم بعدی:** ادامه T3.5: فیلدهای سفارشی یا تعطیلات (implementation-notes §4.13).
**Commitها:** `e2aee8f feat(booking): admin CRUD for the cancellation and reschedule policy (T3.5)`، `5772696 docs(delivery): record the T3.5 policy commit hash in the worklog`، `8cd262e fix(booking): the policy REST schema already enforces RefundTier's bounds`

---

## 2026-09-28 — سشن 20 — T3.5 (بخش ۱): صفحه مشتریان در Admin
**Taskها:** T3.5 (در حال انجام؛ فقط بخش مشتریان)
**انجام شد:** صفحه Admin مشتریان در `packages/admin/src/customers/`: `CustomerList` (جستجو و فیلتر وضعیت، هر دو سمت سرور، روی `GET /customers`)، فرم ایجاد/ویرایش با `ItemEditor` موجود (بدون کد تکراری، چون `Customer` با `{id}` سازگار است)، و `CustomerHistory` (سوابق نوبت هر مشتری روی `GET /appointments?customer=`, از T2.8). تست `customers.test.tsx` (رندر کامل `<App>` روی سرور fake، مثل الگوی `screens.test.tsx`) و E2E `tests/e2e/specs/customers.spec.ts`. علاوه بر این، `GET /customers` یک پارامتر `status` گرفت (`CustomerRoutes`، `CustomerService::customers`، `CustomerRepository::search/count`، `WpdbCustomerRepository::where`) چون Subagent `reviewer` درست گفت فیلتر وضعیت نباید فقط سمت کلاینت روی یک صفحه از سرور باشد (Page X از Y نادرست می‌شد). این یک تغییر واقعی PHP بود، کوچک و با الگوی دقیقاً همان `search` موجود، پس نوشته شد؛ Unit (`CustomerSearchTest`، `CustomerServiceTest`) و Integration (`CustomersRestTest`) به‌روز/اضافه شدند اما هیچ‌کدام محلی اجرا نشدند (PHP نیست)، فقط با CI تأیید می‌شوند.
**تصمیم‌ها و فرض‌ها:** **پروژه به دستگاه جدید منتقل شده و PHP/Composer روی آن نصب نیست؛ کاربر تصمیم گرفت فعلاً نصب نکند** (dev-environment §1 به‌روز شد). به همین دلیل کار مشتریان را عمداً اول انجام دادم چون کاملاً JS بود و محلی قابل تأیید، تا وقتی یک بازبینی (reviewer) یک تغییر واقعی PHP را لازم کرد. `Customer.uuid` را چون سرور نادیده می‌گیرد (خارج از `CustomerRoutes::fields()`) با مقدار خالی در `empty` گذاشتم، چون `ItemEditor`'s `Draft<T>` فقط `id` را کم می‌کند نه `uuid`. فیلترهای لیست مشتریان (جستجو، وضعیت) برخلاف Appointments در hash نگه داشته نشدند؛ ساده‌سازی عمدی چون این لیست معمولاً برای پیدا کردن سریع یک مشتری است نه اشتراک‌گذاری یک نمای فیلترشده.
**تأیید:** `pnpm lint` (js، style، typecheck) سبز. `pnpm test` ← 104 passed (13 فایل). `pnpm build` سبز. `pnpm size` ← admin 28.82KB gz (بودجه 150KB)، widget بدون تغییر. **`composer check` محلی اجرا نشد** (PHP نصب نیست). **CI سبز روی main (run 36457483336): هر 13 job، شامل E2E (admin)، Integration در 4 ترکیب، Concurrency، Lint/PHPStan/Deptrac و Rename** (2026-09-28). reviewer یک مشکل واقعی پیدا کرد (فیلتر وضعیت سمت کلاینت) و رفع شد؛ بقیه‌اش تأیید شد.
**مشکلات و باقیمانده:** دو push اول به `composer stan` خوردند (`Cannot access offset 'id' on mixed` در `CustomersRestTest.php`): بدون PHP محلی، PHPStan سطح 9 فقط در CI دیده می‌شود. علت واقعی، offset دوسطحی روی بدنه REST بود (`$body[0]['id']`؛ خود `body` عنصرهایش را `mixed` می‌بیند، پس یک سطح offset دیگر رد نمی‌شود، حتی با `?? null`)؛ `array_column($body, 'id')` (همان الگوی `ScheduleRestTest`) جواب داد. بقیه T3.5 باقی است: Admin CRUD برای Policy، فیلدهای سفارشی، Price rule و کوپن (Domain، Repository و REST جدید در ماژول Booking لازم دارند) و تعطیلات (Domain از T1.3 آماده، فقط REST admin و UI). جزئیات و نکات طراحی در `02-progress.md` («نکته برای سشن بعد»).
**قدم بعدی:** ادامه T3.5: Policy admin (`PolicyAdminService` + `PolicyRoutes`، با `fromConfig`/`toConfig` روی `CancellationPolicy`/`ReschedulePolicy`).
**Commitها:** `94ce3bb feat(customers): admin screen for customers, with history and a server-side status filter (T3.5)`، `bce7491 fix(customers): satisfy PHPStan on the new status-filter integration test`، `fc2f29a fix(customers): the previous PHPStan fix left one offset chain unguarded`

---

## 2026-09-28 — سشن 19 — T3.4 لیست و جزئیات نوبت
**Taskها:** T3.4
**انجام شد:** Backend شامل `AppointmentService::approve`، `complete` و `saveNote`، `AppointmentRepository::saveNote`، و routeهای `POST /appointments/{id}/approve|complete` و `PUT /appointments/{id}/note`. Admin در `packages/admin/src/appointments/` (لیست با فیلتر و صفحه‌بندی، جزئیات با تغییر وضعیت، لغو و override، یادداشت و history). لینک Details در تقویم. E2E `appointments.spec.ts`.
**تصمیم‌ها و فرض‌ها:** طبق ADR-019 لیست بدون DataViews ساخته شد. پرداخت‌ها تا M5 فقط وضعیت را نشان می‌دهند. modifierهای کلاس وضعیت kebab-case هستند (`_` به `-`) تا Stylelint قبول کند.
**تأیید:** `composer check` ← 802 Unit OK. `pnpm lint` سبز. `pnpm test` ← 101 passed. `pnpm size` ← admin 27.94KB gz و widget 4.99KB. `composer test:rename` OK. reviewer: بدون مشکل مسدودکننده.
**مشکلات و باقیمانده:** reviewer دو نکته جزئی داد. اول، `saveNote` با یادداشت تغییرنکرده هم ردیف history می‌نویسد. دوم، sanitize فقط در route انجام می‌شود. هر دو پذیرفته شدند.
**قدم بعدی:** T3.5.
**Commitها:** `feat(admin): appointment list and detail with approve, complete and notes (T3.4)`

---

## 2026-09-28 — سشن 18 — T3.3 تقویم Admin
**Taskها:** T3.3
**انجام شد:** `calendar/time.ts` (ساعت دیواری از ISO، `isoAt`، `offsetOf` با `Intl` و `longOffset`، `todayIn`، `weekOf` از شنبه، `snap`، و `lanes` برای نوبت‌های هم‌پوشان)، `move.ts` (`reschedule` که رد `policy.*` را به‌صورت نتیجه برمی‌گرداند تا Override پیشنهاد شود و بقیه خطاها را به snackbar می‌سپارد)، `CalendarPage` (نمای روز و هفته، انتخاب شعبه و پرسنل، شبکه یک پیکسل برای هر دقیقه از 07:00 تا 22:00 که برای نوبت‌های بیرون از این بازه بزرگ می‌شود، drag با Pointer Events، `MoveDialog` و `OverrideDialog`) و `QuickBook`. E2E `calendar.spec.ts`: داده از REST، ثبت با کلیک روی 10:00، drag به 12:00، و reload.
**تصمیم‌ها و فرض‌ها:** (1) Drag با Pointer Events انجام می‌شود، نه HTML5 DnD. در CI، drag از `<button draggable>` در Chromium شروع نشد، و HTML5 DnD روی صفحه لمسی (تبلت پذیرش) هم کار نمی‌کند. (2) ثبت سریع فقط شروع‌های `/availability` را پیشنهاد می‌کند (نزدیک‌ترین به محل کلیک)، پس زمان خارج از شبکه اسلات از تقویم ثبت نمی‌شود. (3) Hold راه آزادسازی ندارد، پس Hold و مشتری جدید یک تلاش ناموفق در تلاش بعدی دوباره استفاده می‌شوند. (4) فقط نوبت `confirmed` جابجا می‌شود (همان قانون API).
**تأیید:** `pnpm lint` ← سبز. `pnpm test` ← 95 passed. `pnpm size` ← admin 24.4KB gz. CI: run اول E2E شکست خورد (`dragTo` به click تبدیل شد، و DELETE با 204 در `requestUtils.rest` خطای JSON داد). run دوم: HTML5 DnD اصلاً شروع نشد. run سوم: ref شبکه وصل نبود. **run 36425211018 سبز، E2E 4 passed بدون retry.** Reviewer دو یافته داشت: تلاش دوباره ثبت سریع Hold قبلی را می‌گرفت و مشتری تکراری می‌ساخت، و drag برای نوبت غیر confirmed فعال بود. هر دو رفع شد.
**مشکلات و باقیمانده:** دیالوگ جزئیات کامل نوبت و تغییر وضعیت در T3.4 است. انتخابگر تاریخ جلالی هنوز نداریم (`input type=date`).
**قدم بعدی:** T3.4 لیست و جزئیات نوبت.
**Commitها:** `feat(admin): calendar with day and week views, drag to move and quick booking (T3.3)`، `test(e2e): drag in mouse steps and accept empty delete responses (T3.3)`، `fix(admin): drag appointments with pointer events (T3.3)`، `fix(admin): attach the calendar grid ref for drops (T3.3)`، `fix(admin): reuse a quick booking's hold and customer on retry (T3.3)`

## 2026-09-28 — سشن 17 — T3.2 صفحات کاتالوگ
**Taskها:** T3.2
**انجام شد:** (الف) Scheduling: `ScheduleService` (بررسی دوباره capability `manage_schedules`، وجود صاحب از `CatalogApi::isStored`، رد بازه‌های هم‌پوشان هم‌نوع با `overlapping_rules`، بازه حداکثر 366 روز) و `ScheduleRoutes` (`GET|PUT /schedules/{owner_type}/{owner_id}` و CRUD `/schedule-exceptions`). (ب) Admin: `catalog/` شامل `crud.ts` (useAll، useItem، useSave، useRemove، screenOf)، `CatalogList` (جدول، جستجو با نرمال‌سازی ی و ک، حذف با Modal)، `ItemEditor` (PUT کامل با حفظ فیلدهای مخفی)، `WeeklySchedule` و `TimeOff`، و صفحه‌های شعبه، پرسنل، منبع، دسته و خدمت. `ApiContext` به `api.ts` و `NotFound` به فایل جدا منتقل شدند. (ج) E2E: `tests/e2e` با config ابزار wp-scripts و job `e2e` در CI.
**تصمیم‌ها و فرض‌ها:** (1) **ADR-019:** DataViews در bundle حدود 404KB gz می‌شد، در حالی که بودجه 150KB است. پس لیست‌ها جدول ساده هستند و DataViews در T3.4 به‌صورت lazy دوباره بررسی می‌شود. (2) قیمت در UI به ریال (IRR) است تا تنظیم واحد نمایش اضافه شود. (3) انتخاب تاریخ مرخصی با `input type=date` میلادی است و تاریخ جلالی زیر آن نمایش داده می‌شود. انتخابگر جلالی بعداً اضافه می‌شود. (4) پایان `24:00` از UI قابل انتخاب نیست (محدودیت `input type=time`). (5) Capability جدید `manage_schedules` فقط به administrator داده شده است.
**تأیید:** `composer stan`، phpcs و deptrac ← سبز. `phpunit --testsuite unit` ← 797 passed. `pnpm lint` ← سبز. `pnpm test` ← 82 passed. `pnpm size` ← admin 20.4KB gz. Reviewer روی (الف) ← بدون یافته. Reviewer روی (ب) ← یک یافته: تاریخ 2030 خارج از بازه لیست بود. رفع شد.
**مشکلات و باقیمانده:** run 36406404116 به دلیل انتشار ناقص Playground 3.1.56 در همان لحظه شکست خورد (خطای خارجی). run 36408589556: E2E به دلیل strict mode شکست خورد، چون متن snackbar در ناحیه `a11y-speak` هم تکرار می‌شود. رفع شد. pnpm محلی فیلدهای `libc` را از lockfile حذف می‌کند. این فیلدها دستی برگردانده شدند.
**CI:** **سبز روی main (run 36409232141)**، شامل E2E.
**قدم بعدی:** T3.3 تقویم.
**Commitها:** `feat(scheduling): admin api for weekly schedules and exceptions (T3.2)`، `feat(admin): catalog screens with weekly hours, time off and e2e (T3.2)`، `test(e2e): scope snackbar checks past the a11y live region (T3.2)`

---

## 2026-09-28 — سشن 16 — T3.1 Admin Shell
**Taskها:** T3.1
**انجام شد:** `packages/admin`: `App.tsx` (منوی `SECTIONS` با `aria-current`، هدر، ErrorBoundary برای هر صفحه، `Snackbars` روی store `core/notices`، و `useApi()` از context)، `query.ts` (`createQueryClient`، `shouldRetry`، `errorMessage`)، `config.ts` (خواندن `data-config`)، `theme.ts` (auto، light، dark در localStorage)، و `admin.css` (`color-scheme` با رنگ‌های سیستمی). PHP: `AdminPage` مقدار `data-config` (restUrl و nonce) را render می‌کند و style آن به `wp-components` وابسته است. وابستگی‌ها: `@tanstack/react-query`، `@wordpress/components`، `data` و `notices`. در `eslint.config.cjs` تست‌ها حالا وابستگی‌های پکیج خودشان را هم می‌بینند.
**تصمیم‌ها و فرض‌ها:** (1) منو داخل اپ است و زیرمنوی wp-admin ساخته نشد (route در hash است). (2) صفحه‌های M3 فعلاً `Placeholder` هستند. (3) خطای mutation به‌صورت سراسری snackbar می‌شود، ولی خطای query را خود صفحه نمایش می‌دهد. (4) حالت تاریک فقط محیط اپ را تغییر می‌دهد، نه کل wp-admin را. انتخاب کاربر برای هر مرورگر جداست (تنظیم سراسری White-label در T6.1). (5) روی WP 6.6، propهای `__next*` نادیده گرفته می‌شوند و فقط ظاهر کمی فرق می‌کند.
**تأیید:** `pnpm lint` (eslint، stylelint، tsc) ← سبز. `pnpm test` ← 67 passed. `pnpm build` ← OK. `pnpm size` ← admin 9.92KB gz و widget 4.99KB gz. Reviewer (agent `reviewer` بارگذاری نشد، پس همان تعریف با general-purpose اجرا شد) ← دو یافته، هر دو رفع شد: یک بایت CR به‌جای `\r` در `\rest_url` (خطای ویرایش اسکریپتی که باعث fatal می‌شد)، و نبودن stylesheet `wp-components`. **`composer check` اجرا نشد: PHP و Composer روی این دستگاه نیستند.** تغییر PHP و `AdminPageTest` باید روی CI تأیید شوند.
**مشکلات و باقیمانده:** نصب PHP 8.3 و Composer روی دستگاه جدید (dev-environment §). E2E ناوبری در T3.2 همراه اولین صفحه CRUD اضافه می‌شود.
**CI:** run اول (36351300935) در PHPStan شکست خورد: `$match[1]` بعد از `preg_match` در `AdminPageTest`. Integration در 4 ترکیب از همان ابتدا سبز بود. بعد از رفع، **CI سبز (run 36352743077).**
**قدم بعدی:** T3.2.
**Commitها:** `feat(admin): app shell with navigation, notices, query client and dark mode (T3.1)`، `test(admin): narrow the preg_match group for PHPStan (T3.1)`

---

## 2026-09-27 — سشن 15 — T2.8 Queryهای Admin نوبت
**Taskها:** T2.8
**انجام شد:** Read side نوبت‌ها: `AppointmentBrowser` (Application)، port `AppointmentQuery`، DTOهای `AppointmentRow`، `AppointmentDetail`، `AppointmentFilter`، `AppointmentSearch` و `AppointmentSort`، و `Infrastructure\Query\WpdbAppointmentQuery`. REST: `GET /appointments`، `GET /appointments/{id}` و `GET /calendar` (`AppointmentListRoutes`، `AppointmentJson::row` و `detail`). ماژول Customers: Contract `CustomerDirectory` (`summaries` و `matching`) با `WpdbCustomerDirectory`. migration `AddAppointmentStartIndex`. مستندات: api.md، implementation-notes §4.15، data-model، README دو ماژول، `api-types.ts`.
**تصمیم‌ها و فرض‌ها:** (1) بدون JOIN به جدول ماژول دیگر؛ نام مشتری با یک کوئری batch از Contract می‌آید، و نام پرسنل و خدمت را UI از لیست‌های کاتالوگ می‌گیرد. (2) جستجو: کد پیگیری، یا جدیدترین 200 مشتری مطابق. (3) فیلتر تاریخ روی `local_date` شعبه، با کران `start_at` یک روز حاشیه برای ایندکس. (4) تقویم فقط وضعیت‌هایی که وقت می‌گیرند، حداکثر 42 روز و 2000 آیتم. (5) ایندکس جدید `start_at` برای لیست بدون فیلتر و تقویم همه پرسنل.
**تأیید:** `composer check` ← lint، stan، deptrac سبز و Unit OK (787 tests). `composer test:rename` ← OK. `pnpm lint` ← سبز. Reviewer ← دو یافته، هر دو رفع شد: تقویم سقف ردیف نداشت (حالا 2000 و 422 `too_many_appointments`)، و زمان با میلی‌ثانیه و `Z` (خروجی `toISOString()`) رد می‌شد (حالا پذیرفته می‌شود و تست Integration دارد).
**مشکلات و باقیمانده:** CI اول (run 36341656620) یک شکست داشت که باگ خود تست بود: در fixture، `+` آرایه `customer_note` خالی `columns()` را نگه داشته بود. بعد از رفع، **CI سبز (run 36341868373): Integration 154 تست در 4 ترکیب، همه planهای EXPLAIN مطابق انتظار، concurrency سبز.**
**قدم بعدی:** T3.1 (M3 شروع می‌شود).
**Commitها:** `feat(booking): admin appointment list, detail and calendar queries (T2.8)`، `test(booking): keep the fixture's customer note in the queries test (T2.8)`

---

## 2026-09-27 — سشن 14 — T2.7 مشتریان
**Taskها:** T2.7
**انجام شد:** ماژول `Customers`: Domain (`Customer`، `CustomerStatus`، `CustomerRepository`)، `CustomerService` (CRUD Admin با capability `manage_customers`)، `CustomerReader` و Contract `CustomerApi::canBook`، migration `CreateCustomersTable`، `WpdbCustomerRepository` (جستجوی LIKE روی `search_name`، `email` و `phone`)، REST `/customers`، listener `deleted_user`. `BookingService::confirm` مشتری را از `CustomerApi` بررسی می‌کند. `SearchText` و `Page` به `Shared\Domain` منتقل شدند (دومین مصرف‌کننده). مستندات: api.md، implementation-notes §4.14، README ماژول، `api-types.ts`.
**تصمیم‌ها و فرض‌ها:** (1) شماره تلفن الزامی و هویت مشتری است (UNIQUE). (2) حذف نرم شماره را NULL می‌کند تا ثبت دوباره ممکن باشد. (3) هر حساب وردپرس مال یک مشتری (بررسی در Application). (4) جستجو contains است و از ایندکس استفاده نمی‌کند؛ برای حجم یک کسب‌وکار کافی است. (5) `otp_codes` و `customer_sessions` با T4.3. (6) بررسی `canBook` زیر قفل نیست.
**تأیید:** `composer check` ← lint، stan، deptrac سبز و Unit OK (778 tests). `composer test:rename` ← OK. `pnpm lint` و `pnpm test` ← سبز. Reviewer ← بدون یافته مسدودکننده؛ دو یافته جزئی: رقابت PUT و DELETE حالا 404 می‌دهد (نه 500)، و محدودیت multisite در `deleted_user` مستند شد.
**مشکلات و باقیمانده:** Integration (`CustomersRestTest`، تغییرات `HoldsTest`) فقط روی CI اجرا می‌شود.
**قدم بعدی:** push و دیدن CI، بعد T2.8.
**Commitها:** `feat(customers): customers module with admin crud and search (T2.7)`

---

## 2026-09-27 — سشن 13 — T2.6 فیلدهای سفارشی
**Taskها:** T2.6
**انجام شد:** بیشتر کد و تست‌ها از قبل در commit `46615c4` (با پیام «next task»، بیرون از روال) بود و tracker به‌روز نشده بود. این سشن آن را کامل کرد: (1) `Field::requiredAnswer()` تعریف نشده بود و PHPStan قرمز بود، و checkbox الزامی با `false` خطای fatal می‌داد. (2) `show_if` روی پاسخ خام بود. حالا به ترتیب و روی پاسخ validate‌شده فیلدهای نمایان قبلی است، پس پاسخ یک فیلد مخفی فیلد دیگری را نمایان نمی‌کند و `equals: "0"` برای checkbox کار می‌کند. (3) سقف طول text به کاراکتر (`mb_strlen`)، و trim ارقام فارسی در number. (4) از یافته‌های reviewer: UTF-8 نامعتبر و number بلند یا `INF`/`NAN` حالا 422 می‌دهند، نه 500.
**تصمیم‌ها و فرض‌ها:** شرطی که به فیلد بعدی اشاره کند هرگز برقرار نمی‌شود. کلید تکراری سراسری و خدمت، و `field_key` در details خطا به T3.5 و T4.2 موکول شد (implementation-notes §4.13).
**تأیید:** `composer check` ← lint، stan، deptrac سبز و Unit OK (751 tests). `composer test:rename` ← OK. Reviewer ← بدون یافته مسدودکننده.
**مشکلات و باقیمانده:** Integration (`HoldsTest`، فیلدها و `appointment_answers`) فقط روی CI اجرا می‌شود.
**قدم بعدی:** push و دیدن CI، بعد T2.7.
**Commitها:** `feat(booking): custom fields and booking answers (T2.6)`

---

## 2026-09-27 — سشن 12 (ادامه) — T2.5 Policy، لغو و جابجایی
**Taskها:** T2.5
**انجام شد:** Domain `Policy` (پلکان استرداد، مهلت‌ها، سقف جابجایی)، `Appointment::restore` و `reschedule`، `AppointmentService` (cancel، reschedule، markNoShow)، `Actor`، `WpdbPolicyReader`، `WpdbAppointmentRepository` (find، update، release، occupy)، Jobهای لغو و جابجایی، REST staff، capability `override_policies`. مستندات: implementation-notes §4.12، api.md، README.
**تصمیم‌ها و فرض‌ها:** (1) فقط Policyهای `cancellation` و `reschedule`. `deposit` با M5، `approval` و `booking_window` با T4.2، و CRUD Policy با T3.5 می‌آیند. (2) مبلغ استرداد تا M5 روی «پرداخت‌شده = 0» حساب می‌شود و فقط درصد معنا دارد. (3) بدون Policy همه‌چیز تا شروع آزاد است با استرداد 100%. (4) Override دلیل الزامی دارد و مشتری هرگز override نمی‌کند. (5) جابجایی قیمت را عوض نمی‌کند. (6) نوبت مشتری دیگر 404 است، نه 403. (7) no-show فقط بعد از شروع.
**تأیید:** `composer check` ← lint، stan، deptrac سبز و Unit OK (715 tests). `composer test:rename` ← OK. Reviewer: بدون یافته مسدودکننده. رفع شد: اشغالی که بین خواندن و قفل جابجا شده بود بیرون از قفل حذف می‌شد (حالا `appointment_changed`)، و `local_date` در timezone شعبه حساب می‌شد و ممکن بود با `timezone` خود نوبت نخواند. پذیرفته و مستند شد: بازه قفل در جابجایی‌های دور.
**مشکلات و باقیمانده:** Integration جدید (`testStaffRescheduleAndCancelUnderThePolicies`) فقط روی CI اجرا می‌شود.
**قدم بعدی:** push و دیدن CI، بعد T2.6.
**Commitها:** `feat(booking): policies, cancel, reschedule and no-show (T2.5)`

---

## 2026-09-27 — سشن 12 — T2.4 نوبت و Confirm
**Taskها:** T2.4
**انجام شد:** `Appointment` با ماشین وضعیت (همه گذارهای booking-engine §4 و رد بقیه)، `TrackingCode`، `PriceQuote::fromArray`، `BookingService::confirm`، `WpdbAppointmentRepository`، `WpdbHoldRepository::details` و `handOver`، `WpdbPricingReader::couponForUse` و `countUse`، `ActionSchedulerBookingJobs`، `POST /bookings`، capability `manage_bookings`. مستندات: api.md، implementation-notes §4.11، README ماژول.
**تصمیم‌ها و فرض‌ها:** (1) `POST /bookings` فعلاً فقط برای Admin است و `customer_id` را مستقیم می‌گیرد، چون ماژول Customers در T2.7 و نشست مشتری در T4.3 می‌آید. وجود مشتری بررسی نمی‌شود. (2) وضعیت اولیه همیشه `confirmed` است. `pending_payment` با M5 می‌آید و تأیید دستی با تنظیمات بعدی. (3) `needs_attention` به M5 موکول شد. (4) Job بدون `unique` ثبت می‌شود (Action Scheduler آرگومان‌ها را مقایسه نمی‌کند). (5) برخورد کد پیگیری retry نمی‌شود (احتمال ناچیز، یافته reviewer).
**تأیید:** `composer check` ← lint، stan، deptrac سبز و Unit OK (684 tests). `composer test:rename` ← OK. Reviewer ← بدون یافته مسدودکننده. سه یافته جزئی در implementation-notes §4.11 ثبت شد.
**مشکلات و باقیمانده:** Integration جدید در `HoldsTest` فقط روی CI اجرا می‌شود و هنوز push نشده.
**قدم بعدی:** push و دیدن CI، بعد T2.5.
**Commitها:** `feat(booking): appointment state machine and confirm (T2.4)`

## 2026-09-27 — سشن 11 — CI برای T2.2 و T2.3
**Taskها:** T2.2، T2.3 (push و تأیید CI)
**انجام شد:** هر دو Task روی `main` push شدند و job `concurrency` سه بار رفع شد:
1. خط آخر خروجی `wp eval-file` پیام خود wp-env بود، نه JSON. حالا seed خط `SEED {…}` چاپ می‌کند.
2. `wp eval-file` فایل را با `eval()` اجرا می‌کند و `declare(strict_types=1)` آنجا fatal است. برای `seed.php` برداشته شد و در phpcs استثنا شد.
3. **یافته واقعی:** در حالت «فرقی نمی‌کند»، 7 درخواست از 30 بعد از retryها 500 گرفتند (deadlock). double-booking رخ نداد. علت: `INSERT IGNORE` داخل تراکنش روی ردیف موجود قفل S می‌گیرد و ارتقا به `FOR UPDATE` بین چند تراکنش deadlock می‌شود. رفع: `ResourceLocker::prepare()` ردیف‌ها را بیرون از تراکنش می‌سازد و `lock()` فقط `FOR UPDATE` می‌گیرد (و ردیف گم‌شده را fallback داخل می‌سازد). deadlock باقیمانده بعد از retryها حالا 503 `busy` با `Retry-After` است.
**تأیید:** CI روی main (run 36278605604) ← همه jobها سبز. Integration ← OK (135 tests, 463 assertions) در 4 ترکیب. concurrency ← `staff: {"201":1,"409 slot_taken":29}`، `any: {"201":2,"409 slot_taken":28}` با پرسنل [3,4].
**قدم بعدی:** T2.4.
**Commitها:** `fix(ci): read the concurrency seed from a marked line (T2.2)`، `fix(ci): no strict_types in the eval-file concurrency seed (T2.2)`، `fix(booking): create day-lock rows before the hold transaction (T2.2)`

---

## 2026-09-27 — سشن 11 — T2.3 PriceCalculator
**Taskها:** T2.3
**انجام شد:**
- `Booking\Domain\Pricing`: `PriceCalculator`، `PriceQuote`، `PriceLine`، `PriceContext`، `ChosenExtra`، و Ruleهای `BasePrice`، `TimePricing` (`TimeRule`)، `ExtrasPrice`، `PartySize`، `CouponDiscount` (`Coupon`، `CouponType`) و `RoundTotal`.
- `HoldPricing` (Application) و port `PricingReader` با `WpdbPricingReader` (time ruleها از `price_rules`، کوپن از `coupons`)، و `PricingSettings` (گام و حالت گرد کردن Total).
- Hold قیمت را snapshot می‌کند (`holds.price_quote`)، و `POST /holds` پارامتر `coupon` می‌گیرد و `price` برمی‌گرداند. نوع‌های TS `PriceQuote` و `PriceLine`.
- تست‌ها: جدول Unit با 16 سناریو، کوپن‌های غیرقابل‌استفاده، قانون‌های نامعتبر، و HoldService با قیمت و کوپن. Integration برای time rule از DB، snapshot، و کوپن (بدون حساسیت به حروف).
**تصمیم‌ها و فرض‌ها:**
- قیمت اختصاصی پرسنل همان `StaffOffer::price` است که Catalog حل کرده است، پس مراحل 1 و 2 یک خط `base` هستند.
- از time ruleها فقط اولین قانون منطبق اعمال می‌شود و درصد فقط روی قیمت پایه است. درصد تخفیف کوپن رو به بالا گرد می‌شود (به نفع مشتری).
- Party size شامل Extraها هم ضرب می‌شود.
- یافته reviewer: کد کوپن در ابتدا به هیچ مسیر production وصل نبود. مسیر کامل شد: پارامتر `coupon` در REST ← `PricingReader::coupon()` ← `PriceContext`. شمردن `used` در T2.4 است.
**تأیید:** `composer check` ← OK (661 tests). `composer test:rename` ← OK. `pnpm lint`، `pnpm test` ← OK. Integration روی CI بعد از push.
**مشکلات و باقیمانده:** —
**قدم بعدی:** T2.4.
**Commitها:** `feat(booking): price calculator and hold price quote (T2.3)`

---

## 2026-09-27 — سشن 11 — T2.2 Locker + Hold + تست همزمانی
**Taskها:** T2.2
**انجام شد:**
- **Scheduling:** `AvailabilityCalculator::pick()` (همان قواعد `slots()` برای یک شروع، به‌علاوه واحدهای منبع). `AvailabilityService` حالا port `Contracts\SlotClaims` را پیاده می‌کند: `scope()` برای کاندیدهای قفل و `claim()` برای بررسی مجدد از DB بدون cache. `AvailabilityQuery` به Contracts منتقل شد.
- **Booking:** Domain (`Hold`، `HoldToken`، `LockKey`)، `HoldService` (place، extend، purgeExpired)، `WpdbResourceLocker`، `WpdbHoldRepository`، migration `CreateResourceDayLocksTable`، و `POST /holds`. Job `booking/purge_holds` و action `booking/changed` برای باطل کردن cache.
- **Kernel و Shared:** `Shared\Domain\Conflict` (409)، `Shared\Domain\TransactionRunner`، و `Router::hasRestNonce`.
- **تست‌ها:** Unit برای Hold و HoldService (ترتیب scope ← قفل ← claim ← نوشتن ← commit ← changed)، و property تصادفی `pick` در برابر `slots` (300 seed). Integration `HoldsTest`. CI job `concurrency` با `tests/Concurrency`.
**تصمیم‌ها و فرض‌ها:**
- قفل روی روز **UTC** است و جدول در Booking (data-model، تغییرات T2.2).
- وقتی مشتری پرسنل انتخاب نکند، همه پرسنل کاندید قفل می‌شوند. ساده و درست است، به قیمت سریالی شدن Holdهای یک خدمت در یک روز.
- `price_quote` تا T2.3 برابر `{}` است. تمدید فقط متد سرویس است و route ندارد (مصرف‌کننده‌اش پرداخت در M5 است).
- rate limit برای `POST /holds` برابر 30 در دقیقه است (NAT اپراتورها).
**تأیید:** `composer check` ← OK (630 tests). `composer test:rename` ← OK. `pnpm lint`، `pnpm test` ← OK. `actionlint` ← OK. Reviewer ← بدون یافته (ضد double-booking را بررسی کرد: snapshot، ترتیب قفل، پوشش روز، تمدید، ظرفیت). Integration و concurrency فقط روی CI اجرا می‌شوند و بعد از push بررسی می‌شوند.
**مشکلات و باقیمانده:** —
**قدم بعدی:** T2.3.
**Commitها:** `feat(booking): holds with day locks and POST /holds (T2.2)`

---

## 2026-09-26 — سشن 10 (ادامه) — T2.1 Booking Migrations
**Taskها:** T2.1
**انجام شد:**
- migration دوم Booking، `CreateBookingTables`، با ده جدول data-model §2 و ثبت آن در `BookingModule::migrations()`.
- تست Integration `CreateBookingTablesTest`: InnoDB بودن هر 11 جدول Booking، idempotent بودن، نوع ستون پول، قیدهای UNIQUE، متن فارسی در TEXT و JSON، و کد تخفیف فارسی با SQL خام.
**تصمیم‌ها و فرض‌ها:**
- `fields.key` به `field_key` و `condition` به `show_if` تغییر نام داد (کلمه رزرو MySQL).
- `holds.location_id` اضافه شد (تأیید Hold لازمش دارد).
- یافته‌های reviewer: (1) Policy سراسری `service_id = 0` است، نه NULL، تا UNIQUE واقعاً کار کند. (2) `coupons` ستون ascii ندارد تا کد فارسی با SQL خام پیدا شود (تله §4.5). هشدار مشابه برای جستجوی `appointments` در T2.7 در implementation-notes §4.8 ثبت شد.
- ستون `meta` اضافه نشد (principles §0).
**تأیید:** `composer check` ← OK (609 tests)، `composer test:rename` ← OK. CI روی `wip/t2.1` ← همه jobها سبز، Integration ← OK (129 tests, 444 assertions) در 4 ترکیب (run 36266758573).
**مشکلات و باقیمانده:** —
**قدم بعدی:** T2.2 — ResourceLocker، Hold و `POST /holds` با تست همزمانی.
**Commitها:** `feat(booking): booking tables migration (T2.1)`

---

## 2026-09-26 — سشن 10 (ادامه) — T1.5 Availability API + Cache
**Taskها:** T1.5
**انجام شد:**
- **Scheduling Application:** `AvailabilityService` با سه نما (`day`، `month`، `first`)، `AvailabilityQuery`، `DayAvailability` و `DayStatus` (available، full، closed)، و port `SlotCache`.
- **Scheduling Contracts:** port `OccupancyReader` و DTO `BusySpan`.
- **Scheduling Infrastructure و Presentation:** `WpSlotCache`، `AvailabilitySettings`، action `scheduling/changed` از Repositoryها، و `GET /availability` عمومی با rate limit 120 در دقیقه.
- **ماژول Booking (اسکلت):** migration `CreateOccupanciesTable` و `WpdbOccupancyReader`.
- **Catalog:** `Offer::extras` (`ExtraOffer`)، `StaffOffer::priority` (= `sort`)، `ExtraRepository::ofService()`، و action `catalog/changed` از Repositoryها.
- **رفع تله T1.4:** `LocalDay` لحظه جابه‌جایی DST را با جستجوی دودویی روی `getOffset()` پیدا می‌کند (tzdata سیستم لینوکس).
- **اسناد:** implementation-notes §4.7 و تله tzdata در §4.6، data-model (تغییرات `occupancies`)، architecture §11، `docs/api.md`، `api-types.ts`، و READMEهای Catalog، Scheduling و Booking.

**تصمیم‌ها و فرض‌ها:**
- **جهت وابستگی:** Busy از port در Scheduling می‌آید و Booking آن را پیاده می‌کند، چون Booking به Scheduling وابسته است. جدول `occupancies` زودتر از T2.1 ساخته شد.
- **ستون‌های اضافه در `occupancies`:** `variant_id` و `staff_id` برای مدل جلسه، و `expires_at` تا کوئری Busy بدون join به `holds` باشد.
- **شعبه یا منبع بدون برنامه هفتگی تمام روز باز است.** پرسنل بدون برنامه کار نمی‌کند.
- **Cache:** نتیجه هر روز بدون پنجره رزرو ذخیره می‌شود. invalidation سراسری است (generation) و TTL پنج دقیقه. طرح «cache برای هر مالک و روز» در architecture §11 با این جایگزین شد.
- **پیش‌فرض‌های سراسری:** گام 30 دقیقه، min_notice 60 دقیقه، max_advance 60 روز، `least_busy`. Policy خدمت در T2.5 می‌آید.
- **`closed` در برابر `full`:** `closed` یعنی هیچ پرسنلی آن روز کار نمی‌کند یا روز بیرون از پنجره است. `full` یعنی کار هست ولی شروع آزادی نمانده.
- **مدت Extra ضرب در تعداد واحد است.** تکرار id در `extras[]` یعنی یک واحد بیشتر.

**Review:** subagent `reviewer` شش مورد پیدا کرد و هر شش رفع شد:
1. منبع بدون برنامه هیچ‌وقت آزاد نبود. پس خدمتی که اتاق لازم داشت همیشه «پر» نشان داده می‌شد.
2. race در cache: نتیجه‌ای که قبل از invalidation محاسبه شده بود زیر generation جدید ذخیره می‌شد. حالا generation یک‌بار برای هر درخواست خوانده می‌شود.
3. کوئری occupancies کران پایین نداشت و کل تاریخچه هر کلید را می‌خواند. حالا `start_at > from − 7 روز`.
4. p95 در واقع بیشینه 10 نمونه بود و bootstrap سرور REST را هم می‌شمرد. حالا یک درخواست گرم‌کننده و 30 نمونه برای هر نما.
5. قواعد هفتگی برای هر هفته دوباره خوانده می‌شدند. حالا یک‌بار برای هر درخواست.
6. `catalog/changed` در لایه REST فرستاده می‌شد و WP-CLI یا import آن را دور می‌زد. حالا از Repositoryها و بعد از COMMIT فرستاده می‌شود، و architecture §11 به‌روز شد.

**تأیید:**
- `composer check` ← lint و PHPStan بدون خطا، deptrac با 0 violation، `OK (609 tests, 12534 assertions)`.
- `pnpm lint` و `pnpm test` ← 38 تست سبز.
- `composer test:rename` ← «OK. The renamed copy passed composer check and the JS checks». هشدارهای rmdir فقط از پاک‌کردن کپی موقت در ویندوز است.
- **Mutation:** حذف فیلتر notice دو تست را شکست داد، و حذف پیش‌فرض «شعبه بدون برنامه» 12 تست را.
- **CI (شاخه `wip/t1.5-availability`):**
  - run 36263620890 قرمز بود و دو مشکل داشت:
    - `LocalDayTest` از T1.4: `getTransitions()` با tzdata سیستم لینوکس. T1.4 هیچ‌وقت روی CI نرفته بود.
    - فرض کهنه در تست cache، بعد از رفع مورد 6 reviewer.
  - run 36264007776 سبز است: هر 11 job سبز، Integration 125 تست در 4 ترکیب. p95 نمای روز حدود 5ms و نمای ماه 28 تا 39ms، در برابر بودجه 300ms.

**مشکلات و باقیمانده:**
- invalidation روی نوشتن در `occupancies` در T2.2 وصل می‌شود.
- UI تنظیمات Availability در T6.1 ساخته می‌شود.
- شاخه `wip/t1.5-availability` روی origin مانده است.

**قدم بعدی:** T2.1 — Booking Migrations.
**Commitها:** `feat(scheduling): availability api and cache (T1.5)`

---

## 2026-09-26 — سشن 10 (ادامه) — T1.4 AvailabilityCalculator
**Taskها:** T1.4
**انجام شد:**
- **`Scheduling\Domain\Availability`** (Domain خالص):
  - `AvailabilityCalculator::slots()`
  - `SlotRequest`، `StaffCandidate`، `ResourceCandidate`، `ResourceGroup`، `Occupancy`، `Slot`، `SlotStaff` و `StaffChoice`
  - `LocalDay`: یک تاریخ در یک timezone. دقیقه‌های `DayPlan` را به ثانیه UTC تبدیل می‌کند، با رفتار درست در گپ DST.
- **تست‌ها:**
  - `AvailabilityCalculatorTest`: جدول 27 سناریو، به‌علاوه `seatsLeft` و ورودی نامعتبر.
  - `AvailabilityRandomizedTest`: 300 seed در برابر یک brute force دقیقه‌به‌دقیقه، شامل جلسه‌های قابل پیوستن روی پرسنل و منبع.
  - `AvailabilityBenchmarkTest`: 10 پرسنل با گام 5 دقیقه، بهترین از 5 اجرا باید کمتر از 50ms باشد.
  - `LocalDayTest`: تهران، روز DST، و گپ بهار نیویورک.
- **اسناد:** implementation-notes §4.6، README ماژول، و توضیح ظرفیت در `BookableResource`.

**تصمیم‌ها و فرض‌ها:**
- **شبکه شروع از نیمه‌شب محلی** با گام، نه از اول هر بازه کاری. همه پرسنل ساعت‌های یکسان پیشنهاد می‌دهند. کار از 09:10 با گام 30 اولین نوبت را 09:30 می‌دهد.
- **Buffer باید داخل ساعت کاری باشد** (booking-engine §2).
- **مدل جلسه برای ظرفیت:**
  - فقط پیوستن به همان جلسه مجاز است (همان Variant، همان پرسنل، دقیقاً همان بازه).
  - هر هم‌پوشانی دیگر، حتی از همان Variant، مسدود می‌کند.
  - ظرفیت منبع یعنی جلسه‌های همزمان، و هر جلسه یک‌بار شمرده می‌شود. پس `Occupancy` شناسه `staffId` دارد.
- **`min_notice` و `max_advance` به دقیقه از «الان»** هستند.
- **ترتیب پرسنل:** `least_busy` یعنی مجموع زمان اشغال آن روز، بعد priority، بعد id.
- **«سقف روزانه»** در فهرست T1.4 نبود و به Policy `booking_window` (T2.5) منتقل شد.
- **ساختن ورودی‌ها** از Catalog و DB (intersect با ساعات شعبه و فیلتر شعبه) کار T1.5 است.

**Review:** subagent `reviewer` چهار مورد پیدا کرد و هر چهار رفع شد:
1. `LocalDay` روی بازه‌ای که از گپ بهار DST عبور می‌کند exception می‌داد (بازه برعکس می‌شد). حالا زمان داخل گپ همان لحظه جابه‌جایی است.
2. یک پرسنل می‌توانست دو جلسه گروهی پلکانی هم‌پوشان داشته باشد. حالا فقط پیوستن به همان جلسه مجاز است.
3. ظرفیت منبع صندلی شمرده می‌شد و با تعریف Catalog («نوبت همزمان») نمی‌خواند. در نتیجه کلاس گروهی در سالن با ظرفیت 1 هیچ‌وقت پر نمی‌شد. حالا جلسه‌ها شمرده می‌شوند.
4. `seatsLeft` محدودیت منبع را نادیده می‌گرفت. با مدل بند 3، منبع صندلی را محدود نمی‌کند و این مستند شد.

**تأیید:**
- `composer check` ← lint و PHPStan بدون خطا، deptrac با 0 violation، `OK (580 tests, 12492 assertions)`.
- `composer test:rename` ← «OK. The renamed copy passed composer check and the JS checks». exit code غیرصفر فقط از هشدارهای rmdir هنگام پاک‌کردن کپی موقت در ویندوز است.
- **Mutation:** چهار تغییر عمدی در calculator (مقایسه ظرفیت، ترتیب sweep، و دو مسیر پیوستن به جلسه) را تست تصادفی گرفت (seedهای 107، 4، 184 و 37).
- **بنچمارک:** حدود 3.5 تا 5ms محلی.
- CI: این Task فقط Unit دارد. push در `/wrap` انجام می‌شود.

**قدم بعدی:** T1.5 — Availability API + Cache.
**Commitها:** `feat(scheduling): availability calculator (T1.4)`

---

## 2026-09-26 — سشن 10 — T1.3 Scheduling + تعطیلات
**Taskها:** T1.3
**انجام شد:**
- **کلاس‌های مشترک:** `Name` و `Slug` از Catalog به `Shared\Domain` منتقل شدند و `Row` به `Kernel\Database`، چون Scheduling هم از آن‌ها استفاده می‌کند (قاعده دو مصرف‌کننده).
- **ماژول `Scheduling`:**
  - Domain: `Owner` (پرسنل، منبع یا شعبه)، `ScheduleRule` (روز هفته 0 = شنبه، کار یا استراحت)، `ScheduleException` (off، extra و blocked، برای کل روز یا یک بازه)، `Holiday`، و `DayPlan::of()` برای دقیقه‌های کاری و مسدود یک مالک در یک روز محلی. سه interface Repository.
  - Infrastructure: migration `CreateSchedulingTables` (`schedule_rules`، `schedule_exceptions` و `holidays`)، `HolidayDataset` (JSON با تاریخ شمسی «MM-DD»)، migration `ImportHolidays(1405)` و سه Repository از نوع Wpdb.
  - `SchedulingModule` در فایل اصلی ثبت شد.
- **دیتاست:** `assets/holidays/1405.json` با 26 تعطیلی رسمی.
- **تست‌ها:**
  - Unit: `EntitiesTest`، `DayPlanTest` و `HolidayDatasetTest` (شامل دیتاست واقعی 1405 و 12 فایل خراب).
  - Integration: `SchedulingPersistenceTest` (InnoDB و کلید یکتا، جایگزینی برنامه هفتگی، استثناها، upsert تعطیلی، و import دوباره بدون هیچ تغییری).
- **اسناد:** README ماژول، data-model (یادداشت T1.3)، implementation-notes §4.5.

**تصمیم‌ها و فرض‌ها:**
- **منطق `DayPlan`:** `working = (کار هفتگی − استراحت، و در تعطیلی هیچ) ∪ ساعات اضافه − مرخصی`. ساعات اضافه انتخاب صریح است، پس تعطیلی و استراحت هفتگی روی آن اثر ندارند. زمان مسدود جدا می‌ماند و در Availability جزو Busy است. شیفت بعد از نیمه‌شب دو قاعده است.
- **برنامه هفتگی یک‌جا جایگزین می‌شود.** مثل فرم Admin، قاعده تکی update یا delete ندارد.
- **Application و REST ساخته نشد.** هنوز مصرف‌کننده‌ای ندارند و با UI در T3.2 (برنامه و مرخصی پرسنل) و T3.5 (تعطیلات) ساخته می‌شوند. Repositoryها برای T1.5 و تست Integration لازم بودند.
- **`resource_day_locks` به T2.2 منتقل شد**، همراه `ResourceLocker` که تنها مصرف‌کننده آن است.
- **فهرست تقویم‌ها (option) ساخته نشد.** فعلاً فقط `ir` وجود دارد و تقویم سفارشی با UI در T3.5 می‌آید. ستون `holidays.calendar_id` به `calendar` (Slug) تغییر نام داد.
- **منبع 1405:** تقویم رسمی ژئوفیزیک، به نقل از bahesab.ir و snn.ir (تاسوعا و عاشورا 3 و 4 تیر). chetor.com چند تعطیل قمری را یک روز دیرتر نوشته بود و کنار گذاشته شد. تاریخ قمری پیش‌بینی است و مدیر آن را اصلاح می‌کند. import روزی را که از قبل ثبت شده نگه می‌دارد.

**Review:** subagent `reviewer` مشکل مسدودکننده‌ای پیدا نکرد. سه مورد کم‌اهمیت بود و هر سه رفع شد:
1. سال داخل فایل دیتاست با سال migration مقایسه نمی‌شد. فایل 1406 که از 1405 کپی شود ولی `year` آن عوض نشود، بی‌صدا تاریخ‌های 1405 را وارد می‌کرد. حالا `parse($json, $year)` آن را رد می‌کند.
2. comment مربوط به `replace` می‌گفت DELETE دو ذخیره همزمان را پشت سر هم اجرا می‌کند. وقتی مالک ردیفی ندارد این درست نیست: فقط gap lock گرفته می‌شود و deadlock رخ می‌دهد (که `Transaction` دوباره اجرا می‌کند). comment اصلاح شد.
3. تست، اجرای دوباره خود migration را نمی‌پوشاند. حالا دو بار اجرا می‌شود و مقایسه می‌شود که هیچ ردیفی، حتی timestamp، عوض نشده باشد.

**تأیید:**
- `composer check` ← lint و PHPStan بدون خطا، deptrac با 0 violation، `OK (546 tests, 12142 assertions)`. `composer test:rename` ← OK.
- CI run 36220833030 روی شاخه wip ← Integration قرمز. activation با `DbException` (errno 0) در `ImportHolidays` شکست خورد. علت: وقتی جدول هم ستون ascii و هم utf8mb4 دارد، `wpdb::query()` charset جدول را ascii فرض می‌کند و SQL خام با عنوان فارسی را رد می‌کند. همه ستون‌های `holidays` utf8mb4 شدند (implementation-notes §4.5).
- CI run 36221089129 ← هر 11 job سبز، Integration `114 tests, 357 assertions` در 4 ترکیب.

**مشکلات و باقیمانده:** تا پایان اسفند 1405 باید دیتاست 1406 اضافه شود. شاخه `wip/t1-3-scheduling` روی remote مانده است.
**قدم بعدی:** T1.4 — AvailabilityCalculator.
**Commitها:** `feat(scheduling): schedules, exceptions, holidays and the 1405 dataset (T1.3)`

---

## 2026-09-26 — سشن 9 — T1.2 Catalog REST CRUD + CatalogApi
**Taskها:** T1.2 (و رفع CI قرمز T1.1)
**انجام شد:**
- **رفع CI T1.1:** push `3ec41cd` در Integration قرمز شد (run 36186077348). علت در تست بود، نه migration: helper `column()` همه مقدارها را lowercase می‌کند، پس `IS_NULLABLE` باید `yes` باشد (`9f967c0`).
- **Kernel و Shared:** `Db::getResults()`. `Shared\Domain\NotFound` (404) و `Forbidden` (401/403) در Router. port `Shared\Domain\Authorizer` با پیاده‌سازی `Shared\WpAuthorizer`.
- **Catalog:**
  - Domain: 6 interface Repository.
  - Application: `CatalogService` (24 Use Case)، `CatalogReader implements CatalogApi` و `Page`.
  - Contracts: `CatalogApi`، `Offer`، `StaffOffer`، `ResourceNeed`، `ResourceUnit` و `LocationInfo`.
  - Infrastructure: `CatalogTable`، `Row` و 6 Repository از نوع Wpdb.
  - Presentation: `CrudRoutes`، `CatalogRoutes`، `Input`، `Fields` و 6 کلاس `*Json`.
  - capability `manage_catalog` برای administrator.
- **تست‌ها:**
  - Unit: `CatalogServiceTest` (هر Use Case بدون capability، NotFound، ارجاع‌ها، Variant خدمت دیگر)، `CatalogReaderTest`، `PersistenceTest` (`searchName` و `Row`) و `getResults` در `DbTest`.
  - Integration: `CatalogRestTest` (401، 403 روی هر 30 route، چرخه کامل شعبه، Pagination و هدرها، 400 در برابر 422، پرسنل و `search_name`، `location_in_use`، ذخیره کامل خدمت با حفظ id Variantها، ذخیره دوباره بعد از حذف دسته و پرسنل، حذف Extraها با خدمت)، `CatalogApiTest`، و NotFound و Forbidden در `RouterTest`.
- **اسناد:**
  - `docs/api.md` (جدید)، نوع‌های TS در `api-types.ts` و README ماژول.
  - implementation-notes §4.4.

**تصمیم‌ها و فرض‌ها:**
- **PUT جایگزینی کامل است.** PATCH نداریم. فیلدی که فرستاده نشود مقدار پیش‌فرضش را می‌گیرد.
- **حذف آبشاری نیست، ولی خواندن ارجاع به آیتم حذف‌شده را کنار می‌گذارد.** خدمت بدون دسته حذف‌شده و بدون پرسنل حذف‌شده خوانده می‌شود. دو استثنا:
  - حذف خدمت Extraهایش را هم حذف می‌کند.
  - شعبه‌ای که پرسنل یا منبع حذف‌نشده دارد حذف نمی‌شود (`location_in_use`، 422).
- **`CatalogApi` فقط آیتم فعال برمی‌گرداند.** پرسنل و منبعِ شعبه غیرفعال هم کنار گذاشته می‌شوند. Capability بررسی نمی‌شود.
- **فقط یک capability** (`manage_catalog`)، و **نقش Manager ساخته نشد:** یک capability به‌تنهایی نقش جدا را توجیه نمی‌کند. نقش‌ها با capabilityهای رزرو در M2 و M3 ساخته می‌شوند.
- **در `CatalogApi` هنوز Extra نیامده.** با ورودی AvailabilityCalculator در T1.4 اضافه می‌شود.
- **کار روی `main` بعد از تأیید CI:** Integration محلی اجرا نمی‌شود. پس کار اول روی شاخه `wip/t1-2-catalog-rest` push شد و CI با `workflow_dispatch` روی آن اجرا شد. بعد در یک commit روی `main` squash شد.

**Review:** subagent `reviewer` چهار مورد واقعی پیدا کرد و هر چهار رفع شد:
1. بعد از حذف دسته یا پرسنل، خدمت با همان body خوانده‌شده ذخیره نمی‌شد (422). حالا خواندن ارجاع حذف‌شده را کنار می‌گذارد.
2. Extraهای خدمت حذف‌شده به همین شکل گیر می‌کردند. حالا با خدمت حذف می‌شوند.
3. شعبه غیرفعال در `CatalogApi` نادیده گرفته می‌شد. حالا خودش، پرسنلش و منابعش کنار گذاشته می‌شوند.
4. ذخیره همزمان یک خدمت می‌توانست به Variant حذف‌شده تخصیص بنویسد، یا خدمت حذف‌شده را نیمه‌کاره ذخیره کند و 500 بدهد. حالا ردیف خدمت `FOR UPDATE` قفل می‌شود و بررسی‌ها زیر قفل تکرار می‌شوند.

یک مورد را خودم پیدا کردم: در PUT، `id` داخل body بر `id` در URL مقدم بود. حالا id فقط از URL خوانده می‌شود.

**تأیید:**
- `composer check` ← lint و PHPStan بدون خطا، deptrac با 0 violation، `OK (516 tests, 12079 assertions)`. `pnpm lint` سبز.
- CI run 36188584486 روی شاخه wip ← Integration قرمز (6 شکست، همه از یک علت: `default: []` روی `variants` که وردپرس آن را هم validate می‌کند و با `minItems` رد می‌شد).
- CI run 36189399823 روی شاخه wip، بعد از رفع خطا و یافته‌های Review ← هر 11 job سبز، Integration `108 tests, 337 assertions` در 4 ترکیب.

**مشکلات و باقیمانده:** `test:rename` جدا اجرا نشد، ولی job `rename` در CI سبز است.
**قدم بعدی:** T1.3 (Scheduling Domain و Migration، تعطیلات 1405).
**Commitها:** `9f967c0` test(catalog): compare IS_NULLABLE lowercased like the other columns؛ `feat(catalog): repositories, admin rest crud and catalog api (T1.2)`
---

## 2026-09-25 — سشن 8 — T1.1 Catalog Domain + Migration
**Taskها:** T1.1
**انجام شد:**
- **Domain** (`src/Modules/Catalog/Domain`): `Location`، `Staff`، `BookableResource`، `ServiceCategory`، `Extra`، و Aggregate `Service` با `Variant`، `ServiceStaff` و `ResourceRequirement`. VOها: `Name`، `Color`، `Slug`، و enum `Status`. `Terms` خروجی `Service::terms()` است. trait `GuardsStoredNumbers` idها و `sort` را بررسی می‌کند.
- **Migration:** `CreateCatalogTables` با 9 جدول، ثبت‌شده در `CatalogModule` و فایل اصلی.
- **تست‌ها:** 72 تست Unit برای Catalog (invariantها، VOها و منطق override). Integration: `CreateCatalogTablesTest` (InnoDB بودن هر 9 جدول، اجرای دوباره، نوع ستون‌ها، ایندکس یکتا، قیمت بزرگ‌تر از 2^31).
- **اسناد:** implementation-notes §4.3، یادداشت انحراف در data-model §2، نام‌گذاری migration در data-model §3 و docblock `Migration`، و `src/Modules/Catalog/README.md`.

**تصمیم‌ها و فرض‌ها:**
- **ستون `approval` از `services` حذف شد:** تأیید دستی یک Policy است (`policies`)، و دو منبع حقیقت نباید داشته باشیم.
- **ستون‌های `meta` ساخته نشدند:** هنوز مصرف‌کننده‌ای ندارند (principles §0).
- **`holiday_calendar` به‌جای `holiday_calendar_id`:** مقدارش Slug است، چون تقویم‌ها در option هستند.
- **معنای `ServiceStaff`:** ردیف بدون Variant یعنی همه Variantها، و ردیف با Variant یعنی فقط همان. override فیلد به فیلد است: ردیف Variant، ردیف سراسری، خود Variant. **با بیش از یک Variant، ردیف سراسری قیمت یا مدت ندارد** (`override_needs_variant`).
- **Timezone شعبه:** فقط نام‌های `DateTimeZone::listIdentifiers()`. offset ثابت، `Etc/*` و aliasها رد می‌شوند.
- **سقف‌ها:** مدت، Buffer و گام تا 1440 دقیقه. ظرفیت تا 1000. تعداد تا 100. sort بین 0 و 1,000,000.
- **`BookableResource` به‌جای `Resource`:** `resource` در PHP کلمه soft-reserved است.
- **هنوز ساخته نشدند:** Repository، REST، capabilityها، `CatalogApi` و پر کردن `search_name`. همه در T1.2.
- **نام migration بدون شماره است** (`CreateCatalogTables`)، مثل Kernel. سند (M001_…) با کد هماهنگ شد.

**Review:** subagent `reviewer` چهار مورد واقعی پیدا کرد و هر چهار رفع شد:
1. idها و `sort` بررسی نمی‌شدند، و MySQL بدون strict آن‌ها را clamp می‌کرد.
2. override سراسری مدت و قیمت همه Variantها را یکی می‌کرد.
3. `ALL_WITH_BC` مقدارهای `Etc/GMT-3` و `EST` را می‌پذیرفت.
4. ایندکس `avatar_id` و `image_id` نبود.

دو مورد جزئی هم رفع شد: ناهماهنگی نام migration در سند، و تست Integration ضعیف.

**تأیید:**
- `composer check` ← lint و PHPStan بدون خطا، deptrac با 0 violation، `OK (455 tests, 12011 assertions)`.
- `composer test:rename` ← «OK. The renamed copy passed composer check and the JS checks».
- پوشش Catalog Domain حدود 98% است. تنها خط پوشش‌نیافته `LogicException` غیرقابل‌دسترس در `defaultVariant()` است.
- **Integration محلی اجرا نشد** (Docker نداریم) و بعد از push روی CI بررسی می‌شود.

**مشکلات و باقیمانده:** یکی از اجراهای `test:rename` با exit 255 تمام شد. علت پاک‌کردن پوشه موقت روی Windows بود (`unlink` روی symlink پوشه‌های pnpm)، نه شکست بررسی‌ها. اجرای دوباره پیام OK داد.
**قدم بعدی:** push و دیدن CI، سپس T1.2 (Repositoryها، REST CRUD و `CatalogApi`).
**Commitها:** `feat(catalog): domain entities and catalog tables (T1.1)`
---

## 2026-09-25 — سشن 7 (ادامه) — CI برای T0.11 و پایان M0
**Taskها:** T0.11
**انجام شد:** push `abab1d6`. اولین run (36176335757) فقط در 4 job Integration قرمز شد، و علتش در کد نبود: `actions/setup-node@v5` با دیدن `packageManager` در `package.json` خودکار cache pnpm را روشن می‌کند، ولی در job Integration pnpm نصب نیست. با `package-manager-cache: false` رفع شد (`6dab2b0`). تله در implementation-notes §7.1 ثبت شد.
**تأیید:** run 36176578809 روی `6dab2b0` ← هر 11 job سبز:
- jobهای جدید `test-js` و `build`.
- `rename` با بررسی‌های JS، روی CI با `CI=true`.
- Integration روی {8.1، 8.4} × {6.6، latest} ← `OK (56 tests, 179 assertions)`، که 4 تست `AdminPageTest` را هم شامل می‌شود.
- رفع `MigratorTest` (flaky) هم در همین run پاس شد.
**M0 تمام شد (11/11).**
**قدم بعدی:** T1.1 — Catalog Domain + Migration.
**Commitها:** `fix(ci): no automatic pnpm cache in the integration job`، `docs: close T0.11 and M0 in the tracker`
---

## 2026-09-25 — سشن 7 (ادامه) — T0.11 JS workspace
**Taskها:** T0.11
**انجام شد:**
- **Workspace و ابزارها:** `package.json` و `pnpm-workspace.yaml`، `webpack.config.js` (entryهای admin و widget)، `babel.config.js` (JSX ویجت برای Preact)، `eslint.config.cjs`، `stylelint.config.cjs`، tsconfig برای هر پکیج، `vitest.config.mjs` (یک project برای هر پکیج)، `.size-limit.json`.
- **`packages/shared`:** `ApiClient` و `ApiError`، `api-types`، `jalali` (با jalaali-js)، `money`، `digits`، `identity`.
- **`packages/admin`:** `App` و hash router.
- **`packages/widget`:** `Widget`، `mount` و `readConfig`، و mount خودکار.
- **PHP:** `Modules\Admin\AdminModule` و `Presentation\AdminPage` (اولین ماژول)، ثبت‌شده در `vaqtyar.php`.
- **Rename:** `Shell::pnpm()`، و `rename.php` حالا `pnpm install` و `pnpm format` را اجرا می‌کند. `test-rename.php` بررسی‌های JS را روی کپی rename‌شده هم اجرا می‌کند.
- **CI:** jobهای `test-js` و `build`، و Node و pnpm در job `rename`.
- **تست‌ها:** Vitest برای api-client، jalali، money و digits، shell Admin با React واقعی در jsdom، و mount ویجت با Preact (38 تست). Integration: `AdminPageTest` (4 تست).
- **اسناد:** implementation-notes §7.1.

**تصمیم‌ها و فرض‌ها:**
- **wp-scripts 35، نه 36:** نسخه 36 به Node 24.15 یا بالاتر نیاز دارد.
- **TypeScript 5.9، نه 7.**
- **React 18 در تست‌ها:** WP 6.6 هم React 18 دارد.
- **`ApiClient` خودمان به‌جای `@wordpress/api-fetch`:** یک کلاینت مشترک برای Admin و ویجت، که ویجت در Front بارگذاری‌اش نمی‌کند.
- **هنوز ساخته نشدند:** TanStack Query، کانتکست API در Admin، و config از PHP به JS، چون هنوز مصرف‌کننده‌ای ندارند (principles §0). با اولین صفحه داده‌دار (T1.2 و T3.x) می‌آیند.
- **capability جدید:** `access_admin` برای منوی Admin، به administrator داده می‌شود.
- **job `build`:** فقط build و size. zip و Plugin Check به Task انتشار موکول شدند.
- **`--no-frozen-lockfile`:** rename به‌جای `--lockfile-only` که roadmap گفته بود، `pnpm install --no-frozen-lockfile` و `pnpm format` را اجرا می‌کند (implementation-notes §7.1).
- **`Widget` پارامتر `config` را هنوز نمی‌خواند:** API `mount(el, config)` طبق principles §5 است. یک `eslint-disable` با دلیل دارد.

**Review:**
- اجرای اول subagent `reviewer` با محدودیت API قطع شد و دوباره اجرا شد.
- رفع شد: job `rename` در CI شکست می‌خورد، چون pnpm با `CI=true` قفل را frozen می‌کند. بازتولید شد و با `CI=true` تأیید شد که حالا پاس می‌شود.
- رفع شد: `navigate()` بی‌مصرف حذف شد.
- مستند شد: `wp-i18n` و `wp-hooks` در بودجه ویجت حساب نمی‌شوند.
- مستند شد: nonce مهمان در `ApiClient`.
- پیشگیرانه اضافه شد: `require` فایل `wp-admin/includes/plugin.php` در `AdminPageTest`.

**تأیید:**
- `pnpm lint` ← ESLint، Stylelint و tsc پاک. قوانین Stylelint با فایل عمداً خراب امتحان شدند و هر چهار را گرفتند.
- `pnpm test` ← 38 passed.
- `pnpm build` ← widget.js 11.9KB (4.99KB gz)، admin.js 1.67KB.
- `pnpm size` ← زیر بودجه.
- `composer check` ← OK (383 unit).
- `composer test:rename` ← OK، با بررسی‌های JS، و با `CI=true`.
- actionlint ← OK.
- **Integration و jobهای جدید CI هنوز اجرا نشده‌اند.**

**قدم بعدی:** push و بررسی CI. سپس T1.1.
**Commitها:** `feat(admin): js workspace with admin shell and widget mount (T0.11)`
---

## 2026-09-25 — سشن 7 (ادامه) — CI برای T0.9 و تست flaky
**Taskها:** T0.9، T0.7
**انجام شد:** push `84764c3` به درخواست کاربر. run قبلی (36149811656، commit `f54f8c8` که فقط سند بود) در Integration (PHP 8.4، WP 6.6) قرمز شده بود: `MigratorTest::testDoesNotRunWhileAnotherConnectionHoldsTheLock`. علت یک race در خود تست است: `mysqli::close()` قبل از پایان session در سرور برمی‌گردد و `GET_LOCK(…, 0)` قفل را هنوز گرفته می‌بیند. تست حالا قفل را قبل از close با `RELEASE_LOCK` آزاد می‌کند. تله در implementation-notes §5 ثبت شد.
**تأیید:** run 36152711861 روی `84764c3` ← هر 9 job سبز. Integration روی {8.1، 8.4} × {6.6، latest} ← `OK (52 tests, 164 assertions)`، یعنی هر 9 تست جدید T0.9 از اولین اجرا پاس شدند. Unit روی 8.1 و 8.4 ← 383 تست OK. رفع تست flaky با push بعدی در CI دیده می‌شود.
**قدم بعدی:** T0.11.
**Commitها:** `test(kernel): release the migration lock explicitly in the lock test`
---

## 2026-09-25 — سشن 7 — T0.9 Settings، SecretStore، Logger، Caps
**Taskها:** T0.9
**انجام شد:**
- `src/Kernel/Settings/`: `SettingsGroup` (interface)، `Settings` (get/save، یک option برای هر گروه)، `GeneralSettings` (تقویم و ارقام).
- `src/Kernel/SecretStore.php`: ثابت wp-config با اولویت، رمزنگاری AEAD با نام option به‌عنوان AD، و لاگ خطا وقتی مقدار قابل رمزگشایی نیست.
- `src/Kernel/Log/`: `Logger`، `LogLevel`، `Pii`، و `CreateLogsTable` (دومین migration با owner `kernel`).
- `src/Kernel/Capabilities.php` و `Module::capabilities()`.
- `Plugin`: ثبت `Logger`، `Settings`، `SecretStore` و `DateFormatter` در Container، و اجرای grant در `activate()` و در `boot()` بعد از migration.
- `Router`: خطای 500 با `Logger` ثبت می‌شود. `Db::inTransaction()`.
- تست‌ها:
  - Unit: `PiiTest`، `LoggerTest`، `SettingsTest`، `SecretStoreTest`، `CapabilitiesTest`، `PluginTest`، و fixture `LargeSettings`.
  - Integration: `LoggerTest`، `SettingsTest`، `SecretStoreTest`، `CapabilitiesTest`، و به‌روزرسانی `RouterTest` (خطای 500 حالا در جدول `logs` بررسی می‌شود) و `PaginationTest`.
- اسناد: implementation-notes §6.1 (جدید)، §5 و §6، و data-model (`logs`).

**تصمیم‌ها و فرض‌ها:**
- **`SettingsGroup` یک interface است، با یک پیاده‌سازی واقعی.** دلیل: مرز هر ماژول برای تنظیمات خودش است (Catalog، Booking، Payments، Notifications، White-label). principles §0 را بازبینی کردم. بدون آن، `Settings::get()` نمی‌تواند typed باشد.
- **کلید SecretStore از `wp_salt('auth')` مشتق می‌شود، نه مستقیم از `AUTH_KEY`.** این هم AUTH_KEY را دارد (ADR-014) و هم سایتی را که AUTH_KEY تعریف نکرده پوشش می‌دهد.
- **خرابی Migration همچنان به `error_log` می‌رود، نه Logger** (برخلاف یادداشت T0.7). دلیل: DB همان چیزی است که شکسته و notice مدیر را به لاگ PHP می‌فرستد.
- **Retention لاگ:** 30 روز (ثابت) و prune بعد از هر نوشتن، مثل `RateLimiter`. Job جدا یا تنظیمات برای آن ساخته نشد (principles §0).
- **ماسک PII در Logger با الگو است:** ایمیل و رشته‌های 8 رقمی یا بیشتر. نام و آدرس تشخیص داده نمی‌شوند و این در سند آمده است. timestamp یونیکس هم ماسک می‌شود.
- **نقش‌های خود افزونه** (Manager، Receptionist، Staff) ساخته نشدند، چون هنوز هیچ capability واقعی وجود ندارد. با M1 تا M3 ساخته می‌شوند. Kernel خودش capability ندارد.
- `SecretStore::mask()` به توصیه reviewer حذف شد و با صفحه تنظیمات secretها می‌آید.

**Review:** subagent `reviewer` هفت مورد پیدا کرد، هیچ‌کدام blocker نبود:
- رفع شد: خط fallback در `error_log` context را نداشت. برای 500 در REST این یعنی از دست رفتن کلاس، فایل و خط.
- رفع شد: ثابت عددی wp-config (terminal id) بی‌صدا null می‌شد.
- رفع شد: کلیدهای آرایه context ماسک نمی‌شدند.
- رفع شد: شماره داخل پرانتز، مثل `(0912) 123 4567`، ماسک نمی‌شد.
- رفع شد: prune داخل تراکنش رزرو قفل ردیف می‌گرفت. حالا با `Db::inTransaction()` رد می‌شود.
- مستند شد: `GeneralSettings` تا اولین ذخیره یک کوئری در هر درخواست دارد (Onboarding در T6.1 آن را ذخیره می‌کند).
- حذف شد: `SecretStore::mask()` بدون مصرف‌کننده.

**تأیید:**
- `composer check` ← exit 0:
  - phpcs: پاک
  - PHPStan: No errors
  - deptrac: 0 violation در هر دو فایل. هر 25 مورد uncovered داخل stubهای وردپرس است.
  - PHPUnit: OK (383 tests, 11926 assertions)
- `composer test:rename` ← OK.
- **Integration محلی اجرا نشد** (Docker محلی نداریم). 9 تست جدید و تغییر `RouterTest` فقط در CI بررسی می‌شوند.

**مشکلات و باقیمانده:** نتیجه CI دیده نشده است.
**قدم بعدی:** push و بررسی CI. سپس T0.11.
**Commitها:** `feat(kernel): settings, secret store, logger and capabilities (T0.9)`
---

## 2026-09-25 — سشن 6 (ادامه) — CI برای T0.8
**Taskها:** T0.8
**انجام شد:** push `6513c8e` و بستن T0.8 در Tracker.
**تأیید:** run 36149430470 ← هر 9 job سبز شدند. Integration روی {8.1، 8.4} × {6.6، latest} ← `OK (43 tests, 149 assertions)`، یعنی هر 24 تست جدید (Router، Pagination، RateLimiter روی MySQL واقعی) از اولین اجرا پاس شدند.
**قدم بعدی:** T0.9.
**Commitها:** `docs: close T0.8 in the tracker`
---

## 2026-09-25 — سشن 6 — T0.8 REST base
**Taskها:** T0.8
**انجام شد:**
- `src/Kernel/Rest/`:
  - `Router`: ثبت route زیر `Identity::REST_NAMESPACE`، مرز خطا، Envelope و `request_id`. `Router::ANYONE` فقط روی GET و همراه rate limit پذیرفته می‌شود.
  - `ApiError` و `RateLimit`.
  - `RateLimiter`: fixed window در جدول `rate_limits`، با یک upsert اتمی و `LAST_INSERT_ID(expr)`.
  - `ClientIp`: `REMOTE_ADDR`، فیلتر `rest/client_ip`، IPv6 با /64.
  - `Pagination`.
  - `CreateRateLimitsTable`.
- `Kernel\RequestId`.
- `Plugin`: migrationهای خود Kernel با owner `kernel` (`Plugin::KERNEL_ID`، در `ModuleRegistry` رزرو شده) و ثبت `Clock`، `RequestId`، `RateLimiter` و `Router` در Container. `Db::lastInsertId()`.
- تست‌ها:
  - Unit: `RateLimitTest`، `ClientIpTest`، `RateLimiterTest`، و به‌روزرسانی `PluginTest` و `ModuleRegistryTest`. `FakesWpdb` حالا `%i` و `get_charset_collate` را می‌شناسد. `tests/Fixtures/FixedClock`.
  - Integration: `RouterTest` (نمونه، 401، 403، 422، 404 با details، 500 بدون نشت متن و PII، 429 با `Retry-After`، شمارش جدا برای هر client و هر route، رد route عمومی ناامن)، `PaginationTest`، `RateLimiterTest` (MySQL واقعی، prune).
- اسناد: implementation-notes §6 (Router، RateLimiter، ClientIp، Pagination، جداول Kernel، تست REST، تله PHPStan) و data-model (`rate_limits`). در phpcs، sniff `CamelCapsMethodName` برای `tests/Integration` خاموش شد (`set_up` در WP_UnitTestCase).

**تصمیم‌ها و فرض‌ها:**
- **«Controller پایه» ← `Router` (composition)** به‌جای کلاس abstract. Controllerها کلاس `final` ساده‌اند و وابستگی‌هایشان را از constructor خودشان می‌گیرند.
- خطای schema در `args` همان 400 `rest_invalid_param` وردپرس می‌ماند. 422 برای قاعده دامنه (`InvalidValue`) است.
- Rate limit بعد از permission و داخل callback شمرده می‌شود (فقط آنجا هدر `Retry-After` ممکن است). endpointی که باید تلاش رد‌شده را بشمارد (OTP) خودش `RateLimiter` را صدا می‌زند.
- `RateLimiter` از `Transaction` استفاده نمی‌کند (`START TRANSACTION` تراکنش تست WP را commit می‌کند) و Retry برای deadlock ندارد (چرخه قفل ممکن نیست، implementation-notes §6).
- سایت پشت CDN باید فیلتر `rest/client_ip` را پیاده کند. گزینه تنظیمات برای آن تصمیم باز است.

**Review:** subagent `reviewer` شش مورد پیدا کرد و هر شش بررسی شد:
- رفع شد: متن Exception در `error_log` (نشت PII) حذف شد.
- رفع شد: `SELECT LAST_INSERT_ID()` جدا (با drop-inهای HyperDB یا LudicrousDB fail-open می‌شد) با `Db::lastInsertId()` جایگزین شد.
- رفع شد: فیلتر خراب IP دیگر 500 نمی‌دهد.
- رفع شد: `page` سقف گرفت (سرریز offset).
- رفع شد: `WP_Error` برگشتی از callback هم Envelope می‌گیرد.
- نگه داشته شد و مستند شد: شمرده‌نشدن درخواست‌هایی که permission ردشان کرده.

**تأیید:** `composer check` ← lint و stan (No errors)، deptrac (0 violation در هر دو فایل)، unit (322 tests OK). `composer test:rename` ← OK. **Integration فقط در CI اجرا می‌شود** (نتیجه در ورودی بعدی).
**مشکلات و باقیمانده:** —
**قدم بعدی:** بررسی CI، سپس T0.9.
**Commitها:** `feat(kernel): rest router, error envelope, rate limiter and pagination (T0.8)`
---

## 2026-09-25 — سشن 5 (ادامه) — اولین CI واقعی و رفع آن
**Taskها:** T0.10، T0.7
**انجام شد:** کاربر `gh auth login` زد. اولین اجرای CI (run 36144097562) در سه job قرمز بود و هر سه رفع شد:
- **PHPStan، و به همین دلیل rename:** `str_replace(\range('0', '9'), …)` در `DateFormatter` (از T0.6). `range` عدد int برمی‌گرداند و با یک لیست لیترال جایگزین شد. PHPStan محلی روی PHP 8.3 این را نمی‌گیرد، حتی با `phpVersion: 80100` و بدون cache (امتحان شد). CI روی 8.1 آن را گرفت.
- **Integration PHP 8.4 + WP 6.6:** هر 19 تست پاس شدند، ولی `mysqli_ping()` در مسیر reconnect خود core، روی PHP 8.4 deprecation چاپ کرد. تست risky شد و `failOnRisky` job را شکست داد. در همان یک تست `E_DEPRECATED` خاموش شد.

**تأیید:** run 36145578720 روی `0888e32` ← هر 9 job سبز: quality، unit روی 8.1 و 8.4، legacy-syntax روی 7.0، rename، integration روی {8.1، 8.4} × {6.6، latest}. پس تست‌های Integration مربوط به T0.7 (lock wait واقعی، KILL، MyISAM، GET_LOCK و cache) روی MySQL واقعی پاس شدند. `composer check` محلی ← OK (300 tests).
**مشکلات و باقیمانده:** GitHub اعلام کرده برچسب `ubuntu-latest` از 2026-10-19 به Ubuntu 26 منتقل می‌شود. فعلاً اقدامی لازم نیست.
**قدم بعدی:** T0.8.
**Commitها:** `0888e32 fix(ci): string digits in DateFormatter, core deprecation in reconnect test`
---

## 2026-09-25 — سشن 5 (ادامه) — push و بستن T0.10 و T0.7
**Taskها:** T0.10، T0.7
**انجام شد:** commitهای `9ddb825` (T0.10) و `0ce43dc` (T0.7) به `origin/main` push شدند (`4c93fc7..0ce43dc`).
**تصمیم‌ها و فرض‌ها:** به درخواست کاربر هر دو Task ✅ شدند، **بدون دیدن نتیجه CI**. `gh` لاگین نیست و repo خصوصی است.
**تأیید:** فقط موفقیت push دیده شد. هیچ‌کدام از jobهای CI (integration، unit روی 8.4 و legacy-syntax) از اینجا دیده نشده‌اند.
**مشکلات و باقیمانده:** اگر CI قرمز باشد، رفعش قبل از T0.8 انجام می‌شود. محتمل‌ترین نقاط در ورودی قبلی آمده‌اند.
**قدم بعدی:** T0.8.
**Commitها:** —
---

## 2026-09-25 — سشن 5 — T0.7 Db، Transaction، Migrator
**Taskها:** T0.7
**انجام شد:**
- `src/Kernel/Database/`:
  - `Db`: wrapper نهایی روی `wpdb`. شامل `execute`، `getVar`، `insert`، `update`، `createTable`، و `begin`/`commit`/`rollBack`.
  - `DbException`: همراه با `errno`، `detail` و `isRetryable()`.
  - `Transaction::run()`: Retry روی 1213 و 1205، حداکثر 3 بار.
  - interface `Migration`.
  - `Migrator`: شامل `isCurrent` و `migrate`.
- `Module::migrations()`.
- `Plugin::activate()`. `Plugin::boot()` قبل از boot ماژول‌ها migrate می‌کند و `Db` و `Transaction` را به‌صورت singleton در Container ثبت می‌کند.
- `vaqtyar.php`: لیست مشترک ماژول‌ها (`$vaqtyar_modules`) و `register_activation_hook` در سطح بالای فایل. هر دو سازگار با PHP 7.0 هستند.
- تست‌ها:
  - Unit: `FakesWpdb` (Mockery روی `wpdb`)، `DbTest`، `TransactionTest`، `MigratorTest`، و گسترش `PluginTest`.
  - Integration: `RealDatabase`، `DbTest`، `TransactionTest` (lock wait واقعی با connection دوم، و KILL connection)، `MigratorTest` (DDL واقعی، InnoDB، MyISAM، GET_LOCK، cache)، و `ActivationTest`.
- اسناد: implementation-notes §1 و §5 (بخش‌های جدید Db، Transaction، Migrator و تله‌ها)، data-model §3، architecture §5 (استثنای Migration در boot)، و docblock `Tables`.

**تصمیم‌ها و فرض‌ها:**
- **`literal-string` + `%i`:** PHPStan برای `wpdb::prepare()` رشته literal می‌خواهد. به‌جای ignore، همین قید به `Db::execute/getVar` منتقل شد و نام جدول با `%i` (WP 6.2+) وارد می‌شود. DDL قابل prepare نیست، پس `CREATE TABLE` و بررسی InnoDB داخل `Db::createTable()` است. در نتیجه `Migration` یک interface ساده شد، نه کلاس پایه.
- نسخه هر ماژول = تعداد migrationهای اجراشده (لیست فقط اضافه‌شدنی).
- ADR-004 «تا 3 بار Retry» به معنای 4 تلاش پیاده شد.
- `GET_LOCK` بدون انتظار (timeout 0). درخواستی که قفل را نگرفت با schema فعلی ادامه می‌دهد.
- در فعال‌سازی شبکه‌ای، فقط سایت جاری migrate می‌شود. بقیه سایت‌ها در اولین درخواست خودشان migrate می‌شوند.
- `getRow`/`getResults` ساخته نشدند، چون مصرف‌کننده‌ای ندارند (principles §0). با اولین Repository اضافه می‌شوند.
- migration Kernel (`logs`، `rate_limits`) با T0.8 و T0.9 می‌آید. الان هیچ migration واقعی وجود ندارد و `$vaqtyar_modules` خالی است.

**تأیید:**
- `composer check` ← exit 0:
  - phpcs: پاک
  - PHPStan: No errors
  - deptrac: 0 violation (uncovered جدید `Db` ← `wpdb`، هم‌نوع مورد `Requirements`)
  - PHPUnit: OK (300 tests, 11775 assertions)
- `composer test:rename` ← OK.
- Mutation دستی:
  - `MAX_RETRIES = 2` ← `TransactionTest` شکست خورد.
  - حذف خواندن دوباره زیر قفل و حذف پاک‌کردن cache ← `MigratorTest` شکست خورد.
- **Integration محلی اجرا نشد** (Docker یا MySQL نداریم) و CI هنوز دیده نشده.
- Subagent `reviewer` سه مورد واقعی پیدا کرد و هر سه رفع شد:
  1. خواندن دوباره نسخه زیر قفل از cache `alloptions` می‌آمد و عملاً بی‌اثر بود. حالا قبل از خواندن، cache پاک می‌شود. تست Unit با cache شبیه‌سازی‌شده و تست Integration اضافه شد.
  2. Migration ناموفق در boot کل سایت را از کار می‌انداخت. حالا گرفته می‌شود و این کارها انجام می‌شود: `error_log`، admin notice، و boot نشدن ماژول‌ها. در activation همچنان Exception پرتاب می‌شود.
  3. reconnect بی‌صدای wpdb روی 2006 وسط تراکنش. حالا بررسی `thread_id` انجام می‌شود و `connectionLost` بدون Retry پرتاب می‌شود. ریسک باقیمانده برای T2.2 در implementation-notes §5 ثبت شد.
  - reviewer هم WP core را روی این ماشین نداشت و فرض‌های wpdb را از روی دانسته‌هایش بررسی کرد.

**مشکلات و باقیمانده:**
- T0.10 و T0.7 تا دیده شدن CI روی 🟨 می‌مانند.
- فرض‌هایی که فقط CI تأیید می‌کند:
  - خطای KILL در mysqlnd (2006 یا 2013)
  - `wpdb::__get('dbh')`
  - رفتار `process_fields` روی مقدار بلند
  - `SET autocommit = 1` بعد از `WP_UnitTestCase`

**قدم بعدی:** push و دیدن CI (کاربر: `gh auth login`). بعد T0.8.
**Commitها:** `feat(kernel): db wrapper, transaction and migrator (T0.7)`

---

## 2026-09-25 — سشن 4 — T0.10 wp-env + CI
**Taskها:** T0.10
**انجام شد:**
- `.github/workflows/ci.yml` با این jobها:
  - `quality`: lint، stan و deptrac
  - `unit`: روی PHP 8.1 و 8.4
  - `legacy-syntax`: `php -l` روی PHP 7.0 برای `vaqtyar.php`، `uninstall.php` و `Requirements.php`
  - `rename`: `composer test:rename`
  - `integration`: روی wp-env، با ماتریس PHP {8.1، 8.4} × WP {6.6، latest}
- `phpunit-integration.xml.dist`، `tests/Integration/bootstrap.php`، `tests/Integration/wp-tests-config.php` (prefix جدا به نام `wptests_`) و `BootstrapTest`. این تست Requirements را روی MySQL واقعی و بارگذاری Action Scheduler از فایل اصلی بررسی می‌کند.
- اسکریپت `composer test:integration`.
- `php-stubs/wordpress-tests-stubs` (dev) برای PHPStan.
- `.wp-env.json`: `env.tests` منسوخ حذف و `"testsEnvironment": false` اضافه شد.
- اسناد:
  - implementation-notes §2.2 (جدید)
  - dev-environment §5
  - roadmap: job `concurrency` به T2.2 و jobهای `test-js` و `build` به T0.11 منتقل شدند

**تصمیم‌ها و فرض‌ها:**
- کتابخانه تست WP از خود wp-env می‌آید (`$WP_TESTS_DIR`، هم‌نسخه با core). `wp-phpunit/wp-phpunit` نصب نشد، چون نسخه‌اش در lock ثابت است و با WP 6.6 ماتریس نمی‌خواند.
- jobهای `concurrency`، `test-js` و `build` ساخته نشدند، چون محتوایی ندارند (principles §0). job خالی فقط سبز دروغین می‌دهد.
- wp-env بدون `package.json` با `npx @wordpress/env@11` روی major 11 ثابت شد. در T0.11 به devDependency منتقل می‌شود.
- Actionها فقط `actions/checkout`، `actions/setup-node` و `shivammathur/setup-php` هستند. برای composer از action شخص ثالث استفاده نشد.
- در config Integration، deprecation به Exception تبدیل نمی‌شود، چون core خودش deprecation دارد. suite Unit روی 8.4 کد ما را پوشش می‌دهد.

**تأیید:**
- `composer check` ← exit 0:
  - phpcs: پاک
  - PHPStan: No errors
  - deptrac: 0 violation (1 uncovered که از قبل بود: `Requirements` ← `wpdb`)
  - PHPUnit: OK (256 tests, 11686 assertions)
- `composer test:rename` ← OK (فایل‌های جدید هم در کپی بودند، چون untracked هم کپی می‌شود).
- `actionlint` روی workflow ← پاک.
- **Integration محلی اجرا نشد** (Docker نداریم). **نتیجه CI هنوز دیده نشده.** repo خصوصی است و `gh` لاگین نیست.
- Subagent `reviewer`: همه فرض‌های wp-env را با سورس 11.16.0 و کتابخانه تست WP (6.6.9 و trunk) بررسی کرد. دو مورد پیدا کرد:
  - `testsEnvironment` بدون کلید هنوز روشن است (برخلاف README). اصلاح شد.
  - خط‌شکستگی در `phpcs.xml`. اصلاح شد.

**مشکلات و باقیمانده:**
- تا وقتی CI دیده نشود، T0.10 روی 🟨 می‌ماند.
- unit روی PHP 8.4 هرگز اجرا نشده است (محلی 8.3 داریم).

**قدم بعدی:** دیدن نتیجه CI (کاربر یا `gh auth login`)، رفع اگر لازم بود، ✅ کردن T0.10، سپس T0.7.
**Commitها:** `ci(kernel): wp-env integration suite and github actions (T0.10)`

## 2026-09-24 — سشن 3 — T0.6 Jalali + DateFormatter
**Taskها:** T0.6
**انجام شد:**
- `src/Shared/Domain/Jalali.php` (خالص): `fromGregorian`، `toGregorian`، `isLeapYear`، `daysInMonth`. الگوریتم port شده Borkowski/jalaali-js است و از سال 1 تا 3176 پشتیبانی می‌کند.
- `src/Shared/DateFormatter.php`: `date`، `longDate`، `time`، `dateTime` و `digits`. تقویم با enum `Calendar` و ارقام با enum `Digits` انتخاب می‌شوند.
  - نام ماه‌های شمسی با `_x(…, 'Jalali month', …)` ترجمه‌پذیرند.
  - تاریخ بلند میلادی از `wp_date('j F Y')` می‌آید.
- `ext-intl` به require-dev اضافه شد (lock: فقط hash و `platform-dev` تغییر کرد).
- اسناد: implementation-notes §4.2 (جدید)، دستور phpdbg با `memory_limit=-1`.

**تصمیم‌ها و فرض‌ها:**
- Jalali در `Shared\Domain` است (PHP خالص)، چون Domain، مثلاً نمای ماه Availability، ممکن است به مرز ماه شمسی نیاز داشته باشد. نام ماه و قالب‌بندی در `Shared` است، چون i18n و `wp_date` لازم دارد.
- **تا وقتی فایل fa_IR ساخته نشده (T6.3)، نام ماه شمسی به‌صورت انگلیسی آوانگاری‌شده (Mehr) نمایش داده می‌شود.**
- تاریخ عددی با ارقام فارسی همیشه `/` دارد، به خاطر bidi. کنار هم آمدن تاریخ و زمان در صفحه LTR کار UI است و در متن کاراکتر نامرئی نمی‌گذاریم.
- ICU از سال 1634 به بعد با الگوریتم ما متفاوت است. ICU مرجع درستی در آن سال‌ها نیست.

**تأیید:**
- ابتدا تست‌ها قرمز بودند. آزمون ICU اشتباه port را گرفت: در شاخه قبل از نوروز، leap سال قبل به‌جای سال اولیه استفاده شده بود. بعد از اصلاح سبز شدند.
- `composer check` ← exit 0:
  - phpcs: پاک
  - PHPStan: No errors
  - deptrac: 0 violation
  - PHPUnit: OK (256 tests, 11686 assertions)
- آزمون ICU 72: همه روزهای 1300 تا 1500 در هر دو جهت و وضعیت کبیسه همه سال‌ها، بدون اختلاف.
- آزمون پیوستگی سال‌های 1 تا 3176: شروع هر سال، طول 365 یا 366، و round trip.
- پوشش با phpdbg: `Jalali` برابر 71 از 71 خط، `DateFormatter` برابر 28 از 29 خط. خط باقیمانده شاخه `false` در `wp_date` است.
- `composer test:rename` ← OK.
- Subagent `reviewer`: در سال‌های واقعی باگ درستی پیدا نکرد و کل بازه 1 تا 3177 را مستقل بررسی کرد. 6 مورد دیگر هم اصلاح شد:
  - قرارداد مرز سال 3177
  - آزمون شاخه‌های اصلاحی
  - bidi در تاریخ میلادی با ارقام فارسی
  - ارجاع §7 که باید §9 باشد
  - ادعای بیش از حد دقت در docblock
  - skip بی‌صدای آزمون ICU (با `ext-intl` حل شد)

**مشکلات و باقیمانده:** reviewer لیست رسمی سال‌های کبیسه ایران را از حافظه با خروجی ما مطابقت داد. منبع رسمی در repo نیست.
**قدم بعدی:** T0.10 (wp-env + CI)، قبل از T0.7.
**Commitها:** `feat(shared): jalali calendar and date formatter (T0.6)`

## 2026-09-24 — سشن 3 — T0.5 Value Objectهای Shared
**Taskها:** T0.5
**انجام شد:**
- `src/Shared/Domain/` شامل این موارد است:
  - `InvalidValue` (با `errorCode`)
  - `Money` + `Currency` (فقط IRR) + `Rounding` (`Down`، `Up`، `HalfUp`)
  - `PhoneNumber` (E.164)
  - `Email`
  - `LocalDate`، `LocalTime` (با `24:00`) و `TimeRange` (نیمه‌باز، UTC، ثانیه کامل)
  - `IntervalSet` (union، subtract، intersect و covers با ادغام خطی)
  - `Ulid` (ساخت خالص با `fromParts`)
  - `Clock` (interface)
- `src/Shared/SystemClock.php`.
- `tools/Rename/Shell::run()` حالا خروجی زیرفرایند را از pipe می‌خواند و relay می‌کند. این همان مشکل «پیام گم‌شده» در T0.4 بود.
- اسناد:
  - implementation-notes §4.1 (جدید)
  - dev-environment: پوشش با `phpdbg`، تله `python -`، تله خروجی زیرفرایند

**تصمیم‌ها و فرض‌ها:**
- **پوشش با `phpdbg` محلی اندازه‌گیری می‌شود.** نصب pcov یا xdebug لازم نیست.
- `Currency` فقط IRR دارد (ADR-010). بررسی ارز با `@phpstan-ignore` حفظ شد. reviewer تأیید کرد که با اضافه‌شدن ارز دوم، این ignoreها خطای «unmatched» می‌دهند.
- شماره بدون کد کشور ایرانی فرض می‌شود و کشور دیگر فقط با `+` یا `00` پذیرفته می‌شود. فرم «+98 (0) 912…» پذیرفته می‌شود.
- `IdGenerator` Port با اولین مصرف‌کننده (Appointment، M2) ساخته می‌شود (§0). `Ulid::milliseconds()` به‌خاطر نداشتن مصرف‌کننده حذف شد.
- بنچمارک Availability (بودجه 50ms) مربوط به T1.4 است و از تست IntervalSet حذف شد، چون تست نباید به زمان واقعی وابسته باشد.

**تأیید:**
- ابتدا تست‌ها قرمز بودند، سپس سبز شدند.
- `composer check` ← exit 0:
  - phpcs: پاک
  - PHPStan: No errors
  - deptrac: 0 violation
  - PHPUnit: OK (227 tests, 1904 assertions)
- تست تصادفی IntervalSet: 300 seed در مقایسه با مدل bitmap. reviewer هم 20,000 seed با اعداد منفی را بدون هیچ اختلافی اجرا کرد.
- پوشش با `phpdbg`: `Shared\Domain` برابر 184 از 185 خط (99.5%). خط باقیمانده بررسی ارز است که با یک ارز قابل اجرا نیست.
- ULID با نمونه رسمی spec (`01ARZ3NDEKTSV4RRFFQ69G5FAV`) مقایسه شد، که بخش‌هایش جداگانه با Python decode شده بودند.
- `composer test:rename` ← OK. rename کپی سالم ماند (227 tests) و خروجی حالا کامل و مرتب است.
- Subagent `reviewer`:
  - **1 باگ واقعی:** شماره‌های `+980…` که یک رقم کم داشتند پذیرفته می‌شدند و ممکن بود شماره‌ای نامعتبر در `customers.phone` (UNIQUE) ذخیره شود. اصلاح شد و 4 تست اضافه شد.
  - 4 مورد جزئی: بنچمارک وابسته به زمان، نام تست‌ها، متد بدون مصرف‌کننده، و یادداشت کهنه در tracker. همه اصلاح شدند.

**مشکلات و باقیمانده:** —
**قدم بعدی:** T0.6 (Jalali + DateFormatter).
**Commitها:** `feat(shared): value objects, interval set and clock (T0.5)`

## 2026-09-24 — سشن 3 — T0.4 Helperهای نام + rename.php
**Taskها:** T0.4
**انجام شد:**
- `src/Kernel/`:
  - `Tables::name()`: پیشوند سایت در هر فراخوانی از `$wpdb` خوانده می‌شود (multisite). سقف 64 کاراکتر.
  - `Options::key()`: سقف 191 کاراکتر.
  - `Hooks::name()`: `{hook_prefix}/{module}/{event}`.
  - `Caps::name()`.
  - هر ورودی با `[a-z][a-z0-9_]*` (و modifier `D`) اعتبارسنجی می‌شود و در غیر این صورت `KernelException` می‌دهد.
- `tools/Rename/` (namespace `Vaqtyar\Tools\Rename` در autoload-dev):
  - `RenameSpec`: اعتبارسنجی و مشتق‌کردن شناسه‌ها از slug.
  - `Renamer`: plan و apply.
  - `RenamePlan`، `RenameCommand`، `Shell` (git و composer بدون shell)، `RenameException`.
- CLI: `tools/rename.php` با `--dry-run`.
- `tools/test-rename.php` و `composer test:rename`: کپی، `git init` و commit، `composer install`، rename، و `composer check` روی کپی.
- phpcs و PHPStan حالا `tools/` را هم پوشش می‌دهند. EscapeOutput برای `tools/` exclude شد، چون CLI است و در بسته نهایی قرار نمی‌گیرد.
- تست‌ها:
  - `NamesTest`: 43 مورد با dataProvider.
  - `RenameSpecTest`.
  - `RenamerTest`: روی یک افزونه جعلی با توکن‌های ساختگی `AcmeBook` و `ZetaTool`، تا بعد از rename واقعی repo هم معنی‌دار بماند.
- اسناد: implementation-notes §2.1 (جدید)، جزئیات اجرا در ADR-000، dev-environment §5، roadmap (T0.10: `test:rename` در CI؛ T0.11: lock file در JS).

**تصمیم‌ها و فرض‌ها:**
- **`const_prefix`، `hook_prefix`، `text_domain` و `rest_namespace` از slug مشتق می‌شوند** و slug فقط `a-z0-9` است. این تصمیم ADR-000 را عوض نمی‌کند، فقط قید به آن اضافه می‌کند، و در ADR-000 به‌عنوان «جزئیات اجرا» ثبت شد.
- جایگزینی فقط توکن کامل را عوض می‌کند. هر اثری از توکن قدیمی که عوض نشود، rename را **قبل از نوشتن** رد می‌کند.
- اجرای واقعی فقط روی working tree تمیز مجاز است. lock fileها، `docs/`، `.claude/` و `CLAUDE.md` دست نمی‌خورند.
- Helperها static هستند، چون ADR-000 همین شکل را تعیین کرده است. state ندارند.

**تأیید:**
- `composer check` ← exit 0:
  - phpcs: پاک
  - PHPStan level 9 (حالا با `tools/`): No errors
  - deptrac: هر دو config با 0 violation
  - PHPUnit: OK (116 tests, 219 assertions)
- `composer test:rename` ← OK. کپی به `renamecheck` rename شد (38 ویرایش و یک جابه‌جایی)، اثری از توکن قدیمی نماند و `composer check` روی کپی سبز شد (116 tests).
- CLI روی repo واقعی:
  - dry-run با نام جدید ← 38 ویرایش، بدون هیچ تغییری روی دیسک.
  - اجرا روی tree کثیف ← رد شد.
  - بدون `--name` ← رد شد، با فهرست باقیمانده‌ها.
  - slug نادرست (`nobat-yar`) ← رد شد.
- Subagent `reviewer`: 7 یافته، همه بازتولید و اصلاح شدند:
  - بدون `--name`، rename بعد از نوشتن شکست می‌خورد.
  - شکست وسط `apply()` به «class not found» می‌رسید و tree ناسازگار می‌ماند.
  - `$1` در نام نمایشی خروجی را خراب می‌کرد.
  - hash در lock file با پیشوند کوتاه برخورد می‌کرد.
  - بررسی تداخل، نام قدیمی را در کل فایل‌ها پاک می‌کرد.
  - regex اعتبارسنجی newline انتهایی را می‌پذیرفت (`$` بدون `D`).
  - نام سه تست رفتار را توصیف نمی‌کرد.

**مشکلات و باقیمانده:**
- در اولین اجرای ناموفق `test:rename`، پیام خطای rename در خروجی دیده نشد. نه من توانستم بازتولیدش کنم و نه reviewer. آزمایش مستقیم نشان می‌دهد stdout و stderr زیرفرایند منتقل می‌شوند.
- معیار «lint و test سبز می‌مانند» برآورده شد. deptrac و PHPStan هم روی کپی سبز بودند.

**قدم بعدی:** T0.5 (Shared Value Objects + IntervalSet).
**Commitها:** `feat(kernel): naming helpers and rename tool (T0.4)`

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
