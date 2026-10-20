# Prakshi Foods — PHP MVC store

A PHP 8 MVC e-commerce application for XAMPP / Apache and MySQL or MariaDB. The storefront uses HTML, locally bundled Bootstrap 5.3.3, custom CSS and vanilla JavaScript. No Composer or Node build is required.

## Open the installed local application

- Store: `http://localhost/prakshi%20foods/`
- Admin: `http://localhost/prakshi%20foods/index.php?r=admin/login`
- Database: `prakshi_foods`
- Current local administrator: `prakshifoods@gmail.com`. The new password was provided privately in the setup conversation; the previous default account credentials have been replaced. Admin sign-in requires a fresh email OTP. No fixed password is embedded in source.

Start Apache and MySQL in XAMPP. This checkout is already installed with an illustrative development catalogue: 8 products, 16 pack variants, a trio offer and a `WELCOME10` coupon. There are no fabricated customers, orders, revenue or reviews in the store database. The automated tests use separate temporary databases.

## MVC layout

```text
index.php / public/index.php   Front controller and explicit GET/POST routes
app/Controllers/               Store, authentication and admin request handlers
app/Models/                    Catalogue, cart, order, payment, settings and admin domain logic
app/Core/                     PDO connection, auth guards, uploads, mail, view-data composition
app/Views/                    PHP templates and reusable layouts/partials
config/app.php                Defaults; reads ignored config/local.php
config/local.example.php      Deployment configuration template
database/schema.sql           Relational schema with keys and indexes
public/assets/                Bootstrap, custom CSS/JS and images
public/uploads/               Validated raster uploads (script execution denied)
storage/mail/                 Development email outbox
storage/logs/                 Private application and test logs
bin/install.php               CLI-only database and administrator installer
tests/                        Isolated model and HTTP regression suites
```

Requests enter the front controller, pass through CSRF/auth checks, and dispatch to a controller. Domain models perform transactional cart, pricing, inventory, payment and administrative operations using prepared PDO statements. View data is assembled before rendering; templates contain no SQL. Monetary values are stored as integer paise and formatted as INR at the presentation boundary.

## Features

### Customer experience

- Public browsing, search, category filtering, price sorting and pagination.
- Responsive product cards, pack options, price and stock changes, gallery and full-size image view.
- Login modal triggered by Add to Bag, with the selected variants and quantities retained through registration or login.
- Hashed passwords, duplicate-email checks, session regeneration, CSRF, rate limits, reset links and logout.
- Account-backed cart, quantities, coupons, server-side totals, shipping thresholds and optional tax percentage.
- Bundle builder with eligible products, minimum quantity, percentage or fixed offer and scheduled availability.
- Delivery address entered once and automatically reused at future checkouts. An **Edit address** button opens a prefilled form; saving updates the remembered address without adding duplicates or changing previous order snapshots. COD checkout, order details, tracking reference, reorder and profile/password editing.
- Reviews after delivery, optional customer photo and admin moderation. No invented review counts.
- Contact forms, newsletter consent, configurable story, FAQ and policy pages.

### Store studio

- Database-driven metrics, date filters, daily/monthly revenue, status distribution, top products and category charts.
- Product/category editing, primary and gallery images, slugs, metadata, active/featured flags.
- Separate pack/SKU/price/stock management and stock adjustment history.
- Coupons with percentage/fixed values, product/category restrictions, start/end dates, spend thresholds and global/per-customer limits.
- Bundle eligibility, minimum counts, fixed/percentage pricing and availability scheduling.
- Searchable orders, state transitions, internal notes, tracking, cancellation and printable order summaries.
- Customer profiles/order history and account deactivation.
- Review approval/hiding and store replies.
- Editable homepage, logo, supplied FSSAI image, contact/social links, shipping, payment and policy settings.
- Owner/manager access: owners manage secrets, settings, admin accounts and audit logs; managers handle catalogue and fulfilment.
- UTF-8 CSV exports for spreadsheet use. Exports exclude password hashes and secrets and neutralize spreadsheet formulas. CSV is provided instead of XLSX.

### Inventory and payment integrity

- Stock is reserved by deduction inside the order transaction; variants are locked in consistent ID order.
- Product names, pack labels, prices and quantities are snapshotted on order items.
- Checkout uses a unique idempotency key. Repeated submission returns the existing order.
- Cancellation restores stock once and releases coupon usage. Cancelled orders cannot be reopened.
- COD is unpaid until a delivered order has a collection receipt recorded by an administrator.
- Online orders are created server-side. Signature verification, fetched captured payment state, currency and amount matching are required to mark them paid.
- Webhooks verify the raw-body HMAC and deduplicate event IDs.
- Online cancellation is restricted once a provider order exists until a full refund is verified. Stock for abandoned online orders remains reserved for manual reconciliation; it is not silently released while payment could still settle.

