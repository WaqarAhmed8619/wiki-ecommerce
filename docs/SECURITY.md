# Security model and VAPT checklist

## Implemented protections

Native PDO prepared statements bind all supplied values. SQL column/order choices come from closed server-owned lists. Output is escaped; filtered cards are built with DOM `textContent`, not interpolated HTML. State-changing endpoints require a session CSRF token. Session cookies are HttpOnly, SameSite=Lax and Secure on HTTPS. Production mode rejects non-TLS requests, and login regenerates the session ID. Password resets increment a session version to revoke existing logins.

Accounts use Argon2id when available, otherwise bcrypt, with a 12-character minimum. Login, registration and password-reset attempts are throttled by hashed identity and IP. Do not trust forwarded IP headers without explicit trusted-proxy configuration. Admin routes and every administrative action enforce permissions. Customer addresses, orders and reviews are scoped to their owner; guest order tokens are randomly generated and stored hashed.

Uploads accept JPEG, PNG and WebP only after MIME and dimension checks. GD decoding and re-encoding strips executable payloads and metadata. Random filenames are stored outside the public root and served by an exact filename route with image/jpeg and nosniff headers. The application enforces 5 MB and 18-megapixel limits; also configure PHP/web-server request limits.

Orders lock variant and inventory rows in deterministic order and condition stock updates on available quantity. Coupons lock before checking usage and recording redemption. Coupons require sign-in so guest email rotation cannot evade account limits. Order snapshots retain server-computed prices and addresses. Duplicate submission is guarded by an unguessable, unique checkout key. Cancellation and physical return restore stock once.

Headers include a restrictive same-origin CSP, clickjacking protection, nosniff, referrer policy, permissions policy and HTTPS HSTS. Sensitive pages use no-store. Exceptions log privately and expose a generic message. No gateway credentials or raw card data are stored.

## Authorized assessment plan

Run ZAP or Burp Suite only against a development/staging environment that you own or have permission to test. Seed disposable accounts and disable outbound production integrations. Capture a backup before active scanning.

- [ ] Authentication: incorrect/unknown emails give the same login message; test weak passwords, replayed reset tokens, expired reset tokens and reset races.
- [ ] Session: session ID changes on login; old authenticated sessions fail after password reset/change or role changes; verify HTTPS cookie flags, idle expiry, logout and browser caching.
- [ ] Rate limits: repeated identity/IP attempts stop; proxies cannot forge effective addresses. Add distributed edge limits before multi-node operation.
- [ ] Authorization: test customer, fulfillment and administrator independently. Customers must not call any admin mutation. Fulfillment must not manage users or catalog.
- [ ] IDOR: swap order numbers, address IDs, review product IDs and guest tokens. A second account must not read another account's orders or mutate addresses.
- [ ] CSRF: omit, alter and cross-session replay tokens on every POST, including login, cart, upload and newsletter.
- [ ] SQL injection: exercise search, sort, IDs, coupons, slug and admin fields with quote/meta-character payloads. Values must remain parameters and invalid sort keys must fall back safely.
- [ ] XSS: place markup in product names, reviews, account names, addresses, notifications and search. Verify both initial HTML and Fetch-rendered results escape it.
- [ ] Uploads: reject executable extensions, SVG, polyglots that fail decode, spoofed MIME, oversized files and huge dimensions. Ensure uploaded bytes cannot execute or change content type.
- [ ] Input manipulation: alter prices, discounts, payment methods, variant IDs, quantity signs/decimals/arrays, totals and checkout keys. Only server prices and permitted methods should be used.
- [ ] Inventory concurrency: two separate sessions/workers purchase the final unit simultaneously. Exactly one succeeds; stock stays non-negative. Test cancellation replay and duplicate return receipt.
- [ ] Coupons: expiry, start dates, minimum spend, maximum discount, total usage and per-account limit; concurrent last redemption must not exceed the limit.
- [ ] Checkout: resubmit the same key, refresh the confirmation page, and test stale stock. No duplicate orders or repeated inventory reductions.
- [ ] Order workflow: illegal transitions, unreceived returns and refunds exceeding paid value must fail. Record refund references uniquely.
- [ ] Headers: inspect CSP, frame-ancestors, X-Frame-Options, nosniff, no-store and cookie flags over actual HTTPS.
- [ ] Exposure: requests for `/config/config.php`, `/.env`, `/database/wiki_ecommerce.sql`, `/storage/logs/mail.log`, backups and traversal paths must not return file content.
- [ ] Error handling: unavailable database and malformed requests must not disclose credentials or stack traces.
- [ ] Dependencies: use maintained PHP/MySQL/GD builds. No vendored JavaScript dependencies are required. Review any added SDK/SMTP packages and their licenses.
- [ ] Privacy: no analytics rows before consent; revoking consent stops future optional activity. Verify logs/exports/retention and deletion procedures.
- [ ] Accessibility: keyboard-only checkout, visible focus, screen reader labels, error messages and dialog dismissal; test 320/375/425/768/1024/1440px widths.

## Operational hardening still required

Use least-privilege database accounts, restricted file permissions, encrypted backups, secret management, patching, central logs and alerting. Administrators should be protected by an identity-aware proxy/MFA before commercial operation. The application does not implement MFA, distributed rate limiting, fraud detection, a WAF or external penetration-test certification. Audit logs are database records, not tamper-proof external evidence.

Review worker recovery for interrupted `sending` emails. Do not automatically retry payment operations without idempotency and reconciliation. Cleanup old reset tokens, login attempts and optional analytics on an explicit retention schedule.

## References

- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)

This is defense in depth, not a guarantee that the application is unhackable.
