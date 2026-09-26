# معماری: ساختار کلی سیستم

## 0. اصل حاکم: ساده، ولی با مرزهای درست
دو خطر داریم:
- **کد اسپاگتی:** مشکل Bookly و نوبت‌پلاس، که قابلیت‌هایش با هم ترکیب نمی‌شوند.
- **Overengineering:** Boilerplate زیاد، انتزاع بی‌مصرف و سرعت توسعه پایین.

راه میانه این است:
- **مرزها سخت‌گیرانه:** ماژول‌ها و لایه‌ها با Deptrac کنترل می‌شوند.
- **درون مرزها ساده:**
  - Service class به‌جای Command Bus
  - آرایه‌های تایپ‌شده یا DTO فقط در مرز API
  - انتزاع فقط وقتی که حداقل دو پیاده‌سازی واقعی یا یک مرز خارجی (درگاه، پیامک، دیتابیس) وجود داشته باشد

## 1. سبک
- **Modular Monolith**: هر ماژول مسئولیت، جداول و API خودش را دارد و قابل خاموش‌کردن است (به‌جز هسته).
- **لایه‌بندی Hexagonal سبک** در هر ماژول. **Domain خالص PHP** است تا منطق رزرو بدون وردپرس تست شود.
- **Read/Write جدا فقط جایی که لازم است.** نوشتن از Entity و Repository عبور می‌کند. لیست‌ها و گزارش‌ها کلاس Query با SQL مستقیم دارند (بدون ساختن Entity).

## 2. لایه‌ها و قانون وابستگی
```
Presentation   REST Controllers · Admin page · Block/Shortcode · WP-CLI · Hook listeners
     │ فراخوانی
Application    Services (Use Caseها به‌صورت متد) · Portها (interface) · Query interfaces
     │ استفاده
Domain         Entities · Value Objects · Domain Services (AvailabilityCalculator, PriceCalculator,
               PolicyEvaluator) · Repository interfaces · Domain Events · Exceptions
     ▲ پیاده‌سازی
Infrastructure Wpdb Repositories/Queries · Gateway/SMS Adapters · Action Scheduler jobs · Cache
```
**قوانین (Deptrac در CI):**
1. `Domain` فقط به `Shared\Domain` وابسته است. هیچ تابع WP و هیچ `$wpdb`.
2. `Application` فقط به `Domain` و Portها وابسته است.
3. توابع WP فقط در `Infrastructure` و `Presentation` مجازند.
4. ماژول‌ها به داخل همدیگر دسترسی ندارند. فقط `Contracts/` (interface + DTO) یا Domain Event.
5. کلاس‌های `Shared` هیچ منطق تجاری ندارند.

## 3. ماژول‌ها (نسخه 1.0)
| ماژول | مسئولیت | وابسته به |
|---|---|---|
| **Kernel + Shared** | Bootstrap، Container، Identity، DB و تراکنش، Migration، REST base، Settings، Logger، Clock، Value Objectها، Jalali | — |
| **Catalog** | Location، Staff، Resource، Service، Variant، Extra، قیمت و مدت اختصاصی پرسنل | Shared |
| **Scheduling** | Schedule، Exception، Holiday، **AvailabilityCalculator** | Catalog (Contracts) |
| **Booking** | Hold، Appointment، ماشین وضعیت، Policy، **PriceCalculator**، فیلدهای سفارشی | Catalog، Scheduling، Customers (Contracts) |
| **Customers** | CRM، OTP، نشست مشتری مهمان، API پنل مشتری | Shared |
| **Payments** | Gateway Port + Adapterها، Payment، Refund، تطبیق، ووکامرس | Booking (فقط از طریق رویداد و Contracts) |
| **Notifications** | Template، Channel، SMS/Email Adapterها، یادآوری | گوش‌دادن به رویدادها |
| **Admin** | داشبورد، گزارش، Settings UI، Onboarding، White-label، Site Health | Contracts همه ماژول‌ها |

گراف وابستگی حلقه ندارد. رابطه Booking و Payments فقط از طریق رویداد است: Booking رویداد `AppointmentAwaitingPayment` را منتشر می‌کند و Payments رویداد `PaymentSucceeded` را.

## 4. ساختار داخلی یک ماژول
```
src/Modules/Booking/
├── BookingModule.php          # register(Container) + boot(Context): hooks، routes، migrations، capabilities
├── Contracts/                 # BookingApi (interface)، DTOها، Eventهای عمومی
├── Domain/                    # Appointment، Hold، AppointmentStatus (enum)، PolicyEvaluator، PriceCalculator، …
├── Application/               # BookingService (hold / confirm / cancel / reschedule)، Portها
├── Infrastructure/            # WpdbAppointmentRepository، AppointmentQuery، ResourceLocker، Migrations/، Jobs/
└── Presentation/              # Rest/HoldsController، Rest/AppointmentsController، Cli/
```
پوشه‌ای که فایلی ندارد ساخته نمی‌شود.

