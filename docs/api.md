# مرجع REST API

> دستی نوشته می‌شود و کنار Schemaهای PHP به‌روز می‌ماند (principles §11). نوع‌های TypeScript همین شکل‌ها در `packages/shared/src/api-types.ts` هستند.
> Namespace از `Identity::REST_NAMESPACE` می‌آید (فعلاً `vaqtyar/v1`). قواعد عمومی (JSON به‌صورت `snake_case`، پول، Pagination و Envelope خطا) در architecture §8 و §9 آمده‌اند.

## خطاها
| وضعیت | `code` | کِی |
|---|---|---|
| 400 | `rest_invalid_param`، `rest_missing_callback_param` | نوع یا بازه پارامتر با Schema نمی‌خواند. وردپرس این را **قبل از** بررسی مجوز برمی‌گرداند و `request_id` ندارد |
| 401 / 403 | `rest_forbidden` | مهمان / کاربر بدون Capability |
| 404 | `{item}_not_found` (مثل `location_not_found`) | شناسه وجود ندارد یا حذف شده است |
| 409 | `slot_taken` | درخواست معتبر است ولی وضعیت فعلی اجازه نمی‌دهد (مثلاً اسلات در این فاصله گرفته شد) |
| 422 | کد قاعده دامنه (مثل `invalid_timezone`، `unknown_location`) | مقدار قاعده‌ای را نقض می‌کند |
| 503 | `busy` | قفل‌ها بعد از چند بار تلاش هنوز گرفته بودند. همراه `Retry-After` است و درخواست را می‌شود تکرار کرد |
| 500 | `internal_error` | باگ یا خطای سرور. جزئیات فقط در لاگ، زیر همان `request_id` |

## کاتالوگ (Admin)
همه routeها Capability `manage_catalog` لازم دارند (پیش‌فرض: administrator). `CatalogService` آن را دوباره بررسی می‌کند.

| Route | کار |
|---|---|
| `GET /{resource}` | یک صفحه با `page` و `per_page` (حداکثر 100) و هدرهای `X-WP-Total` و `X-WP-TotalPages`. ترتیب: `sort` و بعد `id` (منابع و Extraها فقط `id`) |
| `POST /{resource}` | ساخت. پاسخ 201 با آیتم ذخیره‌شده |
| `GET /{resource}/{id}` | یک آیتم |
| `PUT /{resource}/{id}` | **جایگزینی کامل:** فیلدی که فرستاده نشود مقدار پیش‌فرضش را می‌گیرد. `id` فقط از URL خوانده می‌شود |
| `DELETE /{resource}/{id}` | حذف نرم (`deleted_at`). پاسخ 204 |

`{resource}` یکی از این‌هاست: `locations`، `staff`، `resources`، `service-categories`، `services`، `extras`. فیلدها همان‌هایی هستند که در `api-types.ts` آمده‌اند.

### نکته‌ها
- رشته خالی در فیلد اختیاری (`phone`، `email`، `holiday_calendar`) یعنی «ندارد».
- **`services`** یک‌جا ذخیره می‌شود: Variantها، `staff` و `resources`. Variant با `id` همان Variant ذخیره‌شده است، Variant بدون `id` جدید است، و Variant ذخیره‌شده‌ای که در درخواست نیامده حذف نرم می‌شود. `id` هر Variant باید مال همین خدمت باشد (`unknown_variant`). تخصیص پرسنل فقط به Variant ذخیره‌شده ممکن است، پس Variant جدید اول ذخیره می‌شود.
- مرجع‌ها بررسی می‌شوند: `unknown_location`، `unknown_category`، `unknown_staff`، `unknown_service`. حساب وردپرس (`unknown_user`) و تصویر (`invalid_image`، فقط attachment تصویری) هم بررسی می‌شوند.
- شعبه‌ای که پرسنل یا منبع حذف‌نشده دارد حذف نمی‌شود (`location_in_use`)، چون ساعات کاری آن‌ها در منطقه زمانی همان شعبه حساب می‌شود.
- حذف دسته، خدمت یا پرسنل آبشاری نیست. ارجاع به آیتم حذف‌شده می‌ماند و `CatalogApi` آن را نادیده می‌گیرد.

## Availability (عمومی)
`GET /availability` بدون ورود در دسترس است (`permission_callback` عمومی فقط برای GET) و rate limit دارد: 120 درخواست در دقیقه برای هر کلاینت. پاسخ فقط **پیشنهاد** است و Hold دوباره زیر قفل از DB بررسی می‌کند (ADR-004).

