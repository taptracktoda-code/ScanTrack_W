# Driver Management & QR/NFC Tracking System

A Laravel-based web application for managing drivers, vehicles, QR cards, NFC scan events, shift tracking, reports, and operational settings.

## Overview

This project is designed for a transport or fleet operation workflow where administrators can:

- register and manage drivers
- assign and manage vehicles
- generate and manage QR codes and NFC cards
- track driver activity through NFC/QR scans
- review logs, pending records, and reports
- manage organization and system settings
- monitor dashboard and operational health

## Features

- Admin authentication and role-based access
- Driver registration and profile management
- QR code generation and activation/deactivation
- NFC scan processing and log tracking
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

## Production Deployment

For production, make sure you:

1. set `APP_ENV=production`
2. set `APP_DEBUG=false`
3. configure your database and cache storage properly
4. run `php artisan optimize`
5. build front-end assets with `npm run build`
6. serve the app via a proper PHP/Apache/Nginx or container setup

## License

This project is open-source and licensed under the MIT license.
