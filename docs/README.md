# مستندات پروژه (نام کاری: Vaqtyar)

افزونه نوبت‌دهی عمومی و حرفه‌ای برای وردپرس، الهام‌گرفته از Medino، Bookly، نوبت‌پلاس، نوبت‌نگار و Booknetic (+Custom Duration)، ولی درست‌تر، ترکیب‌پذیرتر، سبک‌تر و قابل فروش (White-label).

## 🔴 شروع هر سشن
**[05-delivery/02-progress.md](05-delivery/02-progress.md)** ← بخش «از اینجا ادامه بده».

## فهرست
| بخش | سند | محتوا |
|---|---|---|
| **روند کار** | [05-delivery/01-roadmap.md](05-delivery/01-roadmap.md) | روند کامل تا 1.0: 7 Milestone و 46 Task با معیار «انجام‌شده» |
| | [05-delivery/02-progress.md](05-delivery/02-progress.md) | **Tracker** وضعیت همه Taskها + نقطه ادامه |
| | [05-delivery/03-agent-workflow.md](05-delivery/03-agent-workflow.md) | روش کار با Agent: شروع، اجرا و پایان سشن |
| | [05-delivery/04-worklog.md](05-delivery/04-worklog.md) | **Worklog** زمانی همه کارها |
| **تحقیق** | [01-research/01-competitors.md](01-research/01-competitors.md) | تحلیل محصولات مرجع و شکایات کاربران |
| | [01-research/02-gap-analysis.md](01-research/02-gap-analysis.md) | ماتریس مقایسه، دردها، تمایزها |
| | [01-research/03-iran-ecosystem.md](01-research/03-iran-ecosystem.md) | تقویم، پول، پیامک، درگاه، تحریم ← پیامد فنی |
| | [01-research/04-wordpress-platform.md](01-research/04-wordpress-platform.md) | WP 6.9 و 7.0، Action Scheduler، جداول سفارشی |
| **محصول** | [02-product/01-product-scope.md](02-product/01-product-scope.md) | دامنه 1.0، Backlog، آنچه انجام نمی‌دهیم، NFR |
| **معماری** | [03-architecture/01-tech-stack.md](03-architecture/01-tech-stack.md) | Stack + ساختار Repo |
| | [03-architecture/02-architecture.md](03-architecture/02-architecture.md) | لایه‌ها، 7 ماژول، Kernel، تراکنش، API، امنیت |
| | [03-architecture/03-booking-engine.md](03-architecture/03-booking-engine.md) | Availability، Hold/Lock، وضعیت‌ها، قیمت، Policy، پرداخت، اعلان |
| | [03-architecture/04-data-model.md](03-architecture/04-data-model.md) | جداول و ایندکس‌ها |
| | [03-architecture/05-decisions.md](03-architecture/05-decisions.md) | ADRها (000 تا 016) |
| **مهندسی** | [04-engineering/01-principles.md](04-engineering/01-principles.md) | ضد Overengineering، استاندارد کد، تست، امنیت، کارایی، DoD |

## ابزارهای Agent در Repo
- `CLAUDE.md`: قوانین پایه که خودکار بارگذاری می‌شوند.
- `.claude/skills/`: Skillهای `/resume`، `/next-task` و `/wrap`.
- `.claude/agents/reviewer.md`: Subagent بازبینی قبل از commit.
