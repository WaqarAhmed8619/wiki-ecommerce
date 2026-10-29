# Verification — 9 October 2026

Tested locally on Windows with PHP 8.0.30, PDO MySQL, GD and MariaDB 10.4.32 (XAMPP). The deployment target is an actively supported PHP version and MySQL 8. Run the suite again on the target infrastructure; MySQL 8 itself was not installed in this environment.

| Check | Result |
|---|---|
| PHP source syntax | All PHP files pass |
| Business invariants | 15 passed |
| Public routes, session cart, security headers and access checks | 38 passed |
| Customer/admin journey, product CRUD and upload validation | 76 passed |
| Concurrent checkout of the final unit | One order succeeds; one fails; stock is zero |
| Homepage widths | 320, 375, 425, 768, 1024, 1440, 1920px pass overflow checks |
| Mobile page layouts | Shop, product, cart, checkout, login and fit guide pass |
| Browser shopping interactions | Required variants, Fetch cart, gallery zoom and Fetch search pass |
| Browser JavaScript errors | None in the verified run |
| Mail worker | Local log transport processed successfully |

The journey covers password-reset expiry semantics/replay, session revocation, ownership checks, customer rejection from admin reads/mutations, server-authoritative prices, coupons, idempotent checkout, shipments, COD collection, review moderation and escaped stored XSS, returns, inventory restoration, excessive-refund rejection and reconciliation. Product image upload is decoded/re-encoded; disguised PHP uploaded with an image MIME/name is rejected.

Browser checks found and fixed a form field shadowing the native form action property, the separate admin-login dispatcher, and mobile intrinsic image heights. Initial local session write permissions were corrected with private project session storage. The public catalog API exposes an explicit field list and excludes internal cost prices. Tests do not establish a full accessibility audit, external penetration-test certification, payment-provider integration, SMTP deliverability or production load capacity.

Screenshots are generated under ignored `tests/artifacts/`. Temporary fixture users, products, uploads, orders, reviews and coupon usage are removed by the journey suite; generated operational audit/low-stock test records may remain in the local demonstration database. Do not copy that local database into production.
