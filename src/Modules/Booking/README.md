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
- **Changes** (T2.5): `AppointmentService` cancels, reschedules and marks
  no-shows under the service's policies (`Domain\Policy`, `WpdbPolicyReader`);
  staff override with `override_policies` and a reason. Staff routes
  `POST /appointments/{id}/cancel|reschedule|no-show`.
- **Custom fields** (T2.6): `Domain\Field\Field` validates one answer to its
  type, `ShowIf` is a simple `{field, equals}` condition, `AnswerValidator`
  runs both over a service's fields (`FieldReader`, `WpdbFieldReader`: global
  plus the service's own, additive unlike a policy). `BookingService::confirm`
  validates `answers` inside the transaction and `AppointmentRepository::
  saveAnswers` writes `appointment_answers`. Admin CRUD for `fields` is T3.5.
- **Admin reads** (T2.8): `AppointmentBrowser` over the `AppointmentQuery`
  port (`Infrastructure\Query\WpdbAppointmentQuery`: direct SQL, no
  entities). `GET /appointments` (filters, order, pages, search by tracking
  code or customer), `GET /appointments/{id}` and `GET /calendar`.
  Customers are named through `Customers\Contracts\CustomerDirectory`, never
  a JOIN. Migration `AddAppointmentStartIndex`.
- Each write to `occupancies` fires `booking/changed`, which the availability
  cache listens to (implementation-notes §4.7).
