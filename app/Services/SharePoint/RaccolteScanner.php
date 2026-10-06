<?php

namespace App\Services\SharePoint;

use App\Models\DocumentType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class RaccolteScanner
{
    /**
     * Raccolte (righe del CSV) con Elementi > 0.
     *
     * @return list<array{raccolta: string, elementi: int, drive_id: string, web_url: string}>
     */
    public function raccolte(string $csvPath): array
    {
        if (! is_file($csvPath)) {
            throw new RuntimeException("CSV raccolte non trovato: {$csvPath}");
        }

        $handle = fopen($csvPath, 'r');
        fgetcsv($handle, separator: ';', escape: '\\'); // intestazione
        $out = [];

        while (($cols = fgetcsv($handle, separator: ';', escape: '\\')) !== false) {
            $elementi = (int) ($cols[1] ?? 0);
            if ($elementi > 0 && ! empty($cols[2])) {
                $out[] = [
                    'raccolta' => (string) $cols[0],
                    'elementi' => $elementi,
                    'drive_id' => (string) $cols[2],
                    'web_url' => (string) ($cols[3] ?? ''),
                ];
            }
        }
        fclose($handle);

        return $out;
    }

    /**
     * File della raccolta che non corrispondono a nessun DocumentType esistente.
     *
     * @param  iterable<SharePointFile>  $files
     * @param  Collection<int, DocumentType>  $types
     * @return list<array{path: string, name: string, folder: string}>
     */
    public function unmatched(iterable $files, Collection $types): array
    {
        $out = [];

        foreach ($files as $file) {
            $folder = str_contains($file->path, '/') ? dirname($file->path) : '';

            if ($folder !== '' && preg_match((string) config('sharepoint_import.scan_exclude_folders'), $folder) === 1) {
                continue;
            }

            $stem = pathinfo($file->name, PATHINFO_FILENAME);

            if ($types->contains(fn ($t) => $this->matches($t, $stem))) {
                continue;
            }

            $out[] = [
                'path' => $file->path,
                'name' => $file->name,
                'folder' => $folder,
            ];
        }

        return $out;
    }

    private function matches(DocumentType $type, string $stem): bool
    {
        if ($type->regex !== null && $type->regex !== '' && @preg_match($type->regex, $stem) === 1) {
            return true;
        }

        $haystack = Str::slug($stem);

        foreach ([$type->name, $type->slug] as $label) {
            $needle = Str::slug((string) $label);
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
