# Scheduling

When staff, resources and locations work: weekly schedules, exceptions for
one date, and holiday calendars with the shipped Iranian dataset.

- **Domain:** `ScheduleRule` (one work or break range on a weekday, 0 =
  Saturday), `ScheduleException` (time off, extra hours or blocked time on a
  date, whole day or a range), `Holiday` (calendar slug and date). Their
  `Owner` is a staff member, resource or location by catalog id.
  `DayPlan::of()` gives one owner's working and blocked minutes on a local
  date:
  `working = (weekly work − breaks, or nothing on a holiday) ∪ extra − off`.
  Blocked time stays apart, as busy. A shift past midnight is two rules.
- **Availability (`Domain/Availability`):** `AvailabilityCalculator::slots()`
  gives the free start times of one variant on one `LocalDay` (a date in a
  time zone, turning DayPlan minutes into UTC seconds). Its inputs are
  prepared: each `StaffCandidate` and `ResourceCandidate` with working and
  blocked time and its `Occupancy` rows (buffers included). Starts are a
  grid from local midnight; a staff member takes one when the booking with
  its buffers fits their free time, the seats left by the same variant and
  every `ResourceGroup`. Staff are ordered least busy or by priority.
  Offers only: holds re-check the database under locks (ADR-004).
- **Repositories:** a weekly schedule is replaced as a whole
  (`ScheduleRuleRepository::replace`). Rules and exceptions are read for
  many owners in one query per owner type. Holidays are keyed by calendar and
  date.
- **Holiday datasets:** `assets/holidays/{jalali year}.json`, read by
  `HolidayDataset` and imported by the migration `ImportHolidays(year)`.
  Each new year is a new migration. Import keeps a day already stored, so a
  day the admin corrected wins.
- **Not yet:** Application and REST for editing schedules (with the admin
  UI, T3.2 and T3.5), the list of holiday calendars (only `ir` for now), and
  the availability query, cache and REST built on the calculator (T1.5).
- **Capabilities, events:** none yet.
- **Tables:** `schedule_rules`, `schedule_exceptions`, `holidays`
  (data-model §2, migration `CreateSchedulingTables`).
