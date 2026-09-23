# معماری: موتور رزرو

> قلب محصول است. همه این بخش در **Domain خالص** نوشته می‌شود و تست Unit و تست تصادفی (randomized با seed ثابت) دارد.

## 1. نیازمندی منابع (نسخه 1.0)
هر Variant تعریف می‌کند:
- **Staff:** یک نفر از لیست پرسنل مجاز خدمت. مشتری می‌تواند انتخاب کند یا بگذارد «فرقی نمی‌کند».
- **Resources (اختیاری):** صفر یا چند گروه منبع، که از هر گروه 1 عدد لازم است (مثلاً «یکی از اتاق‌های 1 تا 3»).
- **Buffer:** قبل و بعد از نوبت، که روی همه منابع درگیر اعمال می‌شود.
- **Capacity:** پیش‌فرض 1. عدد بزرگ‌تر یعنی رزرو گروهی (چند مشتری در یک زمان با یک پرسنل).

**هر پرسنل یا منبع فقط یک تقویم اشغال دارد**، مستقل از خدمت، فرم یا شعبه. این همان مشکل اصلی نوبت‌پلاس است که حل می‌شود.

> بعد از 1.0: نقش‌های چندگانه با offset (دستیار فقط 30 دقیقه اول)، زنجیره خدمات. مدل داده (`service_requirements`) طوری طراحی شده که این‌ها بدون Migration شکننده اضافه شوند.

## 2. الگوریتم Availability
**ورودی:** Variant، بازه تاریخ، Location، پرسنل انتخابی (اختیاری)، Extraها، تعداد نفرات.
**خروجی:** `Slot { start, end, staff_ids[], remaining_capacity }`

برای هر روز محلی:
1. **Working** هر کاندید = برنامه هفتگی + ساعات اضافه − مرخصی − تعطیلات − استراحت.
2. **Busy** = نوبت‌های فعال (با Buffer) + Holdهای منقضی‌نشده + زمان‌های مسدود. همه با **یک کوئری Batch** برای کل بازه و همه کاندیدها خوانده می‌شوند.
   - برای `capacity > 1`، اشغال شمرده می‌شود (sweep line).
3. **Free** = Working − Busy، با کلاس `IntervalSet` (عملیات union، subtract و intersect).
4. **کاندیدهای شروع:** با گام `slot_step` (از تنظیم Variant یا تنظیم سراسری).
5. **Fit:** یک پرسنل آزاد + از هر گروه منبع یک منبع آزاد، برای بازه `[start − buffer_before, start + duration + extras + buffer_after)`.
6. **قوانین:** `min_notice`، `max_advance`، سقف روزانه.
7. **انتخاب پرسنل** وقتی مشتری انتخاب نکرده: `least_busy` (پیش‌فرض) یا `priority`. تخصیص قطعی در لحظه Hold انجام می‌شود.

**نماها:**
- «اولین نوبت خالی»: روز به روز جلو می‌رود، تا حداکثر N روز.
- «ماه»: فقط وضعیت روز را برمی‌گرداند (دارد، پر، تعطیل).
- «روز»: اسلات‌های همان روز.

**بودجه کارایی:** 10 پرسنل × 1 روز در کمتر از 50ms (بنچمارک در تست).

## 3. Hold و Confirm
```
POST /holds {variant, start, staff?, extras, party_size}
  BEGIN
    INSERT IGNORE resource_day_locks برای (هر منبع درگیر × روز)
    SELECT … FROM resource_day_locks WHERE … ORDER BY resource_id FOR UPDATE
    بررسی مجدد تداخل از DB (appointments فعال + holds منقضی‌نشده)
    INSERT holds + hold_resources  (expires_at = now + TTL، پیش‌فرض 10 دقیقه)
  COMMIT
  → {hold_token, expires_at, price_quote, deposit_due}

POST /bookings {hold_token, customer, answers, payment_method}
  BEGIN
    SELECT hold FOR UPDATE → معتبر و منقضی‌نشده؟
    ایجاد Appointment (confirmed | pending_payment | pending_approval)
    انتقال ردیف‌های hold_resources به appointment_resources، حذف Hold
    ثبت Jobهای اعلان (Action Scheduler، همان تراکنش)
  COMMIT
  → appointment + (در صورت پرداخت آنلاین) payment_url
```
- هنگام رفتن به درگاه، Hold تمدید می‌شود (تا سقف 20 دقیقه).
- **درستی به Job پاک‌سازی وابسته نیست:** کوئری Busy همیشه شرط `expires_at > now` را دارد.
- **حالت لبه‌ای:** اگر پرداخت بعد از انقضای Hold موفق شود و اسلات گرفته شده باشد، نوبت در وضعیت `needs_attention` قرار می‌گیرد، به مدیر اعلان می‌رود و گزینه استرداد یا جابجایی پیشنهاد می‌شود. این سناریو تست دارد.
- **منشی** از همین مسیر رزرو می‌کند (Hold و Confirm در یک درخواست). اجازه Override تداخل فقط با Capability مخصوص است و ثبت می‌شود.

