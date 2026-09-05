=== KantanPro ===
Contributors: kantanpro
Tags: invoice, crm, order management, quotation, business
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.38
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage orders, clients, services and invoicing inside WordPress. A business management workspace for small businesses.

== Description ==

KantanPro turns a WordPress page into a workspace where you can manage orders, clients,
services and subcontractors in one place. Add the shortcode `[ktpwp_all_tab]` to any page,
and logged-in users with editing permission will see the management screen there.

Everything is stored in your own database. The plugin does not require an account,
a subscription, or a connection to any service in order to work.

**Main features**

* **Work list** — list, search and filter orders by progress
* **Documents** — create and edit quotations and invoices, and export them as PDF (single or batch)
* **Client management** — clients, departments and contacts, with envelope address printing
* **Service management** — your products and services with price, quantity and unit
* **Subcontractor management** — partner companies and their skills, plus purchase-order email
* **Recurring contracts** — recurring billing cycles, initial fees and billing history
* **Staff chat** — internal discussion per order (send with Ctrl+Enter)
* **File attachments** — drag and drop, multiple files, automatic cleanup
* **Backup** — export and import your data as JSON or CSV
* **Consumption tax** — reduced tax rates and tax categories, with a per-rate breakdown for qualified invoices
* **Mobile ready** — tested for touch operation on iOS and Android devices

**Progress tracking**

Each order moves through seven stages: Rejected, Estimating, Ordered, In progress,
Completed, Invoiced and Paid. Changing the stage sets the completion date automatically,
and orders approaching their delivery date are highlighted.

**PDF and printing**

Quotations and invoices can be saved and printed as PDF. On desktop browsers the normal
print dialog is used; on iPad and iPhone the document is produced as a PDF file instead,
because the print dialog does not render reliably there. Invoices can also be exported
in batch, grouped by client.

== Installation ==

1. Install and activate the plugin. The database tables are created automatically.
2. Create a page and insert the shortcode `[ktpwp_all_tab]` into its content.
3. Open the "KantanPro" menu in the admin area and fill in your company details,
   consumption tax settings and email settings.
4. Open the page you created. The management screen is displayed there.

== Frequently Asked Questions ==

= Who can use the management screen? =

The page containing `[ktpwp_all_tab]` is only rendered for users who are logged in and
have editing permission. Visitors who are not logged in see a login prompt instead of
your business data.

= The layout looks broken on mobile =

The screens are responsive, but your theme's CSS can conflict with them. Please check
with a current browser first. If the problem remains, ask on the support forum and
include your theme name.

= I get a database error =

Database migrations run automatically when the plugin is updated. If an error persists,
deactivate the plugin and activate it again so the migration can run from a clean state.

= Does the plugin send my data anywhere? =

No, not by default. Your business data stays in your WordPress database. Three optional
features can contact an external service, and each of them is described under
"External services" below. None of them is required to use the plugin.

== A separate plugin with more features ==

The same author also publishes **KantanProEX (WP)**, a separate paid plugin.
It is not required for this plugin to work, and nothing here is disabled without it.
KantanProEX adds:

* Sales reports — revenue and profit over time, broken down by client and by service
* Publishing your services on the site and receiving applications as orders
* Card payments through Stripe
* Use by several staff members on the same data

Details: https://www.kantanpro.com/product/kantanpro-ex

== Third-party libraries ==

The following MIT-licensed libraries are bundled unmodified in `js/lib/`
(the download sources are listed in `js/lib/SOURCES.txt`). They are never loaded from a CDN.

* html2canvas 1.4.1 — https://github.com/niklasvh/html2canvas
* jsPDF 2.5.1 — https://github.com/parallax/jsPDF

== External services ==

This plugin can connect to the following external services. Each one is used only when
you actively use the corresponding feature. Nothing is sent while a visitor simply
browses your site.

**1. zipcloud (Japanese postal code lookup API)**

Used to fill in an address automatically from a postal code, on the client and
subcontractor forms. Only the postal code you typed is sent; no personal data is included.

* Sends to: `https://zipcloud.ibsnet.co.jp/api/search`
* Data sent: the postal code
* Terms of use: http://zipcloud.ibsnet.co.jp/rule/api

**2. Japan Post Digital Address API (optional, disabled by default)**

Used for address lookup, only if you enable it in the plugin's general settings.
It requires API credentials issued to you by Japan Post.

* Sends to: `https://api.da.pf.japanpost.jp`
* Data sent: a postal code or digital address, together with your API credentials
* Terms of use: https://guide-api.da.pf.japanpost.jp/
* Privacy policy: https://www.post.japanpost.jp/privacy/

**3. OpenAI API (optional, disabled by default)**

Used by the data import feature to map the columns of an uploaded file to the plugin's
fields. It only sends anything if you have entered your own OpenAI API key.

* Sends to: `https://api.openai.com/v1/chat/completions`
* Data sent: the column headings and up to three sample rows of the file you are importing
* Terms of use: https://openai.com/policies/terms-of-use
* Privacy policy: https://openai.com/policies/privacy-policy

== Screenshots ==

1. Work list — orders with progress filters
2. Document editor — editing an order
3. Invoice preview and PDF export
4. Clients — client records and departments
5. Staff chat
6. Mobile view

