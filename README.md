# ScanTrack: QR-Based Entry and Exit Monitoring System

A Laravel-based system for managing TODA drivers, vehicles, QR credentials, terminal entry and exit scans, attendance, reports, and operational settings.

## Overview

This project is designed for a transport or fleet operation workflow where administrators can:

- register and manage drivers
- assign and manage vehicles
- generate and manage secure QR codes for drivers
- track driver activity through authenticated QR scans
- review logs, pending records, and reports
- manage organization and system settings
- monitor dashboard and operational health

## Features

- Admin authentication and role-based access
- Driver registration and profile management
- QR code generation and activation/deactivation
- QR scan processing and entry/exit log tracking
- Dashboard statistics and shift status updates
- Reports and export support
- Vehicle management
- Organization configuration and profile settings
- System health monitoring

## Tech Stack

- PHP 8.2+
- Laravel 12
- MySQL database
- Vite + Tailwind for frontend assets
- Composer for dependency management
- PHPUnit for automated testing

## Project Structure

- `app/` — application logic, controllers, models, services, middleware
- `config/` — Laravel configuration files
- `database/migrations/` — database schema definitions
- `database/seeders/` — seed data
- `public/` — public web assets and entrypoint
- `resources/` — Blade views and frontend assets
- `routes/` — web and API route definitions
- `tests/` — test suite
- `vendor/` — Composer dependencies

## Prerequisites

Before running the project, make sure the following are installed:

- PHP 8.2 or newer
- Composer
- Node.js 18+ and npm
- MySQL or another supported database
- A local or remote mail server configuration for email features

## Installation

1. Clone the repository:

   ```bash
   git clone <repository-url>
   cd webappreviveeeee
   ```

2. Install PHP dependencies:

   ```bash
   composer install
   ```

3. Install frontend dependencies:

   ```bash
   npm install
   ```

4. Create your environment file:

   ```bash
   copy .env.example .env
   ```

   If `.env.example` does not exist in your setup, create a `.env` file based on your local configuration.

5. Generate the application key:

   ```bash
   php artisan key:generate
   ```

