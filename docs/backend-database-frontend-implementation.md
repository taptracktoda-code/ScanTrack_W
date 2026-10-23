# ScanTrack Backend, Database, and Frontend Implementation

This document describes the implementation currently present in the ScanTrack Laravel application. It is an implementation reference, not a new API or a proposal for features that are not in the codebase.

## 1. Application architecture

ScanTrack is a Laravel 12 application using PHP 8.2 or later. Laravel routes direct browser requests and JSON API requests to controllers. Controllers validate requests and coordinate application behavior through Eloquent models and services. Blade templates render the browser interface.

| Area | Implementation |
| --- | --- |
| Browser routes | `routes/web.php` |
| JSON API routes | `routes/api.php` |
| Controllers | `app/Http/Controllers/` |
| Request validation | `app/Http/Requests/` |
| Business services | `app/Services/` |
| Eloquent models | `app/Models/` |
| Database schema | `database/migrations/` |
| Browser templates | `resources/views/` |
| Frontend source assets | `resources/css/`, `resources/js/` |

## 2. Backend implementation

### Authentication and access control

The application supports browser-session authentication and token-based API authentication:

- Admin browser login and logout are handled by `AuthController`.
- Manager browser login, logout, and password changes are handled by `ManagerSessionController`.
- API routes use Laravel Sanctum authentication (`auth:sanctum`).
- Role middleware separates admin, manager, and driver access.
- Manager and driver protected operations are further gated by password-change middleware until the user has changed a required temporary password.
- `User` stores the authenticatable accounts in the `admins` table and provides role helpers for super-admin, manager, and driver roles.

### Browser application features

The admin browser routes provide the dashboard, driver registration and review, QR management, manager and vehicle administration, reports, analytics, organization settings, and system-health views. Manager routes provide a manager dashboard and organization event/schedule management.

Controllers are grouped around those features. For example, `DriverController` handles driver and QR management, `DriverVerificationController` handles approval and rejection, `ReportController` and `AnalyticsController` serve reporting screens and exports, and `ManagerDashboardController` handles manager dashboard and schedule actions.

### JSON API

The API includes:

| Route group | Main operations |
| --- | --- |
| Public authentication | Driver, admin, and manager login |
| `/api/admin` | Admin dashboard data and QR scanning |
| `/api/manager` | Manager logout, password change, driver registration, and queue operations |
| `/api/driver` | Driver logout, password change, profile, shift status, dashboard, and attendance |

API access is role-protected where appropriate, with Sanctum tokens used for authenticated requests. The driver API also declares trip and schedule endpoints; their current controller methods return empty results and should be treated as placeholders rather than completed trip/schedule functionality.

### Driver registration and verification

Manager-created drivers are provisioned immediately within the manager's assigned active organization. Their driver account, vehicle, and QR credential are activated together, and a temporary login password is emailed to the driver; the driver must change it on first login. This applies to both Manager web and mobile API creation.

Other driver registration flows that create pending records continue through Super Admin verification. Admin verification actions approve or reject those registrations; approval activates the related records and QR credential, while rejection records the decision and reason. Mail classes and Blade email templates provide account, password OTP, and rejection messages.

### QR scan processing

`QrScanController` validates scan input through `QrScanRequest`, adds the authenticated admin ID, and delegates processing to `QrScanService`.

The service:

1. Looks up the QR credential and loads its driver, organization, and vehicle.
2. Rejects missing or inactive QR credentials, inactive drivers or organizations, and organization mismatches.
3. Opens a database transaction and locks the QR record while deciding whether to accept the scan.
4. Rejects a repeat scan within the configured 10-second duplicate window.
5. Alternates the driver's daily scan type between `ENTRY` and `EXIT`.
6. Saves a `driver_logs` row and a corresponding `terminal_activities` audit row in the same transaction.
7. Returns a JSON success payload with the driver, vehicle, organization, scan type, and timestamp, or a validation-style error response.

### Organization queue

Manager API queue operations create organization-specific queue entries and move them through `WAITING`, `SERVING`, and `COMPLETED`. Queue entries retain the manager who enqueued and served the driver, timestamps, and a queue number. Queue changes are also represented in terminal activity records.

## 3. Database implementation

Database structure is defined by the ordered Laravel migrations in `database/migrations/`. Configure the active database connection in `.env`; the repository README uses MySQL as its example. The migrations also include Laravel infrastructure tables for cache, queue jobs, and API tokens.