## Fresh installation

Requirements: PHP 8.0+ with PDO MySQL, cURL, OpenSSL, mbstring and fileinfo; Apache with rewrite support; MySQL 8 or MariaDB 10.4+. For production, use a currently supported PHP release compatible with this application.

1. Copy the project to your Apache web directory.
2. Copy `config/local.example.php` to `config/local.php`. Set database credentials and `base_url` to the project root URL (without `/public`). Use `env => local` and `mail_mode => log` for local development.
3. Ensure PHP can write to `public/uploads`, `storage/logs`, `storage/mail` and its configured session directory.
4. Run from the project root:

```powershell
& 'H:\xampp\php\php.exe' bin/install.php --admin-email=owner@example.com
```

For a development catalogue only:

```powershell
& 'H:\xampp\php\php.exe' bin/install.php --demo --admin-email=owner@example.com
```

The installer creates the database/schema, initializes content, generates an encryption key in ignored `config/local.php`, and creates the first owner only if no admin exists. To choose the initial password, set `PRAKSHI_ADMIN_PASSWORD` in the process environment before installation. Otherwise a random password is printed once. Save it securely and change it after sign-in.

The SQL schema can also be imported into an existing database. Run the installer afterwards to initialize settings and the first admin. Do not re-run `--demo` on a production store; sample data is for development only.

## Admin workflow

### Maintenance during development

From the project folder, pause public shopping with:

```powershell
& 'H:\xampp\php\php.exe' bin/maintenance.php on
```

Reopen the store with:

```powershell
& 'H:\xampp\php\php.exe' bin/maintenance.php off
```

Use `status` instead of `on` to check the current state. Maintenance is off by default. Visitors receive a responsive maintenance page and HTTP 503; cart and payment AJAX requests receive a JSON error. Admin routes remain available, and administrators who finish password/OTP login can preview the store in that browser. Payment webhooks remain available to reconcile existing payments. The visitor maintenance page works without the database. Static assets remain accessible. The switch is the private `storage/maintenance.flag` file; deleting that file also reopens the store.

1. Add/edit categories, then add products. Product ingredient, allergen, storage and shelf-life information must come from the owner.
2. Open **Inventory & pack sizes** to add variants, SKU, price, original price and stock. Products need active variants before they can be purchased.
3. Use **Media library** to add gallery images. Set the primary image in the product editor. Upload one gallery image per record; add multiple records for a multi-image gallery.
4. Configure coupons and bundle eligibility. Percentage coupon `value=10` means 10%; fixed coupon values are in paise (`10000` = ₹100). Other price fields labelled INR accept rupees.
5. Coupons do not stack on bundle lines. Bundle prices are recalculated from current eligible products and availability.
6. Review stock alerts and update order statuses in sequence. Archive products/categories instead of destroying historical data.
7. Use Reports & exports for CSV downloads. Charts and revenue count verified payments; COD collections enter revenue only when recorded.

## Razorpay setup and testing

