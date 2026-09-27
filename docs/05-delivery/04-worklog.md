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