6. Configure your database and mail settings in `.env`.

   Example:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_database_name
   DB_USERNAME=your_db_user
   DB_PASSWORD=your_db_password
   ```

7. Run database migrations:

   ```bash
   php artisan migrate
   ```

8. Build frontend assets:

   ```bash
   npm run build
   ```

## Running the Application

### Development mode

```bash
composer run dev
```

This starts the Laravel app, queue worker, log monitoring, and Vite dev server together.

### Alternative local run

```bash
php artisan serve
npm run dev
```

Then open the app in your browser at:

```text
http://localhost:8000
```

## Default Access

The system includes authentication routes for login and registration, and uses admin-protected routes for operational management.

## Testing

Run the application test suite with:

```bash
php artisan test
```

Or using Composer:

```bash
composer test
```

## Environment Notes

- `APP_ENV` should be set to `local` during development.
- `APP_DEBUG` should be `true` while debugging issues.
- Update `APP_URL` to match your local environment.
- Set your Mail configuration correctly if activation emails or notifications are used.

## Railway Deployment

### Architecture and runtime

TapTrack is a Laravel 12 monolith: Laravel serves the Blade web application, JSON API, and Vite-built assets from one service. There is no separate frontend service or frontend API URL to configure. The repository's `Dockerfile` builds the assets with Node.js 22, installs production dependencies with Composer 2 on PHP 8.3 with Apache, and serves Laravel's `public` directory.

The Docker entrypoint binds Apache to Railway's `PORT`, requires production mode, `APP_DEBUG=false`, an existing `APP_KEY`, a public HTTPS `APP_URL`, secure session cookies, stderr logging, SMTP configuration, and a MySQL connection. It then caches Laravel config and views and verifies database connectivity without printing connection details. It does not generate keys, run migrations, seed data, or start a queue worker. Railway uses `/up` for its process health check; this endpoint confirms the web process responds and does not claim the database is healthy.

### Create the Railway services

1. Push this repository to the Git branch you intend to deploy. Set the Railway service root directory to the repository root (`.`); Railway reads [`railway.json`](./railway.json) and builds the root [`Dockerfile`](./Dockerfile).
2. Add a Railway MySQL service. In the web service's Variables, reference the MySQL service values (replace `MySQL` with the exact Railway service name):

   ```env
   DB_CONNECTION=mysql
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_PORT=${{MySQL.MYSQLPORT}}
   DB_DATABASE=${{MySQL.MYSQLDATABASE}}
   DB_USERNAME=${{MySQL.MYSQLUSER}}
   DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
   ```

   Use Railway's variable-reference picker if the referenced service has a different name. Do not copy database credentials into Git or the Docker image.
3. Configure the web service variables. Enter real secret values only in Railway's Variables UI:

   ```env
   APP_NAME=TapTrack
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://<your-public-domain>
   APP_KEY=<the-existing-application-key>
   LOG_CHANNEL=stderr
   LOG_LEVEL=info
   SESSION_DRIVER=database
   SESSION_SECURE_COOKIE=true
   CACHE_STORE=database
   QUEUE_CONNECTION=sync
   FILESYSTEM_DISK=public
   MAIL_MAILER=smtp
   MAIL_SCHEME=smtp
   MAIL_HOST=<smtp-host>
   MAIL_PORT=587
   MAIL_USERNAME=<smtp-username>
   MAIL_PASSWORD=<smtp-password>
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=<verified-sender-address>
   MAIL_FROM_NAME=TapTrack
   ```

   Keep the established application's `APP_KEY`; changing it invalidates encrypted data and sessions. For a brand-new installation only, generate a key once using `php artisan key:generate --show` on a trusted local machine and enter it directly in Railway Variables. Never run key generation during deployment or commit the key.

   `SESSION_DRIVER` and `CACHE_STORE` use the existing MySQL tables, created by the migrations. `QUEUE_CONNECTION=sync` is appropriate for the current code, which sends mail synchronously and does not dispatch queued jobs; no worker service is required. If queued jobs are introduced later, configure and monitor a separate Railway worker before switching to a database/Redis queue.
4. Configure SMTP before enabling driver registration or password-reset flows. Those flows depend on email delivery. Keep mail credentials in Railway Variables.
5. Add a Railway Volume to the web service mounted at `/var/www/html/storage/app/public`. This preserves uploaded profile pictures across deploys. Current uploads are profile images validated as JPG, JPEG, PNG, or WebP up to 2 MB; the code does not upload private driver documents. Keep any future private documents off the public disk and authorize downloads. The Docker image creates the `public/storage` symlink. This local volume setup is for a single web instance; use a shared S3-compatible disk before scaling the web service to multiple instances.
6. Enable a Railway public domain and set `APP_URL` to its HTTPS URL. The app trusts the forwarding headers from Railway's ingress so Laravel recognizes the external HTTPS scheme. Do not expose the container directly around the Railway proxy.

### First deployment and migrations

Do not run migrations automatically at startup. Review and back up the target database first; then, in the Railway web service's shell/console (not a local shell), run:

```sh
php artisan migrate:status
php artisan migrate --force
php artisan migrate:status
```

The migrations create the schema but do not seed production accounts. After the migration succeeds, create the initial Super Admin from the Railway service shell using the existing CLI command:

```sh
php artisan scantrack:make-super-admin <administrator-email> --name="<administrator-name>"
```

The command prompts for and confirms the password without echoing it. Do not run `php artisan db:seed` in production: the current `DatabaseSeeder` creates a test account. Do not expose an unauthenticated Super Admin registration route.

The `2026_06_29_000002_delete_admin_driver_records` migration is now a no-op so a driver named “Administrator” is not silently deleted. The NFC retirement migration still copies legacy card records to QR records but retains the original NFC table. These changes protect databases that have not yet applied those migrations; they cannot restore records removed by an earlier release. Back up existing databases and check migration history before deploying.

### Backups and safe updates

Run database backups from a trusted machine with MySQL client tools installed. The `--password` option prompts interactively; replace the placeholders with Railway's database connection details:

```sh
mysqldump --single-transaction --host="<DB_HOST>" --port="<DB_PORT>" --user="<DB_USERNAME>" --password "<DB_DATABASE>" > taptrack-backup.sql
```

Restore only into a deliberately selected database, after confirming the target and testing the backup:

```sh
mysql --host="<DB_HOST>" --port="<DB_PORT>" --user="<DB_USERNAME>" --password "<DB_DATABASE>" < taptrack-backup.sql
```

Back up the Railway Volume's uploaded files separately using an approved encrypted off-service backup process; volume contents are not included in the MySQL dump or the application image. Test database and file restores before relying on them. Before each release, back up the database, deploy the new image, and apply reviewed forward migrations manually. Roll back application code by redeploying a known-good image; do not blindly run `migrate:rollback` against production.

### Verify the deployment

After migrations complete, check:

```sh
php artisan about
php artisan migrate:status
```

Then open `https://<your-public-domain>/up` (expect HTTP 200), the login page, and the web/API flows you use. Check Railway deployment logs if the container exits: the entrypoint reports missing `PORT`, production settings, `APP_KEY`, or MySQL variables without printing their values. A healthy `/up` response does not test database or SMTP availability; verify those separately using the application and Railway logs.

Troubleshooting:

- If the container exits before Apache starts, set all required Railway variables and inspect startup logs for the missing variable or failed database connection.
- If login or session cookies fail behind HTTPS, verify `APP_URL` uses the public `https://` domain and `SESSION_SECURE_COOKIE=true`.
- If uploaded pictures disappear after redeploy, confirm the volume is mounted at the exact path above and the application uses `FILESYSTEM_DISK=public`.
- If registration or OTP mail fails, configure valid SMTP credentials and confirm the sender is allowed by the mail provider.

For local checks before pushing:

```sh
composer validate --no-check-publish
composer check-platform-reqs --no-dev
npm ci
npm run build
php artisan test
docker build -t taptrack .
```

The MySQL migrations must be tested against a fresh Railway MySQL database or a disposable MySQL instance; passing SQLite tests is not proof of MySQL compatibility. Never run `migrate:fresh` against a production database.

### Credential rotation

A mail credential was present in an earlier version of the tracked `.env.example` and has been replaced with placeholders. Removing it from the current file does not remove it from Git history. Revoke/rotate that SMTP credential with the mail provider immediately, update Railway Variables, and review Git history. Do not rewrite shared history or force-push without explicit authorization.

The checked-in `.env.example` is for local development and contains placeholders, not Railway production secrets. `.env` is ignored by Git. There is no separate browser frontend origin or Vite API URL in this application; keep the web app and API on the same origin. If you later add a browser client on another origin, configure CORS to allow only that exact origin rather than using a wildcard.

## License

This project is open-source and licensed under the MIT license.
