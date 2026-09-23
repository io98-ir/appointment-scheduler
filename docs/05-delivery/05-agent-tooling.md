# ابزارهای Agent: Skillها، Pluginها و Subagentها

> چه ابزاری نصب شده، کِی استفاده شود، و **اولویت** بین راهنمای Skillها و تصمیم‌های پروژه.

## 1. قانون اولویت (مهم)
**اسناد پروژه (ADRها، principles، architecture) > Skillهای نصب‌شده > دانش عمومی.**
Skillهای عمومی برای وردپرس «معمولی» نوشته شده‌اند. هرجا با تصمیم‌های ما تعارض داشتند، **تصمیم پروژه برنده است**. تعارض‌های شناخته‌شده در §4 آمده‌اند.

## 2. Skillهای نصب‌شده (پروژه‌ای، در `.claude/skills/`، ثبت در `skills-lock.json`)
| Skill | منبع | کِی |
|---|---|---|
| `resume`، `next-task`، `wrap` | خودمان | شروع سشن، اجرای Task، پایان سشن |
| `wp-plugin-development` | WordPress/agent-skills | Bootstrap، lifecycle (activation/uninstall)، امنیت، بسته‌بندی |
| `wp-rest-api` | WordPress/agent-skills | همه Controllerهای REST، schema، permission_callback |
| `wp-block-development` | WordPress/agent-skills | بلوک فرم رزرو (T4.5) |
| `wp-phpstan` | WordPress/agent-skills | پیکربندی PHPStan (T0.2) |
| `wp-env` | WordPress/agent-skills | محیط محلی و CI (T0.10) |
| `wp-performance` | WordPress/agent-skills | بهینه‌سازی کوئری، autoload، cache (T1.5، T6.3) |
| `wp-wpcli-and-ops` | WordPress/agent-skills | دستورهای WP-CLI و عملیات روی wp-env |
| `wp-plugin-directory-guidelines` | WordPress/agent-skills | GPL، نام‌گذاری، قواعد فروش و Freemium (T6.4، T6.6) |
| `wpds` | WordPress/agent-skills | UI پنل مدیریت با WordPress Design System (M3) |
| `frontend-design` | anthropics/skills | طراحی بصری ویجت رزرو و پنل مشتری (M4) |
| `webapp-testing` | anthropics/skills | بررسی واقعی UI در مرورگر با Playwright و Screenshot |
| `test-driven-development` | obra/superpowers | هر پیاده‌سازی در Domain و Application |
| `systematic-debugging` | obra/superpowers | هر باگ یا تست شکست‌خورده، قبل از پیشنهاد Fix |
| `verification-before-completion` | obra/superpowers | قبل از هر ادعای «انجام شد»، commit یا به‌روزرسانی Tracker |

**Skillهای بررسی‌شده و آگاهانه نصب‌نشده** (برای جلوگیری از شلوغی Context و تعارض):
- `brainstorming`، `writing-plans`، `executing-plans` و `using-superpowers` از superpowers: با روند roadmap و `/next-task` ما تعارض دارند و اجباری‌اند («MUST before any response»).
- `wp-interactivity-api`: طبق ADR-007 استفاده نمی‌کنیم.
- `wp-abilities-*`: Backlog بعد از 1.0.
- `wp-block-themes`، `wp-patterns`، `wp-playground`، `blueprint`، `wordpress-router` و `wp-project-triage`: نامرتبط یا غیرضروری.
- Trail of Bits security (نیاز به CodeQL/Semgrep دارد): در T6.4 بازبینی شود. فعلاً `/security-review` داخلی و plugin `security-guidance` کافی است.

**به‌روزرسانی:** `npx skills update -p` ← بعد از آن diff را بررسی کن (Skillها با دسترسی کامل اجرا می‌شوند) و commit کن.
**بازبینی امنیتی انجام‌شده (2026-09-23):** اسکریپت‌های Skillها فقط `wp` را به‌صورت محلی اجرا می‌کنند (`perf_inspect`، `wpcli_inspect`) یا یک سرور محلی برای تست بالا می‌آورند (`with_server.py`). هیچ فراخوانی شبکه خارجی ندارند.

## 3. Pluginهای Claude Code (باید توسط کاربر نصب شوند)
این‌ها فقط از رابط کاربری نصب می‌شوند: در VS Code، دیالوگ **Manage plugins**. در CLI، دستور `/plugin install <name>@claude-plugins-official` و انتخاب **Project scope**.

| Plugin | فایده | پیش‌نیاز | وضعیت |
|---|---|---|---|
| `php-lsp` | diagnostics فوری PHP بعد از هر ویرایش + go-to-definition | `intelephense` ✅ | ⬜ منتظر نصب توسط کاربر |
| `typescript-lsp` | همان برای TS | `typescript-language-server` ✅ | ⬜ منتظر نصب توسط کاربر |
| `security-guidance` | بازبینی امنیتی خودکار هر تغییر | — | ⬜ منتظر نصب توسط کاربر |

## 4. تعارض‌های شناخته‌شده Skillها با تصمیم‌های پروژه
| Skill | راهنمای Skill | تصمیم ما |
|---|---|---|
| `wp-plugin-development` | «Prefer Settings API» | تنظیمات از طریق REST و React Admin (ADR-007). از Settings API استفاده نمی‌شود |
| `wp-plugin-development` | «custom tables only if necessary» | جداول سفارشی تصمیم قطعی است (ADR-003) |
| `wp-plugin-development` و بقیه | اجرای `node skills/wp-project-triage/...` | این Skill نصب نیست. مسیر واقعی اسکریپت‌ها `.claude/skills/<name>/scripts/` است. Triage لازم نیست، چون ساختار پروژه را می‌دانیم |
| `wp-plugin-development` | Targets WP 7.0+ | حداقل ما WP 6.6 است (ADR-002). از APIهای 6.9+ فقط شرطی استفاده کن |
| `test-driven-development` | «کدِ بدون تست را حذف کن» | برای Domain و Application اجرا می‌شود. برای فایل پیکربندی و اسکلت (T0.1، T0.2) استثناست (خود Skill هم Configuration را استثنا کرده) |
| `frontend-design` | طراحی جسورانه و متمایز | در چارچوب محدودیت‌های ما: RTL، Logical properties، Vazirmatn، بودجه 40KB، WCAG AA و White-label (رنگ‌ها از متغیر CSS) |

## 5. Subagentها
| Agent | کِی |
|---|---|
| `reviewer` (پروژه) | قبل از هر commit در `/next-task` |
| `Explore` (داخلی) | جستجوی گسترده در کد، وقتی نتیجه کافی است و Context اصلی نباید شلوغ شود |
| `claude-code-guide` (داخلی) | سؤال درباره خود Claude Code (hooks، plugins، settings) |
| Worktree موازی | فقط برای Taskهای **واقعاً مستقل** (مثلاً دو Adapter پیامک در T5.5) |