== Changelog ==

The complete history is in `changelog.txt`, bundled with the plugin.

= 1.3.38 - 2026-09-05 =
* Fixed the plugin stylesheet shrinking profile pictures that belong to the theme.
  The rule targeted WordPress's shared `avatar` class without any scoping, so author
  boxes, comment avatars and anything else using that class were forced to 32px.
  The rule is now limited to the plugin's own icons.

= 1.3.37 - 2026-09-04 =
* Fixed the notice suppression for the "translation loading was triggered too early"
  warning raised by other plugins (WooCommerce and friends). It matched on the English
  wording, so on Japanese sites the notice was still printed into the page. It now
  identifies the text domain instead, and never suppresses this plugin's own domain.

= 1.3.36 - 2026-09-03 =
* Rolls the 1.3.32 through 1.3.35 fixes up into a release for the self-hosted
  (GitHub) distribution. No code changes in this version itself.
* Removed three build-script backup files that were being packaged into the
  distribution, and excluded that file pattern from future builds.

= 1.3.35 - 2026-09-03 =
* Added a direct-access guard to the three class files that were missing one.

= 1.3.34 - 2026-09-03 =
* Removed unreachable PDF generation code from the order preview script, along with
  the library loader and vendor URLs it was the only consumer of.

= 1.3.33 - 2026-09-03 =
* Removed leftover script and style assets that belonged to the self-hosted update
  checker and the license manager, which are not part of the WordPress.org build.
* Updated the bundled jsPDF library from 2.5.1 to 4.2.1.

= 1.3.32 - 2026-09-03 =
* All generated script and style output now goes through `wp_add_inline_script()`,
  `wp_add_inline_style()` and `wp_localize_script()`.
* Removed hook registrations that pointed at functions which were never defined.
* Removed the unprefixed global functions `get_logged_in_users()`,
  `check_activation_key()` and `add_htmx_to_head()`, and an unused admin form file.

= 1.3.31 - 2026-09-03 =
* Removed the report tab and replaced it with an "Information" tab showing the plugin version,
  environment and record counts. Reports are provided by a separate plugin.
* Removed the custom CSS field. Use the WordPress customizer's Additional CSS instead.
* Fixed a cross-site scripting hole where the `active_tab` cookie was written into an HTML
  attribute without sanitizing.
* Fixed the data-clear handler leaking buffered output into its JSON response.
* Hardened input handling across the plugin: nonces, `$_SERVER` values, pagination arguments
  and search terms are now sanitized before use.
* Uploads now go through `wp_handle_upload()` instead of `move_uploaded_file()`.
* AJAX actions are now prefixed with `ktp_` to avoid collisions with other plugins.
* Scripts and styles that were printed inline are now registered through the WordPress
  enqueue system.
* The postal code lookup on the subcontractor form moved to its own JavaScript file.

= 1.3.30 - 2026-08-30 =
* Fixed a missing permission check in the internal handlers that return billing candidates
  and order data. Any logged-in user could read that data regardless of their role;
  editing permission is now required.

= 1.3.29 - 2026-08-30 =
* Bundled the PDF and chart libraries (html2canvas, jsPDF, Chart.js) with the plugin so
  that PDF export works where an external CDN is unavailable.
* Switched to the jQuery shipped with WordPress instead of loading it from a CDN.
* Renamed the text domain from `ktpwp` to `kantanpro` for the WordPress.org translation system.
* Fixed missing output escaping, including messages shown through `wp_die()`.
* Raised the minimum WordPress version from 5.0 to 5.9 (`wp_date()` and `str_contains()` are used).
* Fixed places where the free edition described itself as "KantanProEX".

= 1.3.28 - 2026-08-17 =
* Envelope address printing now prints without margins, so the offsets shown in the preview
  match the printed position exactly and no longer depend on the print dialog's margin setting.
* Added a "reset address position" button, returning to the default position for a
  Japanese Nagagata-3 window envelope (16 mm from the top, 33 mm from the left).
* The preview now shows the sheet on a grey background with a drop shadow, so the paper
  edges are easy to see while positioning.

= 1.3.27 - 2026-08-17 =
* The address block in the print preview can now be dragged to adjust its position, which
  makes it easier to line up with a window envelope. The distance from the top and left
  edges is shown in millimetres while dragging, and the position is saved on release
  (adjusting and saving is limited to administrators).

= 1.3.25 - 2026-08-07 =
* Fixed invoice lines in order emails showing "service name: 0 × 0 = 0" when only the
  service name was filled in. Such lines now show the name alone, without the calculation.

= 1.3.24 - 2026-08-03 =
* Fixed banner CSS and JS being stripped on sites using plugins that combine inline CSS/JS
  (such as WP-Optimize), which made the banner render as two stacked rows. The assets are
  now registered through `wp_add_inline_style()` and `wp_add_inline_script()`.

== Upgrade Notice ==

= 1.3.36 =
Rollup release for the self-hosted distribution: the 1.3.32-1.3.35 fixes, including the
jsPDF update and the move to the WordPress script and style APIs.

= 1.3.32 =
Inline script and style output moved to the WordPress APIs; unprefixed and undefined
functions removed.

= 1.3.31 =
Security fixes (including an XSS in the design settings) and compliance changes for the WordPress.org directory.
