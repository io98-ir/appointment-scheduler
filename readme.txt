=== Vaqtyar ===
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: booking, appointments, scheduling, jalali, iran

Appointment booking for WordPress, built for Iranian businesses: Jalali calendar, Iranian payment gateways and SMS providers, and no double bookings.

== Description ==

Vaqtyar lets customers book a time with your staff from a form on your site, pay online if you want them to, and get a reminder by SMS or email. You run everything from one admin app: a calendar, the appointment list, customers, services, staff, prices, holidays and reports.

**Built for Iran**

* Jalali calendar and Persian digits everywhere, with Gregorian and Latin digits as a choice. Times are stored in UTC.
* Payment with Zarinpal and Zibal, or through WooCommerce. Amounts are whole rials, never decimals.
* SMS with Kavenegar, IPPanel, SMS.ir and Melipayamak, with automatic fallback to the next provider, and mobile-number login by one-time code.
* Persian (fa_IR) translation, right-to-left layout, and the 1405 holiday calendar built in.

**Correct by construction**

* A time is never sold twice: a hold and a confirmation take database locks in a fixed order and check the real table, not a cache. A test races 30 simultaneous requests for one slot and expects exactly one winner.
* Cancellation and rescheduling follow rules you set per service (how late a customer may cancel or move, and how many times), and staff can override them on purpose.
* Payments are verified with the gateway on the server, only once, for the amount asked. A payment that arrives after its time was released is flagged for a person, not lost.

**What you get**

* Locations, staff, resources, services with several durations and prices, add-ons, service categories, time-based prices, coupons and custom fields.
* Weekly hours, days off, breaks and holidays for each person and location.
* A booking form as a shortcode or a block, and a "my appointments" page where a customer cancels or moves their booking by phone number.
* Templates for SMS and email on booked, cancelled, rescheduled and before the appointment, with quiet hours.
* Your own name, logo and colour, a setup wizard, a System status page, and WordPress Site Health checks.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the zip from Plugins, Add New.
2. Activate it. It needs PHP 8.1, WordPress 6.6, MySQL 5.7 or MariaDB 10.4 with InnoDB.
3. Open the plugin's menu and follow the setup wizard: your brand, the location and hours, the first service, SMS and payment. Every step can be skipped and done later from Settings.
4. Put the booking form on a page with the shortcode `[vaqtyar_booking]`, or add the "Booking form" block. Put `[vaqtyar_panel]` on another page for customers' own appointments.

The Persian user guide (`user-guide-fa.md`) is in the plugin folder.

== Frequently Asked Questions ==

= Does it work without WooCommerce? =

Yes. WooCommerce is optional and is used only as one of the ways to take payment.

= Where is my data kept, and what happens when I delete the plugin? =

In tables of your own WordPress database. Deleting the plugin removes nothing unless you turned on "Delete all data when the plugin is deleted" on the System status page first.

= Can I add another payment gateway or another way to notify customers? =

Yes. A gateway and a notification channel (a messenger, for example) are added with the `payments/gateways` and `notifications/channels` filters. The developer documentation lists the hooks and the contracts to implement.

= The site is behind a CDN. Why do visitors share one rate limit? =

The plugin trusts only the connecting address. Return the visitor's real address from your CDN's header in the `rest/client_ip` filter, prefixed with the plugin's hook prefix.

== Changelog ==

= 0.1.0 =
* First release candidate. See CHANGELOG.md for the full list.

== Upgrade Notice ==

= 0.1.0 =
First release candidate.
