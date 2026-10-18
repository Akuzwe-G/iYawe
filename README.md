# iYawe — Milestones 1 and 2

Multi-company digital acknowledgement foundation built with Laravel 13, PHP 8.3+, Blade, Tailwind 4, Alpine.js 3 and Spatie Permission 8. Card swap acknowledgements, signatures, PDFs, reports and analytics are implemented in Milestone 2. PWA installation remains a future milestone. See [MILESTONE2.md](MILESTONE2.md) for workflow, mobile layout, consent activation and verification details.

## Start locally

Use Laragon's existing MySQL database and web server, then open http://iyawe.test/login. The project already has its MySQL connection; do not replace credentials or generate a new application key for an existing installation.

For a new checkout, install dependencies and configure its own .env first:

```powershell
composer install
npm.cmd ci
php artisan migrate
npm.cmd run build
```

For live frontend development, use `npm.cmd run dev`. Alternatively serve Laravel using `php artisan serve` and visit the URL it prints. Montserrat 400/500/600/700 is downloaded during the frontend build and served locally by Vite's font integration. A first build needs internet access.

## First System Administrator

Set the following in your local `.env` (use your own email and a unique password):

```dotenv
IYAWE_SYSTEM_ADMIN_NAME="iYawe System Administrator"
IYAWE_SYSTEM_ADMIN_EMAIL="your-email@example.com"
IYAWE_SYSTEM_ADMIN_PASSWORD="your-own-strong-password"
```

The password must contain at least 12 characters, including letters and numbers. Keep it quoted in the environment file.

```powershell
php artisan config:clear
php artisan db:seed
```

`RolesAndPermissionsSeeder` prepares the approved permission catalogue and reserved roles for existing companies. `SystemAdminSeeder` creates the platform administrator from configuration, hashing the password. With empty email/password fields it skips account creation. The default seeder creates no demonstration users or companies.

Sign in at http://iyawe.test/login. Use Companies → Create company, then Assign Company Administrator on the saved company's edit screen. Company administrators are created through these screens; no company-admin command is needed.

Rerunning the seeders preserves existing System Administrator credentials, profile and activation status. An existing non-platform account is never silently promoted. Once the account exists, you can clear `IYAWE_SYSTEM_ADMIN_PASSWORD` from `.env` and run `php artisan config:clear`; use Settings or supported password recovery to change passwords afterward. Keep `.env` private and uncommitted.

The earlier hidden-password `php artisan iyawe:admin` command remains available as an alternative bootstrap method.

## Create a company and its administrator

1. As a System Administrator, open Companies → Create company.
2. Enter its name, unique slug, optional contact details and status; save.
3. On its edit screen, use Assign Company Administrator.
4. New account holders can sign in using the initial password shared securely by their administrator.
5. Existing account holders keep their account identity and password. They see a pending company invitation after signing in and must accept it themselves.
6. The company administrator can use Users, Roles & Permissions, Locations and Settings. Multiple-company members choose an active company using the header switcher.

Staff names can be changed for the current membership using Company display name. Email, global profile name, language and password belong to the account owner and are edited in Settings after confirming their current password. Deactivating a membership blocks that company only. The final active Company Administrator cannot be deactivated or lose that role. Pending invitations are distinct from deactivated memberships; deactivated staff cannot reactivate themselves.

System Administrators can also open Settings → Platform settings to set the default guest language and a support email. A configured support email appears as a contact link on the login screen.

## Architecture and important files

- `app/Http/Middleware/CompanyContext.php`: validates account status, active membership and company status every request, selects a sole company, and resets the Spatie team and loaded permission relations.
- `app/Http/Middleware/LocalePreference.php` and `app/Models/PlatformSetting.php`: account/guest language selection and platform preferences.
- `app/Policies/CompanyPolicy.php`: platform authorization and company permission checks.
- `app/Support/CompanyAccess.php`: reserved roles and approved company permission catalogue.
- `app/Support/MembershipManager.php`: transactional memberships, owner acceptance, scoped role assignment and last-administrator protection using a company row lock.
- `app/Support/Audit.php`: administrative audit events without passwords, tokens or customer data.
- `app/Http/Controllers/{Auth,Company,Staff,Role,Workspace}Controller.php`: authentication and working administration.
- `app/Http/Requests/`: company, staff and role validation.
- `app/Console/Commands/CreateSystemAdministrator.php`: secure administrator bootstrap.
- `routes/web.php`: named web routes, authentication and throttling.
- `resources/views/`, `resources/css/app.css`, `resources/js/app.js`, `vite.config.js`: responsive Montserrat interface and Alpine interactions.
- `lang/en.json`, `lang/rw.json`, `lang/rw/`: interface, validation and common translations.
- `tests/Feature/{Authentication,Foundation,TenantSecurity}Test.php`: feature and tenant security tests.

