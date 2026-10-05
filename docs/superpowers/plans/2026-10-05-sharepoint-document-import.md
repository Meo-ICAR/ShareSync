# Import documenti SharePoint → `documents` Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Comando `sharepoint:import-documents` che legge `1 - COLLABORATORI ATTIVI` da SharePoint, abbina la cartella al `Fornitori`, classifica ogni file con `DocumentType` (regex + Claude) e crea un record `documents` per ogni (file, tipo).

**Architecture:** `SharePointClient` (Graph) → `FornitoreMatcher` → `DocumentClassifier` (regola, poi `AiClassifier`) → `DocumentImporter` (dry-run di default, `--commit` scrive). Servizi in `app/Services/SharePoint/`, nessun `Collection` nei costruttori (il container li auto-risolverebbe vuoti): i dati si iniettano con `withFornitori()` / `withTypes()` nei test.

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit (stile `Tests\TestCase`), `Http` facade (Graph + `api.anthropic.com`), sqlite in-memory nei test, MySQL `sharesync` (DB di sviluppo) per il run reale.

**Spec:** `docs/superpowers/specs/2026-10-05-sharepoint-document-import-design.md`

## Global Constraints

- `documents.documentable_type` = `'fornitore'` (confermato dall'utente).
- `documents.source_app` = `'sharepoint'`, `sync_status` = `'synced'`, `status` = `'uploaded'`.
- Idempotenza sulla chiave (`source_app='sharepoint'`, `app_id`, `document_type_id`), includendo i soft-deleted.
- Dry-run di default; scrittura solo con `--commit`.
- Le righe `documents` esistenti (808) non si modificano mai.
- Modello Claude da `config('services.anthropic.model')` (`ANTHROPIC_MODEL`); mai loggare/stampare la API key.
- Al modello non si invia il nome della cartella del collaboratore (primo segmento del percorso).
- Nel progetto non c'è git: i passi "commit" sono omessi; si verifica con i test.
- Test: `php artisan test --filter=<Classe>`; DB di test = sqlite `:memory:` (da `phpunit.xml`).

## Review Focus

1. Cartella con accenti ("Giofrè Alfonso") vs fornitore "GIOFRE ALFONSO" ⇒ match `exact` (Task 3).
2. Due fornitori con lo stesso nome (uno soft-deleted, uno attivo) ⇒ si sceglie l'attivo (Task 3).
3. Claude non raggiungibile / JSON non valido / id fuori dai candidati ⇒ nessuna eccezione, documento importato senza tipo (Task 4).
4. File direttamente nella radice o nella cartella collaboratore (senza sottocartella numerata) ⇒ radice: saltato; collaboratore: classificato senza hint di cartella (Task 5).
5. Rilancio con `--commit` e file con più tipi ⇒ nessun duplicato, un record per tipo (Task 5).

---

### Task 1: Configurazione, modello `Document`, migration guardata

**Files:**
- Modify: `config/services.php` (aggiungere chiave `anthropic`)
- Create: `config/sharepoint_import.php`
- Create: `app/Models/Document.php`
- Create: `database/migrations/2026_10_05_090000_create_documents_table.php`
- Test: `tests/Feature/DocumentModelTest.php`

**Interfaces:**
- Produces: `App\Models\Document` (uuid, `$guarded = []`, cast `metadata` array, SoftDeletes); `config('sharepoint_import.*')` con chiavi `root`, `default_min_confidence`, `rule_confidence`, `fuzzy_threshold`, `folder_hints`; `config('services.anthropic.key|model')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_is_created_with_uuid_defaults_and_json_metadata(): void
    {
        $doc = Document::create([
            'documentable_type' => 'fornitore',
            'documentable_id' => 'abc',
            'name' => 'x.pdf',
            'metadata' => ['path' => 'a/b'],
        ]);

        $fresh = Document::find($doc->id);

        $this->assertSame(36, strlen($fresh->id));
        $this->assertSame('uploaded', $fresh->status);
        $this->assertSame('local', $fresh->source_app);
        $this->assertSame(['path' => 'a/b'], $fresh->metadata);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DocumentModelTest`
Expected: FAIL (`Class "App\Models\Document" not found`).

- [ ] **Step 3: Write minimal implementation**

`config/services.php` — dentro l'array restituito, dopo la chiave `'sharepoint'`:

```php
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    ],
```

`config/sharepoint_import.php`:

```php
<?php

return [
    'root' => '1 - COLLABORATORI ATTIVI',
    'default_min_confidence' => 70,
    'rule_confidence' => 90,
    'fuzzy_threshold' => 85,

    // Numero cartella (es. "3 - REQUISITI...") => regex sul NOME del DocumentType.
    // null = nessun filtro.
    'folder_hints' => [
        1 => '/contratt?o|mediazione|incarico|collaborazione/i',
        2 => '/identit|patente|tessera|codice fiscale|residenza|curriculum|titolo/i',
        3 => '/casellario|carichi|onorabilit/i',
        4 => '/oam|ivass|formazione|attestato|prova|antiriciclaggio|aml|trasparenza|privacy|231|polizza|titolo|studio|nomina|codice etico/i',
        5 => '/p\.?\s?iva|visura|partita|ateco|camerale/i',
        6 => null,
    ],
];
```

`app/Models/Document.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'documents';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'ai_confidence_score' => 'integer',
        ];
    }
}
```

`database/migrations/2026_10_05_090000_create_documents_table.php` (la tabella esiste già nel DB di sviluppo con molte più colonne: la migration crea solo il sottoinsieme usato, e solo se manca, cioè nei test sqlite):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documents')) {
            return;
        }

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable()->index();
            $table->string('documentable_type');
            $table->string('documentable_id');
            $table->unsignedBigInteger('document_type_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('document_url')->nullable();
            $table->string('status', 50)->default('uploaded');
            $table->string('sync_status', 50)->default('local');
            $table->string('source_app')->default('local');
            $table->string('app_id')->nullable();
            $table->string('app_drive_id')->nullable();
            $table->string('app_etag')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedTinyInteger('ai_confidence_score')->nullable();
            $table->string('spatie_collection', 100)->default('default');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        // La tabella è gestita fuori da questa migration: non va eliminata.
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=DocumentModelTest`
Expected: PASS. Se fallisce per le migration di `fornitoris`/`document_types` su sqlite, annotare quale colonna e correggere la migration con un'alternativa compatibile prima di proseguire (i Task 3–5 dipendono da quelle tabelle).

- [ ] **Step 5: Verificare che la migration non tocchi il DB di sviluppo**

Run: `php artisan migrate --pretend`
Expected: la nuova migration compare ma, essendo la tabella presente, `up()` non esegue nulla. Poi `php artisan migrate`.

---

### Task 2: `SharePointClient` e refactor di `ListSharePointTree`

**Files:**
- Create: `app/Services/SharePoint/SharePointFile.php`
- Create: `app/Services/SharePoint/SharePointClient.php`
- Modify: `app/Console/Commands/ListSharePointTree.php` (riscrittura completa sotto)
- Test: `tests/Feature/SharePointClientTest.php`

**Interfaces:**
- Produces:
  - `SharePointFile` (readonly): `string $id, string $name, string $path, ?string $etag, ?string $webUrl, int $size`; `path` è relativo alla cartella scansionata, es. `Rossi Mario/3 - X/a.pdf`.
  - `SharePointClient::__construct(?string $driveId = null)`; `driveId(): string`; `token(): string` (lancia `RuntimeException`); `children(string $itemId): array` (segue `@odata.nextLink`, lancia `RuntimeException`); `findChildFolder(string $parentId, string $name): ?array`; `files(string $itemId, string $prefix = ''): \Generator<SharePointFile>`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Services\SharePoint\SharePointClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SharePointClientTest extends TestCase
{
    private function fakeGraph(): void
    {
        config([
            'services.sharepoint.tenant_id' => 'tenant',
            'services.sharepoint.client_id' => 'cid',
            'services.sharepoint.client_secret' => 'sec',
            'services.sharepoint.drive_id' => 'D',
        ]);

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok']),
            'graph.microsoft.com/v1.0/drives/D/items/root/children*' => Http::sequence()
                ->push([
                    'value' => [['id' => 'f0', 'name' => 'Altro', 'folder' => ['childCount' => 0]]],
                    '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/drives/D/items/root/children?$skiptoken=abc',
                ])
                ->push([
                    'value' => [['id' => 'f1', 'name' => 'Rossi Mario', 'folder' => ['childCount' => 1]]],
                ]),
            'graph.microsoft.com/v1.0/drives/D/items/f0/children*' => Http::response(['value' => []]),
            'graph.microsoft.com/v1.0/drives/D/items/f1/children*' => Http::response(['value' => [
                ['id' => 's1', 'name' => '3 - ONORABILITA', 'folder' => ['childCount' => 1]],
            ]]),
            'graph.microsoft.com/v1.0/drives/D/items/s1/children*' => Http::response(['value' => [
                ['id' => 'x1', 'name' => 'Rossi Mario - Casellario.pdf', 'file' => [], 'size' => 2048,
                    'eTag' => '"e1"', 'webUrl' => 'https://sp/x1'],
            ]]),
        ]);
    }

    public function test_children_follows_pagination(): void
    {
        $this->fakeGraph();

        $names = array_column((new SharePointClient)->children('root'), 'name');

        $this->assertSame(['Altro', 'Rossi Mario'], $names);
    }

    public function test_files_yields_relative_paths_recursively(): void
    {
        $this->fakeGraph();

        $files = iterator_to_array((new SharePointClient)->files('root'), false);

        $this->assertCount(1, $files);
        $this->assertSame('x1', $files[0]->id);
        $this->assertSame('Rossi Mario/3 - ONORABILITA/Rossi Mario - Casellario.pdf', $files[0]->path);
        $this->assertSame('"e1"', $files[0]->etag);
        $this->assertSame('https://sp/x1', $files[0]->webUrl);
        $this->assertSame(2048, $files[0]->size);
    }

    public function test_find_child_folder_searches_all_pages(): void
    {
        $this->fakeGraph();

        $this->assertSame('f1', (new SharePointClient)->findChildFolder('root', 'Rossi Mario')['id']);
    }

    public function test_find_child_folder_returns_null_when_missing(): void
    {
        $this->fakeGraph();

        $this->assertNull((new SharePointClient)->findChildFolder('root', 'Inesistente'));
    }

    public function test_graph_failure_throws_runtime_exception(): void
    {
        config(['services.sharepoint.drive_id' => 'D']);
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok']),
            'graph.microsoft.com/*' => Http::response([], 500),
        ]);

        $this->expectException(\RuntimeException::class);
        (new SharePointClient)->children('root');
    }

    public function test_tree_command_still_prints_folders(): void
    {
        $this->fakeGraph();

        $this->artisan('sharepoint:tree', ['--depth' => 1])
            ->expectsOutputToContain('Altro')
            ->assertSuccessful();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SharePointClientTest`
Expected: FAIL (`Class "App\Services\SharePoint\SharePointClient" not found`).

- [ ] **Step 3: Write minimal implementation**

`app/Services/SharePoint/SharePointFile.php`:

```php
<?php

namespace App\Services\SharePoint;

final class SharePointFile
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $path,
        public readonly ?string $etag,
        public readonly ?string $webUrl,
        public readonly int $size,
    ) {}
}
```

`app/Services/SharePoint/SharePointClient.php`:

```php
<?php

namespace App\Services\SharePoint;

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

    /** @return array<int, array<string, mixed>> */
    public function children(string $itemId): array
    {
        $url = "https://graph.microsoft.com/v1.0/drives/{$this->driveId}/items/{$itemId}/children"
            .'?$select=id,name,folder,file,size,eTag,webUrl&$top=200';
        $items = [];

        while ($url) {
            $response = Http::withToken($this->token())->get($url);

            if ($response->failed()) {
                throw new RuntimeException("Errore lettura cartella {$itemId} (HTTP {$response->status()})");
            }

            $items = array_merge($items, $response->json('value') ?? []);
            $url = $response->json('@odata.nextLink');
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
                );
            }
        }
    }
}
```

`app/Console/Commands/ListSharePointTree.php` (sostituire l'intero file; output invariato):

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SharePointClientTest`
Expected: PASS (6 test).

- [ ] **Step 5: Verifica manuale del refactor sul tenant reale**

Run: `php artisan sharepoint:tree --depth=1`
Expected: stesso elenco di prima (`1 - COLLABORATORI ATTIVI`, `2 - ...`, `3 - ...`).

---

### Task 3: `FornitoreMatcher`

**Files:**
- Create: `app/Services/SharePoint/FornitoreMatch.php`
- Create: `app/Services/SharePoint/FornitoreMatcher.php`
- Test: `tests/Feature/FornitoreMatcherTest.php`

**Interfaces:**
- Produces:
  - `FornitoreMatch` (readonly): `?Fornitori $fornitore`, `string $kind` (`'exact'|'fuzzy'|'none'`).
  - `FornitoreMatcher::withFornitori(Collection $fornitori): static`; `normalize(string $s): string`; `match(string $folderName): FornitoreMatch`. Senza `withFornitori()` carica `Fornitori::withTrashed()->get()` al primo uso.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Fornitori;
use App\Services\SharePoint\FornitoreMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FornitoreMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function fornitore(string $nome, ?string $deletedAt = null): Fornitori
    {
        return Fornitori::unguarded(fn () => Fornitori::create([
            'nome' => $nome,
            'deleted_at' => $deletedAt,
        ]));
    }

    public function test_normalize_ignores_case_accents_punctuation_and_word_order(): void
    {
        $m = new FornitoreMatcher;

        $this->assertSame($m->normalize('Giofrè Alfonso'), $m->normalize('ALFONSO  GIOFRE'));
        $this->assertSame('ciro di maio', $m->normalize('Di Maio, Ciro'));
    }

    public function test_exact_match_with_accents_and_uppercase(): void
    {
        $f = $this->fornitore('GIOFRE ALFONSO');

        $match = (new FornitoreMatcher)->match('Giofrè Alfonso');

        $this->assertSame('exact', $match->kind);
        $this->assertSame($f->id, $match->fornitore->id);
    }

    public function test_homonyms_prefer_the_non_deleted_record(): void
    {
        $this->fornitore('LEO GIOVANNI', now()->toDateTimeString());
        $active = $this->fornitore('LEO GIOVANNI');

        $match = (new FornitoreMatcher)->match('Leo Giovanni');

        $this->assertSame($active->id, $match->fornitore->id);
    }

    public function test_matches_soft_deleted_when_it_is_the_only_one(): void
    {
        $f = $this->fornitore('ALFIERI FABIO', now()->toDateTimeString());

        $this->assertSame($f->id, (new FornitoreMatcher)->match('Alfieri Fabio')->fornitore->id);
    }

    public function test_fuzzy_match_for_small_typos(): void
    {
        $f = $this->fornitore('MNACINI SERENA');

        $match = (new FornitoreMatcher)->match('Mancini Serena');

        $this->assertSame('fuzzy', $match->kind);
        $this->assertSame($f->id, $match->fornitore->id);
    }

    public function test_none_when_no_similar_name(): void
    {
        $this->fornitore('ROSSI MARIO');

        $match = (new FornitoreMatcher)->match('Verdi Luca');

        $this->assertSame('none', $match->kind);
        $this->assertNull($match->fornitore);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FornitoreMatcherTest`
Expected: FAIL (`Class "App\Services\SharePoint\FornitoreMatcher" not found`).

- [ ] **Step 3: Write minimal implementation**

`app/Services/SharePoint/FornitoreMatch.php`:

```php
<?php

namespace App\Services\SharePoint;

use App\Models\Fornitori;

final class FornitoreMatch
{
    public function __construct(
        public readonly ?Fornitori $fornitore,
        public readonly string $kind,
    ) {}
}
```

`app/Services/SharePoint/FornitoreMatcher.php`:

```php
<?php

namespace App\Services\SharePoint;

use App\Models\Fornitori;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FornitoreMatcher
{
    /** @var Collection<int, Fornitori>|null */
    private ?Collection $fornitori = null;

    /** @var array<string, Collection<int, Fornitori>>|null */
    private ?array $index = null;

    /** @param Collection<int, Fornitori> $fornitori */
    public function withFornitori(Collection $fornitori): static
    {
        $this->fornitori = $fornitori;
        $this->index = null;

        return $this;
    }

    public function normalize(string $value): string
    {
        $tokens = preg_split('/[^a-z0-9]+/', strtolower(Str::ascii($value)), -1, PREG_SPLIT_NO_EMPTY);
        sort($tokens);

        return implode(' ', $tokens);
    }

    public function match(string $folderName): FornitoreMatch
    {
        $key = $this->normalize($folderName);
        $index = $this->index();

        if (isset($index[$key])) {
            return new FornitoreMatch($this->preferred($index[$key]), 'exact');
        }

        $bestKey = null;
        $bestScore = 0.0;
        $tie = false;
        foreach (array_keys($index) as $candidate) {
            similar_text($key, $candidate, $percent);
            if ($percent > $bestScore) {
                [$bestKey, $bestScore, $tie] = [$candidate, $percent, false];
            } elseif ($percent === $bestScore) {
                $tie = true;
            }
        }

        if ($bestKey !== null && ! $tie && $bestScore >= config('sharepoint_import.fuzzy_threshold')) {
            return new FornitoreMatch($this->preferred($index[$bestKey]), 'fuzzy');
        }

        return new FornitoreMatch(null, 'none');
    }

    /** @return array<string, Collection<int, Fornitori>> */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $this->fornitori ??= Fornitori::withTrashed()->get();
        $index = [];

        foreach ($this->fornitori as $fornitore) {
            foreach (array_unique(array_filter([$fornitore->nome, $fornitore->name])) as $label) {
                $index[$this->normalize($label)][] = $fornitore;
            }
        }

        return $this->index = array_map(fn ($items) => collect($items), $index);
    }

    /** @param Collection<int, Fornitori> $candidates */
    private function preferred(Collection $candidates): Fornitori
    {
        return $candidates
            ->sortBy([
                fn ($a, $b) => ($a->deleted_at !== null) <=> ($b->deleted_at !== null),
                fn ($a, $b) => ($b->is_active ?? 0) <=> ($a->is_active ?? 0),
            ])
            ->first();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=FornitoreMatcherTest`
Expected: PASS (6 test). Se `Fornitori::create` fallisce su colonne NOT NULL in sqlite, aggiungere i valori mancanti nell'array di `fornitore()` e riprovare.

- [ ] **Step 5: Verifica sui dati reali (sola lettura)**

Run:
```bash
php artisan tinker --execute='$m = new App\Services\SharePoint\FornitoreMatcher; foreach (["Pedone Matteo","Giofrè Alfonso","Accogli Andrea","Vitale Rino Antonio"] as $n) { $r = $m->match($n); echo $n." => ".$r->kind." ".($r->fornitore->nome ?? "-")."\n"; }'
```
Expected: `Pedone Matteo => exact PEDONE MATTEO`; gli altri esito `exact`/`fuzzy`/`none` annotato (informa la qualità dei dati, non è un fallimento).

---

### Task 4: `DocumentClassifier`, `AiClassifier`, `ClaudeClassifier`

**Files:**
- Create: `app/Services/SharePoint/Classification.php`
- Create: `app/Services/SharePoint/AiClassifier.php`
- Create: `app/Services/SharePoint/ClaudeClassifier.php`
- Create: `app/Services/SharePoint/DocumentClassifier.php`
- Modify: `app/Providers/AppServiceProvider.php` (bind in `register()`)
- Test: `tests/Feature/DocumentClassifierTest.php`, `tests/Feature/ClaudeClassifierTest.php`

**Interfaces:**
- Produces:
  - `Classification` (readonly): `?int $documentTypeId, int $confidence, string $source` (`'rule'|'ai'|'none'`).
  - `interface AiClassifier { /** @param list<array{id:int,name:string}> $candidates @return list<array{document_type_id:int,confidence:int}> */ public function classify(string $path, array $candidates): array; }`
  - `DocumentClassifier::__construct(AiClassifier $ai)`; `withTypes(Collection $types): static`; `classify(string $relativePath): array` (lista di `Classification`, mai vuota).
  - `ClaudeClassifier implements AiClassifier`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/DocumentClassifierTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Services\SharePoint\AiClassifier;
use App\Services\SharePoint\DocumentClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentClassifierTest extends TestCase
{
    use RefreshDatabase;

    private array $aiCalls = [];

    private function type(int $id, string $name, ?string $regex = null, ?int $min = null): DocumentType
    {
        return DocumentType::unguarded(fn () => DocumentType::create([
            'id' => $id, 'name' => $name, 'regex' => $regex, 'min_confidence' => $min,
        ]));
    }

    private function classifier(array $aiAnswer = [], bool $aiThrows = false): DocumentClassifier
    {
        $ai = new class($aiAnswer, $aiThrows, $this->aiCalls) implements AiClassifier
        {
            public function __construct(private array $answer, private bool $throws, public array &$calls) {}

            public function classify(string $path, array $candidates): array
            {
                $this->calls[] = ['path' => $path, 'candidates' => array_column($candidates, 'id')];
                if ($this->throws) {
                    throw new \RuntimeException('api down');
                }

                return $this->answer;
            }
        };

        return (new DocumentClassifier($ai))->withTypes(DocumentType::all());
    }

    public function test_single_regex_hit_is_a_rule_classification_without_ai(): void
    {
        $this->type(2, 'Carichi Pendenti', '/carichi.*pendenti/i');
        $this->type(1, 'Casellario Giudiziale', '/casellario.*giudiziale/i');

        $r = $this->classifier()->classify('Rossi Mario/3 - REQUISITI DI ONORABILITA\'/Rossi - Carichi pendenti del 03_03_2026.pdf');

        $this->assertCount(1, $r);
        $this->assertSame(2, $r[0]->documentTypeId);
        $this->assertSame('rule', $r[0]->source);
        $this->assertSame([], $this->aiCalls);
    }

    public function test_two_regex_hits_go_to_ai_restricted_to_hits_and_yield_two_documents(): void
    {
        $this->type(2, 'Carichi Pendenti', '/carichi.*pendenti/i');
        $this->type(1, 'Casellario Giudiziale', '/casellario|giudiz/i');
        $this->type(9, 'Altro', null);

        $r = $this->classifier([
            ['document_type_id' => 1, 'confidence' => 95],
            ['document_type_id' => 2, 'confidence' => 92],
        ])->classify('Rossi/3 - ONORABILITA/Casellar. Giudiz. e Carichi pendenti.pdf');

        $this->assertSame([1, 2], array_map(fn ($c) => $c->documentTypeId, $r));
        $this->assertSame('ai', $r[0]->source);
        $this->assertEqualsCanonicalizing([1, 2], $this->aiCalls[0]['candidates']);
    }

    public function test_ai_never_receives_the_collaborator_folder_name(): void
    {
        $this->type(9, 'Altro', null);

        $this->classifier([])->classify('Rossi Mario/6 - ALTRO/foglio.pdf');

        $this->assertSame('6 - ALTRO/foglio.pdf', $this->aiCalls[0]['path']);
    }

    public function test_ai_failure_gives_single_untyped_classification(): void
    {
        $this->type(9, 'Altro', null);

        $r = $this->classifier([], aiThrows: true)->classify('Rossi/6 - ALTRO/foglio.pdf');

        $this->assertCount(1, $r);
        $this->assertNull($r[0]->documentTypeId);
        $this->assertSame('none', $r[0]->source);
    }

    public function test_ai_ids_outside_candidates_are_ignored(): void
    {
        $this->type(9, 'Altro', null);

        $r = $this->classifier([['document_type_id' => 777, 'confidence' => 99]])
            ->classify('Rossi/6 - ALTRO/foglio.pdf');

        $this->assertNull($r[0]->documentTypeId);
    }

    public function test_confidence_below_type_min_confidence_leaves_type_null(): void
    {
        $this->type(9, 'Altro', null, 80);

        $r = $this->classifier([['document_type_id' => 9, 'confidence' => 60]])
            ->classify('Rossi/6 - ALTRO/foglio.pdf');

        $this->assertNull($r[0]->documentTypeId);
        $this->assertSame(60, $r[0]->confidence);
    }

    public function test_folder_hint_restricts_ai_candidates(): void
    {
        $this->type(1, 'Casellario Giudiziale', null);
        $this->type(5, 'Visura Camerale', null);

        $this->classifier([])->classify('Rossi/5 - PARTITA IVA - VISURA/documento.pdf');

        $this->assertSame([5], $this->aiCalls[0]['candidates']);
    }

    public function test_file_directly_in_collaborator_folder_uses_all_types(): void
    {
        $this->type(1, 'Casellario Giudiziale', null);
        $this->type(5, 'Visura Camerale', null);

        $this->classifier([])->classify('Rossi/documento.pdf');

        $this->assertEqualsCanonicalizing([1, 5], $this->aiCalls[0]['candidates']);
    }

    public function test_invalid_regex_in_document_types_does_not_break_classification(): void
    {
        $this->type(1, 'Rotto', '/(unclosed');

        $r = $this->classifier()->classify('Rossi/6 - ALTRO/x.pdf');

        $this->assertNull($r[0]->documentTypeId);
    }
}
```

`tests/Feature/ClaudeClassifierTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\SharePoint\ClaudeClassifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClaudeClassifierTest extends TestCase
{
    private array $candidates = [['id' => 1, 'name' => 'Casellario'], ['id' => 2, 'name' => 'Carichi']];

    public function test_parses_json_array_from_model_text(): void
    {
        config(['services.anthropic.key' => 'k', 'services.anthropic.model' => 'm']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => "Ecco:\n[{\"document_type_id\": 1, \"confidence\": 93}]"]],
        ])]);

        $r = (new ClaudeClassifier)->classify('3 - X/a.pdf', $this->candidates);

        $this->assertSame([['document_type_id' => 1, 'confidence' => 93]], $r);
        Http::assertSent(fn ($req) => $req->hasHeader('x-api-key', 'k')
            && $req['model'] === 'm'
            && str_contains($req['messages'][0]['content'], '3 - X/a.pdf'));
    }

    public function test_returns_empty_on_garbage_text(): void
    {
        config(['services.anthropic.key' => 'k']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'non so']]])]);

        $this->assertSame([], (new ClaudeClassifier)->classify('a.pdf', $this->candidates));
    }

    public function test_http_error_throws(): void
    {
        config(['services.anthropic.key' => 'k']);
        Http::fake(['api.anthropic.com/*' => Http::response([], 500)]);

        $this->expectException(\Throwable::class);
        (new ClaudeClassifier)->classify('a.pdf', $this->candidates);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter="DocumentClassifierTest|ClaudeClassifierTest"`
Expected: FAIL (classi non trovate).

- [ ] **Step 3: Write minimal implementation**

`app/Services/SharePoint/Classification.php`:

```php
<?php

namespace App\Services\SharePoint;

final class Classification
{
    public function __construct(
        public readonly ?int $documentTypeId,
        public readonly int $confidence,
        public readonly string $source,
    ) {}
}
```

`app/Services/SharePoint/AiClassifier.php`:

```php
<?php

namespace App\Services\SharePoint;

interface AiClassifier
{
    /**
     * @param  list<array{id:int,name:string}>  $candidates
     * @return list<array{document_type_id:int,confidence:int}>
     */
    public function classify(string $path, array $candidates): array;
}
```

`app/Services/SharePoint/ClaudeClassifier.php`:

```php
<?php

namespace App\Services\SharePoint;

use Illuminate\Support\Facades\Http;

class ClaudeClassifier implements AiClassifier
{
    public function classify(string $path, array $candidates): array
    {
        $list = collect($candidates)->map(fn ($c) => "{$c['id']}: {$c['name']}")->implode("\n");

        $prompt = <<<PROMPT
        Sei un assistente che classifica documenti aziendali di collaboratori (agenti) a partire dal percorso del file.

        Percorso file: {$path}

        Tipi di documento candidati (id: nome):
        {$list}

        Indica quali tipi di documento contiene il file. Un file può contenere più documenti
        (es. "Casellario giudiziale e carichi pendenti" = due tipi). Usa solo gli id elencati.
        Rispondi SOLO con un array JSON, senza testo aggiuntivo, nel formato:
        [{"document_type_id": <id>, "confidence": <0-100>}]
        Se nessun tipo è plausibile rispondi [].
        PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => (string) config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 500,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $response->throw();

        $text = (string) $response->json('content.0.text');
        if (! preg_match('/\[.*\]/s', $text, $m)) {
            return [];
        }

        $decoded = json_decode($m[0], true);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $row) {
            if (is_array($row) && isset($row['document_type_id'], $row['confidence'])) {
                $out[] = [
                    'document_type_id' => (int) $row['document_type_id'],
                    'confidence' => max(0, min(100, (int) $row['confidence'])),
                ];
            }
        }

        return $out;
    }
}
```

`app/Services/SharePoint/DocumentClassifier.php`:

```php
<?php

