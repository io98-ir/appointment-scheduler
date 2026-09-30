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

## برنامه کاری (Admin)
همه routeها Capability `manage_schedules` لازم دارند (پیش‌فرض: administrator). `ScheduleService` آن را دوباره بررسی می‌کند. `{owner_type}` یکی از `staff`، `resource`، `location` است. صاحب باید در کاتالوگ باشد (حذف‌نشده، فعال یا غیرفعال)، وگرنه 404 مثل `staff_not_found`. ساعت‌ها `HH:MM` به وقت محلی شعبه‌اند و تاریخ‌ها `YYYY-MM-DD`.

| Route | کار |
|---|---|
| `GET /schedules/{owner_type}/{owner_id}` | `{rules: [{weekday, start, end, kind}]}` به ترتیب روز و شروع. `weekday` از 0 (شنبه) تا 6 (جمعه)، `kind` یکی از `work` و `break` |
| `PUT /schedules/{owner_type}/{owner_id}` | `rules` (حداکثر 100) کل هفته را **جایگزین** می‌کند؛ آرایه خالی برنامه را پاک می‌کند. `kind` پیش‌فرض `work`. خطاها: 422 `overlapping_rules` (دو بازه هم‌نوع در یک روز هم‌پوشانی دارند؛ مماس بودن مجاز است)، `invalid_time_range`، `invalid_time` |
| `GET /schedule-exceptions` | `owner_type`، `owner_id`، `from` و `to` (هر دو شامل، الزامی، حداکثر 366 روز: 422 `invalid_range`). آرایه به ترتیب تاریخ و شروع |
| `POST /schedule-exceptions` | `owner_type`، `owner_id`، `date`، `kind` (`off` مرخصی، `extra` ساعت اضافه، `blocked` زمان مسدود)، `start` و `end` (هر دو یا هیچ‌کدام؛ هیچ‌کدام یعنی کل روز)، `note` (تا 1000). پاسخ 201. `extra` بدون ساعت: 422 `extra_needs_hours` |
| `PUT /schedule-exceptions/{id}` | جایگزینی کامل، با همان فیلدها. ناموجود: 404 `exception_not_found` |
| `DELETE /schedule-exceptions/{id}` | حذف واقعی (استثنا مرجع نوبتی نیست). پاسخ 204 |

هر نوشتن `scheduling/changed` را می‌فرستد و کش Availability باطل می‌شود.

## تعطیلات (Admin)
همه routeها Capability `manage_bookings` لازم دارند (همان Capability رزرو؛ برای یک صفحه مدیریتی دیگر Capability جدیدی لازم نیست). `{calendar}` کلیدی است که تنظیم `holiday_calendar` یک شعبه به آن اشاره می‌کند (مثلاً `ir`)؛ این route هر تقویمی را با کلیدش می‌خواند و ویرایش می‌کند، حتی پیش از آنکه شعبه‌ای آن را انتخاب کند. تاریخ‌ها `YYYY-MM-DD` میلادی‌اند.

| Route | کار |
|---|---|
| `GET /holidays` | `calendar`، `from` و `to` (هر دو شامل، الزامی، حداکثر 366 روز: 422 `invalid_range`). آرایه به ترتیب تاریخ. تقویم ناشناخته یعنی آرایه خالی، نه خطا |
| `POST /holidays` | `calendar`، `date`، `title`. یک روز از قبل موجود را جایگزین می‌کند (همان کلید calendar/date)؛ همیشه با منبع `manual` ذخیره می‌شود، حتی اگر روزی از دیتاست را جایگزین کند. پاسخ 201 |
| `DELETE /holidays/{calendar}/{date}` | حذف واقعی. ناموجود: 404 `holiday_not_found` |

دیتاست‌های سالانه (`assets/holidays`) فقط با `ImportHolidays` در Migration نوشته می‌شوند، نه از این route.

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

## منوی عمومی رزرو
`GET /catalog` بدون ورود در دسترس است (فقط GET) و rate limit دارد: 120 درخواست در دقیقه برای هر کلاینت، مثل `GET /availability`. فقط چیزی که قابل رزرو است برمی‌گردد: شعبه فعال، دسته‌ها، و خدمت فعالی که دست‌کم یک پرسنل قابل رزرو دارد (پرسنل فعال در شعبه فعال یا بدون شعبه). هیچ داده مشتری در آن نیست. حداکثر 200 مورد از هر فهرست خوانده می‌شود.

