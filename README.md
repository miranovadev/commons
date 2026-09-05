# The Commons

An emergent simulation where ideas are allowed to exist, but only if they can persist under constraint.

The Commons provides a shared cognitive substrate in which propositions are distinguished, related, reinforced, and committed through participant-driven cycles. The implementation is built with Laravel, React, Mantine, and RTK Query.

Read the [public documentation](https://miranova.dev/commons) for the conceptual model, or the [Markdown edition](https://miranova.dev/commons.md) for a portable text version.

## Requirements

- PHP 8.3 or newer
- Composer 2
- Node.js and npm (Node.js 24 is used in CI)
- SQLite with the PHP SQLite extension for the default local setup, or PostgreSQL with `pdo_pgsql`

## Getting started

Clone the repository and enter its directory:

```bash
git clone https://github.com/clairemira/commons.git
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

The development command runs the Laravel server, queue listener, application log viewer, and Vite development server together. Press `Ctrl+C` to stop them.

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
```

GitHub Actions runs the frontend build and PHP test suite against PostgreSQL for every push.
