<?php

namespace App\Services\SharePoint;

use Carbon\Carbon;
use Generator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SharePointClient
{
    private ?string $token = null;

    private string $driveId;

    public function __construct(?string $driveId = null)
    {
        $this->driveId = (string) ($driveId ?? config('services.sharepoint.drive_id'));
    }

    public function driveId(): string
    {
        return $this->driveId;
    }

    public function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $tenantId = config('services.sharepoint.tenant_id');
        $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
            'client_id' => config('services.sharepoint.client_id'),
            'client_secret' => config('services.sharepoint.client_secret'),
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Errore token SharePoint (HTTP {$response->status()})");
        }

        return $this->token = (string) $response->json('access_token');
    }

    /** Data di ultima modifica di un file del drive; null se l'elemento non esiste più. */
    public function lastModified(string $itemId): ?Carbon
    {
        $response = Http::withToken($this->token())
            ->get("https://graph.microsoft.com/v1.0/drives/{$this->driveId}/items/{$itemId}".'?$select=id,lastModifiedDateTime');

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new RuntimeException("Errore lettura elemento {$itemId} (HTTP {$response->status()})");
        }

        $modified = $response->json('lastModifiedDateTime');

        return $modified !== null ? Carbon::parse($modified) : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function children(string $itemId): array
    {
        $url = "https://graph.microsoft.com/v1.0/drives/{$this->driveId}/items/{$itemId}/children"
            .'?$select=id,name,folder,file,size,eTag,webUrl,lastModifiedDateTime&$top=200';
        $items = [];

        while ($url) {
            $response = Http::withToken($this->token())->get($url);

            if ($response->failed()) {
                throw new RuntimeException("Errore lettura cartella {$itemId} (HTTP {$response->status()})");
            }

            $body = $response->json() ?? [];
            $items = array_merge($items, $body['value'] ?? []);
            // Chiave letterale: json('@odata.nextLink') interpreterebbe il punto come annidamento.
            $url = $body['@odata.nextLink'] ?? null;
        }

        return $items;
    }

    /** @return array<string, mixed>|null */
    public function findChildFolder(string $parentId, string $name): ?array
    {
        foreach ($this->children($parentId) as $item) {
            if (isset($item['folder']) && $item['name'] === $name) {
                return $item;
            }
        }

        return null;
    }

    /** @return Generator<int, SharePointFile> */
    public function files(string $itemId, string $prefix = ''): Generator
    {
        foreach ($this->children($itemId) as $item) {
            $path = $prefix === '' ? $item['name'] : "{$prefix}/{$item['name']}";

            if (isset($item['folder'])) {
                yield from $this->files($item['id'], $path);
            } elseif (isset($item['file'])) {
                yield new SharePointFile(
                    id: $item['id'],
                    name: $item['name'],
                    path: $path,
                    etag: $item['eTag'] ?? null,
                    webUrl: $item['webUrl'] ?? null,
                    size: (int) ($item['size'] ?? 0),
                    modifiedAt: isset($item['lastModifiedDateTime']) ? Carbon::parse($item['lastModifiedDateTime']) : null,
                );
            }
        }
    }
}