پاسخ (نوع‌ها در `api-types.ts`: `PublicMenu`): `locations` (`id`، `name`، `timezone`، `address`)، `categories` (`id`، `name`)، `services` (`id`، `name`، `category_id`، `description`، `capacity`، `variants` با `id`، `label`، `duration_min`، `price`، `is_default`، و `staff` با `staff_id`، `name`، `title`، `location_id` (`null` یعنی همه شعبه‌ها)، `variant_id` (`null` یعنی همه Variantها)، `duration_min` و `price` اختصاصی پرسنل یا `null`).

## رزرو مهمان از ویجت (عمومی)
| Route | کار |
|---|---|
| `GET /nonce` | یک nonce تازه `wp_rest` برای مهمان، چون nonce صفحه cache‌شده کهنه است. rate limit: 120 در دقیقه |
| `GET /service-fields?service=` | فیلدهای سفارشی یک خدمت (سراسری‌ها و فیلدهای خود خدمت، به ترتیب `sort`) با `field_key`، `type`، `label`، `required`، `options` و `show_if`. خدمت ناموجود: 404 `service_not_found` |
| `GET /payment-options` | `{online: bool}`: آیا مشتری می‌تواند آنلاین پرداخت کند (درگاهی جز آفلاین فعال است) |
| `POST /book` | Hold را به نوبت تبدیل می‌کند |

`POST /book` نیاز به هدر `X-WP-Nonce` (از `GET /nonce`) دارد و rate limit آن 10 در دقیقه برای هر کلاینت است. بدنه: `hold_token`، `phone` (الزامی، ارقام فارسی مشکلی ندارد)، `first_name` و `last_name` (حداقل یکی الزامی است)، `email` (اختیاری)، `customer_note`، `answers` (بر اساس `field_key`؛ checkbox فقط به‌صورت bool در بدنه JSON). مشتری با شماره پیدا یا ساخته می‌شود؛ مشتری موجود نام و ایمیلش را حفظ می‌کند و مهمان نمی‌تواند آن را عوض کند. `source` نوبت `widget` و `created_by` خالی است. **شماره هنوز تأیید نمی‌شود** (OTP در T4.3 می‌آید). با `pay_online: true` (و `return_url` از همین سایت؛ پیش‌فرض صفحه اصلی) و قیمت بیشتر از صفر، نوبت `pending_payment` می‌شود و زمانش گرفته می‌ماند، پرداخت در اولین درگاه آنلاین باز می‌شود و پاسخ `payment_url` دارد؛ اگر هیچ درگاهی جواب ندهد نوبت همان لحظه `expired` می‌شود و پاسخ 409 `payment_unavailable` است. پرداخت موفق نوبت را `confirmed` و `payment_status` را `paid` می‌کند؛ اگر تا 30 دقیقه پرداخت نیاید نوبت `expired` و زمانش آزاد می‌شود.

پاسخ 201: فقط `code` (کد پیگیری)، `status`، `start`، `end` و `price`. خطاها: 404 `hold_not_found` (توکن ناشناخته، منقضی یا مصرف‌شده)، 409 `service_unavailable`، 422 `customer_unavailable` (مشتری مسدود)، `invalid_phone`، `invalid_email`، `invalid_name`، و برای پاسخ نامعتبر `answer_required` یا `invalid_answer` که **`data.details.field_key`** فیلد خطادار را نشان می‌دهد.

## ورود با شماره (OTP، عمومی)
| Route | کار |
|---|---|
| `GET /otp/config` | `{required, code_length}`؛ `required` یعنی رزرو مهمان شماره تأییدشده می‌خواهد (تنظیم `customer_login`، پیش‌فرض خاموش تا وقتی پیامک یا درگاه خود سایت آماده است) |
| `GET /captcha` | `{a, b, token}`: جمع دو رقم. توکن امضاشده و 120 ثانیه معتبر است و چیزی ذخیره نمی‌شود |
| `POST /otp/request` | `phone`، `captcha_token`، `captcha_answer` و هدر `X-WP-Nonce`. کد 6 رقمی می‌فرستد. پاسخ 202 با `{expires_in: 300, resend_after: 60}`. کد هرگز در پاسخ نیست و معلوم نمی‌کند شماره مشتری دارد یا نه. حداکثر 5 درخواست در دقیقه برای هر کلاینت؛ برای هر شماره یک کد در دقیقه و 3 کد در 10 دقیقه (429 `otp_rate_limited` با `Retry-After`). captcha غلط: 422 `invalid_captcha` |
| `POST /otp/verify` | `phone`، `code` (ارقام فارسی مشکلی ندارد) و هدر `X-WP-Nonce`. پاسخ 200 با `{token, expires_at}`؛ `token` نشست 30 دقیقه‌ای همان شماره است. کد غلط، منقضی یا مصرف‌شده: 422 `invalid_code` (بدون اینکه بگوید کدام). کد 5 حدس مجاز دارد و 5 دقیقه عمر می‌کند و یک‌بار مصرف است |

