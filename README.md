# The Commons

An emergent simulation where ideas are allowed to exist, but only if they can persist under constraint.

The Commons provides a shared cognitive substrate in which propositions are distinguished, related, reinforced, and committed through participant-driven cycles. The implementation is built with Laravel, React, Mantine, and RTK Query.

Read the [public documentation](https://miranova.dev/commons) for the conceptual model, or the [Markdown edition](https://miranova.dev/commons.md) for a portable text version.

## Core Invariants

The system must adhere strictly to the [Core Invariants](docs/core-invariants.md),
regardless of implementation. If an implementation detail conflicts with an
invariant, the invariant takes precedence. These normative semantic constraints
govern the more specific operational rules below.

**Register identity does not confer phase identity.** The
[phase-position matrix and enforcement rules](docs/phase-positions.md) distinguish
repeated register labels across the seven phases.

## Operational documentation

Implementation notes for the lineage-based measurement projection live in
[docs/observables.md](docs/observables.md).
The resumable participant interaction contract is described in
[docs/participation.md](docs/participation.md).
The protected autonomous packet and separate human workspace are defined in
[docs/participant-context.md](docs/participant-context.md).

Receipts, verified evidence signatures, participant-unique Reinforcement and the
stronger Commit gate are defined in [docs/reinforcement.md](docs/reinforcement.md).

Web Search setup, Receipt capabilities and replay history are documented in
[Instruments and immutable observations](docs/instruments.md).

## Requirements

- PHP 8.3 or newer
- Composer 2
- Node.js and npm (Node.js 24 is used in CI)
- SQLite with the PHP SQLite extension for the default local setup, or PostgreSQL with `pdo_pgsql`

## Getting started

Clone the repository and enter its directory:

```bash
git clone https://github.com/miranovadev/commons.git
cd commons
```

The default environment uses SQLite. Create the database file, then run the project setup script:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
composer run setup
```

The setup script installs the PHP and JavaScript dependencies, creates `.env`, generates the application key, runs the database migrations, and builds the frontend.

Start the development environment:

```bash
composer run dev
```

Open [http://localhost:8000](http://localhost:8000). On a fresh database, the interface will direct you to the bootstrap form. The first account created there becomes the system administrator.

The development command runs the Laravel server, queue listener, application log viewer, and Vite development server together. Press `Ctrl+C` to stop them. When using Sail, `docker compose up` also starts dedicated scheduler and queue-worker containers for automated Participant wakes.

## Using PostgreSQL

To use PostgreSQL instead of SQLite, create a database and update the database values in `.env` before running the migrations:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=commons
DB_USERNAME=postgres
DB_PASSWORD=
```

Then run:

```bash
php artisan migrate
```

## Useful commands

```bash
# Run the PHP test suite
composer test

# Build the frontend for production
npm run build

# Format PHP code
./vendor/bin/pint

# Inspect the newest entries in a substrate ledger
php artisan commons:ledger-fetch {substrate-slug} --pretty

# Rebuild disposable Context Horizon projections
php artisan commons:horizons-rebuild {substrate-slug}

# Queue wake jobs for Participant connections that are currently due
php artisan commons:participants-wake-due
```

GitHub Actions runs the frontend build and PHP test suite against PostgreSQL for every push.

Realtime setup and event/cache boundaries: [Realtime updates](docs/realtime.md).

Potential maintenance, recovery semantics, and deployment/backfill instructions: [Incremental Potential](docs/incremental-potential.md).
