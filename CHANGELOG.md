# Changelog

All notable changes to this plugin. The format follows [Keep a Changelog](https://keepachangelog.com/);
the version in the plugin header, `readme.txt` and here must be the same (`composer build:zip` checks it).

## [0.1.0] - Unreleased

The first release candidate.

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
- Email and SMS (Kavenegar, IPPanel, SMS.ir, Melipayamak) with failover, SMS patterns, templates, reminders, quiet hours and a delivery log.

### Admin
- React admin app: dashboard, calendar, appointments, customers, catalog, reports with CSV export, settings.
- White-label name, logo and colour; setup wizard; System status page; WordPress Site Health tests; switchable optional modules.
- fa_IR translation (530 strings), right-to-left layout, Jalali calendar, Persian digits.

### Security
- Every REST route has a permission callback and the application layer checks authorization again.
- Gateway and SMS secrets are encrypted at rest (or set in `wp-config.php`) and never returned by the API.
- OTP attempts are limited by the database write, not by a read.
- Emails are sent as plain text whatever other plugins do to the content type.
- See `docs/05-delivery/07-security-review.md` in the source repository for the review and the accepted risks.

### Data
- Deleting the plugin keeps the data unless "Delete all data when the plugin is deleted" is turned on (System status).
