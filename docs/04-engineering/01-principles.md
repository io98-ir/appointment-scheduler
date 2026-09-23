# مهندسی: اصول، استانداردها و قواعد کدنویسی

> این سند **الزام‌آور** است. هر PR با این قواعد سنجیده می‌شود. استثنا فقط با ADR.

## 0. قانون‌های ضد Overengineering (الزام‌آور)
1. **ساده‌ترین طرحی که نیاز Milestone فعلی را برآورده کند و راه Milestoneهای بعدی را نبندد.**
2. **انتزاع (interface، کلاس پایه، Registry) فقط در دو حالت ساخته می‌شود:**
   - حداقل دو پیاده‌سازی واقعی وجود دارد، یا
   - مرز خارجی است: دیتابیس، HTTP، درگاه، پیامک، زمان.
3. **هیچ کدی برای «شاید روزی»** نوشته نمی‌شود. آینده فقط در سند طراحی می‌آید، نه در کد.
4. **هیچ پوشه یا کلاس خالی یا Placeholder.**
5. **وابستگی جدید Production به ADR نیاز دارد.** ترجیح با کد کوچک خودمان است، مگر اینکه مسئله واقعاً سخت باشد (مثل PDF).
6. **DTO فقط در مرز** (REST و Contracts ماژول). درون ماژول، Entity و Value Object کافی‌اند.
7. **Pattern به اسم نمی‌آید.** نمی‌گوییم «چون DDD می‌گوید». هر ساختار باید مشکل مشخصی را حل کند.
8. **سؤال هر PR:** «آیا می‌شد همین را با کد کمتر و به همان خوانایی نوشت؟» اگر بله، ساده‌اش کن.
9. **استثنا:** در **درستی** (قفل رزرو، پول، امنیت، Migration) صرفه‌جویی نمی‌کنیم. این‌ها جایی است که پیچیدگی لازم ارزشش را دارد.

## 1. اصول بنیادین
1. **درستی قبل از سرعت، سرعت قبل از زیبایی.** Double booking یا خطای مالی = باگ بحرانی.
2. **Domain خالص:** منطق تجاری هرگز به وردپرس، HTTP یا دیتابیس وابسته نیست.
3. **صراحت بر جادو:** بدون magic methods، بدون `extract()`، بدون global state، بدون Service Locator در Domain/Application (Container فقط در Composition Root و Providers).
4. **Make illegal states unrepresentable:** Value Object و enum به‌جای string/int خام؛ سازنده‌ای که invariant را نقض کند Exception می‌دهد.
5. **Immutability پیش‌فرض:** Value Objectها و DTOها `readonly`؛ تغییر وضعیت فقط از طریق متدهای رفتاری Aggregate.
6. **Fail fast و بلند:** خطای برنامه‌نویس (نقض قرارداد) ← Exception؛ هرگز بازگشت `false`/`null` بی‌صدا.
7. **YAGNI در پیاده‌سازی، نه در مرزها:** Portها و مدل داده برای فازهای بعد فکر شده‌اند؛ ولی کد فقط وقتی نوشته می‌شود که Use Case واقعی دارد.
8. **Boy Scout Rule:** هر فایلی که لمس می‌کنی کمی تمیزتر رهایش کن — ولی Refactor بزرگ در PR جدا.
9. **هر تصمیم معماری ← ADR.** هر رفتار تجاری ← تست.

## 2. SOLID و طراحی
- **SRP:** هر کلاس یک دلیل برای تغییر. Handler = یک Use Case. Controller = فقط تبدیل HTTP ↔ Command/Query.
- **OCP:** افزودن درگاه/پیامک/Rule قیمت = افزودن کلاس + ثبت در Registry؛ بدون تغییر کد موجود.
- **LSP:** همه Adapterهای یک Port قرارداد یکسان (Contract tests مشترک برای همه Adapterها).
- **ISP:** Portهای کوچک (`SlotReader`, `SlotWriter` به‌جای `SlotRepository` غول‌آسا در صورت نیاز).
- **DIP:** Application به Interface وابسته است؛ Binding در Service Provider ماژول.
- **Composition over inheritance:** کلاس‌ها `final` به‌صورت پیش‌فرض؛ ارث‌بری فقط برای کلاس‌های پایه چارچوب داخلی (مثل `AbstractMigration`) و با `abstract`.
- **Tell, don't ask:** `$appointment->cancel($reason, $actor, $policy)` نه `if ($a->getStatus() === ...) $a->setStatus(...)`.
- **Law of Demeter** در Domain.
- DRY با احتیاط: تکرار بهتر از انتزاع اشتباه (Rule of Three).

