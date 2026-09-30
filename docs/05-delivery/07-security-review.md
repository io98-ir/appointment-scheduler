# بازبینی امنیتی 1.0 (T6.4)

> تاریخ: 2026-09-30. روش: خواندن کد با چک‌لیست [principles §7](../04-engineering/01-principles.md)، اسکریپت‌های شمارش (همه routeها و همه متدهای Application)، `composer audit` و `pnpm audit`. این بازبینی روی کد main انجام شد؛ ابزار خودکار `/security-review` فقط روی diff کار می‌کند و کل کد را نمی‌بیند، پس جایگزین آن خواندن مستقیم شد.
> **Plugin Check محلی اجرا نشد** (نیاز به WordPress دارد و Docker محلی نداریم). job تازه `plugin-check` در CI نوشته شد و تا رفع billing گیتهاب اجرا نشده است.

## 1. نتیجه چک‌لیست

| مورد | نتیجه | شواهد |
|---|---|---|
| `permission_callback` واقعی | ✅ | هر 78 route از `Router::add` می‌گذرد. `ANYONE` فقط روی GET با rate limit پذیرفته می‌شود (`KernelException::unprotectedRoute`). 63 route ادمین با `current_user_can(Caps::name(...))`؛ 15 route عمومی یا با `ANYONE` یا با nonce و rate limit |
| Authorization در Application | ✅ | اسکریپت روی 98 فایل Application: 14 متد بدون فراخوانی مستقیم، که همه یا به متد دیگرِ دارای authorize می‌سپارند (`CatalogService::location()`، `AppointmentService::staffMove`) یا عمداً عمومی‌اند و با داده سمت سرور تأیید می‌شوند (`PaymentService::settle*`، `BookingService::confirmAsGuest` با Hold token) |
| SQL | ✅ | `Db` فقط `literal-string` می‌گیرد (PHPStan level 9 اجرا می‌کند) و مقدار و نام جدول با `%s` `%d` `%i` می‌روند. جستجوی الگوهای الحاق رشته ← هیچ مورد |
| Escape خروجی | ✅ | همه `echo`/`printf` در PHP با `esc_html`/`esc_attr`. در JS هیچ `dangerouslySetInnerHTML`/`innerHTML` نیست |
| ورودی و Schema | ✅ | `args` با type و حداکثر طول. پاسخ‌های سفارشی با `AnswerValidator` و کلید ناشناس دور ریخته می‌شود |
| nonce ادمین | ✅ | REST با کوکی را هسته وردپرس با `X-WP-Nonce` می‌پذیرد |
| PII در لاگ | ✅ | `Pii::mask` روی پیام، context و کلیدها |
| Rate limit عمومی | ✅ | همه routeهای عمومی. کلید IP فقط `REMOTE_ADDR` (filter برای پشت CDN) |
| OTP | ⚠️→✅ | کد 6 رقمی، HMAC، `hash_equals`، 5 تلاش، 5 دقیقه. **یافته F1 رفع شد** |
| Callback درگاه | ✅ | verify سمت سرور، تطبیق مبلغ، idempotent با `FOR UPDATE`. درگاه `offline` از callback عمومی هرگز تأیید نمی‌شود (پارامترها فقط string‌اند و `true === ` لازم است؛ تست `OfflineGatewayTest`) |
| بازگشت از درگاه | ✅ | `return_url` با `wp_validate_redirect` هم هنگام ثبت و هم هنگام redirect |
| Upload | ✅ | ندارد |
| `eval`، `unserialize`، `extract`، `$_GET/POST` | ✅ | هیچ مورد. تنها `$_SERVER['REMOTE_ADDR']` با `sanitize_text_field(wp_unslash())` |
| Secretها | ✅ | XChaCha20-Poly1305 با کلید مشتق از salt و بسته به نام؛ اولویت ثابت `wp-config`. API هرگز مقدار را برنمی‌گرداند، فقط `set` و `fixed` |
| SSRF | ✅ | همه hostهای درگاه و پیامک ثابت (`private const API`)، مدیر فقط کلید می‌دهد |
| خروجی CSV | ✅ | `csvCell` سلول‌های فرمول‌مانند را با `'` خنثی می‌کند (تست دارد) |
| Brand | ✅ | رنگ فقط `#rrggbb` (در `style` چاپ می‌شود)، لوگو فقط http(s) بدون نقل‌قول و `<>` |
| suppression کد | ✅ | 9 `phpcs:ignore` در `src/` (همه یا `Db` با prepare یا `error_log` fallback یا طول خط رشته ترجمه‌پذیر)، 3 `@phpstan-ignore` |

## 2. یافته‌ها و رفع

### F1 — شمارش تلاش OTP با خواندن قدیمی دور زده می‌شود (متوسط) ✅ رفع شد
`OtpService::verify` مقدار `attempts` را می‌خواند، بعد افزایش می‌داد. درخواست‌های موازی همگی `attempts=0` می‌دیدند و هر کدام یک حدس می‌زدند؛ کامنت کد «موازی‌ها نمی‌توانند از حد بگذرند» درست نبود. حد واقعی فقط rate limit هر IP (10 در دقیقه) بود.
**رفع:** `OtpStore::recordAttempt(id, max): bool` حالا `UPDATE … SET attempts = attempts + 1 WHERE id = ? AND attempts < ?` است و `verify` اگر یک ردیف عوض نشد، بدون مقایسه رد می‌کند. تست: `OtpServiceTest::testGuessesThatReadTheAttemptCountBeforeAnotherWroteItStillRunOutOfAttempts` با storeای که همیشه شمارش قدیمی برمی‌گرداند.

