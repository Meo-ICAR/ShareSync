<?php

namespace App\Console\Commands;

use App\Services\SharePoint\SharePointClient;
use Illuminate\Console\Command;
use RuntimeException;

class ListSharePointTree extends Command
{
    protected $signature = 'sharepoint:tree
                            {--depth=3 : Profondità massima della scansione}
                            {--with-files : Mostra anche i file contenuti nelle cartelle}';

    protected $description = 'Mostra l\'albero delle cartelle e dei file di SharePoint usando il Drive ID specifico';

    private SharePointClient $client;

    public function handle()
    {
        $driveId = config('services.sharepoint.drive_id');

        $maxDepth = (int) $this->option('depth');
        $withFiles = $this->option('with-files');

        if (! $driveId) {
            $this->error('ERRORE: SHARE_DRIVE_ID non configurato nel file .env');

            return Command::FAILURE;
        }

        $this->client = new SharePointClient($driveId);

        // 1. Ottenimento Token
        try {
            $this->client->token();
        } catch (RuntimeException $e) {
            $this->error('Errore durante l\'ottenimento del token.');

            return Command::FAILURE;
        }

        $this->info("📁 Albero SharePoint - Target Drive ID: [{$driveId}]");
        $this->line('/');

        // 2. Scansione a partire dalla root del Drive specifico
        $this->scanFolder('root', '', 1, $maxDepth, $withFiles);

        $this->newLine();
        $this->info('Scansione completata.');

        return Command::SUCCESS;
    }

    private function scanFolder(string $itemId, string $prefix, int $currentDepth, int $maxDepth, bool $withFiles): void
    {
        if ($currentDepth > $maxDepth) {
            return;
        }

        try {
            $items = $this->client->children($itemId);
        } catch (RuntimeException $e) {
            $this->warn("{$prefix}└── [{$e->getMessage()}]");

            return;
        }

        if (! $withFiles) {
            $items = array_values(array_filter($items, fn ($item) => isset($item['folder'])));
        }

        $total = count($items);
        $currentIndex = 0;

        foreach ($items as $item) {
            $currentIndex++;
            $isLast = ($currentIndex === $total);

            $connector = $isLast ? '└── ' : '├── ';
            $childPrefix = $prefix.($isLast ? '    ' : '│   ');

            if (isset($item['folder'])) {
                $folderName = $item['name'];
                $childCount = $item['folder']['childCount'] ?? 0;
                $this->line("{$prefix}{$connector}📁 <comment>{$folderName}</comment> <fg=gray>({$childCount} elementi)</>");

                $this->scanFolder($item['id'], $childPrefix, $currentDepth + 1, $maxDepth, $withFiles);
            } elseif ($withFiles && isset($item['file'])) {
                $fileName = $item['name'];
                $sizeKb = round(($item['size'] ?? 0) / 1024, 1);
                $this->line("{$prefix}{$connector}📄 <fg=cyan>{$fileName}</> <fg=gray>({$sizeKb} KB)</>");
            }
        }
    }
}