نشست شماره یک توکن امضاشده (HMAC با `wp_salt('auth')`) از شماره و انقضاست. `POST /book` آن را در `session_token` می‌گیرد و اگر `required` باشد و توکن مال همین شماره نباشد، 422 `phone_not_verified` می‌دهد. کد را هیچ‌جا نمی‌نویسیم؛ فقط action `{prefix}/customers/otp` با `($phone, $code)` صدا زده می‌شود تا سایت آن را با درگاه خودش بفرستد (ماژول Notifications آن را با سرویس‌دهنده‌های پیامک می‌فرستد؛ بخش پیامک پایین‌تر).

## پنل مشتری (عمومی، با نشست شماره)
مشتری همان نشست شماره `POST /otp/verify` است که در هدر `X-Phone-Session` می‌آید، و هدر `X-WP-Nonce` نشان می‌دهد درخواست از خود سایت است. rate limit: 60 در دقیقه برای هر کلاینت. نشست نامعتبر، منقضی یا شماره‌ای که مشتری قابل‌رزرو ندارد: 401 یا 403.

| Route | کار |
|---|---|
| `GET /my/appointments` | تازه‌ترین 50 نوبت همان مشتری (همان شکل `GET /appointments` بدون نام مشتری)، هر کدام با `cancel` و `reschedule`: `{allowed, reason_code, refund_percent, refund}` (Policy، booking-engine §6). برای نوبتی که شروع شده، لغو یا کامل شده و امثالش هر دو `null` است |
| `POST /my/appointments/{id}/cancel` | `reason` اختیاری. پاسخ: نوبت و `decision`. نوبت مشتری دیگر: 404 `appointment_not_found`. Policy اجازه ندهد: 409 با کد دلیل (`policy.cancel_window_passed` و امثالش) |
| `POST /my/appointments/{id}/reschedule` | `start` (ISO با offset، از `GET /availability` همان Variant، شعبه و پرسنل). خطاها: 409 `slot_taken` یا `policy.reschedule_window_passed` و `policy.reschedule_limit_reached` |

مشتری هرگز Override ندارد و فقط نوبت خودش را می‌بیند. ویجت آن را با `<div data-{slug}-panel>` می‌گیرد (Shortcode در T4.5).

## جاسازی ویجت و پنل (Shortcode و بلوک)
| | |
|---|---|
| `[vaqtyar_booking]` | فرم رزرو. ویژگی‌ها (همه اختیاری): `service`، `variant`، `location`، `staff` (شناسه؛ 0 یعنی مشتری انتخاب می‌کند)، `calendar` (`jalali` پیش‌فرض یا `gregorian`)، `digits` (`latin` پیش‌فرض یا `persian`) |
| `[vaqtyar_panel]` | پنل مشتری (نوبت‌ها، لغو و جابجایی). فقط `calendar` و `digits` |
| بلوک `vaqtyar/booking` و `vaqtyar/panel` | همان ویژگی‌ها، در دسته «ابزارک‌ها». رندر سمت سرور است و ویرایشگر از `assets/blocks.js` (بدون build) می‌آید |

هر کدام یک `<div data-{slug}-widget>` یا `<div data-{slug}-panel>` با config به‌صورت JSON می‌نویسد (`restUrl` و ویژگی‌های غیرصفر) که `packages/widget` mount می‌کند. اسکریپت و استایل ویجت (`build/widget.*`) فقط وقتی اولین embed رندر شود enqueue می‌شوند، پس صفحه بدون آن چیزی بارگذاری نمی‌کند. بدون `pnpm build` مهمان چیزی نمی‌بیند و مدیر یک پیام. پوشه `assets/` باید در zip انتشار باشد (T6.6).

