# Annie's Massages Admin Dashboard

Project scaffold generated from the architecture spec. The repository is organized as a modular PHP monolith with shared runtime files, business modules, process handlers, models, integrations, and supporting assets.

## MySQL In Docker

This project is scaffolded for a Docker MySQL setup that mirrors the `optical-express` style as closely as possible:

- Docker runs the MySQL server
- database credentials live in `.env`
- the shared connector lives in `func/connect.php`
- SQL bootstrap files can live in `database/init`
- long-term schema and incremental changes still belong in `database/` and `migrations/`

Setup flow:

- Copy env defaults: `cp .env.example .env`
- Install PHP dependencies: `composer install`
- Start MySQL: `docker compose up -d`
- Watch startup logs: `docker compose logs -f mysql`

Connection defaults:

- Host: `127.0.0.1`
- Port: `3307`
- Database: `anniesmassages`
- Username: `admin`
- Password: `Password@123!`

Shared connector entry points:

- `func/connect.php`
- `func/functions.php`

Helper functions available once bootstrap loads:

- `db_configured()`
- `db_connection()`
- `pdo_connection()`
- `db_is_online()`

## Tailwind CSS

Tailwind CSS is configured with the official CLI and a PHP-friendly source file at `resources/css/tailwind.css`.

- Install dependencies: `npm install`
- Build once: `npm run build:css`
- Watch for changes: `npm run watch:css`

Compiled output is written to `assets/css/tailwind.css` and is included globally by `includes/css.php`.

## TypeScript

Frontend scripts are authored in TypeScript under `resources/ts` and compiled to `assets/js` for the PHP templates.

- Build once: `npm run build:ts`
- Watch for changes: `npm run watch:ts`

Current source/output mapping:

- `resources/ts/app.ts` -> `assets/js/app.js`
- `resources/ts/bookings.ts` -> `assets/js/bookings.js`
- `resources/ts/calendar.ts` -> `assets/js/calendar.js`
- `resources/ts/modals.ts` -> `assets/js/modals.js`

## Typography

The UI uses `Inter` as the primary typeface with a neutral product-style sans-serif stack:

- `font-family: 'Inter', system-ui, -apple-system, sans-serif;`

The dashboard avoids decorative or serif display fonts so the visual tone comes from spacing, color, and contrast instead of typography.
