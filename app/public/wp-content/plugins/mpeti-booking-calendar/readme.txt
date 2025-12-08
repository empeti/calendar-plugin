=== mpeti Booking Calendar ===
Contributors: yourname
Tags: booking, appointments, calendar
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

mpeti Booking Calendar adds a full appointment booking system with staff, services, REST endpoints, and a React admin calendar.

== Installation ==

1. Zip the `mpeti-booking-calendar` folder or place it into `wp-content/plugins/`.
2. Activate "mpeti Booking Calendar" from the Plugins screen.
3. Add the shortcode `[mpeti_booking_calendar]` to any page to display the calendar and booking form.

== Shortcodes ==

- `[mpeti_booking_calendar]`
- `[mpeti_booking_calendar staff="3" service="7"]`

== Notes ==

- Extend payments in `includes/class-mbc-payments.php`.
- Connect Google Calendar API in `includes/class-mbc-google-calendar.php`.
- Add more admin calendar filters inside `assets/js/admin-calendar.js`.