| پارامتر | نوع | توضیح |
|---|---|---|
| `variant` | int، الزامی | Variant قابل رزرو. در غیر این صورت 404 `variant_not_found` |
| `location` | int، الزامی | شعبه فعال. در غیر این صورت 404 `location_not_found`. پرسنل و منبع شعبه‌های دیگر کنار گذاشته می‌شوند |
| `view` | `day` (پیش‌فرض)، `month`، `first` | |
| `date` | `YYYY-MM-DD` | روز (یا شروع بازه) به تقویم میلادی و در منطقه زمانی شعبه. پیش‌فرض: امروزِ شعبه |
| `days` | 1 تا 62، پیش‌فرض 31 | طول بازه `month`، یا افق جستجوی `first` |
| `staff` | int | پرسنل انتخابی. اگر این خدمت را در این شعبه ارائه ندهد: 422 `unknown_staff` |
| `extras[]` | int[] | Extraها. تکرار یک id یعنی یک واحد بیشتر. Extra خارج از خدمت یا بیشتر از `max_qty`: 422 `invalid_extra` |
| `party_size` | 1 تا 1000، پیش‌فرض 1 | بیشتر از ظرفیت خدمت: 422 `party_too_large` |

پاسخ‌ها (نوع‌ها در `api-types.ts`):
- **day:** `{timezone, date, status, slots: [{start, end, staff_ids, seats_left}]}`. `staff_ids` به ترتیب تخصیص است (`least_busy` یا `priority`) و `end` و `seats_left` مال اولین پرسنل است.
- **month:** `{timezone, days: [{date, status}]}`.
- **first:** `{timezone, date | null, slots}`.

`status` یکی از این‌هاست: `available` (شروع آزاد دارد)، `full` (کسی کار می‌کند ولی جایی نمانده، یا شروع‌های باقیمانده از مهلت گذشته‌اند)، `closed` (کسی کار نمی‌کند: تعطیل، مرخصی، یا بیرون از بازه رزرو).

## Hold (عمومی)
`POST /holds` یک شروع را برای مشتری نگه می‌دارد تا فرم رزرو را پر کند (booking-engine §3). بدون ورود در دسترس است، ولی header `X-WP-Nonce` با nonce `wp_rest` سایت لازم است (بدون آن: 401 `rest_forbidden`)، و rate limit دارد: 30 درخواست در دقیقه برای هر کلاینت.

| پارامتر | نوع | توضیح |
|---|---|---|
| `variant`، `location`، `staff`، `extras[]`، `party_size` | | مثل `GET /availability`، با همان خطاها |
| `coupon` | string، 1 تا 64 کاراکتر | کد تخفیف، بدون حساسیت به بزرگی و کوچکی حروف. کد ناموجود: 422 `coupon_not_found`. غیرفعال، بیرون از بازه، مال خدمت دیگر، یا تمام‌شده: 422 `coupon_inactive`، `coupon_expired`، `coupon_not_applicable`، `coupon_used_up` |
| `start` | ISO-8601 با offset، الزامی | یکی از `start`های `GET /availability`، مثل `2026-10-03T10:00:00+03:30`. بدون offset: 422 `invalid_start` |

- **201:** `{token, expires_at, staff_id, start, end, price}`. زمان‌ها با offset همان `start` درخواست هستند. `price` برابر `{total, lines: [{code, amount, ref, qty}]}` است؛ `code` یکی از `base`، `time_rule`، `extra`، `party`، `coupon` و `rounding` است و `ref` شناسه Extra، Price rule یا کوپن. تخفیف منفی است. این قیمت با Hold ذخیره می‌شود و تغییر تعرفه بعد از آن اثری ندارد. `token` فقط همین‌جا برمی‌گردد (فقط هش آن ذخیره می‌شود) و برای تأیید نوبت (T2.4) لازم است.
- **409 `slot_taken`:** شروع دیگر آزاد نیست، روی شبکه اسلات نیست، یا بیرون از بازه رزرو است. کلاینت باید availability را دوباره بگیرد.
- Hold بعد از 10 دقیقه منقضی می‌شود. تمدید (موقع رفتن به درگاه) هر بار 10 دقیقه است و از 20 دقیقه بعد از ساخت جلوتر نمی‌رود.

## مشتریان (Admin)
همه routeها capability `manage_customers` لازم دارند (پیش‌فرض: administrator). `CustomerService` آن را دوباره بررسی می‌کند. شکل‌ها همان `Customer` در `api-types.ts` است.

| Route | کار |
|---|---|
| `GET /customers?search=` | یک صفحه (`page`، `per_page`) با `X-WP-Total`، جدیدترین اول. `search` (تا 100 کاراکتر) بخشی از نام یا ایمیل را پیدا می‌کند، بعد از نرمال‌سازی (ی و ک عربی، ارقام فارسی، نیم‌فاصله، حروف کوچک). اگر `search` عدد باشد، بخشی از شماره تلفن را هم پیدا می‌کند (صفر اول نادیده گرفته می‌شود) |
| `POST /customers` | ساخت. پاسخ 201. `uuid` را سرور می‌سازد |
| `GET /customers/{id}` | یک مشتری |
| `PUT /customers/{id}` | جایگزینی کامل. `uuid` هرگز عوض نمی‌شود |
| `DELETE /customers/{id}` | حذف نرم. پاسخ 204. شماره تلفن آزاد می‌شود تا همان شخص دوباره ثبت شود |

