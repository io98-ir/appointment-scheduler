# Booking

Holds, appointments, their state machine and policies (architecture §3).
Built in M2; for now only what availability needs.

- **Tables:** `occupancies` (data-model §2, migration
  `CreateOccupanciesTable`): the time each staff member (`staff:{id}`) and
  resource (`res:{id}`) is taken by a hold or an appointment, in UTC with
  buffers, plus `variant_id`, `staff_id` and a hold's `expires_at`.
- **Implements** `Scheduling\Contracts\OccupancyReader`
  (`WpdbOccupancyReader`): one indexed query, expired holds left out.
- **Not yet:** holds, the locker and appointments (T2.1 to T2.5). Each write
  to `occupancies` must fire an action that the availability cache listens
  to (implementation-notes §4.7).
