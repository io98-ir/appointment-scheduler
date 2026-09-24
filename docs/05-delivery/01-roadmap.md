# روند کامل اجرا (Roadmap)

> این سند **ترتیب کار** تا نسخه 1.0 است. وضعیت لحظه‌ای هر کار در [02-progress.md](02-progress.md) ثبت می‌شود.
> هر Task طوری تعریف شده که در **یک سشن** قابل انجام باشد و یک **معیار «انجام‌شده»** قابل‌آزمون داشته باشد.
> ترتیب Taskها مهم است: هر Task فقط به Taskهای قبلی خودش وابسته است.

## نمای کلی
| Milestone | هدف | خروجی قابل مشاهده |
|---|---|---|
| **M0 — زیربنا** | اسکلت، ابزار، Kernel، Shared | افزونه فعال می‌شود، CI سبز است، `rename.php` کار می‌کند |
| **M1 — کاتالوگ و زمان‌بندی** | داده پایه + موتور Availability | API اسلات‌های آزاد را درست برمی‌گرداند |
| **M2 — هسته رزرو** | Hold، قفل، نوبت، Policy، قیمت | رزرو از طریق API، با Double booking صفر |
| **M3 — Admin** | پنل مدیریت React | مدیر همه‌چیز را از UI تنظیم و مدیریت می‌کند |
| **M4 — سمت مشتری** | ویجت، OTP، پنل مشتری | رزرو کامل از سایت |
| **M5 — پرداخت و اعلان** | درگاه‌ها، پیامک، یادآوری | پرداخت واقعی و پیامک واقعی |
| **M6 — انتشار 1.0** | کیفیت، White-label، بسته‌بندی | zip قابل فروش |

---

## M0 — زیربنا
| ID | Task | انجام‌شده وقتی |
|---|---|---|
| T0.1 | اسکلت Repo: فایل اصلی افزونه (header + Requirements check)، `identity.json`، `composer.json` (PSR-4 + dev tools)، `.gitignore`، `.editorconfig`، `uninstall.php` | افزونه در WP فعال می‌شود. روی PHP 8.0 پیام واضح نمایش داده می‌شود و Fatal رخ نمی‌دهد |
| T0.2 | ابزار کیفیت PHP: `tools/phpcs.xml` (ADR-008)، `phpstan.neon` (level 9)، `deptrac.yaml` (لایه‌ها و ماژول‌ها)، `phpunit.xml.dist` (فقط Unit؛ config و bootstrap Integration در T0.10) + اسکریپت‌های composer (`lint`, `stan`, `test`) | همه ابزارها روی اسکلت سبزند |
| T0.3 | Kernel: `Identity`، `Container` (ADR-011)، `ModuleRegistry`، `Module` interface، `Context`، `Plugin::boot()`. اینکه لیست کلاس‌های ماژول کجا باشد (composition root) باید با `tools/deptrac-*.yaml` هماهنگ شود: الان Kernel به هیچ ماژولی دسترسی ندارد | تست Unit برای Container و Registry، و بارگذاری ماژول نمونه |
| T0.4 | Helperهای نام: `Tables`، `Options`، `Hooks`، `Caps` + **`tools/rename.php`** با dry-run و بررسی باقیمانده | تست rename روی کپی موقت: بعد از rename، lint و test سبز می‌مانند |
| T0.5 | Shared Domain: `Money`، `PhoneNumber` (نرمال‌سازی ارقام فارسی و E.164)، `Email`، `LocalDate`، `LocalTime`، `TimeRange`، **`IntervalSet`**، `Ulid`، `Clock` | Unit + randomized test برای IntervalSet، پوشش ≥ 95% |
| T0.6 | `Jalali` (تبدیل دوطرفه، نام ماه‌ها، تعداد روز ماه، کبیسه) + `DateFormatter` (شمسی یا میلادی، ارقام فارسی) | تست روی بازه 1300 تا 1500 و مقایسه با intl (اگر موجود باشد) |
| T0.7 | Persistence: **(پیش‌نیاز: T0.10، چون تست Integration بدون bootstrap و CI اجرا نمی‌شود)** `Db` (wrapper روی wpdb با prepare و insert/update typed)، `Transaction` (با Retry روی deadlock)، `Migrator` + `Migration` base + activation hook در سطح بالای فایل اصلی با لیست مشترک ماژول‌ها (تله Activation در implementation-notes §1) + بررسی InnoDB هنگام ساخت جدول (`ENGINE=InnoDB` صریح و تأیید از `information_schema`، با پیام خطای واضح) | تست Integration: اجرای migration، idempotency و Rollback تراکنش |
| T0.8 | REST base: `Controller` پایه، ثبت route با Identity، Error Envelope، تبدیل Exception به HTTP، `RateLimiter`، Pagination helper | تست Integration: endpoint نمونه، 403، 422 و 429 |
| T0.9 | `Settings` (typed، گروه‌بندی، autoload=no برای موارد حجیم)، `SecretStore` (sodium)، `Logger` (جدول logs)، ثبت Capabilityها روی نقش‌ها | تست Unit و Integration |
| T0.10 | محیط: `.wp-env.json` (✅ از قبل ساخته شده)، bootstrap و config جدای PHPUnit برای Integration + اسکریپت `composer test:integration`، **GitHub Actions** (lint، stan، deptrac، test-php با ماتریس، **`composer test:rename`**، **`php -l` با PHP 7.0 روی `vaqtyar.php`، `uninstall.php` و `src/Kernel/Requirements.php`**، و **Integration و Concurrency روی wp-env داخل runner**، build) | CI روی push سبز است. **Docker محلی لازم نیست**: تست‌های وابسته به MySQL در CI اجرا می‌شوند |
| T0.11 | JS workspace: (نام پکیج‌های JS با توکن‌های Identity؛ `rename.php` بعد از rename، `pnpm install --lockfile-only` را هم اجرا کند و `test:rename` روی JS هم سبز بماند)، `pnpm-workspace`، `packages/shared` (api client، types، jalali، money)، `packages/admin` (shell صفحه Admin + router ساده)، `packages/widget` (Preact + mount)، wp-scripts، ESLint، Prettier، Stylelint، Vitest، size-limit | `pnpm build` و `pnpm test` سبزند و صفحه خالی Admin رندر می‌شود |

