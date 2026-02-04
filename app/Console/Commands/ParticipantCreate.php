<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ParticipantCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commons:participant-create
        {callsign : Callsign for the participant}
        {--designation= : Optional short designation}
        {--dossier= : Optional longer dossier}
        {--id= : Optional ULID (otherwise generated)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a Commons participant';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $callsign = trim((string) $this->argument('callsign'));
        $designation = $this->option('designation');
        $dossier = $this->option('dossier');
        $idOpt = $this->option('id');

        if (empty($callsign)) {
            $this->error('Callsign cannot be empty.');
            return self::FAILURE;
        }

        $id = !empty($idOpt) ? $idOpt : (string) Str::ulid();
        $now = now();

        // Basic ULID sanity chec
        if (!preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id)) {
            $this->error('Invalid ULID provided for --id (must be 26 chars Crockford Base32).');
            return self::FAILURE;
        }

        try {
            DB::table('participants')->insert([
                'id'            => $id,
                'callsign'      => $callsign,
                'designation'   => !empty($designation) ? $designation : null,
                'dossier'       => !empty($dossier) ? $dossier : null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        } catch (\Throwable $e) {
            $this->error('Failed to create participant: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Participant created: {$id}");
        $this->line("callsign: {$callsign}");
        if ($designation) $this->line("designation: {$designation}");
        if ($dossier) $this->line("dossier: (".mb_strlen($dossier)." chars)");

        return self::SUCCESS;
    }
}