## پرداخت
| Route | کار |
|---|---|
| `GET /payments/callback/{gateway}` | عمومی؛ جایی که درگاه مشتری را برمی‌گرداند. پارامتر شناسه پرداخت را خود درگاه نام‌گذاری می‌کند (`Authority` در Zarinpal، `trackId` در Zibal، `authority` در آفلاین) و adapter آن را می‌خواند؛ پارامترهای query به‌صورت متن به adapter می‌رسد (هیچ‌کدام باور نمی‌شود؛ درگاه استعلام می‌شود). اگر `return` (آدرسی از همین سایت) باشد، پاسخ 302 به آن آدرس است با پارامتر `{prefix}_payment` برابر `succeeded`، `failed` یا `pending`؛ وگرنه JSON `{id, appointment_id, gateway, amount, status, ref_id}`. تکراری بودن callback بی‌اثر است. درگاه یا پرداخت ناموجود: 404 `payment_not_found`. rate limit: 60 در دقیقه |
| `POST /payments/offline/confirm` | `authority`؛ فقط با capability `manage_bookings`. ثبت اینکه پرداخت آفلاین دریافت شد |
| `POST /payments/refunds` | `payment_id`، `amount` (ریال)، `reason`؛ فقط با capability `manage_bookings`. ثبت استردادی که دستی انجام شده (در پنل درگاه یا کارت‌به‌کارت). فقط برای پرداخت موفق و تا سقف مبلغ پرداخت‌شده (جمع استردادها): وگرنه 409 `payment_not_paid` یا `refund_exceeds_payment`. پاسخ 201 با `{id}` |

جریان: `PaymentService::start()` درگاه‌ها را به ترتیب امتحان می‌کند (Failover) و یک ردیف `awaiting_callback` می‌سازد؛ callback با `settle()` یک‌بار به `succeeded` یا `failed` می‌رود (درگاه بیرون از قفل استعلام می‌شود و انتقال زیر قفل ردیف انجام می‌شود). پس از commit، action `{prefix}/payments/succeeded` با `($appointmentId, $paymentId)` اجرا می‌شود؛ Booking و Payments فقط از راه رویداد به هم می‌رسند. درگاه‌ها با filter `{prefix}/payments/gateways` ثبت می‌شوند؛ فعلاً فقط `offline`.

## اعلان‌ها (Admin)
همه routeها capability `manage_notifications` لازم دارند (پیش‌فرض: administrator). `NotificationAdminService` آن را دوباره بررسی می‌کند. `trigger` یکی از `booked`، `cancelled`، `rescheduled`، `reminder`؛ `audience` یکی از `customer`، `staff`، `admin`. `offset_min` (دقیقه پیش از شروع) فقط برای `reminder` لازم است و برای بقیه باید `null` باشد.

| Route | کار |
|---|---|
| `GET /notification-templates` | همه قالب‌ها با `{id, trigger, audience, channel, offset_min, subject, body, enabled, sms_patterns}` |
| `POST /notification-templates` | ساخت. پاسخ 201. `channel` باید یکی از کانال‌های ثبت‌شده باشد (`email`، و `sms` وقتی یک سرویس‌دهنده پیامک تنظیم شده)، وگرنه 422 `unknown_channel`. `body` الزامی و تا 2000 کاراکتر؛ `subject` تا 191. `sms_patterns` (اختیاری): `{"kavenegar": {"code": "booked", "args": ["customer_name", "code"]}}`؛ کلید شناسه سرویس‌دهنده است و خطا 422 `invalid_sms_pattern` |
| `PUT /notification-templates/{id}` | جایگزینی کامل. نبودن: 404 `template_not_found` |
| `DELETE /notification-templates/{id}` | 204 |
| `GET /notification-log` | یک صفحه (`page`، `per_page`) با `X-WP-Total`، جدیدترین اول: `{id, template_id, channel, recipient (ماسک‌شده)، status (sending/sent/failed)، provider_ref, error, sent_at, created_at}` |

**پیامک (T5.5).** سرویس‌دهنده‌ها: `kavenegar`، `ippanel`، `smsir`، `melipayamak`. همان capability `manage_notifications` و بررسی دوباره در `SmsAdminService`. هیچ‌وقت مقدار یک secret برگردانده نمی‌شود، فقط `set`.

| Route | کار |
|---|---|
| `GET /sms` | `{order, senders, otp_patterns, providers: [{id, configured, secrets: [{name, set, fixed}]}]}`. `order` ترتیب failover است و سرویس‌دهنده‌ای که در آن نیست خاموش است؛ `fixed` یعنی مقدار در wp-config.php تعریف شده |
| `PUT /sms` | جایگزینی تنظیمات: `order`، `senders` (خط ارسال هر سرویس‌دهنده)، `otp_patterns` (کد پترن کد ورود هر سرویس‌دهنده) و `secrets` (`{sms_kavenegar_key: "…"}`؛ نیامدن یعنی بدون تغییر، رشته خالی یعنی حذف). خطاها: 422 `invalid_sms_config`، `unknown_secret`، `secret_in_config` |
| `POST /sms/test` | `phone` و `text` (اختیاری). از ترتیب failover می‌فرستد و `{reference: "provider:ref"}` می‌دهد؛ اگر همه رد کنند 422 `sms_failed` با دلیل هر کدام. 5 بار در دقیقه |

