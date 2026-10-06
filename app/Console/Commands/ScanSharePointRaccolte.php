<?php

namespace App\Console\Commands;

use App\Models\DocumentType;
use App\Services\SharePoint\DocumentClassifier;
use App\Services\SharePoint\RaccolteScanner;
use App\Services\SharePoint\SharePointClient;
use Illuminate\Console\Command;
use RuntimeException;

class ScanSharePointRaccolte extends Command
{
    protected $signature = 'sharepoint:scan-raccolte
                            {--csv= : Percorso del CSV (default storage/app/private/ShareSync_raccolte.csv)}
                            {--classify : Classifica con AI i file senza tipo (nessuna scrittura su documents)}';

    protected $description = 'Scansiona le raccolte del CSV con Elementi > 0 e riporta i file senza DocumentType corrispondente (sola lettura)';

    public function handle(RaccolteScanner $scanner, DocumentClassifier $classifier): int
    {
        $csv = $this->option('csv') ?: storage_path('app/private/ShareSync_raccolte.csv');

        try {
            $raccolte = $scanner->raccolte($csv);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $types = DocumentType::all();
        $classify = (bool) $this->option('classify');
        $names = $types->pluck('name', 'id');
        $report = [];
        $summary = [];

        foreach ($raccolte as $raccolta) {
            $this->line("Scansione \"{$raccolta['raccolta']}\"...");

            try {
                $client = new SharePointClient($raccolta['drive_id']);
                $files = iterator_to_array($client->files('root'), false);
            } catch (RuntimeException $e) {
                $this->warn("  {$e->getMessage()}");
                $summary[] = [$raccolta['raccolta'], '-', '-', 'errore'];

                continue;
            }

            $unmatched = $scanner->unmatched($files, $types);
            foreach ($unmatched as $row) {
                $c = $classify ? $classifier->withTypes($types)->classify($row['path'], false)[0] : null;
                $report[] = [
                    $raccolta['raccolta'], $row['folder'], $row['name'], $row['path'],
                    $c?->documentTypeId, $c?->documentTypeId ? $names[$c->documentTypeId] : null, $c?->confidence, $c?->source,
                ];
            }

            $summary[] = [$raccolta['raccolta'], count($files), count($unmatched), 'ok'];
        }

        $path = storage_path('app/sharepoint-raccolte-scan-'.now()->format('Ymd-His').'.csv');
        $handle = fopen($path, 'w');
        fputcsv($handle, ['raccolta', 'cartella', 'file', 'path', 'document_type_id', 'document_type', 'confidence', 'source'], escape: '\\');
        foreach ($report as $row) {
            fputcsv($handle, $row, escape: '\\');
        }
        fclose($handle);

        $this->table(['raccolta', 'file', 'senza tipo', 'esito'], $summary);
        $this->line("Report: {$path}");

        return self::SUCCESS;
    }
}
