# معماری: مدل داده (نسخه 1.0)

## 1. قواعد
- **پیشوند جداول** از Identity خوانده می‌شود: `{$wpdb->prefix}{table_prefix}_` (فعلاً `wp_vqy_`). در کد، نام جدول فقط از طریق `Tables::name('appointments')` ساخته می‌شود.
- InnoDB و utf8mb4. کلید اصلی `id BIGINT UNSIGNED AUTO_INCREMENT`.
- **شناسه عمومی** (`uuid CHAR(26)`، ULID) فقط برای موجودیت‌هایی که بیرون از Admin دیده می‌شوند: appointments و customers. به‌علاوه `code` کوتاه برای کد پیگیری.
- **زمان** به‌صورت `DATETIME` در UTC است (`*_at`). تاریخ محلی `DATE` است (`local_date`).
- **پول** به‌صورت `BIGINT` ریال است. **وضعیت‌ها** `VARCHAR(32)` هستند (نه ENUM).
- ستون `meta JSON` فقط برای داده‌ای که روی آن فیلتر نمی‌زنیم.
- **FK فیزیکی نداریم** (ADR-006). روی همه ستون‌های `*_id` ایندکس داریم.
- ستون `created_at` و `updated_at` روی همه جداول هست. ستون `deleted_at` فقط روی کاتالوگ و مشتری. نوبت هیچ‌وقت حذف نمی‌شود، فقط لغو می‌شود.

## 2. جداول

### Kernel / Shared
| جدول | ستون‌ها | ایندکس |
|---|---|---|
| `logs` | `level, channel, message, context JSON, request_id, created_at`. بدون `updated_at`: ردیف فقط نوشته می‌شود. Retention سی‌روزه (implementation-notes §6.1) | (level, created_at)، (created_at) |
| `rate_limits` | `bucket` (HMAC، بدون PII)، `window_start, expires_at, hits`. بدون `created_at`: هر ردیف فقط یک پنجره عمر می‌کند | PK(bucket, window_start)، (expires_at) |

> وضعیت Migration در option `{prefix}_db_versions` نگه داشته می‌شود (یک آرایه برای همه ماژول‌ها). جدول جدا لازم نیست.

### Catalog
| جدول | ستون‌های کلیدی |
|---|---|
| `locations` | `name, timezone, address, phone, holiday_calendar, status, sort`. `timezone` نام IANA است و `holiday_calendar` Slug تقویم تعطیلات |
| `staff` | `wp_user_id NULL, location_id NULL, name, search_name, title, email, phone, color, avatar_id, bio, status, sort` |
| `resources` | `location_id NULL, group_key, name, capacity, status` (اتاق، صندلی، دستگاه) |
| `service_categories` | `name, color, sort` |
| `services` | `category_id, name, description, image_id, capacity, status, sort` |
| `service_variants` | `service_id, label, duration_min, price, buffer_before_min, buffer_after_min, slot_step_min NULL, is_default, sort, deleted_at` |
| `service_staff` | `service_id, staff_id, variant_id NULL, price NULL, duration_min NULL` — قیمت و مدت اختصاصی پرسنل |
| `service_resources` | `service_id, resource_group_key, quantity`، UNIQUE(service_id, resource_group_key) |
| `extras` | `service_id NULL, name, price, duration_min, max_qty, status` |

> **تغییرات T1.1 نسبت به طرح اولیه:**
> - ستون `approval` از `services` حذف شد، چون تأیید دستی یک Policy است (`policies` با `type = approval` و `service_id`) و دو منبع حقیقت نباید داشته باشیم.
> - ستون‌های `meta` حذف شدند، چون هنوز مصرف‌کننده‌ای ندارند (principles §0). اگر لازم شدند، با Migration جدید اضافه می‌شوند.
> - `holiday_calendar_id` به `holiday_calendar` تغییر نام داد و مقدارش Slug است (مثل `ir`)، چون تقویم‌ها در option هستند و id عددی ندارند.
> - `service_variants` ستون `deleted_at` دارد، چون نوبت‌ها به Variant ارجاع می‌دهند.
> - `service_staff` قید یکتا ندارد، چون `variant_id` برای تخصیص سراسری NULL است و UNIQUE در MySQL چند NULL را می‌پذیرد. یکتایی (پرسنل، Variant) قاعده Aggregate `Service` است.

### Scheduling
| جدول | ستون‌های کلیدی | ایندکس |
|---|---|---|
| `schedule_rules` | `owner_type (staff/resource/location), owner_id, weekday (0=شنبه), start_time, end_time, kind (work/break)` | (owner_type, owner_id) |
| `schedule_exceptions` | `owner_type, owner_id, local_date, start_time NULL, end_time NULL, kind (off/extra/blocked), note` | (owner_type, owner_id, local_date) |
| `holidays` | `calendar, local_date, title, source (dataset/manual)` | UNIQUE(calendar, local_date) |
| `resource_day_locks` | `lock_key (staff:12 / res:4), local_date` | PK(lock_key, local_date) |

