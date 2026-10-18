# iYawe Milestone 2

## Delivered functionality

The existing multi-company application now includes a five-step card swap acknowledgement, three signature purposes, protected records, PDF documents, company reports, CSV summaries and database-driven dashboards. Public customer accounts, balance transfers, card deactivation, integrations and offline synchronisation are not implemented.

Mobile layouts use smaller headings and labels, compact spacing, wrapping controls, stacked record cards and bounded charts. The short-screen login hides its decorative introduction. Form inputs retain 16px text on phones to avoid iPhone focus zoom. There is no page-level horizontal overflow in the sampled browser checks.

## Run locally

The current workspace already has its new packages installed, migration applied and company-role permissions extended. Keep Laragon/MySQL running and visit http://iyawe.test/login.

For another checkout:

```powershell
composer install
npm.cmd ci
php artisan migrate
php artisan db:seed --class=SwapPermissionsSeeder
npm.cmd run build
```

Use `npm.cmd run dev` for live frontend development. The existing System Administrator environment bootstrap and company administration remain unchanged.

Dependencies: barryvdh/laravel-dompdf 3.1.2, dompdf 3.1.6, Chart.js 4.5.1 and Signature Pad 5.1.4. PDF Montserrat Regular/Bold TrueType assets and their SIL Open Font License are included under resources/fonts, from the official Montserrat project. The UI continues to use locally hosted Montserrat web fonts.

## Enable applicable approved consent

1. Sign in as the relevant Company Administrator, select the company and open **Consent versions**.
2. Read the supplied English Tap&Go declaration and receipt text. They preserve the wording provided by the project owner, including the reference to **AC Mobility / Tap&Go**.
3. Confirm that the exact wording is approved and applies to this company, then publish its version.
4. Staff can now select English consent independently of an English or Kinyarwanda interface.

No consent is activated automatically for unrelated organisations. No Kinyarwanda legal declaration was supplied, so its template remains empty and cannot be signed. An authorised company administrator may publish an independently approved translation or organisation-specific declaration through the same interface. Each publication creates a new version and retires the previous version for new signings. Previously signed records retain their original immutable declaration.

Operators may alternatively publish a completed configuration template for a specific company:

```powershell
php artisan iyawe:consent-publish company-slug en --approved-by=company-admin@example.com
```

The approving account must have company settings permission and an active membership. Templates live in config/consent.php.

## Lifecycle

| State | Behaviour |
|---|---|
| Draft | Steps 1–2 collect commuter, identification, card, balance, reason and optional location details. Unsigned drafts may return to an earlier step. |
| Consent Signed | Step 3 records an approved declaration/version, matching commuter name, explicit agreement, server timestamp and customer consent signature. Commuter/card details are locked afterward. |
| Pending Processing | Step 4 records the authenticated verifier, staff signature, server time and remarks. A pending outcome can be resumed; each staff verification retains a separate signed snapshot. |
| Processing Unsuccessful | A failed physical process requires remarks. The record becomes read-only and has no customer receipt confirmation. |
| Completed | Physical completion must first be explicitly confirmed in Step 4. Step 5 then captures customer receipt agreement and a separate receipt signature. Completed records are read-only. |

No receipt can be signed before physical completion. A pending record at step 5 means physical processing is finished but receipt confirmation remains outstanding. No completed historical record can be edited through the application; a correction workflow that alters signed records is intentionally not offered.

Creation uses a unique submission key. Each stage submission uses a revision checked inside a database transaction/row lock, so repeat or stale submissions cannot create duplicate signatures. Timestamps consistently use Laravel's configured app.timezone, displayed beside signatures and events.

Signature Pad supports pointer/touch/stylus input, clear/retry, expansion and landscape rotation. The server checks PNG type, decoded size, dimensions and visible ink. Images are associated with the record, company, purpose, actor, revision, timestamp and signed-data snapshot.

## Database

One additive migration was applied:

`database/migrations/2026_10_18_085545_create_card_swap_workflow_tables.php`

It creates:

- **card_swaps**: company, unique reference, idempotency key, creator/verifier/location, lifecycle/revision, commuter and card fields, fixed-precision decimal balances, consent/version and timestamps, immutable identity snapshots and PDF integrity metadata.
- **consent_versions**: company/language/version, complete declaration/receipt, approver, approval time and active flag.
- **swap_signatures**: company/record, purpose, revision, capturing actor, time, encrypted-file path, image/payload hashes and encrypted signed-data snapshot.
- **swap_events**: company/record, actor, event type, timestamp and non-sensitive metadata.

Company/date/status, creator/date, reason, location and exact-search fingerprints are indexed. Identification, names, contacts, card numbers and sensitive free text use Laravel encrypted casts. Exact searches use keyed HMAC fingerprints; encrypted values are never searched using wildcard decryption.

Signature images and generated PDFs are encrypted under storage/app/private. Authenticated, tenant-authorised controllers decrypt them for delivery with no-store responses. PDF generation embeds the three required signature purposes, original declaration/version, company and staff snapshots, card details and completion timestamps. The document is created and encrypted on the first authorised PDF request and reused afterward, with integrity checks.

Retain APP_KEY and private storage in backups. Changing the encryption key requires an explicit migration/re-encryption strategy for records, fingerprints and files.