## 3. استاندارد PHP

### قالب و ابزار
- `declare(strict_types=1);` در **همه** فایل‌ها.
- PSR-4، PSR-12 سبک با **WPCS 3** برای فایل‌های Presentation/Infrastructure که با WP درگیرند؛ برای `src/` قانون ترکیبی در `tools/phpcs.xml` (WPCS security sniffs + Slevomat برای type hints). تصمیم نهایی قالب‌بندی: ADR-008.
- **PHPStan level 9** بدون baseline برای کد جدید؛ هر `@phpstan-ignore` با توضیح.
- هیچ `mixed` بدون دلیل؛ آرایه‌ها با shape در DocBlock (`array{id:int, name:string}`) یا بهتر: DTO.

### نام‌گذاری
> `Vaqtyar`، `vaqtyar` و `vqy` **نام کاری** هستند و با `tools/rename.php` عوض می‌شوند (ADR-000). در کد، نام جدول، option، hook، capability و REST namespace **هرگز لیترال نوشته نمی‌شوند** و فقط از Helperها ساخته می‌شوند: `Tables::name()`، `Options::key()`، `Hooks::name()`، `Caps::name()`، `Identity::REST_NAMESPACE`. تنها استثناها namespace در PHP و text-domain هستند که باید لیترال باشند.

| مورد | قاعده | مثال |
|---|---|---|
| Namespace | `Vaqtyar\Modules\{Module}\{Layer}\…` | `Vaqtyar\Modules\Booking\Domain\Model\Appointment` |
| Class | PascalCase، اسم (Noun) | `AvailabilityCalculator` |
| Interface | بدون پیشوند `I`، اسم نقش | `PaymentGateway`, `AppointmentRepository` |
| پیاده‌سازی | پیشوند فناوری | `WpdbAppointmentRepository`, `ZarinpalGateway` |
| Command | فعل امری | `ConfirmBooking` |
| Handler | `{Command}Handler` | `ConfirmBookingHandler` |
| Event | گذشته | `AppointmentConfirmed` |
| Exception | توصیفی + `Exception` یا نام حالت | `SlotUnavailable` (extends `DomainException`) |
| Enum | PascalCase، case ها PascalCase | `AppointmentStatus::PendingPayment` |
| متد | camelCase، فعل | `calculateSlots()` |
| Hook | `vaqtyar/{module}/{snake_event}` | `vaqtyar/booking/appointment_confirmed` |
| Option | `vaqtyar_{snake}` | `vaqtyar_settings_general` |
| Capability | `vaqtyar_{verb}_{noun}` | `vaqtyar_manage_appointments` |
| REST route | جمع، kebab | `/vaqtyar/v1/service-variants` |
| DB table/column | snake_case | `appointment_resources.occupied_start_at` |

### قواعد کد
- حداکثر ~20 خط برای متد (راهنما، نه قانون سخت)؛ Cyclomatic complexity ≤ 10.
- پارامتر بولی در API عمومی ممنوع (`book(true)`) ← enum یا متد جدا.
- بدون `static` state؛ متد static فقط برای Named Constructor (`Money::ofRial()`).
- `DateTimeImmutable` همیشه؛ **زمان فعلی فقط از `Clock` interface خودمان** (`now(): DateTimeImmutable`) ← تست‌پذیری.
- شناسه‌ها از `IdGenerator` Port (ULID).
- رشته‌های قابل‌نمایش همیشه از i18n (`__('…', 'vaqtyar')`) در Presentation؛ Domain فقط کد خطا برمی‌گرداند.
- هر `catch` یا بازپرتاب با context، یا لاگ + تبدیل؛ `catch (\Throwable)` خالی ممنوع.
- کوئری SQL فقط در `Infrastructure/Persistence` یا `Infrastructure/Query`.

## 4. قواعد DDD تاکتیکی
- **Aggregate** مرز تراکنش است؛ یک تراکنش = یک Aggregate (به‌جز رزرو که قفل چند منبع را می‌گیرد ولی فقط Appointment/Hold را می‌نویسد).
- ارجاع بین Aggregateها با **ID**، نه شیء.
- Repository فقط برای Aggregate Root؛ `get(id)` Exception می‌دهد، `find(id)` nullable.
- Domain Event: `readonly`، شامل `event_id`, `occurred_at`, داده حداقلی و **پایدار** (برای Webhook نسخه‌دار).
- Value Objectهای Shared (فقط همین‌ها تا زمانی که نیاز واقعی دیگری پیدا شود): `Money`, `PhoneNumber`, `Email`, `TimeRange`, `IntervalSet`, `LocalDate`, `LocalTime`, `Ulid`. تبدیل جلالی در یک سرویس `Jalali` است و Value Object جدایی ندارد.

