# Worklog

> گزارش زمانی همه کارها. **جدیدترین ورودی بالاست.** این فایل Append-only است: ورودی‌های قبلی ویرایش نمی‌شوند، فقط اصلاحیه اضافه می‌شود.
>
> قالب هر ورودی:
> ```
> ## YYYY-MM-DD — سشن N — <عنوان کوتاه>
> **Taskها:** T…
> **انجام شد:** …
> **تصمیم‌ها و فرض‌ها:** …  (هر فرضی که کاربر باید بداند)
> **تأیید:** دستورهایی که اجرا شد و نتیجه (مثلاً `composer test` ← 124 passed)
> **مشکلات و باقیمانده:** …
> **قدم بعدی:** …
> **Commitها:** <hash> <message>
> ```

---

## 2026-09-23 — سشن 1 (بخش 2) — بازنگری طبق نظر کارفرما + زیرساخت روند کار
**Taskها:** P.3، P.4
**انجام شد:**
- `git init` (شاخه `main`).
- **ADR-000:** نام قابل تعویض طراحی شد: `identity.json` + Helperهای نام + `tools/rename.php` + White-label برای نام نمایشی.
- ADR-008 (PSR-12 + Sniffهای امنیتی WPCS) طبق تأیید کارفرما `Accepted` شد.
- **ADR-016:** محصول عمومی است و حوزه تخصصی ندارد. موارد Overengineering حذف شدند: Ledger، Outbox، Command Bus، league/container و Strauss، OpenAPI، Zustand، Eris و Infection، رمزنگاری بالینی.
- ماژول‌ها از 15 به 7 کاهش یافتند. مدل داده ساده شد (جدول واحد `occupancies`).
- اسناد product-scope، tech-stack، architecture، booking-engine و data-model بازنویسی شدند. principles بخش §0 ضد Overengineering گرفت.
- ساخته شد: `docs/05-delivery/` (roadmap با 46 Task، progress tracker، agent workflow، worklog)، Skillهای `/resume`، `/next-task` و `/wrap`، و Subagent `reviewer`.

**تصمیم‌ها و فرض‌ها:**
- نام کاری `Vaqtyar` / `vqy` حفظ شد، چون توکن منحصربه‌فرد برای rename امن لازم است.
- ثبت Job در Action Scheduler داخل تراکنش جایگزین Outbox شد (ADR-005).
- Reschedule همان رکورد نوبت را به‌روز می‌کند و رکورد جدید نمی‌سازد.

**تأیید:** فقط اسناد تغییر کردند. کدی وجود ندارد.
**قدم بعدی:** T0.1 (اسکلت Repo). اول بررسی نسخه‌های PHP، Composer، Node، pnpm و Docker روی سیستم.
**Commitها:** `docs: planning, architecture and delivery workflow` (commit اولیه)

## 2026-09-23 — سشن 1 (بخش 1) — تحقیق و معماری اولیه
**Taskها:** P.1، P.2
**انجام شد:**
- تحقیق روی Medino، Bookly، نوبت‌پلاس، نوبت‌نگار و Booknetic Custom Duration، به‌علاوه Booknetic core، Amelia، LatePoint و پذیرش24.
- بررسی اکوسیستم ایران (پیامک پترن، درگاه‌ها، شاپرک، تحریم، نبود DST، تعطیلات قمری).
- بررسی WP 6.9/7.0، Action Scheduler و جداول سفارشی.
- نوشته شد: `docs/01-research/*`، `docs/02-product`، `docs/03-architecture/*` (نسخه اولیه)، `docs/04-engineering/01-principles.md`، `CLAUDE.md`.

**قدم بعدی:** تأیید تصمیم‌های باز توسط کارفرما (انجام شد در بخش 2).