یک سرویس‌دهنده وقتی «تنظیم‌شده» است که همه secretهایش (`sms_kavenegar_key`، `sms_ippanel_key`، `sms_smsir_key`، `sms_melipayamak_username` و `sms_melipayamak_password`) و (به‌جز Kavenegar) خط ارسالش را داشته باشد. تا یکی تنظیم نشده کانال `sms` ثبت نیست و قالب‌های پیامکی بی‌صدا رد می‌شوند. کد ورود (OTP) هم با همین سرویس‌دهنده‌ها می‌رود.

جای‌نگه‌دارها (در `subject` و `body`): `{code}`، `{customer_name}`، `{service}`، `{staff}`، `{location}`، `{date}`، `{time}`، `{end_time}`، `{party_size}`، `{total}` (ریال، با جداکننده هزار). نام ناشناخته خالی می‌شود.

## برند، Onboarding و تنظیم درگاه (Admin، T6.1)
| Route | توضیح |
|---|---|
| `GET /brand` | `{name, logo_url, color}`؛ رشته خالی یعنی پیش‌فرض (نام محصول، بدون لوگو، رنگ تم) |
| `PUT /brand` | جایگزینی. `name` تا 60 کاراکتر بدون `<` و `>`، `logo_url` آدرس http یا https تا 500 کاراکتر، `color` به شکل `#rrggbb` (به حروف کوچک ذخیره می‌شود). خطاها: 422 `invalid_brand_name`، `invalid_brand_logo`، `invalid_brand_color` |
| `GET /onboarding` | `{done}`: آیا ویزارد نصب تمام یا رد شده است |
| `PUT /onboarding` | `done` (بولی، الزامی). `false` ویزارد را دوباره نشان می‌دهد |
| `GET /payments/settings` | `{gateways: [{id, secret, set, fixed}], woocommerce: {available, enabled}}`. `id` یکی از `zarinpal` و `zibal`؛ `secret` نام secret همان درگاه؛ `fixed` یعنی مقدار در wp-config.php تعریف شده. merchant id هرگز برنمی‌گردد |
| `PUT /payments/settings` | `secrets` (مثل `{zarinpal_merchant: "…"}`؛ نیامدن یعنی بدون تغییر، رشته خالی یعنی حذف) و `woocommerce` (بولی؛ نیامدن یعنی بدون تغییر). merchant id فقط حروف، رقم و خط تیره تا 64 کاراکتر است. خطاها: 422 `unknown_secret`، `secret_in_config`، `invalid_merchant`؛ اگر یکی رد شود هیچ‌کدام ذخیره نمی‌شود |

`/brand` و `/onboarding` capability `access_admin` و `/payments/settings` capability `manage_payments` لازم دارند (پیش‌فرض: administrator)؛ سرویس Application هر دو را دوباره بررسی می‌کند. نام و رنگ برند در منوی wp-admin، هدر برنامه Admin و ویجت (متغیر CSS `--vqy-accent` روی عنصر embed) اعمال می‌شود.

## System Status و ماژول‌ها (Admin، T6.2)
| Route | توضیح |
|---|---|
| `GET /status` | `{versions: {plugin, wordpress, php, database}, checks: [{id, status, label, description}], queue: {pending, late, failed}, schema: {owner: count}, modules: [{id, switchable, enabled}], errors: [{at, channel, message}]}`. `status` یکی از `good`، `recommended`، `critical`؛ `at` به UTC؛ `errors` ده خطای آخر لاگ، جدیدترین اول |
| `PUT /modules/{id}` | `enabled` (بولی، الزامی). پاسخ `{modules}` بعد از تغییر. اثرش از درخواست بعد است. خطا: 422 `module_not_switchable` برای ماژول هسته یا ناشناخته |

هر دو capability `manage_system` می‌خواهند (پیش‌فرض: administrator) و `StatusService` دوباره بررسی می‌کند. ماژول‌های خاموش‌شدنی: `notifications` و `widget`. همین بررسی‌ها زیر Tools ← Site Health هم هستند (تست direct با کلید `{PREFIX}_{id}`).

## مشتریان (Admin)
همه routeها capability `manage_customers` لازم دارند (پیش‌فرض: administrator). `CustomerService` آن را دوباره بررسی می‌کند. شکل‌ها همان `Customer` در `api-types.ts` است.

