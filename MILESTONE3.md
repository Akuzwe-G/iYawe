# Milestone 3 and company document branding

Implemented in the existing Laravel application. This is an online-first PWA; comprehensive end-to-end and physical-device acceptance testing remains for the later milestone.

## Using the new features

- System Administrators: Companies → Create/Edit → Document Branding. Set display/legal name, address, document contact details, report heading/footer, and optional logo.
- Company members: Settings → Edit document branding requires `branding.manage`. The reserved Company Administrator role receives it; custom roles can be granted it through Roles & Permissions. Tenant membership and active-company checks still apply.
- Formal documents use the legal name, then branding display name, then company name. Missing optional fields are omitted. PNG/JPEG/WebP uploads are limited to 2 MB and 4000×4000 pixels, decoded and re-encoded as a maximum 720×360 PNG. SVG is rejected. Logos and branding are stored inside the company's encrypted database field, never in a public upload directory.
- Branding appears in acknowledgement PDFs, CSV metadata, operational PDF reports, print screens and report previews. Branding affects documents; iYawe colours and Montserrat remain unchanged.
- A completed acknowledgement stores an encrypted branding snapshot during receipt finalisation. Subsequent PDF creation uses that snapshot. Already generated PDFs remain encrypted, hash-checked and unchanged. An archived PDF that goes missing returns an error rather than silently regenerating it. Legacy completed records without a snapshot use their saved historical company name without applying today's logo/contact details.
- Card Swap steps 1–2 autosave after 1.5 seconds without input. Save Draft saves immediately. The save indicator confirms server success and time. Incomplete values can be saved, but Next still enforces complete step validation. Back and Exit flush unsigned field changes before navigation. No additional record is created by autosave.
- My Drafts lists the current user's unsigned drafts, masked name, reference, last update and step. Resume continues the same record. Discard asks for confirmation and soft-deletes unsigned drafts while retaining audit history. Signed/pending operational records remain under Records.
- Revision conflicts stop saving until the record is reloaded. Network failures preserve current form/signature in memory; reconnect before submitting. Closing or reloading can lose unsaved input or an unsubmitted signature. Nothing is queued offline or persisted in browser storage.
- At consent or receipt, choose Customer signing mode and the commuter's language before handing over the phone. Staff navigation is hidden and server-blocked. Return to staff requires the current staff password. Changing the route or using Back cannot unlock staff controls. Logout remains possible.
- Signing supports touch, high DPI, Clear/retry and expanded landscape space. Resizing preserves strokes. A signature is saved only by an explicit server-confirmed submission. Receipt requires physical completion and staff verification. Signatures and PDFs stay encrypted in private storage with authorised, no-store endpoints.
- Completion displays a masked summary and PDF/print, Records and New Card Swap actions. Authorised full details remain in an expandable section.

## Installation

Serve over HTTPS. For local desktop testing, `http://127.0.0.1:8000` via `php artisan serve --host=127.0.0.1` is a secure-context exception. A plain HTTP `iyawe.test` or LAN IP is insufficient for PWA installation on a phone; configure trusted HTTPS.

Android: open iYawe in Chrome or a compatible browser and choose Install when offered. Settings also has an Install button when the browser exposes installation. Browser eligibility controls availability.

iPhone/iPad: open the HTTPS site in Safari, tap Share, then Add to Home Screen. Dismissible guidance is shown when appropriate. Installed mode uses the same login, company permissions, sessions and logout.

`public/manifest.webmanifest` defines standalone display, /dashboard entry, official colours and iYawe icons. Icons are 192×192, 512×512, an additional maskable 512×512 and Apple touch 180×180. They extend the existing blue/ocean/green i mark and contain no company logos.

`npm run build` compiles Vite and runs `php artisan iyawe:pwa-build`, producing icons, a content-versioned worker, manifest and bilingual offline page with local Montserrat. The worker precaches only an explicit build-generated static allowlist, verifies MIME types and fetches those files without credentials. Authenticated HTML, API responses, transactions, customer details, signatures and PDFs are never added to PWA caches. Navigation always uses the network; connection failure shows the generic bilingual offline page.

Updates prompt users to save work before reloading. Installation dismissal stores only a non-sensitive timestamp in localStorage. No customer information is stored in localStorage, sessionStorage or IndexedDB.

## Languages and consent

Interface language uses Laravel English/Kinyarwanda translations. Customer language is selected independently before handoff and limited to approved declarations for that language. The signed language/version and original declaration remain attached to the record; PDFs use that same language. Signed declarations cannot be edited through draft saving.