Payment code follows the [official PHP integration sequence](https://razorpay.com/docs/payments/server-integration/php/integration-steps/).

1. In **Connect Razorpay**, select Test mode and enter your test Key ID and Key Secret.
2. Configure an HTTPS webhook endpoint using the URL shown in settings. Subscribe to `payment.captured` and `payment.failed`, and configure automatic payment capture in Razorpay.
3. Set an independent webhook secret in Razorpay and in the store settings.
4. Enable Razorpay. A key-mode mismatch disables payments. Secrets are encrypted at rest using the application key and never rendered back into forms.
5. Test a successful captured payment, failed and dismissed checkout, duplicate callback, webhook replay, wrong signature and provider outage. Confirm the amount, currency and order reference match.
6. Make refunds in the Razorpay dashboard. Use **Verify payment / refund with Razorpay** on the order to reconcile a provider-confirmed full refund. Partial refunds are not automatically reconciled by this implementation.
7. Only then switch to matching Live keys/mode and test the live HTTPS webhook.

**No real Razorpay credentials were provided, so provider calls and live payments have not been exercised.** Checkout success/cancellation/failure messages do not claim payment is complete until backend verification succeeds.

## Mail and password resets

This installation now uses Gmail SMTP (`smtp.gmail.com:465`, TLS with certificate verification) from `prakshifoods@gmail.com`. The app password is stored only in ignored, web-protected `config/local.php`. It is never sent to the frontend or logged. Configuration follows [Google's SMTP guidance](https://developers.google.com/workspace/gmail/imap/imap-smtp). Gmail accepted a test admin OTP on 19 October 2026; recipient inbox placement must be checked by the owner.

Customers register or sign in with a password, then verify a six-digit email OTP before accessing their account/cart. Selected items are preserved until verification succeeds. Admin accounts also require password plus email OTP on every login. Codes expire after 10 minutes, are stored as keyed hashes, allow five incorrect attempts, and are consumed once. Resending waits 60 seconds, invalidates the previous code and is limited to five messages per account per hour. Codes are tied to the requesting browser session and cannot be used across customer/admin roles. Changing a password invalidates existing authenticated sessions and pending codes for that password.

Customer support requests are saved in the database and forwarded to `prakshifoods@gmail.com`. Unexpected server errors trigger a sanitized technical alert, limited to one per 15 minutes. Errors caused by invalid user input do not trigger alerts. If email delivery fails, saved support messages and completed orders remain available in admin. SMTP outage alerts cannot themselves be delivered while SMTP is unavailable; failures remain in private logs.

Password-reset links and order notifications also use SMTP. `mail_mode => log` remains available for isolated development/tests and writes to private `storage/mail/`; it does not send email. SMS OTP is not implemented. Email SMTP secrets remain server configuration rather than editable browser fields.

For an existing installation created before email verification, run:

```powershell
& 'H:\xampp\php\php.exe' bin/migrate-otp.php
```

Fresh installations include the OTP schema in `database/schema.sql`. Set the SMTP fields shown in `config/local.example.php` before real registrations. A mailbox does not become verified merely because an email was sent: the customer must enter the correct code. `bin/test-email.php` sends a delivery-test admin OTP; use the new code generated by the actual browser login when signing in.

## Tests

```powershell
& 'H:\xampp\php\php.exe' tests/integration.php
python tests/http_checks.py
node --check public/assets/js/app.js
node --check public/assets/js/admin.js
```

The PHP suite creates a random `prakshi_test_*` database and drops it after its checks. The HTTP suite creates a random `prakshi_http_*` database, starts an isolated server on port 8098, tests workflows, stops it and drops the fixture database. Tests require a database account with CREATE/DROP permission and a writable PHP session directory. Override `PHP_BINARY` if your PHP executable is elsewhere. `PRAKSHI_DB_NAME` and `PRAKSHI_BASE_URL` are process-level configuration overrides used by these tests.

Validated locally: PHP syntax, JavaScript syntax, 42 domain assertions and 86 HTTP workflow checks. OTP tests cover account access before verification, invalid/expired/replayed codes, customer/admin separation, persisted attempt counters, resend limits and registration resuming the selected cart. HTTP tests also cover support email routing, CSRF, authorization, coupons, checkout idempotency, order ownership, admin pages, editing, CSV exports, fulfilment, review moderation, COD collection, reorder and logout. Tests explicitly use a private local email outbox, never live Gmail. Browser-based visual/JavaScript interaction checks and real Razorpay tests remain separate manual checks.

## Before real business launch

- Replace the generated illustrative photograph and all development catalogue prices, stock, descriptions and packaging with the actual products. Generated imagery is not evidence of the real jar/packaging.
- Confirm the supplied FSSAI image/licence details, contact information, shipping promise, ingredients/allergens and approved policy text. Missing business information is intentionally not invented.
- Set HTTPS `base_url`, restricted database credentials, a production mail transport, verified payments and real business settings. The root `.htaccess` denies direct access to private source/configuration/storage directories; preserve it when deploying.
- Disable PHP `display_errors` at the server level and keep error logging private. The PHP built-in test router is for tests only.
- Set your domain’s crawler rules and sitemap URL (`index.php?r=sitemap`). Product/category pages currently use stable query-string routes and slugs; an Apache pretty-URL layer can be added without changing the MVC controllers.
- Printable order summaries are provided; statutory GST tax invoicing, courier API integration, partial refunds, customer data-erasure automation, and abandoned-payment reservation expiry are not implemented. Tracking references are entered manually.
- Optional external fonts and Razorpay Checkout require network access. Bootstrap is bundled locally. No analytics/tracking service is enabled by default.

## Backup and restore

Use a database account with backup privileges. Supply the password interactively, not in shell history:

```powershell
& 'H:\xampp\mysql\bin\mysqldump.exe' -u prakshi -p --single-transaction --routines --triggers --result-file='D:\secure-backups\prakshi-foods.sql' prakshi_foods
```

Back up `public/uploads` and `config/local.php` (especially the encryption key) separately into access-controlled storage. Never place backups in the web root. Keep a retention schedule appropriate to your business and test restoration into a separate database before relying on backups. Restore SQL with the MySQL client's `SOURCE` command against the intended database, restore uploaded media and the original application key, then verify login, catalogue, order totals and encrypted payment settings.

The reference store was reviewed for its catalogue/bundle shopping structure; no competitor branding, photography, reviews or proprietary copy were copied. Original assets supplied by the user are in `public/assets/images/`.
#   p r a k s h i f o o d  
 