### Main application tables

| Table | Purpose |
| --- | --- |
| `admins` | Admin, manager, and driver login accounts; includes role, status, organization, profile, and password-change fields. |
| `drivers` | Driver identity, license, contact, vehicle number, organization, registration, and approval/rejection data. |
| `vehicles` | Fleet vehicle details, including a unique plate number, status, and organization association. |
| `qr_codes` | Driver QR credentials, with a unique token and one credential row per driver. |
| `driver_logs` | Driver attendance scan records, including scan type, method, terminal, and timestamp. |
| `toda_organizations` | Organization records and operational status. |
| `organization_queue_entries` | Organization queue number, driver, state, manager actors, and service timestamps. |
| `terminal_activities` | Audit trail of terminal actions, optionally linked to an account and driver. |
| `settings` | Application settings stored as key/value data. |
| `organization_events` | Organization schedule/event records. |
| `organization_destinations` | Organization destination records. |
| `trips` | Driver trip records, with destination association introduced by a later migration. |
| `personal_access_tokens` | Sanctum polymorphic API tokens. |

### Relationships and integrity

- An organization has many drivers and vehicles; drivers and vehicles reference their organization.
- A driver has many attendance logs and trips, and at most one QR credential. QR credentials and scan logs use foreign keys to drivers.
- Queue entries reference an organization and driver, with optional account references for the manager who queued or served the driver.
- Terminal activities can reference an account and driver; those references are nullable so audit records can remain when an associated record is removed.
- Driver-to-vehicle association is resolved in the model through `drivers.vehicle_number` matching `vehicles.plate_number`; it is not a conventional `vehicle_id` foreign key.
- Driver accounts are represented in `admins`; account-to-driver linkage uses the fields and conventions in the application rather than assuming a dedicated database foreign key.
- Foreign-key deletion behavior and uniqueness/index constraints are defined by the migrations and should be treated as authoritative.

## 4. Frontend implementation

### Rendering and shared layout

The browser UI is server-rendered with Blade. Authenticated management pages share `resources/views/layouts/app.blade.php`, which composes the sidebar and navbar, yields the page content, and loads shared dashboard styling and Bootstrap assets. Authentication pages use the separate auth layout.

The sidebar menu is built from route-aware menu items and marks the current route active. It includes role-aware profile/logout links. The shared application layout also persists desktop sidebar collapse state in local storage, remembers sidebar scroll positions for the session, confirms forms marked for deletion, and displays a success toast when a status message is present.

### Page/view areas

| View directory | User interface |
| --- | --- |
| `resources/views/dashboard/` | Admin dashboard |
| `resources/views/drivers/` | Driver list, verification, and related forms/modals |
| `resources/views/managers/` | Manager list and manager dashboard |
| `resources/views/vehicles/` | Vehicle administration |
| `resources/views/qr-management/` | QR credential management |
| `resources/views/queues/` | Queue monitoring |
| `resources/views/reports/` | Reports, attendance/activity, and pending logs |
| `resources/views/analytics/` | Analytics and export interface |
| `resources/views/settings/` | Application, profile, and organization settings |
| `resources/views/system-health/` | System health view |
| `resources/views/auth/` | Login, registration, and password-change screens |
| `resources/views/emails/` | Account and password-related email templates |

### Styling and browser scripts

The project includes Vite, Tailwind CSS, Axios, and Cropper.js in its frontend dependencies. The dashboard layout directly loads Bootstrap 5, Bootstrap Icons, and SweetAlert2, along with `public/css/dashboard.css`. The Vite JavaScript entry point imports the shared bootstrap setup; the profile-picture cropper has its own JavaScript source file.

## 5. Local development and verification

1. Install PHP dependencies with `composer install`.
2. Install frontend dependencies with `npm install`.
3. Configure `.env`, including the database connection.
4. Run migrations with `php artisan migrate`.
5. Start the development processes with `composer run dev`, or run the Laravel server and Vite separately.
6. Build frontend assets with `npm run build`.
7. Run tests with `php artisan test`.

## 6. Implementation boundaries

- This guide documents code and routes present in the repository; it does not claim that declared empty trip/schedule API methods implement those business workflows.
- Migrations are the source of truth for the physical database schema. When updating the schema, add a migration rather than editing an already-applied migration.
- Keep browser routes, API routes, role middleware, validation requests, and corresponding UI actions consistent when adding features.
- Store secrets and environment-specific connection settings in `.env`, not in source files.
