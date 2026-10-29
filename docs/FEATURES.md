# Implementation status

## Working application flows

- Server-rendered responsive storefront, collections/category/search/new-arrival/best-seller/sale routes.
- Database product data, clothing size/color variants, fragrance volumes/notes, gallery/zoom, related/recent products, wishlist and fit calculator.
- Search across name/SKU/description/category; server filters, sorting and pagination; Fetch enhancement with safe DOM rendering.
- Session-backed cart with server prices, quantity limits, inventory validation, coupons, shipping and tax calculations.
- Guest/account COD checkout, separate billing address, idempotent submission, immutable order snapshots and private guest tracking links.
- Registration, login/logout, password recovery/change, profile, saved addresses, order history and returns.
- Verified delivered-purchase reviews with moderation. No invented customer testimonials or fake ratings are seeded.
- Permission-checked admin catalog creation/edit/archive, category tree, variant creation, stock adjustments, safe uploads, order/shipment transitions, COD collection, return workflow and refund reconciliation.
- Coupons, banners, newsletter subscribers, settings, account role assignment, reports, event totals, login attempt records, security logs and low-stock notifications.
- Email outbox and private development log transport; XML sitemap/catalog feed; product microdata; security headers and VAPT guide.

## Explicit boundaries

This project is not a ready-to-launch managed commerce service. Online payment providers, bulk newsletters, provider webhooks, carrier APIs, tax services, MFA, omnichannel stock sync and GA4 are integration work. Their relevant data/adapter boundaries are provided; they are not represented as live integrations. Email verification uses expiring hashed tokens and a CSRF-protected confirmation form.

Admin roles/permissions are installed from SQL rather than edited through a dynamic permission designer. Category creation supports subcategories through parent IDs. Existing variants are retained for immutable historical order references; inventory is editable, while variant definition changes should be represented by new SKUs. Reviews can be approved, rejected or deleted. Addresses can be added, edited and deleted. No wishlist is persisted for anonymous visitors.

Sample gallery entries reuse illustrative photographs; genuine front/back/detail product photography must be supplied by the merchant. Product review ratings are calculated from approved customer reviews, not fabricated development ratings. Recommendations are related products, not a trained frequently-bought-together model. Homepage community testimonials appear when approved reviews exist.

The current feed is limited to 48 products, and account order history displays the latest 100. Admin tables and shop listings have pagination. Coupon redemption requires a signed-in account. Marketing banners use internal links only. Policies are clearly marked drafts for merchant/legal review.

Security testing results are documented in tests/RESULTS.md once executed. A passing local test suite does not establish production security certification.
