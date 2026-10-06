<?php

namespace App\Console\Commands;

use App\Services\SharePoint\DocumentVersioner;
use Illuminate\Console\Command;

class SupersedeSharePointVersions extends Command
{
    protected $signature = 'sharepoint:supersede-versions
                            {--commit : Elimina (soft delete) le versioni precedenti (altrimenti dry-run)}';

    protected $description = 'Versioning dei documenti SharePoint di dipendenti e fornitori: le versioni più vecchie dello stesso tipo vengono sostituite';

    public function handle(DocumentVersioner $versioner): int
    {
        $commit = (bool) $this->option('commit');

        $count = $versioner->supersedeAll(['employee', 'fornitore'], $commit);

        $this->info(($commit ? 'Versioni sostituite' : 'DRY-RUN: versioni da sostituire').": {$count}");

        return self::SUCCESS;
    }
}