> تقویم‌های تعطیلات در option نگه داشته می‌شوند (تعدادشان کم است). جدول `holidays` فقط روزها را دارد.
>
> **تغییرات T1.3 نسبت به طرح اولیه:**
> - `calendar_id` به `calendar` تغییر نام داد و مقدارش Slug است (مثل `ir`)، همانند `locations.holiday_calendar`.
> - `start_time` و `end_time` از نوع `TIME` هستند و `24:00:00` پایان روز است. `owner_type` و `kind` مثل بقیه وضعیت‌ها `VARCHAR(32)` هستند.
> - `resource_day_locks` هنوز ساخته نشد. همراه `ResourceLocker` در T2.2 می‌آید، چون تنها مصرف‌کننده آن است.
> - option فهرست تقویم‌ها هنوز ساخته نشد. تا تقویم سفارشی در UI (T3.5) فقط دیتاست `ir` وجود دارد.

### Booking
| جدول | ستون‌های کلیدی | ایندکس |
|---|---|---|
| `holds` | `token_hash, variant_id, staff_id, start_at, end_at, party_size, extras JSON, price_quote JSON, expires_at` | (expires_at), UNIQUE(token_hash) |
| `appointments` | `uuid, code, customer_id, location_id, service_id, variant_id, staff_id, status, label_id NULL, payment_status, source, start_at, end_at, local_date, timezone, party_size, price_total, price_lines JSON, deposit_amount, customer_note, internal_note, cancelled_at, cancel_reason, created_by, version` | (status, start_at), (customer_id, start_at), (staff_id, start_at), (start_at), UNIQUE(uuid), UNIQUE(code) |
| `occupancies` | `owner_type (hold/appointment), owner_id, lock_key, start_at, end_at, seats` | **(lock_key, start_at, end_at)**, (owner_type, owner_id) |
| `appointment_extras` | `appointment_id, extra_id, qty, price` | |
| `appointment_history` | `appointment_id, action, from_status, to_status, changes JSON, actor_type, actor_id, reason, created_at` | (appointment_id) |
| `appointment_answers` | `appointment_id, field_key, value` | (appointment_id) |
| `fields` | `scope (global/service), service_id NULL, key, type, label, required, options JSON, condition JSON, sort` | |
| `labels` | `name, color` | |
| `price_rules` | `type, service_id NULL, config JSON, priority, status` | |
| `policies` | `type, service_id NULL, config JSON` | UNIQUE(type, service_id) |
| `coupons` | `code, type, value, max_uses, used, valid_from, valid_to, service_ids JSON, status` | UNIQUE(code) |
| `waitlist` | `customer_id, location_id, variant_id, staff_id NULL, wanted_date, page_url, status (waiting/notified/expired), notified_at, created_at, updated_at` | (status, wanted_date)، (customer_id)، (location_id)، (variant_id) |

> **`occupancies`** یک جدول واحد برای اشغال زمان است، چه Hold باشد و چه نوبت. **Buffer در `start_at` و `end_at` آن لحاظ شده است.** کوئری تداخل فقط روی همین جدول و ایندکس `(lock_key, start_at, end_at)` اجرا می‌شود. تبدیل Hold به نوبت فقط `owner_type` و `owner_id` را عوض می‌کند. با لغو نوبت، ردیف‌هایش حذف می‌شود و سابقه در `appointment_history` می‌ماند.
>
> **تغییرات T1.5 نسبت به طرح اولیه (`occupancies`):**
> - جدول در T1.5 ساخته شد (migration `CreateOccupanciesTable` در ماژول Booking)، چون Availability آن را می‌خواند. بقیه جداول Booking در T2.1 می‌آیند.
> - سه ستون اضافه شد: `variant_id` و `staff_id` (NULL‌پذیر) تا جلسه گروهی بدون join شناخته شود (implementation-notes §4.6)، و `expires_at` (انقضای Hold، برای نوبت NULL) تا شرط `expires_at > now` بدون join به `holds` اجرا شود. **تمدید Hold در T2.2 باید `expires_at` همین ردیف‌ها را هم به‌روز کند، و تبدیل به نوبت آن را NULL کند.**
> - `lock_key` به شکل `staff:{id}` و `res:{id}` است.
>
> **T2.8:** ایندکس `(start_at)` با migration `AddAppointmentStartIndex` اضافه شد، برای لیست Admin بدون فیلتر و تقویم همه پرسنل (implementation-notes §4.15).

