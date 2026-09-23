# معماری: Tech Stack

> اصل انتخاب: **کمترین وابستگی‌ای که کار را درست انجام دهد.** هر وابستگی Production جدید به یک ADR نیاز دارد. تغییر هر مورد این سند هم ADR جدید در [05-decisions.md](05-decisions.md) می‌خواهد.

## 1. سرور
| لایه | انتخاب | دلیل |
|---|---|---|
| زبان | **PHP 8.1+** (تست روی 8.1 تا 8.4) | `enum`، `readonly` و first-class callable |
| پلتفرم | **WordPress 6.6+** (تست تا 7.x) | React 18.3 در هسته، block.json v3 |
| دیتابیس | **MySQL 5.7+ / MariaDB 10.4+**، **InnoDB**، utf8mb4 | تراکنش و `SELECT … FOR UPDATE` الزامی است |
| صف پس‌زمینه | **Action Scheduler** (bundled) | پایدار و قابل مشاهده. جداولش InnoDB است، پس Job داخل همان تراکنش ما ثبت می‌شود (ADR-005) |
| HTTP خارجی | `wp_remote_*` پشت یک `HttpClient` کوچک | پراکسی، SSL و Mock در تست |
| DI | **Container کوچک اختصاصی** (حدود 100 خط، API به سبک PSR-11 بدون پکیج `psr/container`) با Factory صریح | بدون وابستگی و بدون جادو. نیازی هم به Strauss نیست (ADR-011) |
| تقویم جلالی | پیاده‌سازی خالص PHP خودمان + `ext-intl` برای فرمت، در صورت وجود | بدون وابستگی |

### وابستگی‌های Composer
- **Production:** فقط `woocommerce/action-scheduler`. بقیه کد مال خودمان است.
- **Dev:**
  - PHPUnit 9.6 + yoast/phpunit-polyfills (ADR-018)، Brain Monkey، Mockery
  - wp-phpunit (از طریق `@wordpress/env`)
  - PHPStan (level 9) + `szepeviktor/phpstan-wordpress`
  - PHP_CodeSniffer + WPCS 3 (security/i18n sniffs) + PHPCompatibilityWP + Slevomat
  - Deptrac
- اگر روزی وابستگی Production دیگری لازم شد، **Strauss** برای Prefix اضافه می‌شود (نه قبل‌تر).

## 2. Frontend
| لایه | انتخاب | دلیل |
|---|---|---|
| زبان | **TypeScript (strict)** | |
| Admin | **React** (از هسته WP، به‌صورت external) + **@wordpress/components** + **@wordpress/dataviews** | ظاهر بومی WP 7 و صفر KB برای React |
| Server state در Admin | **TanStack Query** | Cache، invalidation و optimistic update برای Drag & Drop تقویم |
| Routing در Admin | Router هش‌محور ساده خودمان (حدود 50 خط) | فقط چند صفحه داریم |
| فرم در Admin | کنترل‌های `@wordpress/components` + Validation ساده | فعلاً بدون کتابخانه فرم |
| تقویم Admin | کامپوننت اختصاصی سبک (روز با ستون پرسنل، هفته، لیست) + `@dnd-kit` | نمای Resource در FullCalendar پولی است (ADR-009) |
| ویجت رزرو و پنل مشتری | **Preact + @preact/signals** | کمتر از 40KB. در Front، React بارگذاری نمی‌شود (ADR-007) |
| تاریخ در JS | `jalaali-js` (~2KB) + `Intl.DateTimeFormat` | |
| استایل | CSS مدرن (Custom Properties، Logical Properties، `@layer`) | RTL و LTR خودکار، بدون Tailwind |
| فونت | **Vazirmatn** محلی (WOFF2) | بدون Google Fonts |
| Build | **@wordpress/scripts** + **pnpm workspaces** | تولید خودکار `*.asset.php` (ADR-015) |
| تست | **Vitest** (unit) + **Playwright** (E2E روی wp-env) + axe | |
| Lint | ESLint (`@wordpress/eslint-plugin`) + Prettier + Stylelint | |
| بودجه حجم | `size-limit` در CI | |

**قرارداد API:** Schema هر endpoint در PHP تعریف می‌شود و نوع‌های TypeScript در `packages/shared/src/api-types.ts` دستی و کنار آن نگه داشته می‌شوند. یک تست Integration شکل پاسخ را با Schema مقایسه می‌کند. تولید خودکار OpenAPI فعلاً نداریم.

## 3. Adapterهای نسخه 1.0
| Port | Adapterها |
|---|---|
| `PaymentGateway` | Zarinpal v4، Zibal، Offline (پرداخت در محل)، WooCommerce |
| `SmsProvider` | Kavenegar، IPPanel (فراز)، SMS.ir، Melipayamak، Log (برای dev) |
| `Mailer` | `wp_mail` |
| `Captcha` | Built-in سبک (ریاضی یا تصویری) |

Portها برای Google Calendar، Meeting، Messenger و غیره **فقط وقتی ساخته می‌شوند که Adapter واقعی نوشته شود.**

## 4. محیط و CI
- **@wordpress/env** (Docker) برای توسعه و تست Integration/E2E.
- **GitHub Actions:**
  - `lint`: phpcs، phpstan، deptrac، eslint، tsc
  - `test-php`: PHP {8.1, 8.4} × WP {6.6, latest}
  - `test-js`
  - `e2e`
  - `concurrency`
  - `build`: zip + Plugin Check + size-limit
- **Conventional Commits** + **SemVer** + `CHANGELOG.md`.

## 5. ساختار Repo
```
<slug>/                          # نام پوشه = slug فعلی (vaqtyar)
├── vaqtyar.php                  # Bootstrap
├── uninstall.php
├── identity.json                # ★ منبع واحد نام و پیشوندها (ADR-000)
├── composer.json  package.json  pnpm-workspace.yaml
├── src/
│   ├── Kernel/                  # Plugin، Container، ModuleRegistry، Requirements، Identity
│   ├── Shared/                  # Value Objectها، DB، REST base، Settings، Logger، Clock، Jalali
│   └── Modules/
│       ├── Catalog/             # Locations، Staff، Resources، Services، Variants، Extras
│       ├── Scheduling/          # Schedules، Exceptions، Holidays، Availability Engine
│       ├── Booking/             # Holds، Appointments، Policies، Pricing، Custom fields
│       ├── Customers/           # CRM، OTP auth، Customer portal API
│       ├── Payments/            # Gateways، Payments، Refunds، Reconciliation، WooCommerce
│       ├── Notifications/       # Templates، SMS/Email، Reminders
│       └── Admin/               # Dashboard، Reports، Settings UI، Onboarding، White-label
├── packages/                    # JS: shared/ admin/ widget/
├── blocks/                      # block.json
├── assets/                      # فونت، holidays/1405.json
├── languages/
├── templates/                   # قالب‌های PHP (ایمیل، Shortcode fallback)
├── tests/                       # Unit/ Integration/ Concurrency/ e2e/ fixtures/
├── tools/                       # rename.php، phpcs.xml، phpstan.neon، deptrac.yaml
└── docs/
```
