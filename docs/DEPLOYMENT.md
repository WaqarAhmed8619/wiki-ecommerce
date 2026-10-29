# Deployment and integration guide

## Hosting

Use an actively supported PHP-FPM release and MySQL 8 with InnoDB. Set the document root to the absolute `public/` directory. All tables should use InnoDB (the MySQL 8 default); verify with `SHOW TABLE STATUS` after import. Set `env=production`, the public HTTPS URL, actual shipping/tax settings and production database credentials in private config. The app must see HTTPS at its server; when TLS terminates at a proxy, configure the server's trusted TLS indication rather than accepting arbitrary request headers.

Example Nginx location rules (adapt the root and FPM socket):
```nginx
root /srv/wiki/public;
index index.php;
client_max_body_size 6m;
location / { try_files $uri /index.php?$query_string; }
location = /index.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
location ~ \.php$ { return 404; }
location ~ /\. { deny all; }
location /assets/ {
    expires 7d;
    add_header X-Content-Type-Options nosniff;
    try_files $uri =404;
}
```

Apache: use `public/.htaccess` with mod_rewrite and an explicit `DocumentRoot`. Disable directory listing and set PHP file/request upload limits. Give the web process write access only to sessions, private uploads and necessary logs. Schema/config/source must not be web-writable.

Use Brotli or gzip for CSS, JavaScript, XML and HTML. Keep generated account/checkout responses private and uncached; cache versioned static assets through your web server/CDN. Convert real product photos to WebP/AVIF and add size-specific sources when your image pipeline supplies them. Catalog queries are paginated and indexed, but benchmark correlated ratings/sales queries with your expected catalog size and add summary tables for large catalogs.

## External payments

`PaymentGateway` in `app/Commerce.php` is the adapter boundary. Only COD is enabled. A real online payment integration must:

1. Create a pending server order and hosted checkout using the immutable order amount and currency.
2. Keep API secrets outside public/. Use official provider SDKs/APIs, HTTPS and tokenization.
3. Verify signed webhooks against the raw request body and a timestamp tolerance. Persist unique provider event IDs before processing.
4. Confirm amount, currency, merchant account, order reference and provider status. Never trust a browser redirect as payment proof.
5. Make confirmation/refund operations idempotent; reserve stock with an expiry and release failed/abandoned reservations transactionally.
6. Reconcile settlements and partial refunds. Never store PAN/CVV or build your own card-entry form.

Card, PayPal and regional methods must stay hidden until those provider-specific paths and webhook tests are complete.

## Mail and marketing

The outbox separates business transactions from delivery. Run the worker via a scheduler; transactional row locks ensure only one claim processes a row. Configure an authenticated SMTP transport or a trusted local MTA and validated sender domain (SPF, DKIM, DMARC). Provide delivery monitoring, bounce suppression and unsubscribe tokens before launching marketing email. Newsletter records have explicit opt-in; the current admin can suppress an address, but no bulk campaign sender is enabled.

Welcome with an email-verification link, reset, confirmation, payment, order-status, return and refund messages are queued by the implemented flows. Provider-specific delivery callbacks require additional integration. Protect verification/reset/guest tracking content and redact tokens in reverse-proxy logs.

## Analytics and feeds

Activity is opt-in and uses daily pseudonymous session hashes. Revenue/order metrics come from transaction records; do not interpret opt-in visitor metrics as universal conversion attribution. A GA4 adapter can run only after consent; update CSP narrowly for your provider and define retention first. `/feed.xml` is a simple XML catalog export; channel-specific validation, full pagination and scheduled synchronization must be added for real shopping channels. `/sitemap.xml` lists active product URLs. Add your absolute sitemap URL to robots.txt on deployment.

## Reliability

Use health checks, centralized monitoring, error budgets, managed database replication, encrypted tested backups, restore drills, deployment rollback and scheduled security updates. For horizontal scaling, move PHP sessions and rate limits into shared infrastructure and use shared/object image storage. A 99.9% infrastructure target requires operational design; this application cannot guarantee uptime.

Before launch, replace placeholder contact details and policy drafts, define supported shipping regions, calculate actual tax treatment, verify product claims/image licenses, test accessibility and payment/mail integrations, and complete the VAPT checklist.