## Permissions and privacy

The new controlled catalogue adds swaps.create, swaps.view, swaps.view-all, swaps.update-pending, swaps.complete, swaps.download and reports.export. Existing reports.view is reused.

Reserved Company Administrators receive company operational/report capabilities. Field Staff receive create, own-record view, continue, complete and download capabilities. Role assignments are preserved; permissions are added to reserved roles rather than resetting assignments. Existing custom roles can receive the new capabilities through Roles & Permissions.

Field records are restricted to their creator unless swaps.view-all is granted. Reports-only roles can access company aggregates without access to customer records. Platform analytics expose aggregate counts only; a System Administrator does not receive implicit access to customer details, signatures or PDFs.

Records mask names/card numbers in lists. Exact identification searches submit using POST and keep search criteria encrypted in the authenticated session; pagination URLs do not contain the identification number. Searches are bound to the active company. Personal identifiers/signature data are excluded from validation flashes and audit messages.

CSV exports include aggregate totals plus non-customer record summaries, with spreadsheet-formula escaping. They exclude customer National IDs, names, contacts, card numbers and signatures.

## Dashboards and data sources

- **System Administrator**: company/user totals, period swap aggregate count, company registrations by date, aggregate activity by month and current company-status distribution. Company/user/status totals are explicitly labelled all-time.
- **Company operational/report role**: total/completed/pending/unsuccessful counts, completion rate, active field staff, daily activity, staff comparisons, status distribution, replacement reasons and actual configured-location activity.
- **Field Staff**: their own counts, activity line chart, status distribution and permitted quick actions.

All figures use company-scoped database queries or authorised platform aggregates. There are no invented production figures. Period choices (7/30/90 days, year or up to 366 custom days) update period-based KPIs, charts and recent activity consistently. All-time metrics are labelled. Empty states replace charts where no activity exists. Each chart has real tooltips, legends where useful and an accessible data table.

## Routes and pages

| Route | Purpose |
|---|---|
| GET /swaps/create, POST /swaps | Start an acknowledgement |
| GET /swaps/{id}/continue | Current wizard step |
| POST /swaps/{id}/steps/{step} | Validated server-side transition |
| POST /swaps/{id}/back | Reopen an unsigned draft step |
| GET /records, POST /records/search | Filter/search authorised records |
| GET /swaps/{id} | Details, signatures and audit history |
| GET /swaps/{id}/signatures/{signature} | Protected signature image |
| GET /swaps/{id}/pdf | Download completed PDF; inline=1 opens browser viewing/printing |
| GET /reports, GET /reports/export | Company metrics and authorised CSV |
| GET /consents, POST /consents | Publish and inspect company consent versions |
| GET /dashboard | Updated role-aware analytics |

## Important files

- app/Models/{CardSwap,ConsentVersion,SwapSignature,SwapEvent}.php
- app/Policies/CardSwapPolicy.php
- app/Http/Requests/SwapStepRequest.php
- app/Support/{SwapWorkflow,SwapQuery,SwapAnalytics,SwapPdf}.php
- app/Http/Controllers/{CardSwapController,ReportController,WorkspaceController}.php
- app/Support/CompanyAccess.php and database/seeders/SwapPermissionsSeeder.php
- config/consent.php and app/Console/Commands/PublishConsentVersion.php
- resources/views/swaps/, resources/views/reports/, resources/views/dashboard.blade.php
- resources/views/components/swap-input.blade.php
- resources/css/app.css, resources/js/app.js and resources/views/layouts/app.blade.php
- resources/fonts/, lang/en.json, lang/rw.json and lang/rw/validation.php
- tests/Feature/SwapWorkflowSmokeTest.php (three focused smoke scenarios)

## Verification and remaining dedicated testing

The implementation uses a small new smoke test file rather than a comprehensive new suite. It exercises completion, private signature/PDF delivery, pending/unsuccessful outcomes, receipt gating, exact search, reports-only access and cross-company denial.

The browser review uses synthetic rendered fixtures, not customer data. It covers login in both languages, company dashboards, commuter form and consent/signature form at 320×568, 360×640, 375×667, 390×844, 430×932, 768×1024 and 1280×900, plus signature preservation after expansion/rotation. Results are stored locally under storage/app/milestone2-mobile-review.json.

Final command results are reported in the delivery message. These checks do not constitute comprehensive testing. The later dedicated phase should include real Android/iPhone/Safari devices and styluses, concurrency/load/fault recovery, larger production-like datasets, print layout review and a native-speaker translation review. Approved Kinyarwanda legal text remains outstanding; it is not substituted with generated wording.

## Final verification results

- MySQL additive workflow migration: applied successfully; existing data retained.
- Existing regression tests plus the three new smoke scenarios: **42 passed, 341 assertions**.
- Production frontend build: passed.
- Blade compilation and compiled PHP syntax checks: passed.
- Laravel Pint formatting: passed.
- Headless Edge fixture review: **43 checks passed**, no recorded browser errors, no horizontal page overflow at the sampled widths, login contained within the sampled phone heights, and signature data retained through expansion/rotation.

No known implementation errors remained in these checks. This is limited smoke/regression/browser verification; comprehensive testing and real Android/iPhone/Safari/stylus testing remain for the later dedicated phase.
