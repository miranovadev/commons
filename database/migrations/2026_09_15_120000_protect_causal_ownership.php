<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** No historical relabelling or backfill: these identities already exist. */
    public function up(): void
    {
        foreach ($this->identities() as $table => $columns) {
            $trigger = $table.'_causal_owner_immutable';
            $message = 'Creating causal ownership cannot be changed';
            $updatedColumns = implode(', ', $columns);
            if (DB::getDriverName() === 'pgsql') {
                $changed = implode(' OR ', array_map(fn ($column) => "NEW.{$column} IS DISTINCT FROM OLD.{$column}", $columns));
                DB::unprepared(<<<SQL
                    CREATE FUNCTION commons_{$trigger}() RETURNS trigger LANGUAGE plpgsql AS \$\$
                    BEGIN
                        IF {$changed} THEN RAISE EXCEPTION '{$message}'; END IF;
                        RETURN NEW;
                    END;
                    \$\$;
                    CREATE TRIGGER {$trigger} BEFORE UPDATE OF {$updatedColumns} ON {$table}
                    FOR EACH ROW EXECUTE FUNCTION commons_{$trigger}();
                    SQL);
            } elseif (DB::getDriverName() === 'sqlite') {
                $changed = implode(' OR ', array_map(fn ($column) => "NEW.{$column} IS NOT OLD.{$column}", $columns));
                DB::unprepared(<<<SQL
                    CREATE TRIGGER {$trigger} BEFORE UPDATE OF {$updatedColumns} ON {$table}
                    WHEN {$changed}
                    BEGIN SELECT RAISE(ABORT, '{$message}'); END;
                    SQL);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->identities() as $table => $columns) {
            $trigger = $table.'_causal_owner_immutable';
            if (DB::getDriverName() === 'pgsql') {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trigger} ON {$table}; DROP FUNCTION IF EXISTS commons_{$trigger}();");
            } elseif (DB::getDriverName() === 'sqlite') {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
            }
        }
    }

    private function identities(): array
    {
        return [
            'phases' => ['cycle_id'],
            'propositions' => ['phase_id'],
            'context_horizons' => ['parent_context_horizon_id', 'domain', 'opened_by_proposition_id', 'substrate_id'],
        ];
    }
};
