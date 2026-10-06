<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentType;
use App\Services\SharePoint\DocumentClassifier;
use App\Services\SharePoint\RaccolteLinker;
use App\Services\SharePoint\RaccolteScanner;
use App\Services\SharePoint\SharePointClient;
use App\Services\SharePoint\SharePointFile;
use Illuminate\Console\Command;
use RuntimeException;

class LinkSharePointRaccolte extends Command
{
    private const MAX_URL_LENGTH = 255;

    protected $signature = 'sharepoint:link-raccolte
                            {--csv= : Percorso del CSV (default storage/app/private/ShareSync_raccolte.csv)}
                            {--classify : Assegna il tipo con AI ai file collegati}
                            {--commit : Scrive i record su documents (altrimenti dry-run)}';

    protected $description = 'Collega i file senza tipo delle raccolte a istituti (clientis), clients, company o employees';

    public function handle(RaccolteScanner $scanner, RaccolteLinker $linker, DocumentClassifier $classifier): int
    {
        $csv = $this->option('csv') ?: storage_path('app/private/ShareSync_raccolte.csv');
        $commit = (bool) $this->option('commit');
        $this->info($commit ? 'COLLEGAMENTO' : 'DRY-RUN');

        try {
            $raccolte = $scanner->raccolte($csv);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $types = DocumentType::all();
        $varieIstitutiId = DocumentType::where('code', 'VARIE_ISTITUTI')->value('id');
        $daClassificareId = DocumentType::where('code', 'DA_CLASSIFICARE')->value('id');
        $varieId = DocumentType::where('code', 'VARIE')->value('id');
        // Documenti già collegati e ancora "Da classificare": ricevono il tipo se ora è deducibile.
        $pending = Document::where('source_app', 'sharepoint')->where('document_type_id', $daClassificareId)->pluck('app_id', 'app_id')->all();
        $rows = [];
        $summary = [];

        foreach ($raccolte as $raccolta) {
            if (config("sharepoint_import.link_targets.{$raccolta['raccolta']}") === null) {
                continue;
            }

            $this->line("Scansione \"{$raccolta['raccolta']}\"...");
            $client = new SharePointClient($raccolta['drive_id']);
            $files = collect(iterator_to_array($client->files('root'), false));
            $untyped = array_column($scanner->unmatched($files, $types), 'path');
            $counts = [];

            foreach ($files->filter(fn ($f) => in_array($f->path, $untyped, true) || isset($pending[$f->id])) as $file) {
                $target = $linker->target($raccolta['raccolta'], $file->path);
                $typeId = null;
                $confidence = null;
                if ($target !== null && $linker->isVarieIstituti($file->name)) {
                    $typeId = $varieIstitutiId;
                } elseif ($target !== null && ($typeId = $linker->typeFor($file->path)) !== null) {
                    // tipo dedotto dal percorso
                } elseif ($target !== null && $this->option('classify')) {
                    $c = $classifier->withTypes($types)->classify($file->path, false)[0];
                    [$typeId, $confidence] = [$c->documentTypeId, $c->confidence];
                }
                // Istituti: ciò che resta senza tipo è "Varie"; altrove "Da classificare".
                $typeId ??= $target === null ? null : ($target['type'] === 'clienti' ? $varieId : $daClassificareId);
                $action = $target === null ? 'no_match' : ($commit ? $this->store($file, $raccolta['drive_id'], $raccolta['raccolta'], $target, $typeId, $confidence, $daClassificareId) : 'dry_run');
                $counts[$action] = ($counts[$action] ?? 0) + 1;
                $rows[] = [$raccolta['raccolta'], $file->path, $target['type'] ?? null, $target['label'] ?? null, $typeId ? $types->firstWhere('id', $typeId)?->name : null, $confidence, $action];
            }

            $summary[] = [$raccolta['raccolta'], count($untyped), $counts['no_match'] ?? 0, count($untyped) - ($counts['no_match'] ?? 0)];
        }

        $path = storage_path('app/sharepoint-raccolte-link-'.now()->format('Ymd-His').'.csv');
        $handle = fopen($path, 'w');
        fputcsv($handle, ['raccolta', 'path', 'documentable', 'anagrafica', 'document_type', 'confidence', 'action'], escape: '\\');
        foreach ($rows as $row) {
            fputcsv($handle, $row, escape: '\\');
        }
        fclose($handle);

        $this->table(['raccolta', 'senza tipo', 'senza anagrafica', 'collegabili'], $summary);
        $this->line("Report: {$path}");

        return self::SUCCESS;
    }

    /** @param array{type: string, id: string, label: string, company_id: ?string} $target */
    private function store(SharePointFile $file, string $driveId, string $raccolta, array $target, ?int $typeId, ?int $confidence, ?int $daClassificareId): string
    {
        $existing = Document::withTrashed()
            ->where('source_app', 'sharepoint')
            ->where('app_id', $file->id)
            ->first();

        if ($existing !== null) {
            // Il file è stato riassegnato a un'altra anagrafica (es. da company a employee).
            if ($existing->documentable_type !== $target['type'] || (string) $existing->documentable_id !== $target['id']) {
                $existing->update(['documentable_type' => $target['type'], 'documentable_id' => $target['id'], 'company_id' => $target['company_id']]);
                $existing->refresh();
                $moved = true;
            }

            // Un documento rimasto "Da classificare" riceve il tipo se ora è deducibile.
            if ($typeId !== null && $typeId !== $daClassificareId && $existing->document_type_id === $daClassificareId) {
                $existing->update(['document_type_id' => $typeId, 'ai_confidence_score' => $confidence]);

                return 'updated';
            }

            return ($moved ?? false) ? 'updated' : 'exists';
        }

        Document::create([
            'company_id' => $target['company_id'],
            'documentable_type' => $target['type'],
            'documentable_id' => $target['id'],
            'document_type_id' => $typeId,
            'ai_confidence_score' => $confidence,
            'name' => $file->name,
            'document_url' => $file->webUrl !== null && strlen($file->webUrl) <= self::MAX_URL_LENGTH ? $file->webUrl : null,
            'status' => 'uploaded',
            'sync_status' => 'synced',
            'source_app' => 'sharepoint',
            'app_id' => $file->id,
            'app_drive_id' => $driveId,
            'app_etag' => $file->etag,
            'emitted_at' => $file->modifiedAt,
            'metadata' => [
                'path' => $file->path,
                'web_url' => $file->webUrl,
                'raccolta' => $raccolta,
                'needs_review' => true,
            ],
        ]);

        return 'created';
    }
}