namespace App\Services\SharePoint;

use App\Models\DocumentType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentClassifier
{
    /** @var Collection<int, DocumentType>|null */
    private ?Collection $types = null;

    public function __construct(private readonly AiClassifier $ai) {}

    /** @param Collection<int, DocumentType> $types */
    public function withTypes(Collection $types): static
    {
        $this->types = $types;

        return $this;
    }

    /** @return list<Classification> */
    public function classify(string $relativePath): array
    {
        $segments = explode('/', $relativePath);
        $fileName = pathinfo((string) end($segments), PATHINFO_FILENAME);
        $folderNo = count($segments) >= 3 && preg_match('/^(\d+)\s*-/', $segments[1], $m) ? (int) $m[1] : null;

        $candidates = $this->candidatesFor($folderNo);
        $hits = $candidates->filter(fn ($t) => $this->matches($t->regex, $fileName))->values();

        if ($hits->count() === 1) {
            return [$this->finalize($hits->first(), (int) config('sharepoint_import.rule_confidence'), 'rule')];
        }

        $pool = $hits->isNotEmpty() ? $hits : $candidates;
        $aiPath = implode('/', array_slice($segments, 1));

        return $this->classifyWithAi($aiPath, $pool);
    }

    /** @return Collection<int, DocumentType> */
    private function candidatesFor(?int $folderNo): Collection
    {
        $all = $this->types();
        $hint = $folderNo !== null ? config("sharepoint_import.folder_hints.{$folderNo}") : null;

        if ($hint === null) {
            return $all;
        }

        $filtered = $all->filter(fn ($t) => (bool) preg_match($hint, (string) $t->name))->values();

        return $filtered->isNotEmpty() ? $filtered : $all;
    }

    /** @return Collection<int, DocumentType> */
    private function types(): Collection
    {
        return $this->types ??= DocumentType::all();
    }

    private function matches(?string $regex, string $subject): bool
    {
        if ($regex === null || $regex === '') {
            return false;
        }

        return @preg_match($regex, $subject) === 1;
    }

    /**
     * @param  Collection<int, DocumentType>  $pool
     * @return list<Classification>
     */
    private function classifyWithAi(string $path, Collection $pool): array
    {
        if ($pool->isEmpty()) {
            return [new Classification(null, 0, 'none')];
        }

        try {
            $answers = $this->ai->classify($path, $pool->map(fn ($t) => ['id' => $t->id, 'name' => (string) $t->name])->all());
        } catch (Throwable $e) {
            Log::warning('Classificazione AI fallita: '.$e->getMessage());

            return [new Classification(null, 0, 'none')];
        }

        $byId = $pool->keyBy('id');
        $out = [];
        foreach ($answers as $answer) {
            $type = $byId->get($answer['document_type_id']);
            if ($type !== null) {
                $out[] = $this->finalize($type, $answer['confidence'], 'ai');
            }
        }

        return $out !== [] ? $out : [new Classification(null, 0, 'none')];
    }

    private function finalize(DocumentType $type, int $confidence, string $source): Classification
    {
        $min = $type->min_confidence ?? (int) config('sharepoint_import.default_min_confidence');

        return new Classification($confidence >= $min ? $type->id : null, $confidence, $source);
    }
}
```

`app/Providers/AppServiceProvider.php` — dentro `register()`, al posto del commento `//`:

```php
        $this->app->bind(\App\Services\SharePoint\AiClassifier::class, \App\Services\SharePoint\ClaudeClassifier::class);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter="DocumentClassifierTest|ClaudeClassifierTest"`
Expected: PASS (12 test). Nota: `test_two_regex_hits_...` richiede che `hits` contenga gli id 1 e 2 e non il 9; `test_folder_hint_...` richiede il tipo "Visura Camerale" per la cartella 5 (regex `visura`).

---

### Task 5: `DocumentImporter` e comando `sharepoint:import-documents`

**Files:**
- Create: `app/Services/SharePoint/DocumentImporter.php`
- Create: `app/Console/Commands/ImportSharePointDocuments.php`
- Test: `tests/Feature/DocumentImporterTest.php`

**Interfaces:**
- Consumes: `SharePointClient::{findChildFolder, files, driveId}`, `FornitoreMatcher::match`, `DocumentClassifier::classify`, `Document`.
- Produces: `DocumentImporter::__construct(SharePointClient, FornitoreMatcher, DocumentClassifier)`; `run(string $rootName, bool $commit): array` → lista di righe `array{path:string, collaborator:?string, fornitore_id:?string, fornitore:?string, match:string, document_type_id:?int, confidence:?int, source:?string, action:string}` con `action` ∈ `dry_run|created|exists|skipped_no_fornitore|skipped_outside_collaborator`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Fornitori;
use App\Services\SharePoint\AiClassifier;
use App\Services\SharePoint\DocumentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumentImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sharepoint.drive_id' => 'D',
            'services.sharepoint.tenant_id' => 't',
        ]);

        DocumentType::unguarded(function () {
            DocumentType::create(['id' => 1, 'name' => 'Casellario Giudiziale', 'regex' => '/casellario|giudiz/i']);
            DocumentType::create(['id' => 2, 'name' => 'Carichi Pendenti', 'regex' => '/carichi.*pendenti/i']);
            DocumentType::create(['id' => 3, 'name' => 'Visura Camerale', 'regex' => '/visura/i']);
        });

        $this->fornitore = Fornitori::unguarded(fn () => Fornitori::create([
            'nome' => 'ROSSI MARIO',
            'company_id' => 'company-1',
        ]));

        $this->app->instance(AiClassifier::class, new class implements AiClassifier
        {
            public function classify(string $path, array $candidates): array
            {
                return [
                    ['document_type_id' => 1, 'confidence' => 95],
                    ['document_type_id' => 2, 'confidence' => 92],
                ];
            }
        });

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok']),
            'graph.microsoft.com/v1.0/drives/D/items/root/children*' => Http::response(['value' => [
                ['id' => 'R', 'name' => '1 - COLLABORATORI ATTIVI', 'folder' => ['childCount' => 3]],
            ]]),
            'graph.microsoft.com/v1.0/drives/D/items/R/children*' => Http::response(['value' => [
                ['id' => 'c1', 'name' => 'Rossi Mario', 'folder' => ['childCount' => 3]],
                ['id' => 'c2', 'name' => 'Sconosciuto Tizio', 'folder' => ['childCount' => 1]],
                ['id' => 'loose', 'name' => 'readme.pdf', 'file' => [], 'size' => 1, 'eTag' => 'e0', 'webUrl' => 'u0'],
            ]]),
            'graph.microsoft.com/v1.0/drives/D/items/c1/children*' => Http::response(['value' => [
                ['id' => 'f1', 'name' => 'Visura Camerale 2026.pdf', 'file' => [], 'size' => 10, 'eTag' => 'e1', 'webUrl' => 'u1'],
                ['id' => 'f2', 'name' => 'Casellar. Giudiz. e Carichi pendenti.pdf', 'file' => [], 'size' => 20, 'eTag' => 'e2', 'webUrl' => 'u2'],
            ]]),
            'graph.microsoft.com/v1.0/drives/D/items/c2/children*' => Http::response(['value' => [
                ['id' => 'f3', 'name' => 'x.pdf', 'file' => [], 'size' => 5, 'eTag' => 'e3', 'webUrl' => 'u3'],
            ]]),
        ]);
    }

    private Fornitori $fornitore;

    private function importer(): DocumentImporter
    {
        return app(DocumentImporter::class);
    }

    public function test_dry_run_writes_nothing_but_reports_everything(): void
    {
        $rows = $this->importer()->run('1 - COLLABORATORI ATTIVI', commit: false);

        $this->assertSame(0, Document::count());
        $actions = array_count_values(array_column($rows, 'action'));
        $this->assertSame(3, $actions['dry_run']);          // f1 (1 tipo) + f2 (2 tipi)
        $this->assertSame(1, $actions['skipped_no_fornitore']);
        $this->assertSame(1, $actions['skipped_outside_collaborator']);
    }

    public function test_commit_creates_one_record_per_file_and_type(): void
    {
        $this->importer()->run('1 - COLLABORATORI ATTIVI', commit: true);

        $this->assertSame(3, Document::count());

        $doc = Document::where('app_id', 'f1')->first();
        $this->assertSame('fornitore', $doc->documentable_type);
        $this->assertSame($this->fornitore->id, $doc->documentable_id);
        $this->assertSame('company-1', $doc->company_id);
        $this->assertSame(3, $doc->document_type_id);
        $this->assertSame('sharepoint', $doc->source_app);
        $this->assertSame('synced', $doc->sync_status);
        $this->assertSame('D', $doc->app_drive_id);
        $this->assertSame('e1', $doc->app_etag);
        $this->assertSame('u1', $doc->document_url);
        $this->assertSame('Visura Camerale 2026.pdf', $doc->name);
        $this->assertSame('Rossi Mario/Visura Camerale 2026.pdf', $doc->metadata['path']);
        $this->assertSame('exact', $doc->metadata['match']);

        $this->assertEqualsCanonicalizing(
            [1, 2],
            Document::where('app_id', 'f2')->pluck('document_type_id')->all()
        );
    }

    public function test_rerun_is_idempotent_even_for_soft_deleted_rows(): void
    {
        $this->importer()->run('1 - COLLABORATORI ATTIVI', commit: true);
        Document::where('app_id', 'f1')->first()->delete();

        $rows = $this->importer()->run('1 - COLLABORATORI ATTIVI', commit: true);

        $this->assertSame(2, Document::count());
        $this->assertSame(1, Document::onlyTrashed()->count());
        $this->assertSame(3, array_count_values(array_column($rows, 'action'))['exists']);
    }

    public function test_existing_unrelated_documents_are_untouched(): void
    {
        $old = Document::create(['documentable_type' => 'fornitore', 'documentable_id' => 'x', 'name' => 'vecchio']);

        $this->importer()->run('1 - COLLABORATORI ATTIVI', commit: true);

        $this->assertSame('vecchio', $old->fresh()->name);
        $this->assertSame('local', $old->fresh()->source_app);
    }

    public function test_missing_root_folder_throws(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->importer()->run('Cartella Inesistente', commit: false);
    }

    public function test_command_defaults_to_dry_run_and_writes_csv(): void
    {
        $this->artisan('sharepoint:import-documents')
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertSame(0, Document::count());
        $csv = glob(storage_path('app/sharepoint-import-*.csv'));
        $this->assertNotEmpty($csv);
        array_map('unlink', $csv);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DocumentImporterTest`
Expected: FAIL (`Class "App\Services\SharePoint\DocumentImporter" not found`).

- [ ] **Step 3: Write minimal implementation**

`app/Services/SharePoint/DocumentImporter.php`:

```php
<?php

namespace App\Services\SharePoint;

use App\Models\Document;
use RuntimeException;

class DocumentImporter
{
    public function __construct(
        private readonly SharePointClient $client,
        private readonly FornitoreMatcher $matcher,
        private readonly DocumentClassifier $classifier,
    ) {}

    /** @return list<array<string, mixed>> */
    public function run(string $rootName, bool $commit): array
    {
        $root = $this->client->findChildFolder('root', $rootName)
            ?? throw new RuntimeException("Cartella radice non trovata: {$rootName}");

        $rows = [];
        $matches = [];

        foreach ($this->client->files($root['id']) as $file) {
            $segments = explode('/', $file->path);

            if (count($segments) < 2) {
                $rows[] = $this->row($file, null, null, 'none', null, 'skipped_outside_collaborator');

                continue;
            }

            $collaborator = $segments[0];
            $match = $matches[$collaborator] ??= $this->matcher->match($collaborator);

            if ($match->fornitore === null) {
                $rows[] = $this->row($file, $collaborator, null, 'none', null, 'skipped_no_fornitore');

                continue;
            }

            foreach ($this->classifier->classify($file->path) as $classification) {
                $action = $commit ? $this->store($file, $match, $classification) : 'dry_run';
                $rows[] = $this->row($file, $collaborator, $match, $match->kind, $classification, $action);
            }
        }

        return $rows;
    }

    private function store(SharePointFile $file, FornitoreMatch $match, Classification $c): string
    {
        $exists = Document::withTrashed()
            ->where('source_app', 'sharepoint')
            ->where('app_id', $file->id)
            ->when(
                $c->documentTypeId === null,
                fn ($q) => $q->whereNull('document_type_id'),
                fn ($q) => $q->where('document_type_id', $c->documentTypeId)
            )
            ->exists();

        if ($exists) {
            return 'exists';
        }

        Document::create([
            'company_id' => $match->fornitore->company_id,
            'documentable_type' => 'fornitore',
            'documentable_id' => $match->fornitore->id,
            'document_type_id' => $c->documentTypeId,
            'name' => $file->name,
            'document_url' => $file->webUrl,
            'status' => 'uploaded',
            'sync_status' => 'synced',
            'source_app' => 'sharepoint',
            'app_id' => $file->id,
            'app_drive_id' => $this->client->driveId(),
            'app_etag' => $file->etag,
            'ai_confidence_score' => $c->confidence,
            'metadata' => [
                'path' => $file->path,
                'match' => $match->kind,
                'classification_source' => $c->source,
                'needs_review' => $match->kind !== 'exact' || $c->documentTypeId === null,
            ],
        ]);

        return 'created';
    }

    /** @return array<string, mixed> */
    private function row(SharePointFile $file, ?string $collaborator, ?FornitoreMatch $match, string $kind, ?Classification $c, string $action): array
    {
        return [
            'path' => $file->path,
            'collaborator' => $collaborator,
            'fornitore_id' => $match?->fornitore?->id,
            'fornitore' => $match?->fornitore?->nome,
            'match' => $kind,
            'document_type_id' => $c?->documentTypeId,
            'confidence' => $c?->confidence,
            'source' => $c?->source,
            'action' => $action,
        ];
    }
}
```

`app/Console/Commands/ImportSharePointDocuments.php`:

```php
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
        fputcsv($handle, ['path', 'collaborator', 'fornitore_id', 'fornitore', 'match', 'document_type_id', 'confidence', 'source', 'action']);
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }
        fclose($handle);

        $this->table(['action', 'righe'], collect($rows)->countBy('action')->map(fn ($n, $a) => [$a, $n])->values()->all());
        $this->line("Report: {$path}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=DocumentImporterTest`
Expected: PASS (6 test). Se `app(DocumentImporter::class)` non risolve `SharePointClient` (costruttore con `?string`), il container usa il default `null`: corretto.

- [ ] **Step 5: Run the whole suite**

Run: `php artisan test`
Expected: tutti PASS, incluso `ExampleTest` esistente (se quest'ultimo fallisce già prima di queste modifiche, annotarlo senza correggerlo).

---

### Task 6: Dry-run reale e verifica (DB di sviluppo)

**Files:** nessuno nuovo.

- [ ] **Step 1: Dry-run sul tenant reale**

Run: `php artisan sharepoint:import-documents`
Expected: tabella riepilogo per `action` (`dry_run`, `skipped_no_fornitore`, …) e percorso del CSV in `storage/app/`. Può durare diversi minuti (decine di chiamate Graph e Claude); lanciarlo in background se necessario.

- [ ] **Step 2: Rivedere il CSV con l'utente**

Controllare: cartelle senza fornitore (`skipped_no_fornitore`), match `fuzzy`, righe con `document_type_id` vuoto, tipi duplicati (33/34, 35 vs 41, 37 vs 43…). Correggere regex/`folder_hints` se necessario e rilanciare il dry-run.

- [ ] **Step 3: Import reale (dopo il via dell'utente sul report)**

Run: `php artisan sharepoint:import-documents --commit`
Expected: righe `created`. Verifica:
```bash
php artisan tinker --execute='echo App\Models\Document::where("source_app","sharepoint")->count()," | ",App\Models\Document::count();'
```
Expected: il secondo numero = 808 + il primo (le righe esistenti non sono state toccate). Rilanciare `--commit` deve dare solo `exists`.