## 4. ماشین وضعیت نوبت
```
pending_approval ─approve─► confirmed
pending_payment ──paid────► confirmed   (یا pending_approval اگر تأیید دستی لازم باشد)
pending_payment ──timeout─► expired
confirmed ──complete─► completed
confirmed ──no_show──► no_show
confirmed | pending_* ──cancel──► cancelled
confirmed ──reschedule──► (همان رکورد، زمان جدید + ثبت در history)
```
- گذار فقط از طریق متدهای Entity (`confirm()`, `cancel()`, `reschedule()`) انجام می‌شود. `setStatus` وجود ندارد.
- **وضعیت پرداخت جداست:** `unpaid`، `deposit_paid`، `paid`، `refunded`، `partially_refunded`.
- **Reschedule** همان رکورد را به‌روز می‌کند: قفل جدید گرفته می‌شود، `appointment_resources` جایگزین می‌شود و یک ردیف در `appointment_history` ثبت می‌شود. این از ساختن رکورد جدید ساده‌تر است.
- **وضعیت سفارشی** (مثلاً «در انتظار مدارک») فقط یک **برچسب رنگی** است، نه وضعیت جدید در ماشین.

## 5. قیمت
در لحظه Hold محاسبه و در نوبت **Snapshot** می‌شود، پس تغییر تعرفه روی نوبت‌های قبلی اثر ندارد.

`PriceCalculator` مراحل زیر را به ترتیب اجرا می‌کند:
1. قیمت Variant
2. قیمت اختصاصی پرسنل (اگر تعریف شده باشد)
3. Price Ruleهای زمانی (ساعت، روز هفته، تاریخ)
4. Extraها
5. ضرب در تعداد نفرات
6. کوپن
7. گرد کردن

- هر مرحله یک `PriceRule` است (interface با متد `apply(PriceContext, PriceQuote): PriceQuote`). Add-onها می‌توانند Rule اضافه کنند.
- خروجی شامل `lines[]` است تا ریز قیمت به مشتری و منشی نشان داده شود.
- `Money` یک عدد صحیح ریال است و درصدها با گرد کردن صریح محاسبه می‌شوند.

## 6. Policy
هر Policy یک تنظیم ساده JSON است و در سطح سراسری یا خدمت تعریف می‌شود. تنظیم خدمت بر تنظیم سراسری مقدم است.

| Policy | نمونه |
|---|---|
| `cancellation` | مشتری تا 24 ساعت قبل لغو کند. استرداد پلکانی: بیش از 48 ساعت ← 100%، بین 24 تا 48 ساعت ← 50% |
| `reschedule` | تا 12 ساعت قبل، حداکثر 2 بار |
| `deposit` | `none` / `percent:30` / `fixed:500000` / `full` |
| `approval` | همه نوبت‌ها / فقط مشتری جدید / هیچ |
| `booking_window` | min_notice، max_advance، حداکثر نوبت فعال هر مشتری |

- `PolicyEvaluator` خروجی `Decision { allowed, refund_amount, reason_code }` دارد. UI همین را نشان می‌دهد، مثلاً «مبلغ قابل استرداد: 350,000 تومان».
- پرسنل با Capability مخصوص می‌تواند Policy را Override کند. دلیل ثبت می‌شود.

## 7. پرداخت
```
payments: pending ─redirect─► awaiting_callback ─verify ok─► succeeded
                                          └─ verify fail / timeout ─► failed
```
- هر تلاش یک ردیف در `payments` دارد (gateway، amount، authority یکتا، ref_id).
- **Verify idempotent:** callback تکراری همان نتیجه را برمی‌گرداند.
- **تطبیق:** یک Job هر 5 دقیقه پرداخت‌هایی را که بیش از 15 دقیقه در `awaiting_callback` مانده‌اند با API استعلام درگاه بررسی می‌کند.
- **Failover:** اگر درخواست روی درگاه اول خطا بدهد، درگاه فعال بعدی امتحان می‌شود.
- **استرداد:** ثبت در `refunds` (دستی یا از طریق API درگاه، اگر پشتیبانی کند). وضعیت پرداخت نوبت از جمع `payments` و `refunds` مشتق می‌شود.
- **ووکامرس:** یک Gateway است که سفارش WC می‌سازد و نتیجه را از hook وضعیت سفارش می‌گیرد. منبع حقیقت جدول `payments` ماست.

## 8. اعلان و یادآوری
- **Template** = trigger (رویداد یا زمان نسبی مثل «24 ساعت قبل») × گیرنده (مشتری، پرسنل، مدیر) × کانال (sms، email).
  - برای SMS، کد پترن و نگاشت متغیرها برای هر Provider تعریف می‌شود.
- یادآوری‌ها هنگام confirm به‌صورت Job زمان‌بندی می‌شوند و با cancel یا reschedule لغو و از نو ساخته می‌شوند. **قبل از ارسال، وضعیت فعلی نوبت دوباره چک می‌شود.**
- **ساعات سکوت:** مثلاً از 22 تا 8 هیچ پیامکی نمی‌رود و ارسال عقب می‌افتد.
- **Dedup:** با کلید `(appointment, trigger, channel, recipient)`.
- **Failover پیامک:** اگر Provider اول خطا بدهد، دومی استفاده می‌شود.
- **لاگ:** وضعیت ارسال و خطا برای هر پیام ثبت می‌شود.

## 9. طرح اولیه قابلیت‌های بعد از 1.0 (بدون پیاده‌سازی)
| قابلیت | روی چه چیزی سوار می‌شود |
|---|---|
| رزرو تکرارشونده | RRule ساده ← تولید occurrenceها ← Hold گروهی «همه یا هیچ» ← `series_id` |
| لیست انتظار | ثبت ترجیحات ← رویداد `SlotReleased` ← Hold اختصاصی برای نفر اول با مهلت |
| پکیج | اعتبار (credit) هر خدمت ← یک PriceRule که قیمت را صفر می‌کند |
| زنجیره یا چندنقشی | گسترش `service_requirements` با `offset` و `sequence` |