Platform administrators are represented by a protected `users.is_system_admin` flag. This is deliberately outside Spatie company roles; there is no blanket Gate bypass. They manage company metadata and initial administrators, with no implicit access to company staff, roles or locations. Company roles use Spatie teams with `company_id`. Reserved Company Administrator and Field Staff roles are immutable through the web interface. Company custom roles can only receive approved company permissions.

All company resource lookups are scoped to the validated session company. Submitted company IDs cannot override that context. New memberships for existing accounts remain pending until accepted by their owner. No public registration routes exist.

## Database

Existing users, password reset tokens, sessions, cache and queue tables are preserved.

- `companies`: name, unique slug, contacts, active/suspended status, timestamps.
- `users`: adds active account flag, protected platform-admin flag and preferred locale.
- `company_user`: unique company/user pair, foreign keys, active/pending membership flags, local display name and timestamps.
- `roles`: company foreign key and unique company/name/guard; `permissions`: unique name/guard catalogue.
- `model_has_roles` and `model_has_permissions`: company-scoped composite keys and company foreign keys; `role_has_permissions` connects roles to catalogue permissions.
- `platform_settings`: unique configuration key/value pairs for default guest language and support contact.
- `locations`: company foreign key, unique company/name, address and active status.
- `activity_logs`: actor, company, action, subject ID and timestamps, indexed by company/date. No secrets are recorded.

Migrations are additive. Do not run `migrate:fresh` against the local MySQL database. Automated tests use isolated in-memory SQLite and RefreshDatabase.

## Working screens and routes

| Screen | Routes |
|---|---|
| Login / logout | GET/POST /login, POST /logout |
| Password recovery, when mail supports delivery | GET/POST /forgot-password, GET /reset-password/{token}, POST /reset-password |
| Language | POST /language |
| Personalised dashboard | GET /dashboard |
| Company switch / invitation acceptance | POST /company/switch, POST /invitations/{company}/accept |
| Company search, create, edit and suspend | GET/POST /companies, GET /companies/create, GET /companies/{company}/edit, PUT /companies/{company} |
| Initial administrator | POST /companies/{company}/administrator |
| Staff search, create and membership edit | GET/POST /staff, GET /staff/create, GET /staff/{user}/edit, PUT /staff/{user} |
| Roles and permissions | GET/POST /roles, GET /roles/create, GET /roles/{role}/edit, PUT /roles/{role} |
| Locations | GET/POST /locations, PUT /locations/{location} |
| Profile / company settings | GET/PUT /settings, PUT /settings/company, PUT /settings/platform |

Use `php artisan route:list --except-vendor` for the complete route list. The root URL redirects to login or dashboard.

## Password reset and deployment

The local mailer is currently `log`. Recovery is hidden and its endpoints return 404 with this mailer so reset tokens are never logged. Configure a supported real mailer (for example `MAIL_MAILER=smtp` and the appropriate MAIL_HOST/MAIL_PORT/MAIL_USERNAME/MAIL_PASSWORD/MAIL_FROM_ADDRESS), then run `php artisan config:clear` to enable recovery. Delivery depends on a working mail server. Unit tests use Laravel's in-memory mail transport.

Deploy with HTTPS, `APP_DEBUG=false`, the correct APP_URL, and `SESSION_SECURE_COOKIE=true`. Retain the existing APP_KEY and protect the environment file. Milestone 2 adds active records, PDF and reporting screens; see MILESTONE2.md. No sample business activity is generated. Kinyarwanda translations should receive a native-speaker review before public rollout.

## Verification

```powershell
php artisan test --compact
php vendor/bin/pint --format agent
npm.cmd run build
php artisan migrate:status
```

Actual verification results are recorded below. This workspace has no Git repository, so Pint's --dirty option is unavailable; run Pint normally.

## Milestone 1 verification

- MySQL: all seven migrations applied successfully, including four additive milestone migrations. Existing tables and data were retained.
- Full automated suite: 32 tests passed, 245 assertions.
- Production Vite build: passed, with locally hosted Montserrat assets.
- Blade compilation: passed.
- Laravel Pint: completed. The --dirty option was attempted but is unavailable because this workspace has no Git repository.
- Password delivery was tested through Laravel notifications; live SMTP delivery and manual browser/device checks have not been performed.
