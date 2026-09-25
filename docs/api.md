# مرجع REST API

> دستی نوشته می‌شود و کنار Schemaهای PHP به‌روز می‌ماند (principles §11). نوع‌های TypeScript همین شکل‌ها در `packages/shared/src/api-types.ts` هستند.
> Namespace از `Identity::REST_NAMESPACE` می‌آید (فعلاً `vaqtyar/v1`). قواعد عمومی (JSON به‌صورت `snake_case`، پول، Pagination و Envelope خطا) در architecture §8 و §9 آمده‌اند.

## خطاها
| وضعیت | `code` | کِی |
|---|---|---|
| 400 | `rest_invalid_param`، `rest_missing_callback_param` | نوع یا بازه پارامتر با Schema نمی‌خواند. وردپرس این را **قبل از** بررسی مجوز برمی‌گرداند و `request_id` ندارد |
| 401 / 403 | `rest_forbidden` | مهمان / کاربر بدون Capability |
| 404 | `{item}_not_found` (مثل `location_not_found`) | شناسه وجود ندارد یا حذف شده است |
| 422 | کد قاعده دامنه (مثل `invalid_timezone`، `unknown_location`) | مقدار قاعده‌ای را نقض می‌کند |
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
