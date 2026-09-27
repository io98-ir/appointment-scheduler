# Booking

Holds, appointments, their state machine and policies (architecture §3).
Built in M2.

- **Tables:** `occupancies` (data-model §2, migration
  `CreateOccupanciesTable`): the time each staff member (`staff:{id}`) and
  resource (`res:{id}`) is taken by a hold or an appointment, in UTC with
  buffers, plus `variant_id`, `staff_id` and a hold's `expires_at`.
- **Implements** `Scheduling\Contracts\OccupancyReader`
  (`WpdbOccupancyReader`): one indexed query, expired holds left out.
- **Holds** (T2.2, T2.3): `HoldService`, `POST /holds`, priced by
  `HoldPricing`.
- **Appointments** (T2.4): `Domain\Appointment\Appointment` is the state
  machine of booking-engine §4. `BookingService::confirm` and
  `POST /bookings` (staff only for now) turn a hold into an appointment in one
  transaction; the job `booking/appointment_booked` is queued in it.
- Each write to `occupancies` fires `booking/changed`, which the availability
  cache listens to (implementation-notes §4.7).