| Route | کار |
|---|---|
| `GET /customers?search=&status=` | یک صفحه (`page`، `per_page`) با `X-WP-Total`، جدیدترین اول. `search` (تا 100 کاراکتر) بخشی از نام یا ایمیل را پیدا می‌کند، بعد از نرمال‌سازی (ی و ک عربی، ارقام فارسی، نیم‌فاصله، حروف کوچک). اگر `search` عدد باشد، بخشی از شماره تلفن را هم پیدا می‌کند (صفر اول نادیده گرفته می‌شود). `status` اختیاری است (`active` یا `blocked`)؛ نبودنش یعنی هر دو (T3.5) |
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

## خواندن نوبت‌ها (Admin)
هر سه route برای `manage_bookings` هستند (T2.8). زمان‌ها با offset شعبه و پول به‌صورت `{amount, currency}` است. شکل‌ها در `api-types.ts` (`AppointmentListItem` و `AppointmentDetail`) است.

| Route | پارامترها |
|---|---|
| `GET /appointments` | `page`، `per_page`؛ `status` (آرایه)، `staff` (آرایه id)، `service`، `location`، `customer`؛ `from` و `to` (`YYYY-MM-DD`، تاریخ محلی شعبه، هر دو شامل)؛ `search` (تا 100)؛ `orderby` (`start` یا `created`، پیش‌فرض `start`)؛ `order` (`asc` یا `desc`، پیش‌فرض `desc`) |
| `GET /appointments/{id}` | — |
| `GET /calendar` | `from` و `to` (ISO با offset یا `Z`، با یا بدون کسر ثانیه مثل خروجی `toISOString()`، الزامی)، `staff` (آرایه id)، `location` |

- **لیست:** یک صفحه با `X-WP-Total` و `X-WP-TotalPages`. همه فیلترها با هم (AND) اعمال می‌شوند. آرایه‌ها به‌صورت `status[]=confirmed&status[]=completed` یا `status=confirmed,completed` فرستاده می‌شوند.
- **`search`:** کد پیگیری (بزرگ و کوچک و ارقام فارسی مهم نیست)، یا بخشی از نام، ایمیل یا تلفن مشتری، همانند `GET /customers`. فقط جدیدترین 200 مشتری مطابق در نظر گرفته می‌شوند.
- **هر آیتم:** `{id, code, status, payment_status, customer_id, customer, location_id, service_id, variant_id, staff_id, start, end, party_size, total}`. `customer` برابر `{id, name, phone, deleted}` است. برای مشتری حذف‌شده `deleted: true` و `phone: null` است.
- **جزئیات:** همان آیتم به‌علاوه `uuid`، `source`، `price` (همان `PriceQuote`)، `customer_note`، `internal_note`، `extras` (`{extra_id, qty, unit_price}`)، `answers` (شیء `field_key: value`)، `history` (قدیمی‌ترین اول: `{action, from, to, changes, actor_type, actor_id, reason, at}`)، `created_by`، `created_at`، `cancelled_at` و `cancel_reason`. 404 `appointment_not_found`.
- **تقویم:** همه نوبت‌هایی که با `[from, to)` تداخل دارند و وقت می‌گیرند (لغوشده و منقضی نه)، به ترتیب شروع و بدون صفحه‌بندی، حداکثر 2000 آیتم. هر آیتم همان شکل لیست را دارد.
- **422:** `invalid_range` (`from` بعد از `to`)، `invalid_date`، `invalid_time`، `range_too_long` (تقویم بیش از 42 روز)، `too_many_appointments` (بیش از 2000 نوبت در بازه؛ بازه یا پرسنل را محدودتر کنید). **400 `rest_invalid_param`:** وضعیت ناشناخته یا نوع اشتباه.

## تغییر نوبت (Admin)
هر سه route برای `manage_bookings` هستند. پاسخ موفق 200 است و همان شکل پاسخ `POST /bookings` را دارد. `cancel` و `reschedule` این‌ها را هم دارند: `decision: {allowed, reason_code, refund_percent, refund}` و `overridden`. `refund` تا M5 صفر است.

| Route | پارامترها | خطاها |
|---|---|---|
| `POST /appointments/{id}/cancel` | `reason` (تا 1000)، `override` (bool) | 409 `policy.already_started` یا `policy.cancel_window_passed`؛ 409 `invalid_transition` |
| `POST /appointments/{id}/reschedule` | `start` (ISO با offset، الزامی)، `staff`، `reason`، `override` | 409 `slot_taken`، `policy.reschedule_window_passed`، `policy.reschedule_limit_reached`، `invalid_transition` (فقط `confirmed` جابجا می‌شود) |
| `POST /appointments/{id}/no-show` | — | 409 `not_started` (قبل از شروع)؛ 409 `invalid_transition` |
| `POST /appointments/{id}/complete` | — | 409 `not_started` (قبل از شروع)؛ 409 `invalid_transition` (فقط `confirmed`) |
| `POST /appointments/{id}/approve` | — | 409 `invalid_transition` (فقط `pending_approval`). زمان از قبل گرفته شده، پس قفلی لازم نیست |
| `PUT /appointments/{id}/note` | `note` (الزامی، تا 5000) | 404 `appointment_not_found`. پاسخ 204. یادداشت داخلی را جایگزین می‌کند و یک ردیف `note` بدون متن به history اضافه می‌کند |