> **تغییرات T2.1 نسبت به طرح اولیه (بقیه جداول Booking، migration `CreateBookingTables`):**
> - `fields.key` به `field_key` و `fields.condition` به `show_if` تغییر نام داد، چون `KEY` و `CONDITION` در MySQL کلمه رزرو هستند.
> - `holds` ستون `location_id` دارد، چون تأیید Hold آن را برای نوبت لازم دارد.
> - `policies.service_id` برابر `NOT NULL DEFAULT 0` است و **0 یعنی Policy سراسری**. با NULL، قید UNIQUE جلوی دو ردیف سراسری را نمی‌گرفت (یافته reviewer).
> - `coupons` هیچ ستون `ascii` ندارد، چون کد ممکن است فارسی باشد (تله charset، implementation-notes §4.5). `code` با collation جدول است، پس بزرگی و کوچکی حروف فرقی ندارد.
> - `appointment_history` مثل `logs` ستون `updated_at` ندارد، چون ردیف فقط نوشته می‌شود.
>
> **تغییرات T2.2 نسبت به طرح اولیه:**
> - `resource_day_locks` در ماژول Booking ساخته شد (migration `CreateResourceDayLocksTable`)، چون `ResourceLocker` تنها کاربر آن است. ستون `local_date` به `day` تغییر کرد و **تاریخ UTC** است: هر دو اشغال هم‌پوشان یک کلید حداقل یک روز UTC مشترک دارند، پس Locker به timezone نیاز ندارد.
> - جدول `hold_resources` و `appointment_resources` (booking-engine §3) ساخته نمی‌شوند: همان ردیف‌های `occupancies` با `owner_type = hold` این نقش را دارند.
> - `holds.price_quote` همان `PriceQuote::toArray()` است: `{total, lines}` (T2.3).
>
> **T2.3 (`price_rules`):** فعلاً فقط `type = time` خوانده می‌شود. `service_id` برابر NULL یعنی سراسری است و ترتیب `priority DESC, id` است. `config` برابر `{"weekdays": [0-6], "from": "HH:MM", "to": "HH:MM", "valid_from": "YYYY-MM-DD"|null, "valid_to": "YYYY-MM-DD"|null, "percent": int}` است (روز هفته 0 = شنبه، `to` انحصاری، درصد از -100 تا +1000 و نه صفر). ردیفی که parse نشود نادیده گرفته می‌شود.

### Customers
| جدول | ستون‌های کلیدی | ایندکس |
|---|---|---|
| `customers` | `uuid, wp_user_id NULL, first_name, last_name, search_name, phone (E.164), email, birth_date, note, tags, status` | UNIQUE(phone), (search_name), (wp_user_id) |
| `otp_codes` | `phone, code_hash, attempts, expires_at, consumed_at, ip` | (phone, expires_at) |
| `customer_sessions` | `token_hash, customer_id, expires_at` | UNIQUE(token_hash) |

### Payments
| جدول | ستون‌های کلیدی | ایندکس |
|---|---|---|
| `payments` | `appointment_id, gateway, amount, status, authority, ref_id, card_mask, raw JSON, verified_at` | UNIQUE(gateway, authority), (appointment_id), (status, created_at) |
| `refunds` | `payment_id, appointment_id, amount, method (gateway/manual), status, reason, created_by` | (appointment_id) |

### Notifications
| جدول | ستون‌های کلیدی | ایندکس |
|---|---|---|
| `notification_templates` | `trigger_type, audience, channel, offset_min NULL, subject, body, enabled` (`sms_patterns TEXT NULL`، JSON با `{provider: {code, args}}`، T5.5) | (trigger_type, enabled) |
| `notification_log` | `dedup_key, template_id, channel, provider, recipient_masked, status (sending/sent/failed), provider_ref, error, sent_at, created_at` | UNIQUE(dedup_key), (created_at) |

> **T5.4:** `trigger` به `trigger_type` تغییر کرد چون `TRIGGER` در MySQL کلمه رزرو است. جزئیات در implementation-notes §4.19.

## 3. Migration
- هر ماژول Migrationهای خودش را دارد: کلاس‌هایی در `Infrastructure/Migrations` با نامی که کارشان را می‌گوید (مثل `CreateCatalogTables`، بدون شماره، چون جایگاه در لیست همان نسخه است) که interface `Kernel\Database\Migration` را پیاده می‌کنند و متد `up(Db)` آن‌ها **idempotent** است. ماژول آن‌ها را به ترتیب از `Module::migrations()` برمی‌گرداند. این لیست فقط اضافه‌شدنی است.
- `db_versions` برای هر ماژول تعداد migrationهای اجراشده را نگه می‌دارد، مثل `{"booking": 3}`.
- Migrator یک `GET_LOCK` بدون انتظار می‌گیرد، migrationهای جدید را به ترتیب اجرا می‌کند و `db_versions` را بعد از هر کدام به‌روز می‌کند. در activation و در boot هر درخواستی که نسخه‌اش عقب باشد اجرا می‌شود. جزئیات در implementation-notes §5.
- هیچ Schema دستی تغییر نمی‌کند. هر تغییر یک Migration جدید است، حتی قبل از انتشار (تا تست ارتقا واقعی باشد).
