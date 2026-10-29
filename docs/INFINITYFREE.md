# WIKI portfolio deployment

Target: https://wikifits.infinityfree.io

Use the account-specific database name, hostname and username from your InfinityFree dashboard. Database passwords are deliberately excluded from this document and the upload archive.

## Deployment bundle

`php bin/package-infinityfree.php` creates a clean package under the ignored `storage/runtime/infinityfree/` directory. It includes application code and images, a fresh database import, and private administrator credentials. It excludes local customer data, orders, logs, sessions, uploads, and configuration secrets.

The generated `htdocs` directory is the **only** directory to ZIP and upload to the hosting file manager. Do not upload the parent directory: it contains the database import and private administrator credentials.

Import `wiki-import.sql` using phpMyAdmin with the existing hosted database selected. Import it once into an empty database. The generated SQL explicitly uses InnoDB and includes the sample catalog and a fresh administrator hash.

## Hosting layout

InfinityFree requires application files inside `htdocs`. The deployment adds explicit HTTP denial rules to `app`, `config`, `views`, and `storage` and blocks these paths in the root rewrite configuration as well. The PHP application can include these private files internally. Public assets are served from `public/assets` through the `/assets` URL. This host-specific arrangement is different from a configurable server where only the original `public` folder is the web root.

Create `htdocs/config/config.php` privately in the hosting file manager after upload. Use `config/config.example.php` as a reference, set production mode, HTTPS site URL, hosted database credentials, and keep mail transport set to `log` for this demo. Never publish credentials in a repository or screenshots.

## Verify before sharing

- Homepage, shop, filters, product images and variants load over HTTPS.
- Cart updates and guest demo checkout work with sample details.
- Login and administrator product/order screens work.
- HTTP redirects to HTTPS.
- Requests to `/config/config.php`, `/storage/`, `/app/`, and `/views/` are denied.
- No deployment archive, SQL import or private credentials file is publicly accessible.
- The visible portfolio demo notice explains there are no real payments or deliveries.

## Operating the demo

Use `/admin/login`, then Products to create/edit/archive products; Inventory to manage variants and stock; Orders to view demo orders. Keep administrator access private. A local private credentials file is generated alongside the SQL import.

Online payment gateways and real email delivery are not enabled. Free hosting availability and limits are controlled by the provider. Use fictitious details for demonstrations and avoid collecting real customer data. Back up the database and uploaded images before replacing a live release.

## Deployment verification — 20 October 2026

The site is live at https://wikifits.infinityfree.io. The fresh SQL import completed with 294 queries. The live storefront, variant pricing, cart, guest demo checkout, administrator sign-in and Products screen were verified. A sample order (WK-261017-48D7960AEA) remains in the database, clearly marked as deployment testing with fictitious details. No money was collected.

The live Products screen contains all 10 seeded products. Create products using “Create product +”, edit with “Manage”, upload images using “Upload product photography +”, and manage sizes/colors/volumes and stock through Variants and Inventory.

A mobile viewport check found no horizontal overflow. An unauthenticated request to the admin products route displayed the sign-in screen. Direct access to the private configuration path returned 403 Forbidden during deployment.

Administrator credentials are in the ignored local file `storage/runtime/infinityfree/admin-credentials.txt`. Keep them private; share only the storefront link on LinkedIn. Real payment gateways and outbound email delivery remain disabled.