## 5. استاندارد Frontend
- TypeScript `strict: true`، `noUncheckedIndexedAccess: true`؛ `any` ممنوع (`unknown` + narrowing).
- کامپوننت‌ها تابعی؛ منطق در Hookهای سفارشی؛ کامپوننت‌های UI خالص و بدون fetch.
- **Server state فقط با TanStack Query**؛ داده سرور در state محلی کپی نمی‌شود.
- نوع‌های API در `packages/shared/src/api-types.ts`؛ هر تغییر Schema در PHP همان PR نوع TS را هم به‌روز می‌کند.
- استایل: **CSS Logical Properties** اجباری (Stylelint rule)؛ متغیرهای طراحی در `--vqy-*`؛ هیچ رنگ hard-code.
- ویجت: بدون وابستگی به jQuery، بدون global؛ یک `mount(el, config)`؛ چند نمونه در یک صفحه پشتیبانی شود.
- هر رشته قابل نمایش از `@wordpress/i18n` (`__`)؛ هیچ متن فارسی hard-code در JS.
- **دسترس‌پذیری:** ناوبری کامل صفحه‌کلید در تقویم و اسلات‌ها، `aria-live` برای تغییر اسلات‌ها، کنتراست AA، focus ring قابل مشاهده، برچسب واقعی برای هر input، پشتیبانی `prefers-reduced-motion`.
- **حالت تاریک** از طریق `prefers-color-scheme` + تنظیم دستی؛ ویجت از متغیرهای CSS قالب (theme.json) در صورت وجود ارث می‌برد.

## 6. تست

### هرم تست
| سطح | ابزار | پوشش | زمان اجرا |
|---|---|---|---|
| Unit — Domain | PHPUnit (بدون WP) | ≥ 90% خطوط؛ همه invariants؛ تست‌های **randomized با seed ثابت** (حلقه ساده، بدون کتابخانه) برای IntervalSet، Availability و Jalali (مقایسه با intl) | < 10s |
| Unit — Application | PHPUnit + Fakeهای Port (In-memory Repository، FakeClock) | همه Handlerها، مسیر خوش و خطا | < 20s |
| Contract | PHPUnit | هر Adapter یک Port همان Suite مشترک را پاس می‌کند؛ Gateway/SMS با HTTP Mock ضبط‌شده | < 30s |
| Integration | wp-phpunit + wp-env (MySQL واقعی) | Repositoryها، Migrationها، REST endpoints (permission + schema)، hooks | دقایق |
| Concurrency | اسکریپت PHP/Node با درخواست موازی روی wp-env | Double booking = 0 | دقایق |
| E2E | Playwright | سناریوهای طلایی: رزرو مهمان با OTP + پرداخت Mock، لغو از پنل، ثبت منشی، Drag&Drop تقویم، RTL، axe | دقایق |
| JS Unit | Vitest | Hooks، utils (jalali، money)، کامپوننت‌های کلیدی | ثانیه |

### قواعد
- **هر باگ ← ابتدا تست شکست‌خورنده ← سپس Fix.**
- تست‌ها نام رفتاری دارند: `test_customer_cannot_cancel_within_policy_window()`.
- هیچ تست به زمان واقعی، شبکه واقعی یا ترتیب اجرا وابسته نیست.
- Test Data Builders (`AppointmentBuilder::aConfirmed()->at('1405-07-10 10:00')->build()`).
- Fixtureهای سناریوی واقعی کلینیک (3 پزشک، 2 یونیت، تعطیلات 1405) در `tests/fixtures`.

