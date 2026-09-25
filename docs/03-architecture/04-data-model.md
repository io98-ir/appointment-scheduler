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
| `logs` | `level, channel, message, context JSON, request_id, created_at` | (level, created_at) |
| `rate_limits` | `bucket` (HMAC، بدون PII)، `window_start, expires_at, hits`. بدون `created_at`: هر ردیف فقط یک پنجره عمر می‌کند | PK(bucket, window_start)، (expires_at) |

> وضعیت Migration در option `{prefix}_db_versions` نگه داشته می‌شود (یک آرایه برای همه ماژول‌ها). جدول جدا لازم نیست.

### Catalog
| جدول | ستون‌های کلیدی |
|---|---|
| `locations` | `name, timezone, address, phone, holiday_calendar_id, status, sort` |
| `staff` | `wp_user_id NULL, location_id NULL, name, search_name, title, email, phone, color, avatar_id, bio, status, sort, meta` |
| `resources` | `location_id NULL, group_key, name, capacity, status` (اتاق، صندلی، دستگاه) |
| `service_categories` | `name, color, sort` |
| `services` | `category_id, name, description, image_id, capacity, approval, status, sort, meta` |
| `service_variants` | `service_id, label, duration_min, price, buffer_before_min, buffer_after_min, slot_step_min NULL, is_default, sort` |
| `service_staff` | `service_id, staff_id, variant_id NULL, price NULL, duration_min NULL` — قیمت و مدت اختصاصی پرسنل |
| `service_resources` | `service_id, resource_group_key, quantity` |
| `extras` | `service_id NULL, name, price, duration_min, max_qty, status` |

### Scheduling
| جدول | ستون‌های کلیدی | ایندکس |
|---|---|---|
| `schedule_rules` | `owner_type (staff/resource/location), owner_id, weekday (0=شنبه), start_time, end_time, kind (work/break)` | (owner_type, owner_id) |
| `schedule_exceptions` | `owner_type, owner_id, local_date, start_time NULL, end_time NULL, kind (off/extra/blocked), note` | (owner_type, owner_id, local_date) |
| `holidays` | `calendar_id, local_date, title, source (dataset/manual)` | UNIQUE(calendar_id, local_date) |
| `resource_day_locks` | `lock_key (staff:12 / res:4), local_date` | PK(lock_key, local_date) |

> تقویم‌های تعطیلات در option نگه داشته می‌شوند (تعدادشان کم است). جدول `holidays` فقط روزها را دارد.

### Booking
| جدول | ستون‌های کلیدی | ایندکس |
|---|---|---|
| `holds` | `token_hash, variant_id, staff_id, start_at, end_at, party_size, extras JSON, price_quote JSON, expires_at` | (expires_at), UNIQUE(token_hash) |
| `appointments` | `uuid, code, customer_id, location_id, service_id, variant_id, staff_id, status, label_id NULL, payment_status, source, start_at, end_at, local_date, timezone, party_size, price_total, price_lines JSON, deposit_amount, customer_note, internal_note, cancelled_at, cancel_reason, created_by, version` | (status, start_at), (customer_id, start_at), (staff_id, start_at), UNIQUE(uuid), UNIQUE(code) |
| `occupancies` | `owner_type (hold/appointment), owner_id, lock_key, start_at, end_at, seats` | **(lock_key, start_at, end_at)**, (owner_type, owner_id) |
| `appointment_extras` | `appointment_id, extra_id, qty, price` | |
| `appointment_history` | `appointment_id, action, from_status, to_status, changes JSON, actor_type, actor_id, reason, created_at` | (appointment_id) |
| `appointment_answers` | `appointment_id, field_key, value` | (appointment_id) |
| `fields` | `scope (global/service), service_id NULL, key, type, label, required, options JSON, condition JSON, sort` | |
| `labels` | `name, color` | |
| `price_rules` | `type, service_id NULL, config JSON, priority, status` | |
| `policies` | `type, service_id NULL, config JSON` | UNIQUE(type, service_id) |
| `coupons` | `code, type, value, max_uses, used, valid_from, valid_to, service_ids JSON, status` | UNIQUE(code) |

> **`occupancies`** یک جدول واحد برای اشغال زمان است، چه Hold باشد و چه نوبت. **Buffer در `start_at` و `end_at` آن لحاظ شده است.** کوئری تداخل فقط روی همین جدول و ایندکس `(lock_key, start_at, end_at)` اجرا می‌شود. تبدیل Hold به نوبت فقط `owner_type` و `owner_id` را عوض می‌کند. با لغو نوبت، ردیف‌هایش حذف می‌شود و سابقه در `appointment_history` می‌ماند.

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
| `notification_templates` | `trigger, audience, channel, offset_min NULL, subject, body, sms_patterns JSON, enabled` | (trigger) |
| `notification_log` | `dedup_key, template_id, channel, provider, recipient_masked, status, provider_ref, error, sent_at` | UNIQUE(dedup_key) |

## 3. Migration
- هر ماژول Migrationهای خودش را دارد: کلاس‌های `Migrations/M001_Create….php` که interface `Kernel\Database\Migration` را پیاده می‌کنند و متد `up(Db)` آن‌ها **idempotent** است. ماژول آن‌ها را به ترتیب از `Module::migrations()` برمی‌گرداند. این لیست فقط اضافه‌شدنی است.
- `db_versions` برای هر ماژول تعداد migrationهای اجراشده را نگه می‌دارد، مثل `{"booking": 3}`.
- Migrator یک `GET_LOCK` بدون انتظار می‌گیرد، migrationهای جدید را به ترتیب اجرا می‌کند و `db_versions` را بعد از هر کدام به‌روز می‌کند. در activation و در boot هر درخواستی که نسخه‌اش عقب باشد اجرا می‌شود. جزئیات در implementation-notes §5.
- هیچ Schema دستی تغییر نمی‌کند. هر تغییر یک Migration جدید است، حتی قبل از انتشار (تا تست ارتقا واقعی باشد).
