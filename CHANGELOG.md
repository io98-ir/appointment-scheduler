# Changelog

All notable changes to this plugin. The format follows [Keep a Changelog](https://keepachangelog.com/);
the version in the plugin header, `readme.txt` and here must be the same (`composer build:zip` checks it).

## [1.1.0] - 2026-10-01

### Changed
- The admin app has a new, minimal look in the style of io98.ir: a light page, white cards, one accent colour. Dark mode follows the system or a choice in the header.
- **Palette:** pick one of six ready palettes or any colour under Settings, Brand. Buttons, links, the calendar, charts and the WordPress components all follow it.
- **Calendar and language:** Settings, Language and calendar sets the calendar (Jalali or Gregorian), the digits and the language of the plugin's screens and booking form (Persian, English or the site's), independent of WordPress. Persian shows the name as "وقت یار", English as "Vaqtyar".
- Every date field in the admin is now a Jalali date field (typed, or from a month grid) when the calendar is Jalali, instead of the browser's Gregorian date input. Dates, times, amounts and counts follow the chosen digits.
- The shortcode and block take the calendar and digits from the site setting unless they name their own.
- The plugin's translations are now loaded on init from the plugin folder; before, the PHP side (menu name, emails) read English whatever the site language.

### Added
- A custom admin menu icon with "io98", an "io98" credit in the admin footer and the plugins list, Author and Author URI in the plugin header.
- Booking rules under Settings: step between start times, minimum notice, how far ahead booking is open, who gets a booking when the customer does not choose (`GET/PUT /booking-rules`).
- Add to calendar (.ics) for a customer after booking and in the customer panel.
- A thank-you page for the booking form (`thanks` attribute, or the block field): an address of the same site, with the tracking code added.
- Export the customers list as CSV; the shortcode of each service in the services list.
- An illustrated step-by-step guide in `docs/guide` (Persian) and a parity review against the reference plugins.

## [1.0.0] - 2026-09-30

The first release.

### Booking
- Locations, staff, resources, services with several durations and prices, add-ons and categories.
- Weekly hours, days off, breaks and holidays per person and location, with the 1405 holiday calendar.
- Availability with a short-lived cache that never decides a booking.
- Holds and confirmations under InnoDB row locks taken in a fixed order, with a concurrency test in CI.
- Cancellation, rescheduling and no-show with per-service policies and a staff override.
- Time-based prices, coupons, per-person prices, custom fields with conditions.

### Customers
- Booking form (shortcode and block) and "my appointments" page (shortcode and block) in Preact, 15 KB gzipped.
- Mobile-number verification by one-time code, with a captcha and rate limits.

### Payments
- Zarinpal and Zibal, WooCommerce (HPOS), offline payment, server-side verification, reconciliation, manual refunds.

### Notifications
- Email and SMS (Kavenegar, IPPanel, SMS.ir, Melipayamak) with failover, SMS patterns, reminders, quiet hours and a delivery log.
- A Notifications screen to write, switch off and delete the message templates, with each SMS provider's pattern code and values.

### Admin
- React admin app: dashboard, calendar, appointments, customers, catalog, reports with CSV export, settings.
- White-label name, logo and colour; setup wizard; System status page; WordPress Site Health tests; switchable optional modules.
- fa_IR translation (557 strings), right-to-left layout, Jalali calendar, Persian digits.

### Security
- Every REST route has a permission callback and the application layer checks authorization again.
- Gateway and SMS secrets are encrypted at rest (or set in `wp-config.php`) and never returned by the API.
- OTP attempts are limited by the database write, not by a read.
- Emails are sent as plain text whatever other plugins do to the content type.
- See `docs/05-delivery/07-security-review.md` in the source repository for the review and the accepted risks.

### Data
- Deleting the plugin keeps the data unless "Delete all data when the plugin is deleted" is turned on (System status).