## M1 — کاتالوگ و زمان‌بندی
| ID | Task | انجام‌شده وقتی |
|---|---|---|
| T1.1 | Catalog Domain + Migration: Location، Staff، Resource، Category، Service، Variant، Extra، ServiceStaff (override) | Entityها + invariants + تست |
| T1.2 | Catalog Repositories + Admin REST CRUD (با Capability و Validation) + `CatalogApi` Contract | تست Integration برای CRUD و مجوز |
| T1.3 | Scheduling Domain + Migration: ScheduleRule، Exception، Holiday + دیتاست `assets/holidays/1405.json` + import | تست Unit و Integration |
| T1.4 | **AvailabilityCalculator** (Domain خالص): Working − Busy، گام، Buffer، Extra، ظرفیت، min_notice/max_advance، انتخاب پرسنل | سناریوهای تست جدولی + randomized + بنچمارک کمتر از 50ms |
| T1.5 | Availability Infrastructure: کوئری Batch برای occupancies، Cache و invalidation، REST عمومی `GET /availability` (نماهای day، month و first) | تست Integration + p95 کمتر از 300ms با fixture |

## M2 — هسته رزرو
| ID | Task | انجام‌شده وقتی |
|---|---|---|
| T2.1 | Booking Migration: holds، appointments، occupancies، history، answers، fields، labels، policies، price_rules، coupons | Migration تست‌شده |
| T2.2 | `ResourceLocker` + **Hold** (ایجاد، تمدید، انقضا) + `POST /holds` | **تست همزمانی: 30 درخواست موازی، دقیقاً 1 موفق** |
| T2.3 | `PriceCalculator` + Ruleها (Variant، Staff override، Time rule، Extra، Party size، Coupon، Rounding) | تست Unit جدولی |
| T2.4 | `Appointment` Entity + ماشین وضعیت + `BookingService::confirm` + `POST /bookings` + ثبت Job اعلان در تراکنش | تست Unit گذارها + Integration |
| T2.5 | `PolicyEvaluator` + cancel، reschedule و no_show (مشتری و پرسنل) + Override با Capability | تست پلکان استرداد و مهلت‌ها |
| T2.6 | فیلدهای سفارشی (تعریف، شرط ساده، Validation پاسخ‌ها، ذخیره) | تست |
| T2.7 | Customers: Entity، Repository، CRUD Admin، جستجو (نرمال‌سازی فارسی)، ادغام با کاربر WP | تست |
| T2.8 | Admin Query: لیست نوبت‌ها (فیلتر، مرتب‌سازی، Pagination) + جزئیات + تقویم (بازه، پرسنل) | تست Integration + EXPLAIN |

