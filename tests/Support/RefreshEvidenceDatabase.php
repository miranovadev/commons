<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

/** Real transaction boundaries for reading and inference; each case starts on an isolated fresh schema. */
trait RefreshEvidenceDatabase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        // Evidence history deliberately prevents rollback to the obsolete schema.
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }
}