- `override: true` به capability `override_policies` و `reason` غیرخالی نیاز دارد (403، 422 `reason_required`).
- 404 `appointment_not_found`. 409 `appointment_changed` یعنی نوبت همزمان تغییر کرد و باید دوباره تلاش کرد.

## Policy لغو و جابجایی (Admin)
همه routeها capability `manage_bookings` لازم دارند (همان `POST /bookings`). `{type}` یکی از `cancellation` یا `reschedule` است و `{service_id}` صفر برای Policy سراسری یا شناسه یک خدمت (باید در کاتالوگ باشد، حذف‌نشده، فعال یا غیرفعال؛ وگرنه 404 `service_not_found`). Policy خدمت بر سراسری مقدم است و نبود هیچ‌کدام یعنی لغو و جابجایی تا شروع آزاد و استرداد 100% (booking-engine §6).

| Route | کار |
|---|---|
| `GET /policies/{type}/{service_id}` | `{config}`. `config` برابر `null` است وقتی این سطح تنظیم نشده |
| `PUT /policies/{type}/{service_id}` | Upsert. `cancellation`: `notice_hours` (عدد یا `null`)، `refund` (آرایه `{hours, percent}`، حداکثر 50 پله). `reschedule`: `notice_hours`، `max_times` (عدد یا `null`، یعنی بدون سقف). پاسخ 200 با `{config}` ذخیره‌شده |
| `DELETE /policies/{type}/{service_id}` | حذف این سطح؛ سطح پایین‌تر (سراسری، یا در نهایت آزاد) اعمال می‌شود. پاسخ 204 |

ردیف خراب در جدول تنها روی مسیر رزرو (`WpdbPolicyReader`) نادیده گرفته می‌شود؛ این API همیشه یک `config` معتبر می‌نویسد یا می‌خواند.

## فیلدهای سفارشی (Admin)
همه routeها capability `manage_bookings` لازم دارند. `scope` یکی از `global` (همه خدمت‌ها) یا `service` است؛ برای `service`، `service_id` الزامی و باید در کاتالوگ باشد (وگرنه 404 `service_not_found`). ذخیره همیشه در برابر «مجموعه در دسترس» آن فیلد بررسی می‌شود: فیلدهای سراسری، به‌علاوه فیلدهای همان خدمت برای یک فیلد `service`؛ فقط فیلدهای سراسری برای یک فیلد `global` (implementation-notes §4.13).

| Route | کار |
|---|---|
| `GET /fields` | `scope` و (برای `service`) `service_id`. آرایه به ترتیب `sort` |
| `POST /fields` | `scope`، `service_id` (فقط `service`)، `field_key`، `type` (`text`، `textarea`، `number`، `select`، `checkbox`)، `label`، `required`، `options` (فقط `select`، حداکثر 50)، `show_if` (`{field, equals}` یا `null`)، `sort`. پاسخ 201 |
| `PUT /fields/{id}` | جایگزینی کامل، با همان فیلدها. ناموجود: 404 `field_not_found` |
| `DELETE /fields/{id}` | حذف واقعی. ناموجود: 404 `field_not_found` |

خطاهای 422: `invalid_field` (کلید یا برچسب خالی، `select` بدون `options`)، `duplicate_field_key` (کلید تکراری در مجموعه در دسترس)، `invalid_show_if` (اشاره به خودش، فیلدی ناموجود، یا فیلدی با `sort` بزرگ‌تر یا مساوی؛ `sort` مساوی هم رد می‌شود چون ترتیب تضمین‌شده نیست). این بررسی‌ها فقط اینجاست؛ مسیر رزرو (`WpdbFieldReader`) نه تکراری را حذف می‌کند و نه `show_if` را اعتبارسنجی می‌کند، فقط آخرین فیلد هم‌کلید در پاسخ می‌نویسد.

## کوپن‌ها (Admin)
همه routeها capability `manage_bookings` لازم دارند. `used` را فقط رزرو (`BookingService::confirm`) می‌شمارد؛ این API آن را نمی‌نویسد و ویرایش کوپن آن را دست نمی‌زند.