### F2 — بدنه ایمیل با فیلتر سراسری HTML به markup تبدیل می‌شد (پایین تا متوسط) ✅ رفع شد
`EmailChannel` از `wp_mail` بدون تعیین content type استفاده می‌کرد. افزونه‌های «ایمیل HTML» با فیلتر `wp_mail_content_type` همه ایمیل‌ها را HTML می‌کنند و نام مشتری (`<a href=…>`) در ایمیل به مدیر یا مشتری لینک می‌شد. (Header injection پیش‌تر با یک‌خطی‌کردن مقادیر بسته بود.)
**رفع:** برای همان یک ارسال، فیلتر با اولویت `PHP_INT_MAX` مقدار `text/plain` را برمی‌گرداند و بعد برداشته می‌شود. تست Integration: `EmailChannelTest` (فقط در CI قابل اجراست).

## 3. ریسک‌های پذیرفته‌شده (بدون رفع در 1.0)
هر کدام محدود شده‌اند اما حذف نشده‌اند؛ تصمیم محصول است که کدام را سخت‌تر کنیم.

1. **Captcha ساده.** جمع دو رقم است و توکن بدون حافظه (stateless)؛ در 120 ثانیه بارها قابل استفاده است و فقط 17 جواب ممکن دارد. فقط سرعت‌گیر است. حفاظت واقعی: rate limit هر IP (5 کد در دقیقه) و هر شماره (3 در 10 دقیقه، 1 در دقیقه).
2. **SMS pumping.** مهاجمی با IPهای متعدد می‌تواند به شماره‌های مختلف کد بفرستد و هزینه پیامک بسازد. سقف سراسری نداریم چون برای سایت پرترافیک قفل قانونی ایجاد می‌کند. پیشنهاد بعد از 1.0: سقف سراسری قابل تنظیم و هشدار در System Status.
3. **پر کردن ساعت‌ها با Hold.** هر IP تا 30 Hold در دقیقه و هر Hold 10 دقیقه می‌ماند. کاهش: WAF/CDN و filter `rest/client_ip`. پیشنهاد بعد از 1.0: سقف Hold فعال برای هر IP یا شماره.
4. **nonce مهمان مرز امنیتی نیست.** `GET /nonce` آن را به هر کسی می‌دهد؛ فقط نشان می‌دهد درخواست از یک صفحه سایت آمده (architecture §12). حفاظت واقعی rate limit و اعتبارسنجی دامنه است.
5. **هشدارهای `pnpm audit` (10 مورد، 6 high).** همه وابستگی‌های transitive ابزار build (`@wordpress/scripts` ← `markdownlint-cli`، `copy-webpack-plugin`، `webpack-dev-server`). در هیچ bundle منتشرشده نیستند: `pnpm audit --prod` و `composer audit` (با و بدون dev) صفر یافته دارند. با به‌روزشدن `@wordpress/scripts` بسته می‌شوند.
6. **`WordPress.Security.EscapeOutput.ExceptionNotEscaped`.** سیاست ثبت‌شده (implementation-notes §6): پیام Exception متن ساده است و هر جا نمایش داده می‌شود escape می‌شود. Plugin Check این را خطا می‌گیرد، پس در job آن ignore شده است. اگر wp.org کانال فروش شد، باید Exceptionها متغیر در پیام نداشته باشند.

## 4. Plugin Check
- job `plugin-check` در `.github/workflows/ci.yml`: wp-env، نصب `plugin-check`، اجرای `wp plugin check` روی افزونه با build تولیدی و بدون وابستگی dev. اول یک اجرا با warning برای خواندن، بعد اجرای فقط error که اگر خط `FILE:` ببیند شکست می‌خورد.
- استثناها: پوشه‌هایی که در zip نیستند (`node_modules`، `tests`، `tools`، `docs`، `packages`، …)، سیاست بالا و `no_plugin_readme` تا T6.6 (`readme.txt` آنجا نوشته می‌شود؛ بعد از آن از `IGNORED_CODES` برداشته شود).
- **هنوز هیچ‌بار اجرا نشده.** اولین اجرا احتمالاً یافته‌ای می‌آورد (مثلاً هشدار `DirectDatabaseQuery` و `SchemaChange` که برای جدول سفارشی انتظار می‌رود، یا فایل‌های `vendor/`). تا آن موقع «Plugin Check» این Task باز است.
- Plugin Check جایگزین چک سرپرستی wp.org نیست؛ کانال فروش هنوز تصمیم باز است (#2).

## 5. کارهای باقی‌مانده برای بستن T6.4
1. رفع billing گیتهاب و اجرای CI (همه jobها به‌ویژه `plugin-check` و `EmailChannelTest`).
2. خواندن خروجی Plugin Check و رفع خطاها یا ثبت استثنای موجه.