- `phone` الزامی است و به E.164 تبدیل می‌شود (ارقام فارسی و فاصله مشکلی ندارند). حداقل یکی از `first_name` و `last_name` لازم است (`invalid_name`).
- **409 `phone_taken`:** شماره مال مشتری دیگری است. **409 `user_taken`:** حساب وردپرس مال مشتری دیگری است.
- **422:** `invalid_phone`، `invalid_email`، `invalid_date` (`birth_date` به‌صورت `YYYY-MM-DD` میلادی)، `invalid_tag` یا `too_many_tags` (حداکثر 20 برچسب، هر کدام تا 50 کاراکتر؛ تکراری‌ها حذف می‌شوند)، `unknown_user`.
- با حذف حساب وردپرس، `wp_user_id` مشتری `null` می‌شود و خود مشتری می‌ماند.

## ثبت نوبت (Admin)
`POST /bookings` یک Hold را به نوبت تبدیل می‌کند (booking-engine §3). فعلاً فقط برای کاربر با capability `manage_bookings` است (پیش‌فرض: administrator). رزرو خود مشتری از ویجت در T4.2 اضافه می‌شود.

| پارامتر | نوع | توضیح |
|---|---|---|
| `hold_token` | string، 43 کاراکتر، الزامی | `token` پاسخ `POST /holds` |
| `customer_id` | integer، الزامی | شناسه مشتری (`/customers`) |
| `customer_note` | string، تا 2000 کاراکتر | یادداشت مشتری |
| `answers` | object | پاسخ فیلدهای سفارشی خدمت، به‌صورت `field_key: value` (T2.6) |

- **201:** `{id, uuid, code, status, payment_status, staff_id, start, end, price}`. `code` کد پیگیری 8 کاراکتری است. `status` فعلاً همیشه `confirmed` است و `payment_status` برابر `unpaid`. زمان‌ها با offset شعبه هستند. `price` همان قیمت Hold است.
- **404 `hold_not_found`:** توکن ناشناخته، منقضی، یا قبلاً تأییدشده است. هر توکن فقط یک‌بار کار می‌کند.
- **409 `service_unavailable`:** خدمت یا شعبه بعد از Hold غیرفعال یا حذف شده است.
- **422 `customer_unavailable`:** مشتری وجود ندارد، حذف شده یا مسدود است. Hold سر جایش می‌ماند.
- **422 `coupon_*`:** کوپن Hold دیگر قابل استفاده نیست، مثلاً ظرفیتش در این فاصله تمام شده است.
- **422 `answer_required`:** یک فیلد سفارشی الزامی (که شرط نمایشش هم برقرار است) بی‌پاسخ مانده است.
- **422 `invalid_answer`:** پاسخ با نوع فیلد (متن، عدد، یکی از گزینه‌های select یا checkbox) نمی‌خواند.
- **401/403:** بدون ورود یا بدون capability.

فیلدهای هر خدمت، فیلدهای سراسری به‌علاوه فیلدهای همان خدمت‌اند (`fields`، data-model §2). هر فیلد `type` (`text`، `textarea`، `number`، `select`، `checkbox`)، `required` و یک شرط ساده اختیاری `show_if: {field, equals}` دارد: فقط وقتی پاسخ validate‌شده یک فیلد نمایان **قبلی** برابر مقدار باشد نمایش داده و بررسی می‌شود (checkbox به `"1"` یا `"0"` و عدد با ارقام لاتین مقایسه می‌شود)؛ در غیر این صورت نه الزامی است و نه ذخیره می‌شود. مدیریت (ایجاد/ویرایش) فیلدها در T3.5 می‌آید؛ فعلاً فقط از دیتابیس خوانده می‌شوند.

## تغییر نوبت (Admin)
هر سه route برای `manage_bookings` هستند. پاسخ موفق 200 است و همان شکل پاسخ `POST /bookings` را دارد. `cancel` و `reschedule` این‌ها را هم دارند: `decision: {allowed, reason_code, refund_percent, refund}` و `overridden`. `refund` تا M5 صفر است.

| Route | پارامترها | خطاها |
|---|---|---|
| `POST /appointments/{id}/cancel` | `reason` (تا 1000)، `override` (bool) | 409 `policy.already_started` یا `policy.cancel_window_passed`؛ 409 `invalid_transition` |
| `POST /appointments/{id}/reschedule` | `start` (ISO با offset، الزامی)، `staff`، `reason`، `override` | 409 `slot_taken`، `policy.reschedule_window_passed`، `policy.reschedule_limit_reached`، `invalid_transition` (فقط `confirmed` جابجا می‌شود) |
| `POST /appointments/{id}/no-show` | — | 409 `not_started` (قبل از شروع)؛ 409 `invalid_transition` |

- `override: true` به capability `override_policies` و `reason` غیرخالی نیاز دارد (403، 422 `reason_required`).
- 404 `appointment_not_found`. 409 `appointment_changed` یعنی نوبت همزمان تغییر کرد و باید دوباره تلاش کرد.