## 7. امنیت — چک‌لیست هر PR
- [ ] هر REST route `permission_callback` واقعی دارد (هرگز `__return_true` برای نوشتن)
- [ ] هر Use Case Authorization را در Application چک می‌کند (capability + scope)
- [ ] ورودی‌ها Schema-validated و Value Object شده‌اند؛ ارقام فارسی نرمال شده‌اند
- [ ] SQL فقط prepared؛ identifierها whitelist
- [ ] خروجی HTML escape (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
- [ ] فرم‌های Admin با nonce؛ عملیات state-changing فقط POST/PUT/PATCH/DELETE
- [ ] PII (موبایل، ایمیل) در لاگ ماسک‌شده
- [ ] endpointهای عمومی rate-limited (OTP: 3 در 10 دقیقه per phone + per IP)
- [ ] کد OTP: 5-6 رقم، هش‌شده، حداکثر 5 تلاش، انقضای 2 دقیقه، مقایسه `hash_equals`
- [ ] Callback درگاه: verify سمت سرور، مبلغ و authority تطبیق، idempotent
- [ ] آپلود: whitelist MIME + بررسی محتوا + مسیر محافظت‌شده + حداکثر حجم
- [ ] بدون `eval`, `unserialize` روی داده کاربر، `extract`
- [ ] Secretها (API key درگاه/پیامک) در option **رمزنگاری‌شده** و در UI ماسک (`••••1234`)؛ امکان تعریف در `wp-config.php` (ثابت) با اولویت

## 8. کارایی — چک‌لیست
- [ ] بدون N+1 (کوئری Batch/Eager)؛ Query Monitor در dev
- [ ] هر کوئری روی جداول بزرگ ایندکس مناسب دارد (EXPLAIN در PR برای کوئری جدید)
- [ ] Assets فقط در صفحات لازم enqueue (شرط وجود block/shortcode)
- [ ] Autoload options حداقلی (تنظیمات حجیم `autoload=no`)
- [ ] کار سنگین (> 1s یا فراخوانی خارجی غیرضروری) ← Action Scheduler
- [ ] Pagination اجباری روی همه لیست‌ها (حداکثر `per_page=100`)
- [ ] بودجه حجم JS/CSS رعایت شده (size-limit)

## 9. i18n و L10n
- Text domain: `vaqtyar`؛ زبان مبدأ **انگلیسی**؛ ترجمه کامل `fa_IR` همراه افزونه.
- `_n()` برای جمع، `_x()` برای context، placeholder با `sprintf` و ترجمه‌کننده‌کامنت (`/* translators: %s: customer name */`).
- تاریخ/عدد/پول فقط از Formatterهای مرکزی (`DateFormatter`, `MoneyFormatter`, `NumberFormatter`) که locale و تقویم را رعایت می‌کنند.
- تقویم نمایشی مستقل از زبان قابل انتخاب (فارسی با میلادی یا انگلیسی با شمسی).

## 10. Git و فرایند
- **Conventional Commits**: `feat(booking): …`, `fix(payments): …`, `refactor`, `test`, `docs`, `chore`, `perf`, `build`, `ci`.
- شاخه: `feat/<module>-<short>`؛ PR کوچک (< 400 خط تغییر منطقی ترجیحاً).
- **Definition of Done:**
  1. کد + تست (سطوح مناسب) + CI سبز (lint, static, tests, e2e مرتبط)
  2. بدون کاهش پوشش Domain
  3. مستندات: DocBlock hooks، نوع‌های API در TS، CHANGELOG، و در صورت نیاز docs/ کاربر
  4. **Tracker و Worklog به‌روز شده‌اند** (ر.ک. [../05-delivery/03-agent-workflow.md](../05-delivery/03-agent-workflow.md))
  5. ترجمه: رشته‌های جدید در `.pot`
  6. Migration (در صورت تغییر Schema) + تست ارتقا از نسخه قبل
  7. بررسی امنیت و کارایی طبق چک‌لیست
  8. RTL و موبایل در UI بررسی شده
  9. هیچ اثری از نام کاری به‌صورت لیترال (جز namespace/text-domain) — تست Identity سبز
- **Code Review:** حداقل یک تأیید؛ Reviewer چک‌لیست‌های 7 و 8 را بررسی می‌کند.
- **SemVer:** MAJOR = شکستن Contract عمومی/Hook/REST؛ MINOR = قابلیت؛ PATCH = رفع باگ.
- **انتشار:** Tag ← CI build ← zip تمیز (بدون dev files، با vendor prefixشده) ← Plugin Check ← تست ارتقا روی داده نمونه نسخه قبل.

## 11. مستندسازی
- `docs/` (معماری و تصمیم‌ها — این پوشه)
- `docs/hooks.md` تولید خودکار از DocBlockهای `@hook`
- مرجع REST API (`docs/api.md`) — دستی، کنار Schemaها
- راهنمای کاربر (فارسی) جدا — بخشی از DoD برای قابلیت‌های کاربرمحور
- هر ماژول یک `README.md` کوتاه: مسئولیت، Contracts، Events منتشر/مصرف‌شده، جداول.
- **روند کار، Tracker و Worklog:** [../05-delivery/](../05-delivery/) — ر.ک. [../05-delivery/03-agent-workflow.md](../05-delivery/03-agent-workflow.md).
