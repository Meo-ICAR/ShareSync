<?php

namespace App\Console\Commands;

use App\Services\SharePoint\EmissionDateBackfiller;
use Illuminate\Console\Command;

class BackfillSharePointEmissionDates extends Command
{
    protected $signature = 'sharepoint:backfill-emission-dates
                            {--commit : Scrive emitted_at sui documenti (altrimenti dry-run)}
                            {--sql= : Percorso del file SQL generato (default storage/app)}';

    protected $description = 'Imposta emitted_at (data di modifica del file su SharePoint) ai documenti importati senza data, ricalcola la scadenza e sostituisce le versioni precedenti';

    public function handle(EmissionDateBackfiller $backfiller): int
    {
        $commit = (bool) $this->option('commit');
        $this->info($commit ? 'BACKFILL' : 'DRY-RUN');

        $result = $backfiller->run($commit);

        $path = $this->option('sql') ?: storage_path('app/backfill-emitted-at-'.now()->format('Ymd-His').'.sql');
        $lines = array_map(
            fn (array $row): string => "UPDATE documents SET emitted_at = '{$row['emitted_at']}' WHERE id = '{$row['id']}' AND emitted_at IS NULL;",
            $result['rows'],
        );
        file_put_contents($path, implode("\n", $lines).($lines === [] ? '' : "\n"));

        $this->table(['esito', 'documenti'], [
            [$commit ? 'aggiornati' : 'da aggiornare', $result['updated']],
            ['file non più presente su SharePoint', $result['missing']],
            ['versioni precedenti sostituite (soft delete)', $result['superseded']],
            ['errori', $result['errors']],
        ]);
        $this->line("SQL: {$path}");

        return self::SUCCESS;
    }
}
