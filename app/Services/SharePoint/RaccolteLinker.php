<?php

namespace App\Services\SharePoint;

use App\Models\DocumentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RaccolteLinker
{
    private const STOPWORDS = ['spa', 'srl', 'srls', 's', 'p', 'a', 'banca', 'bank', 'group'];

    /** @var array<string, list<array{id: string, name: string, key: string}>> */
    private array $entities = [];

    private ?string $companyId = null;

    /** @var array<string, int|null> */
    private array $typeIds = [];

    /**
     * Anagrafica a cui abbinare il file, o null se non riconosciuta.
     *
     * @return array{type: string, id: string, label: string, company_id: ?string}|null
     */
    public function target(string $raccolta, string $path): ?array
    {
        $mode = config("sharepoint_import.link_targets.{$raccolta}");
        $segments = explode('/', $path);

        return match ($mode) {
            'clienti' => $this->byName('clienti', $this->entityFolder($segments)),
            'client' => $this->client((string) preg_replace('/^\d+\s*-\s*/', '', $segments[0])),
            'societari' => $this->societari($segments),
            default => null,
        };
    }

    /** Tipo dedotto da cartella e nome file con config('sharepoint_import.link_type_rules'). */
    public function typeFor(string $path): ?int
    {
        foreach (config('sharepoint_import.link_type_rules') as [$pattern, $target]) {
            if (preg_match($pattern, $path) === 1) {
                return $this->typeId($target);
            }
        }

        return null;
    }

    private function typeId(string $codeOrName): ?int
    {
        $this->typeIds ??= [];

        return $this->typeIds[$codeOrName] ??= DocumentType::where('code', $codeOrName)->value('id')
            ?? DocumentType::where('name', $codeOrName)->orderBy('id')->value('id');
    }

    public function isVarieIstituti(string $fileName): bool
    {
        return in_array(strtolower($fileName), config('sharepoint_import.link_varie_istituti'), true);
    }

    public function normalize(string $value): string
    {
        $tokens = preg_split('/[^a-z0-9]+/', strtolower(Str::ascii($value)), -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_values(array_diff($tokens, self::STOPWORDS));
        sort($tokens);

        return implode(' ', $tokens);
    }

    /** @param list<string> $segments */
    private function entityFolder(array $segments): string
    {
        // "Accordi Attivi/<istituto>/...": la prima cartella è solo un contenitore.
        $folder = preg_match('/^accordi\s+(attivi|cessati)$/i', $segments[0]) === 1 ? ($segments[1] ?? '') : $segments[0];

        return (string) $folder;
    }

    private function client(string $folder): ?array
    {
        // Le cartelle delle società del gruppo che coincidono con la company non sono anagrafiche di `clients`.
        if (in_array($this->normalize($folder), array_map($this->normalize(...), config('sharepoint_import.link_company_folders')), true)) {
            return $this->company();
        }

        return $this->byName('client', $folder);
    }

    private function company(): array
    {
        $companyId = $this->companyId();

        return ['type' => 'company', 'id' => (string) $companyId, 'label' => 'company', 'company_id' => $companyId];
    }

    /** @param list<string> $segments */
    private function societari(array $segments): ?array
    {
        $at = array_search('dipendenti', array_map('strtolower', $segments), true);

        if ($at !== false) {
            return $this->byName('employee', (string) ($segments[$at + 1] ?? ''));
        }

        // Una cartella che porta il nome esatto di un employee (es. "Faraone Vincenzo") appartiene a lui.
        $employees = array_column($this->entities('employee'), null, 'key');
        foreach (array_slice($segments, 0, -1) as $segment) {
            $employee = $employees[$this->normalize((string) preg_replace('/\s*-\s*documenti personali$/i', '', $segment))] ?? null;
            if ($employee !== null) {
                return ['type' => 'employee', 'id' => $employee['id'], 'label' => $employee['name'], 'company_id' => $this->companyId()];
            }
        }

        return $this->company();
    }

    private function byName(string $type, string $name): ?array
    {
        // Lettura diretta dell'array: i nomi contengono punti, che config() leggerebbe come annidamento.
        $name = config('sharepoint_import.link_aliases')[$name] ?? $name;
        $key = $this->normalize($name);

        if ($key === '') {
            return null;
        }

        $entities = $this->entities($type);
        $hits = array_values(array_filter($entities, fn ($e) => $e['key'] === $key));

        if ($hits === []) {
            // Token del nome cercato contenuti in quelli dell'anagrafica, o viceversa (es. "Compass" / "Banca Compass S.p.A.").
            $wanted = explode(' ', $key);
            $hits = array_values(array_filter($entities, function ($e) use ($wanted) {
                $have = explode(' ', $e['key']);

                return array_diff($wanted, $have) === [] || array_diff($have, $wanted) === [];
            }));
        }

        if ($hits === []) {
            return null;
        }

        // Anagrafiche con lo stesso nome normalizzato (es. "VIVIBANCA" / "VIVIBANCA SPA"): vince il nome identico.
        usort($hits, fn ($a, $b) => (strcasecmp($b['name'], $name) === 0) <=> (strcasecmp($a['name'], $name) === 0));

        return ['type' => $type, 'id' => (string) $hits[0]['id'], 'label' => $hits[0]['name'], 'company_id' => $this->companyId()];
    }

    /** @return list<array{id: string, name: string, key: string}> */
    private function entities(string $type): array
    {
        $table = ['clienti' => 'clientis', 'client' => 'clients', 'employee' => 'employees'][$type];

        return $this->entities[$type] ??= DB::table($table)
            // Anche le anagrafiche soft-deleted (es. CASHME) restano collegabili.
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($e) => ['id' => (string) $e->id, 'name' => (string) $e->name, 'key' => $this->normalize((string) $e->name)])
            ->filter(fn ($e) => $e['key'] !== '')
            ->values()
            ->all();
    }

    /** Unica company dell'installazione (gli impiegati ne portano l'id). */
    private function companyId(): ?string
    {
        return $this->companyId ??= DB::table('employees')->whereNotNull('company_id')->value('company_id');
    }
}