## 5. Kernel و چرخه عمر
```
vaqtyar.php
  ├─ Requirements::met()          PHP، WP، نسخه MySQL/MariaDB، mbstring، وجود vendor. اگر رد شود: admin notice و توقف (بدون Fatal)
  │                               (InnoDB نیاز به کوئری دارد، پس Migrator هنگام ساخت جدول بررسی‌اش می‌کند، نه هر درخواست)
  └─ روی plugins_loaded (اولویت 5):
       Kernel\Plugin::boot(file, version, ...modules)   لیست ماژول‌ها از فایل اصلی می‌آید (Composition root)
         ├─ ModuleRegistry: id یکتا (روشن و خاموش کردن ماژول در T6.2)
         ├─ Container
         ├─ module->register(container)     همه ماژول‌ها، فقط binding، بدون Side effect
         ├─ Migrator: اگر db_version عقب باشد (با GET_LOCK)        ← T0.7
         └─ module->boot(context)           همه ماژول‌ها. Context = container + مسیر فایل اصلی + نسخه
```
- **Composition root = فایل اصلی افزونه** (T0.3). فایل اصلی نمونه ماژول‌ها را به `Plugin::boot()` پاس می‌دهد. در نتیجه Kernel به هیچ ماژولی وابسته نیست و deptrac همین را اجبار می‌کند. فایل اصلی سینتکس PHP 7.0 دارد، ولی `new XModule()` داخل closure مشکلی ایجاد نمی‌کند.
- **Context نوع درخواست را حدس نمی‌زند.** در `plugins_loaded` هنوز `REST_REQUEST` تعریف نشده و تشخیص REST فقط حدسی است. ماژول کار هر نوع درخواست را با hook خود وردپرس محدود می‌کند:
  - REST: `rest_api_init`
  - Admin: `admin_menu` و `admin_enqueue_scripts`
  - Front: `wp_enqueue_scripts` و render بلوک
  - CLI: `cli_init`
  - Cron: Action Scheduler

  سرویس‌ها داخل callback از Container گرفته می‌شوند.
- **Context-aware:** صفحه Front بدون ویجت یعنی صفر asset و صفر کوئری. این نتیجه طبیعی قاعده بالاست.
- **Exception در boot** یعنی باگ. گرفته نمی‌شود و recovery mode وردپرس افزونه را متوقف می‌کند و به مدیر سایت خبر می‌دهد. **استثنا: Migration ناموفق** شرط سرور است، نه باگ. گرفته و لاگ می‌شود، admin notice نمایش داده می‌شود و ماژول‌ها boot نمی‌شوند، تا کل سایت از کار نیفتد (implementation-notes §5).
- Hook ثبت ماژول برای Add-onها (`{prefix}/modules/register`، §10) وقتی ساخته می‌شود که اولین Add-on واقعی نوشته شود. آن hook نمونه Registry را به Add-on پاس می‌دهد.
- **Activation به boot وابسته نیست:** در درخواست فعال‌سازی، `plugins_loaded` زودتر رخ داده است (implementation-notes §1).
- Activation: Migration، Capabilityها و Jobهای تکراری. Deactivation: لغو Jobها. Uninstall: فقط با گزینه صریح «حذف داده».

## 6. تراکنش و رویدادها (ساده‌شده)
```
BookingService::confirm()
  └─ Transaction::run(fn() =>
        entity متد دامنه را اجرا می‌کند و Eventها را جمع می‌کند
        repository->save(entity)
        EventBus::record(events)                  ← فقط در حافظه
     )
  └─ پس از COMMIT: EventBus::flush()
        ├─ Listenerهای sync: invalidate cache
        ├─ Listenerهای async: as_enqueue_async_action(...)  ← پیامک، ایمیل
        └─ do_action('vaqtyar/booking/appointment_confirmed', $dto)   ← برای توسعه‌دهنده ثالث
```
- اگر تراکنش Rollback شود، هیچ رویدادی منتشر نمی‌شود.
- برای کارهای **حیاتی** مثل «پیامک تأیید» و «یادآوری»، Job مربوطه **داخل همان تراکنش** با Action Scheduler ثبت می‌شود. جداول AS هم InnoDB هستند و روی همان connection اجرا می‌شوند، پس ثبت Job اتمی است و به جدول Outbox جدا نیازی نیست (ADR-005).
- Jobها idempotent هستند: جدول `notification_log.dedup_key` و قید یکتای `payments.authority` این را تضمین می‌کنند.

## 7. ضد Double-booking (خلاصه)
Hold یا Confirm داخل تراکنش انجام می‌شود. برای همه پرسنل و منابع درگیر، ردیف `resource_day_locks` با `FOR UPDATE` و **به ترتیب `resource_id`** قفل می‌شود. بعد تداخل **از DB** دوباره چک می‌شود، رکورد درج می‌شود و COMMIT انجام می‌شود. اگر deadlock یا timeout رخ دهد، تا 3 بار Retry می‌شود و بعد خطای `slot_unavailable` برمی‌گردد. جزئیات در [03-booking-engine.md](03-booking-engine.md).

