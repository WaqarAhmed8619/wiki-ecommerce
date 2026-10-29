# WIKI — implementation blueprint

## System architecture
Browser → public/index.php router → controllers/services → PDO → MySQL 8 (InnoDB).
HTML is rendered on the server; Fetch enhances filtering, cart and wishlist interactions.
Only public/ is exposed by the web server. No Node.js build step is required.
Tier 1: storefront, discovery, fit guide, variant selection, cart, guest checkout.
Tier 2: promotions, coupons, consent-based events, reports, email outbox, product feed.
Tier 3: transactional orders/inventory, COD payment adapter, shipment and return administration.

## Folder structure
```
app/
  bootstrap.php          configuration, session, autoload and headers
  Support.php            validation, escaping, database and authorization
  Catalog.php            catalog queries and variants
  Auth.php               accounts, rate limits, recovery
  Commerce.php           authoritative totals, orders, inventory
  Actions.php            customer mutations
  Admin.php              permission-checked administrative mutations
config/config.example.php
database/wiki_ecommerce.sql
database/seed.php
public/
  index.php              route dispatcher
  router.php             development server router
  .htaccess              Apache clean URLs
  assets/css/style.css
  assets/js/app.js
  assets/images/         local sample imagery
views/
  layout.php
  home.php
  shop.php
  product.php
  cart.php
  checkout.php
  auth.php
  account.php
  admin.php
  page.php
storage/uploads/         validated originals, never directly executable
storage/logs/
bin/                     installation, administrator creation and mail worker
tests/                   smoke and business invariant checks
docs/                    security, deployment and integration guides
README.md
```

## Database schema
The executable schema is database/wiki_ecommerce.sql. Integer minor currency units avoid rounding drift.
Categories form a parent/child tree. Products own variants, images and approved reviews.
Each variant owns a single inventory row. Orders contain immutable item/address/price snapshots.
Coupon usage and inventory are changed in the same transaction as orders, under row locks.
Unified users with roles avoid duplicate authentication implementations for administrators.
Guest orders are accessible only through session-held random access secrets (stored hashed).

## UI design system
Palette: ivory #f6f4ef, ink #20241f, olive #68735c, stone #dcdcd3.
Typography: system sans for controls; Georgia editorial display; 8px spacing rhythm.
Portrait 4:5 product imagery; landscape editorial banners; pill tags; fine borders.
Minimum 44px touch targets, visible focus, semantic landmarks, reduced-motion support.
Responsive grid: 2 cards on mobile, 3 on tablet, 4 on desktop. No mandatory animation.

## Roadmap
1. Configuration, normalized schema, seed data, session security, roles and auth.
2. Catalog, search, filtering, gallery, variant matrix and wishlists.
3. Session cart, coupons, locked inventory, guest/account COD checkout and orders.
4. Profile, addresses, password recovery, reviews and returns.
5. Admin catalog, uploads, inventory, order transitions, marketing and reports.
6. Email/payment extension points, feeds, SEO, consent, tests and deployment documentation.

Production services need operator credentials and deployment configuration. Payment providers must be integrated and verified before enabling card/PayPal/regional payment options. COD is the runnable default.
