# WIKI

**E-Commerce Website I | HTML5, CSS3, JavaScript, PHP, MySQL**

**Live portfolio demo:** [wikifits.infinityfree.io](https://wikifits.infinityfree.io)

This is a portfolio demonstration with sample products. No real payments or deliveries are processed; outbound email delivery is not enabled. Please use fictitious details when trying checkout.

Built a full-stack e-commerce platform with product catalog, shopping cart, secure checkout, user authentication, product/order CRUD, inventory management, responsive UI, and an administrator dashboard using PHP and MySQL.

## Start here

The prepared workspace preview runs at **http://127.0.0.1:8080**. Its separate local database listens on 127.0.0.1:3307. Local administrator credentials are in the ignored private file `storage/runtime/preview-admin.txt`; they are excluded from the distribution. Restart this prepared Windows preview with `powershell -ExecutionPolicy Bypass -File bin/start-local.ps1`.

- Architecture, complete folder structure, schema design, design system and development roadmap: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
- Executable MySQL schema: [database/wiki_ecommerce.sql](database/wiki_ecommerce.sql).
- Security and authorized testing: [docs/SECURITY.md](docs/SECURITY.md).
- Deployment and integrations: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).
- Feature implementation and boundaries: [docs/FEATURES.md](docs/FEATURES.md).

## Requirements

PHP **8.2 or newer recommended** (core application also uses PHP 8.0-compatible syntax), MySQL 8.0+, PDO MySQL, mbstring, fileinfo, JSON and GD. Apache with mod_rewrite or Nginx. PHP's built-in server is suitable for development only. No Node.js, npm or frontend build step is required. Use an actively supported PHP version for deployment.

## Setup

1. Create a local configuration:
   - Copy `config/config.example.php` to `config/config.php`.
   - Set your database credentials, canonical URL, shipping amounts and tax basis points.
   - All monetary database values are **minor units**: PKR 4,500 = `450000`.
2. Import the schema as a database administrator:
   ```sh
   mysql -u root -p < database/wiki_ecommerce.sql
   ```
   In phpMyAdmin, choose **Import**, select this SQL file, and run it. The schema creates `wiki_ecommerce`; tables are intended for a fresh database.
3. Create an application database account (replace the example password):
   ```sql
   CREATE USER 'wiki_app'@'localhost' IDENTIFIED BY 'a-long-random-private-password';
   GRANT SELECT, INSERT, UPDATE, DELETE ON wiki_ecommerce.* TO 'wiki_app'@'localhost';
   ```
   Use that account in `config/config.php`. Do not use root for deployment.
4. Populate the catalog:
   ```sh
   php bin/install.php
   ```
5. Create an administrator with a private password. PowerShell example:
   ```powershell
   $env:WIKI_ADMIN_PASSWORD = Read-Host 'Temporary environment password for admin creation'
   php bin/create-admin.php admin@example.com 'WIKI Administrator'
   Remove-Item Env:WIKI_ADMIN_PASSWORD
   ```
   The installer does not create default users or passwords. On shared terminals, enter the password through your secret manager instead of saving it in shell history.
6. Run locally from the project root:
   ```sh
   php -S 127.0.0.1:8080 -t public public/router.php
   ```
   XAMPP on Windows: replace `php` with `C:\xampp\php\php.exe` if it is not on PATH. Enable `extension=gd` in your local PHP configuration for image uploads.
7. Open `http://127.0.0.1:8080`. Sign in at `/admin/login` to access administration.

**Web document root must be `public/`.** Never point Apache or Nginx at the project root: private configuration, uploaded originals, email content and database scripts must remain inaccessible over HTTP.

## Try a complete purchase

Browse `/shop`, choose a variant and add it to your bag. Update quantities in `/cart`, then complete a guest or account checkout. Cash on Delivery creates an order, reserves stock immediately as a confirmed order, queues email and provides an order page. Account holders can try `WELCOME10` above PKR 5,000; its limits and dates are checked on the server.

In admin, save shipment details, move the order through processing → shipped → out for delivery → delivered, then record cash collection. A customer may request a return after delivery. Approve it and mark it received before changing the order to returned. Inventory is restored only once. Record an actual external refund with its reference; the application never claims to transfer money itself.

## Email

Run `php bin/mail-worker.php` to process up to 50 queued messages. Development mode uses a private log at `storage/logs/mail.log`, useful for opening password-reset and guest-tracking links. **Log transport does not deliver email.** Configure a trusted local MTA and `mail_transport=mail`, or replace the worker transport with a vetted SMTP provider adapter. Protect and purge development logs. Do not use real customer addresses until outbound email is configured and tested.

## Tests

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
php tests/invariants.php
php tests/http-smoke.php http://127.0.0.1:8080
php tests/journey.php
php tests/concurrency.php
```

Tests create temporary catalog/coupon/order fixtures and roll them back where possible. Run only against an isolated development database. Follow the manual checks in the security guide, including concurrency testing with separate PHP workers.

## Catalog imagery

Local sample photography is illustrative, not evidence of real WIKI goods or multiple genuine camera angles. Every product supports an ordered multi-image gallery; replace the repeated seed gallery references with the actual front, back, detail and lifestyle photographs before launch. Photograph credits and source references are in `docs/IMAGES.md`. A local SVG fallback prevents broken-image icons.

An optional `tests/browser-check.cjs` uses Playwright for development QA. It is not required to install or run the PHP website. See `tests/RESULTS.md` for executed checks and environment limitations.

## Important launch boundaries

This is a portfolio implementation with operational COD ordering. Card, PayPal and regional gateway architecture must be connected to official hosted/tokenized checkout and verified webhooks before enabling those methods. No PAN or CVV fields exist. Tax, delivery regions, legal policies, email sender identity, return operations, image rights, backups and monitoring need merchant-specific configuration. Application code cannot guarantee security or 99.9% uptime.
