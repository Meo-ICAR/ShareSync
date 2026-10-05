<?php

namespace App\Console\Commands;

use App\Services\SharePoint\DocumentImporter;
use Illuminate\Console\Command;
use RuntimeException;

class ImportSharePointDocuments extends Command
{
    protected $signature = 'sharepoint:import-documents
                            {--root= : Cartella radice su SharePoint (default da config)}
                            {--commit : Scrive i record su documents (altrimenti dry-run)}';

    protected $description = 'Classifica i file dei collaboratori su SharePoint e li importa in documents';

    public function handle(DocumentImporter $importer): int
    {
        $root = $this->option('root') ?: config('sharepoint_import.root');
        $commit = (bool) $this->option('commit');

        $this->info(($commit ? 'IMPORT' : 'DRY-RUN')." da \"{$root}\"");

        try {
            $rows = $importer->run($root, $commit);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $path = storage_path('app/sharepoint-import-'.now()->format('Ymd-His').'.csv');
        $handle = fopen($path, 'w');
        fputcsv($handle, ['path', 'collaborator', 'fornitore_id', 'fornitore', 'match', 'document_type_id', 'confidence', 'source', 'action', 'error']);
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }
        fclose($handle);

        $this->table(['action', 'righe'], collect($rows)->countBy('action')->map(fn ($n, $a) => [$a, $n])->values()->all());
        $this->line("Report: {$path}");

        return self::SUCCESS;
    }
}