| Route | کار |
|---|---|
| `GET /coupons` | آرایه، تازه‌ترین اول |
| `POST /coupons` | `code` (1 تا 64 نویسه، بدون تفاوت حرف بزرگ و کوچک یکتا)، `type` (`percent` یا `fixed`)، `value` (درصد 1 تا 100، یا ریال برای `fixed`)، `active` (پیش‌فرض `true`)، `valid_from` و `valid_to` (ISO 8601 یا `null`؛ ابتدا شامل و انتها غیرشامل)، `max_uses` (عدد ≥ 1 یا `null` یعنی بی‌سقف)، `service_ids` (آرایه یا `null` یعنی همه خدمت‌ها). پاسخ 201 |
| `PUT /coupons/{id}` | جایگزینی کامل، با همان فیلدها. ناموجود: 404 `coupon_not_found` |
| `DELETE /coupons/{id}` | حذف واقعی. ناموجود: 404 `coupon_not_found` |

خطاها: 404 `service_not_found` (یکی از `service_ids` در کاتالوگ نیست)، 409 `coupon_code_taken` (کوپن دیگری همین کد را دارد)، 422 `invalid_coupon_value` (درصد بیرون از 1 تا 100، یا مقدار کمتر از 1) و `invalid_coupon` (کد خالی یا بلندتر از 64، `valid_to` نه بعد از `valid_from`، `max_uses` کمتر از 1، یا `service_ids` خالی؛ برای همه خدمت‌ها `null` بفرست).

## قیمت زمانی (Admin)
ردیف‌های `price_rules` از نوع `time` (مثلاً +20% عصرهای جمعه). همه routeها capability `manage_bookings` لازم دارند. قاعده‌ای که به یک شروع می‌خورد درصدش را روی قیمت پایه اعمال می‌کند؛ ترتیب چند قاعده با `priority` است (بالاتر اول).

| Route | کار |
|---|---|
| `GET /time-rules` | آرایه، به ترتیب `priority` نزولی و بعد `id` |
| `POST /time-rules` | `service_id` (یا `null`، یعنی همه خدمت‌ها)، `priority` (پیش‌فرض 0)، `active` (پیش‌فرض `true`)، `weekdays` (آرایه 0 تا 6 که 0 شنبه است؛ خالی یعنی هر روز)، `from` و `to` (ساعت محلی `HH:MM`؛ `from` شامل، `to` غیرشامل، `24:00` پایان روز)، `valid_from` و `valid_to` (تاریخ محلی `YYYY-MM-DD` یا `null`، هر دو شامل)، `percent` (عدد صحیح از -100 تا 1000 و غیر از 0؛ منفی یعنی تخفیف). پاسخ 201 |
| `PUT /time-rules/{id}` | جایگزینی کامل، با همان فیلدها. ناموجود: 404 `time_rule_not_found` |
| `DELETE /time-rules/{id}` | حذف واقعی. ناموجود: 404 `time_rule_not_found` |

خطاها: 404 `service_not_found`، 422 `invalid_weekday`، `invalid_time_range` (بازه بیرون از یک روز یا `to` نه بعد از `from`)، `invalid_date_range` (`valid_to` قبل از `valid_from`) و `invalid_percent`.

## گزارش و داشبورد (Admin)
capability `manage_bookings` لازم است.

| Route | کار |
|---|---|
| `GET /reports/summary` | `from` و `to` (تاریخ محلی `YYYY-MM-DD`، هر دو شامل، حداکثر 366 روز و به ترتیب؛ وگرنه 422 `invalid_range`)، `location` (اختیاری). محاسبه بر پایه `local_date` هر نوبت است، نه UTC |

پاسخ: `totals` (`appointments` و `revenue` فقط برای نوبت‌های `confirmed` و `completed`، `cancelled`، `no_show` و `cancel_rate` که درصدِ لغوها از «تصمیم‌گرفته‌شده‌ها» یعنی booked، no_show و cancelled با یک رقم اعشار است)، `statuses` (شمار هر وضعیت)، `days` (هر روز بازه، حتی صفر)، `services` و `staff` (`id`، `appointments`، `revenue`؛ به ترتیب درآمد نزولی). `revenue` مجموع `price_total` ریال است؛ مبلغ واقعاً پرداخت‌شده با M5 می‌آید. خروجی CSV سمت Admin از `GET /appointments` ساخته می‌شود (تا 5000 ردیف، سلول‌های شبیه فرمول با آپوستروف خنثی می‌شوند).
