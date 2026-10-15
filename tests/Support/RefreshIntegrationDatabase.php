<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use LogicException;

/** Keep transaction isolation while reclaiming rolled-back PostgreSQL fixture storage. */
trait RefreshIntegrationDatabase
{
    use RefreshDatabase {
        refreshTestDatabase as private refreshTransactionalDatabase;
    }

    protected function refreshTestDatabase()
    {
        $connection = $this->app->make('db')->connection();
        if (getenv('COMMONS_INTEGRATION_MAINTENANCE') === '1'
            && $connection->getDriverName() === 'pgsql' && RefreshDatabaseState::$migrated) {
            if ($connection->transactionLevel() !== 0 || $connection->getPdo()->inTransaction()) {
                throw new LogicException('Integration maintenance must run after fixture rollback.');
            }
            // Do not erase a logical leak to obtain isolation. These fixtures have
            // no committed seed data; only the migrations ledger may retain rows.
            foreach ($connection->select("SELECT schemaname, tablename FROM pg_tables WHERE schemaname = current_schema() AND tablename <> 'migrations'") as $table) {
                $name = '"'.str_replace('"', '""', $table->schemaname).'"."'.str_replace('"', '""', $table->tablename).'"';
                if ($connection->selectOne('SELECT EXISTS (SELECT 1 FROM '.$name.') AS present')->present) {
                    throw new LogicException('Committed fixture data survived rollback in '.$name);
                }
            }
            // VACUUM cannot run inside RefreshDatabase's next transaction. It
            // removes dead storage, not live rows, and avoids migrate:fresh per case.
            $connection->unprepared('VACUUM (ANALYZE)');
        }

        $this->refreshTransactionalDatabase();
    }
}