## 8. خطاها
| Exception | HTTP | مثال `code` |
|---|---|---|
| `DomainException` | 409/422 | `slot_unavailable`, `policy.cancel_window_passed` |
| `ValidationException` | 422 | خطاهای فیلدی |
| `NotFoundException` / `ForbiddenException` | 404 / 403 | |
| `ExternalServiceException` | 502 | درگاه یا پیامک |

- REST یک Envelope یکسان دارد:
  ```json
  {"code":"…","message":"…","data":{"status":409,"details":{},"request_id":"…"}}
  ```
- Controller و Hook listener مرز خطا هستند. هیچ خطایی نباید سایت را Fatal کند.

## 9. API
- Namespace از Identity خوانده می‌شود (مثلاً `vaqtyar/v1`).
- **Public** (availability، hold، booking، پنل مشتری): nonce یا توکن نشست مشتری + rate limit.
- **Admin:** مبتنی بر Capability.
- JSON به‌صورت `snake_case` است. زمان ISO-8601 با offset است. پول به شکل `{"amount":1500000,"currency":"IRR"}` است.
- Pagination با `page` و `per_page` (حداکثر 100) و هدر `X-WP-Total`.
- **Idempotency ساده:** Confirm یک Hold فقط یک‌بار موفق می‌شود (Hold مصرف می‌شود)، و Verify پرداخت با قید یکتای authority تکرارناپذیر است.

## 10. توسعه‌پذیری (برای فروش و پروژه‌های مختلف)
- **Hooks:** `{hook_prefix}/{module}/{event}`. نام‌ها از `Hooks::name()` ساخته می‌شوند تا با تغییر Identity عوض شوند.
- **Registryها** فقط برای نقاطی که واقعاً توسعه‌پذیرند: `PaymentGatewayRegistry`، `SmsProviderRegistry`، `PriceRuleRegistry`، `FieldTypeRegistry`.
- **Add-on:** یک افزونه جداگانه که روی hook `{prefix}/modules/register` یک Module ثبت می‌کند. قابلیت‌های تخصصی آینده (مثل پزشکی) همین‌طور ساخته می‌شوند.
- **White-label:** نام نمایشی، لوگو، رنگ‌ها و متن‌های اصلی در تنظیمات. هیچ نام برندی در UI hard-code نمی‌شود.

## 11. Cache
- نتیجه محاسبه‌شده هر روز یک درخواست Availability (بدون پنجره رزرو، که هر بار از نو اعمال می‌شود) در `wp_cache` نگه داشته می‌شود. **Invalidation سراسری است** (یک generation در کلید): هر تغییر Catalog، Schedule، Exception، تعطیلی، و از T2.2 هر نوشتن در `occupancies`، کل cache را باطل می‌کند. TTL پنج دقیقه است. طرح اولیه (cache برای هر پرسنل یا منبع و روز، با invalidation روزانه) کنار گذاشته شد، چون تغییر برنامه هفتگی همه روزها را عوض می‌کند و هر نویسنده باید روزهای همه شعبه‌ها را می‌شناخت (T1.5، implementation-notes §4.7).
- **تصمیم نهایی رزرو هرگز از Cache گرفته نمی‌شود.**
- بدون Redis هم کار می‌کند. Object cache در صورت وجود استفاده می‌شود.

## 12. امنیت
- **Auth:**
  - Cookie + nonce برای کاربران WP
  - توکن نشست کوتاه‌عمر پس از OTP برای مشتری (هش‌شده در DB)
  - Application Passwords برای اپ خارجی
- **Authorization:** در Controller (`permission_callback`) **و** در Application Service (Capability + مالکیت، مثلاً «این نوبت مال این مشتری است»).
- **SQL:** فقط `prepare` است و identifierها از whitelist می‌آیند. **خروجی:** escape می‌شود.
- **Secretها** (کلید درگاه و پیامک): در option به‌صورت رمزنگاری‌شده با `sodium` و کلیدی مشتق از `AUTH_KEY` ذخیره می‌شوند. امکان تعریف به‌صورت ثابت در `wp-config.php` هم هست. در UI ماسک می‌شوند.
- **آپلود فایل:** پوشه محافظت‌شده، whitelist برای MIME، و دانلود فقط از endpoint مجاز.
- **Callback درگاه:** همیشه verify سمت سرور انجام می‌شود و مبلغ تطبیق داده می‌شود.
- **PII** در لاگ ماسک می‌شود.

## 13. Observability
- Logger داخلی (جدول `logs` با Retention) + `request_id`.
- صفحه System Status: نسخه‌ها، Migrationها، صف AS، خطاهای اخیر درگاه و پیامک.
- Site Health tests: tzdata (نبود DST ایران)، cron واقعی، InnoDB، intl.