## M3 — Admin (React)
| ID | Task | انجام‌شده وقتی |
|---|---|---|
| T3.1 | Shell: منو، Layout، اعلان‌ها، مدیریت خطا، TanStack Query، api-client، i18n JS، حالت تاریک | ناوبری بین صفحات |
| T3.2 | صفحات کاتالوگ: خدمات (Variant، Extra، پرسنل)، پرسنل (برنامه هفتگی، مرخصی)، منابع، شعبه‌ها | CRUD کامل از UI + E2E |
| T3.3 | **تقویم**: نمای روز (ستون پرسنل) و هفته، Drag & Drop برای جابجایی (با Policy Override)، کلیک برای ثبت سریع | E2E جابجایی و ثبت |
| T3.4 | لیست نوبت‌ها (DataViews) + جزئیات نوبت (تغییر وضعیت، لغو، یادداشت، history، پرداخت‌ها) | E2E |
| T3.5 | مشتریان (لیست، پروفایل، سوابق) + تعطیلات + Policyها + Price rule و کوپن + فیلدهای سفارشی | E2E |
| T3.6 | داشبورد (امروز، هفته، درآمد، نرخ لغو) + گزارش پایه + خروجی CSV | تست |

## M4 — سمت مشتری
| ID | Task | انجام‌شده وقتی |
|---|---|---|
| T4.1 | ویجت: انتخاب خدمت، Variant و پرسنل ← تقویم ماه (رنگ روزها) + اسلات‌ها در یک نما + «اولین نوبت خالی» | کمتر از 40KB، RTL، ناوبری کامل با صفحه‌کلید |
| T4.2 | ویجت: Hold با تایمر ← فرم (فیلدهای سفارشی) ← خلاصه قیمت ← تأیید و پرداخت ← صفحه موفقیت با کد پیگیری | E2E رزرو مهمان |
| T4.3 | OTP: درخواست و تأیید کد (rate limit، هش، انقضا) + نشست مشتری + Captcha داخلی | تست امنیتی |
| T4.4 | پنل مشتری: نوبت‌ها، لغو و تغییر (با نمایش Decision از Policy)، پرداخت مانده | E2E |
| T4.5 | Shortcode + بلوک گوتنبرگ (تنظیمات: خدمت یا پرسنل پیش‌فرض، نما) + بارگذاری شرطی asset | بدون asset در صفحات دیگر |

## M5 — پرداخت و اعلان
| ID | Task | انجام‌شده وقتی |
|---|---|---|
| T5.1 | Payments core: Migration، `PaymentGateway` Port، Registry، جریان start ← callback ← verify (idempotent)، Failover، Offline | Contract test مشترک برای همه درگاه‌ها |
| T5.2 | Adapterهای Zarinpal v4 و Zibal + Job تطبیق + ثبت استرداد دستی | تست با HTTP Mock ضبط‌شده |
| T5.3 | ووکامرس به‌عنوان درگاه (HPOS) | تست Integration با WC |
| T5.4 | Notifications core: Template، trigger، audience، زمان‌بندی یادآوری، ساعات سکوت، dedup، لاگ، Email | تست |
| T5.5 | `SmsProvider` + Adapterها: Kavenegar، IPPanel، SMS.ir، Melipayamak + Failover + صفحه تنظیم پترن‌ها + پیامک تست | Contract test |

## M6 — انتشار 1.0
| ID | Task | انجام‌شده وقتی |
|---|---|---|
| T6.1 | White-label (نام، لوگو، رنگ) + Onboarding Wizard (شعبه، ساعت کاری، اولین خدمت، پیامک، درگاه) | E2E نصب تازه |
| T6.2 | Site Health tests + صفحه System Status + ماژول‌های قابل خاموش‌کردن | تست |
| T6.3 | ترجمه کامل fa_IR + بررسی en + ممیزی دسترس‌پذیری (axe) + بودجه کارایی | گزارش بدون خطای جدی |
| T6.4 | بازبینی امنیتی کامل (چک‌لیست + `/security-review`) + Plugin Check | بدون یافته باز |
| T6.5 | E2E کامل سناریوهای طلایی + تست ارتقا (از نسخه قبل با داده نمونه) + تست uninstall | CI سبز |
| T6.6 | Build نهایی (zip تمیز)، CHANGELOG، `readme.txt`، راهنمای کاربر فارسی، `docs/api.md`، `docs/hooks.md` | zip آماده فروش |

---

## بعد از 1.0
Backlog به ترتیب ارزش در [../02-product/01-product-scope.md §5](../02-product/01-product-scope.md) آمده است. هر قابلیت پیش از شروع، Task‌هایش را به همین سند اضافه می‌کند.
