# تحقیق: وضعیت پلتفرم وردپرس (سپتامبر 2026)

> ⚠️ **بازنگری 2026-09-23:** Strauss فعلاً لازم نیست، چون وابستگی Production نداریم (ADR-011). Migration بدون جدول جدا انجام می‌شود. **منبع تصمیم نهایی `docs/03-architecture/` است.**

## 1. نسخه‌ها
| مورد | وضعیت |
|---|---|
| WordPress 6.9 «Gene» | دسامبر 2025 — **Abilities API**، بهبود Interactivity API، پشتیبانی بتای PHP 8.5 |
| WordPress 7.0 | **20 مه 2026** — AI Client در هسته، Abilities سمت کلاینت، Connectors hub، **DataViews** برای مدیریت محتوا، طراحی جدید Admin، بلوک‌های فقط-PHP، ویرایش همزمان |
| حداقل PHP وردپرس | 7.4 (از 7.0، پشتیبانی 7.2/7.3 حذف شد)؛ **توصیه‌شده 8.3** |
| رقبای ایرانی | نوبت‌نگار حداقل PHP 8.1 و WP 6.0 می‌خواهد ← بازار ایران آماده PHP 8.1+ است |

## 2. APIهای مهم برای ما
| API | کاربرد در افزونه |
|---|---|
| **REST API** | کل ارتباط Admin SPA، ویجت رزرو، اپ‌های خارجی |
| **Script Modules** (6.5+) و **Interactivity API** | قابل‌استفاده برای بخش‌های سبک Front (پنل مشتری)، ولی ویجت رزرو تعاملی سنگین است |
| **@wordpress/components**, **@wordpress/dataviews** | ظاهر یکپارچه با Admin جدید WP 7.0؛ جداول، فیلترها، نماهای لیست/گرید |
| **Block API** (block.json, apiVersion 3) | بلوک «فرم رزرو»، «پنل من»، «لیست پزشکان» |
| **Abilities API** (6.9+) | ثبت قابلیت‌ها: `find_available_slots`, `book_appointment`, `cancel_appointment` برای عامل‌های AI و MCP |
| **Site Health API** | چک‌های سلامت: tzdata، cron واقعی، ext-intl، InnoDB، دسترسی به درگاه/پیامک |
| **Privacy API** (Exporter/Eraser) | خروجی و حذف داده شخصی مشتری |
| **Application Passwords** | احراز هویت اپ‌های خارجی به REST |
| **HTTP API** (`wp_remote_*`) | همه فراخوانی‌های خارجی (قابل Mock و سازگار با پراکسی) |
| **Action Scheduler** (کتابخانه WooCommerce، مستقل قابل‌استفاده) | صف Job ماندگار، Retry، UI مشاهده Jobها |

## 3. WP-Cron در برابر Action Scheduler
- WP-Cron همه رویدادها را در یک option سریالایز‌شده نگه می‌دارد، وابسته به بازدید است و در ترافیک همزمان Write Contention دارد.
- Action Scheduler: جدول اختصاصی، اجرای Batch، ثبت خطای هر Job، UI مدیریت. اگر ووکامرس نصب باشد نسخه مشترک بارگذاری می‌شود (مکانیزم Versioning خودش).
- ← تصمیم: **Action Scheduler** برای همه کارهای پس‌زمینه (اعلان، یادآوری، تطبیق پرداخت، انقضای Hold، همگام‌سازی تقویم).

## 4. جداول سفارشی
- `wp_posts/postmeta` برای داده رزرو **نامناسب** است (کوئری بازه زمانی، ایندکس ترکیبی، قفل ردیف). Bookly/Booknetic/Amelia هم جدول سفارشی دارند.
- `dbDelta` فقط اضافه می‌کند (ستون/ایندکس)؛ حذف/تغییر نام نیاز به `ALTER` صریح دارد.
- ← تصمیم: **سیستم Migration نسخه‌دار خودمان** (کلاس‌های Migration ترتیبی با `up()`؛ هر migration idempotent؛ ثبت در جدول `migrations`) — dbDelta فقط برای ساخت اولیه جداول ساده استفاده نمی‌شود تا رفتار قطعی باشد.

## 5. رقابت با افزونه‌های دیگر (Conflict)
- افزونه‌ها Composer vendor را در فضای نام سراسری PHP بارگذاری می‌کنند ← تداخل نسخه (مثلاً دو نسخه `psr/container`).
  ← تصمیم: **Strauss** برای Prefix کردن همه وابستگی‌های vendor به `Vaqtyar\Vendor\`.
- CSS قالب‌ها روی ویجت اثر می‌گذارد.
  ← تصمیم: کلاس‌های پیشوندی + `@layer` + ریست محدود داخل ریشه ویجت (بررسی Shadow DOM در ADR).

## 6. الزامات کیفیت
- **Plugin Check (PCP)** رسمی وردپرس باید بدون خطا عبور کند.
- WPCS 3 + PHPCompatibilityWP.
- امنیت: nonce، capability، `$wpdb->prepare`، escaping خروجی، `permission_callback` برای همه REST routes.

## منابع
- https://make.wordpress.org/core/2025/11/10/abilities-api-in-wordpress-6-9/
- https://make.wordpress.org/core/2026/01/09/dropping-support-for-php-7-2-and-7-3/
- https://wordpress.com/blog/2025/12/03/wordpress-6-9-new-for-developers/
- https://www.inmotionhosting.com/support/edu/wordpress/wordpress-news/wordpress-7-0-release-date/
- https://actionscheduler.org/
- https://docs.wpvip.com/databases/custom-tables/
- https://clixo.sh/blog/prevent-double-booking-concurrent-reservation-requests
- https://hackernoon.com/how-to-solve-race-conditions-in-a-booking-system