The supplied English and Kinyarwanda wording remains in `config/consent.php`, including all six consent points and separate receipt text. Kinyarwanda is marked pending formal approval until authorised company personnel explicitly approve and publish a company version in Consent versions. Nothing was automatically approved for production. Existing signed versions remain immutable.

## Dashboard and mobile changes

Real database-backed Chart.js dashboards, date filters, accessible data tables, empty states, Montserrat legends and brand colours are retained. Added safe-area spacing, navigation shade and Escape dismissal, measured header positioning, larger customer consent text, network/save notifications and accessible focus/skip controls. Existing compact login and responsive cards remain.

## Migration and commands

Added `2026_10_18_105456_add_document_branding_and_draft_retention.php`: encrypted company branding storage, encrypted card-swap branding snapshots, draft soft-delete timestamp and recovery index. Existing rows are retained.

Already run in this workspace:
```powershell
php artisan migrate --no-interaction
php artisan db:seed --class=BrandingPermissionsSeeder --no-interaction
npm.cmd run build
```

On another environment, run these same commands after dependencies are installed. Normal `php artisan db:seed` also provisions the new permission through RolesAndPermissionsSeeder. Rebuild assets after changes to PWA sources; generating icons alone can use `php artisan iyawe:pwa-build`.

## Future HTTPS production preparation

- Set APP_ENV=production, APP_DEBUG=false, APP_URL to the final HTTPS origin, SESSION_DRIVER=database, SESSION_SECURE_COOKIE=true, SESSION_HTTP_ONLY=true and SESSION_SAME_SITE=lax. SESSION_ENCRYPT=true is recommended. Retain the existing APP_KEY.
- Terminate TLS correctly. If behind a load balancer, configure Laravel trusted proxies with the actual trusted addresses and forwarded headers in bootstrap/app.php; do not blindly trust arbitrary clients. No proxy allowlist is assumed for this local application.
- Serve only public/ as the web root. Deny direct access to .env, database backups and storage/app/private; do not symlink private signatures or PDFs into public storage.
- Make storage/ and bootstrap/cache writable by the application account only as needed. Protect the database and encrypted files. Back up MySQL, private files and APP_KEY through separate protected channels and test restores.
- Serve sw.js and manifest.webmanifest with correct JavaScript/manifest MIME types and revalidation/no-cache headers. Static fingerprinted build assets can have long cache lifetimes. Keep the previous release's assets during rolling deployment.
- Configure real mail delivery for password recovery and restrict production logs/retention. Do not log request payloads or signatures.
- These additions are synchronous and require no new queue worker or scheduled job. Existing mail/queue configuration still applies. Draft discard retains records; there is no automatic retention purge.
- No deployment was performed. No offline transactions, card transfers, external integrations or native apps were added.

## Verification

See storage/app/milestone3-*.json for local check output. Focused feature tests cover draft revision/tenant protection and retention, branding permissions/upload validation/encryption/report output, historical branding and customer handoff. Existing workflow tests verify encrypted signatures/PDFs and unchanged archived branding. Headless Edge checks cover responsive layouts/signature rotation and a service-worker cache/offline check. These do not establish real Android/iPhone installation or full production acceptance.

## Principal files

- Data/security: app/Support/DocumentBranding.php; app/Support/SwapWorkflow.php; app/Support/SwapPdf.php; app/Models/Company.php; app/Models/CardSwap.php; app/Support/CompanyAccess.php; the migration and database/seeders/BrandingPermissionsSeeder.php.
- HTTP: BrandingController, CustomerModeController, DraftController, updated CardSwapController/CompanyController/ReportController; BrandingRequest/DraftRequest/CompanyRequest; CustomerHandoff middleware; routes/web.php.
- UI: resources/views/branding/, documents/, pwa/; layouts/customer.blade.php and layouts/app.blade.php; swaps/drafts, customer-done, show, wizard, consents and pdf; reports/document and index; companies/form and settings; resources/css/app.css.
- Browser/PWA: resources/js/workflow.js, pwa.js and app.js; resources/pwa/; app/Console/Commands/BuildPwa.php; generated public/sw.js, offline.html, manifest.webmanifest and pwa/; package.json.
- Localisation/checks: lang/en.json, lang/rw.json, lang/rw/validation.php; tests/Feature/Milestone3Test.php and updated SwapWorkflowSmokeTest.php.
