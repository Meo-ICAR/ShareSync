<?php

namespace App\Console\Commands;

use App\Jobs\RunSharePointRealignment;
use App\Services\SharePoint\SharePointRealigner;
use Illuminate\Console\Command;

class RealignSharePointDocuments extends Command
{
    protected $signature = 'sharepoint:realign
                            {--commit : Scrive su documents (altrimenti dry-run)}
                            {--classify : Assegna il tipo con AI ai file collegati dalle raccolte}
                            {--queue : Mette in coda il job invece di eseguirlo subito}';

    protected $description = 'Riscansiona tutto SharePoint: import fornitori e dipendenti, collegamento raccolte, date di emissione, scadenze e versioni';

    public function handle(SharePointRealigner $realigner): int
    {
        $commit = (bool) $this->option('commit');
        $classify = (bool) $this->option('classify');

        if ($this->option('queue')) {
            RunSharePointRealignment::dispatch($commit, $classify);
            $this->info('Riscansione messa in coda ('.($commit ? 'commit' : 'dry-run').').');

            return self::SUCCESS;
        }

        $this->info(($commit ? 'RISCANSIONE' : 'DRY-RUN').' completa');

        $results = $realigner->run($commit, $classify);

        $this->table(['passaggio', 'esito'], array_map(
            fn (array $r): array => [$r['step'], $r['exit_code'] === 0 ? 'ok' : 'ERRORE'],
            $results,
        ));

        foreach ($results as $result) {
            if ($result['exit_code'] !== 0) {
                $this->error("{$result['step']}: {$result['output']}");
            }
        }

        return collect($results)->contains(fn (array $r): bool => $r['exit_code'] !== 0) ? self::FAILURE : self::SUCCESS;
    }
}
