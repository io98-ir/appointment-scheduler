# Hookها و نقاط توسعه

> برای توسعه‌دهنده‌ای که به افزونه وصل می‌شود: چه چیزی را می‌شود شنید، چه چیزی را می‌شود جایگزین کرد، و چه قراردادی باید رعایت شود. REST در [api.md](api.md) است.
> نام‌ها با **پیشوند** ساخته می‌شوند (`Hooks::name()`، ADR-000). پیش‌فرض `vaqtyar` است، پس `payments/succeeded` یعنی `vaqtyar/payments/succeeded`. بعد از `tools/rename.php` پیشوند عوض می‌شود.

## رویدادها (action)
این‌ها بعد از COMMIT تراکنش فراخوانی می‌شوند، پس داده‌ای که می‌خوانید نهایی است.

| Action | آرگومان‌ها | وقتی |
|---|---|---|
| `payments/succeeded` | `int $appointmentId`، `int $paymentId` | پرداختی تأیید شد (فقط یک بار برای هر پرداخت) |
| `payments/refunded` | `int $appointmentId`، `int $refundId` | استرداد ثبت شد |
| `booking/needs_attention` | `int $appointmentId`، `int $paymentId` | پول آمد ولی نوبت را نشد «پرداخت‌شده» کرد (مثلاً زمانش آزاد شده بود). یک نفر باید نگاه کند؛ اینجا اعلان به مدیر وصل می‌شود |
| `customers/otp` | `string $phone` (E.164)، `string $code` | کد ورود ساخته شد. **کد فقط همین‌جا می‌آید**، نه در لاگ و نه در پاسخ. افزونه خودش با پیامک ارسالش می‌کند؛ برای کانال دیگر (پیام‌رسان) همین را بشنوید. کد را ذخیره یا لاگ نکنید |
| `booking/changed`، `catalog/changed`، `scheduling/changed` | — | چیزی عوض شد که جواب «چه زمان‌هایی آزاد است» را تغییر می‌دهد (برای باطل‌کردن cache دیگران) |

### رویدادهای ناهمگام برای یکپارچه‌سازی
این سه با Action Scheduler و **داخل همان تراکنش** نوبت ثبت می‌شوند (ADR-005)، پس هیچ نوبتی بدون رویدادش نیست و هیچ رویدادی بدون نوبت:

| Action | آرگومان | |
|---|---|---|
| `booking/appointment_booked` | `int $appointmentId` | نوبت تأیید شد: همان لحظه ثبت، یا وقتی پرداخت آنلاینش رسید. نوبتی که هنوز منتظر پرداخت است اعلان نمی‌شود |
| `booking/appointment_cancelled` | `int $appointmentId` | مشتری یا پرسنل لغوش کرد (انقضای پرداخت‌نشده رویداد ندارد، چون هیچ‌وقت «booked» نشده بود) |
| `booking/appointment_rescheduled` | `int $appointmentId` | زمانش عوض شد |

- تحویل **حداقل یک‌بار** است: handler باید idempotent باشد. وضعیت امروز نوبت را از `GET /appointments/{id}` یا Repository بخوانید، نه از فرض درباره آنچه باعث رویداد شد.
- دیر هم می‌رسند (بسته به صف). کار سنگین را در handler نکنید؛ خودتان صف کنید.

## فیلترها

| Filter | مقدار | کاربرد |
|---|---|---|
| `rest/client_ip` | `string $remote` ← `string` | آدرس مشتری برای rate limit. **پشت CDN یا پروکسی لازم است**، وگرنه همه بازدیدکننده‌ها یک سطل مشترک دارند. آدرس را از هدری بدهید که همان پروکسی می‌گذارد و به هدر دست‌کاری‌شده توسط کلاینت اعتماد نکنید. خروجی نامعتبر به «ناشناس» تبدیل می‌شود |
| `payments/gateways` | `list<PaymentGateway>` ← همان | درگاه تازه اضافه کنید یا درگاهی را بردارید |
| `notifications/channels` | `list<NotificationChannel>` ← همان | کانال تازه (پیام‌رسان و …) |

### قرارداد درگاه (`Vaqtyar\Modules\Payments\Application\PaymentGateway`)
- `id(): string`: یکتا، فقط `a-z` و `_`؛ با هر پرداخت ذخیره می‌شود.
- `start(int $appointmentId, Money $amount, string $callbackUrl): StartedAttempt`: پرداخت را نزد درگاه باز کن. مبلغ **ریال** است؛ تبدیل به واحد درگاه با شما. اگر درگاه الان نمی‌تواند `GatewayException` بدهید تا درگاه بعدی امتحان شود.
- `callbackAuthority(array $params): ?string`: کدام پارامتر بازگشت، پرداخت را مشخص می‌کند.
- `verify(string $authority, Money $amount, array $params): Verification`: از درگاه بپرسید پرداخت انجام شد و **مبلغ همان است**. چند بار صدا زده می‌شود و جوابش نباید عوض شود. پارامترهای برگشتی را هرگز باور نکنید؛ فقط متن‌اند. درگاه در دسترس نبود `GatewayException` (پرداخت منتظر می‌ماند).

### قرارداد کانال (`Vaqtyar\Modules\Notifications\Application\NotificationChannel`)
- `id(): string`: همان کلمه‌ای که `channel` قالب نگه می‌دارد.
- `address(): string`: `email` یا `phone`؛ گیرنده به همین شکل می‌آید (موبایل E.164).
- `send(Message $message): string`: متن ساده (بدون markup) است؛ مرجع پیام نزد سرویس را برگردانید یا رشته خالی. شکست: `DeliveryFailed`. کانال ثبت‌نشده یا شکست‌خورده باعث گم‌شدن نوبت نمی‌شود؛ در لاگ اعلان‌ها می‌ماند.

## Capabilityها
نام واقعی با پیشوند دادهٔ جدول می‌آید (`vqy_manage_bookings`). پیش‌فرض همه فقط به `administrator` داده می‌شوند و نقش دیگر را با هر افزونه مدیریت نقش می‌توان داد؛ اگر مدیر capabilityای را از نقشی گرفت، دوباره داده نمی‌شود.

| Capability | برای |
|---|---|
| `access_admin` | باز کردن برنامه مدیریت، برند و ویزارد |
| `manage_bookings` | نوبت‌ها، تقویم، گزارش، تأیید پرداخت آفلاین، ثبت استرداد |
| `override_policies` | لغو یا جابه‌جایی برخلاف سیاست |
| `manage_catalog` | شعبه، پرسنل، منبع، خدمت، دسته، افزودنی |
| `manage_schedules` | ساعت کاری، مرخصی، تعطیلات |
| `manage_customers` | مشتریان |
| `manage_notifications` | قالب‌ها، لاگ و تنظیم پیامک |
| `manage_payments` | کلید درگاه‌ها |
| `manage_system` | System status، ماژول‌های اختیاری و **حذف داده هنگام حذف افزونه** |

## چیزهایی که API نیستند
جاب‌های داخلی (`booking/expire_unpaid`، `booking/purge_holds`، `payments/reconcile`، `notifications/remind`) و نام جدول‌ها و optionها ثابت نیستند و ممکن است در نسخه‌های بعد عوض شوند؛ از آن‌ها استفاده نکنید. دسترسی به داده فقط با REST ([api.md](api.md)) یا hookهای بالا.
