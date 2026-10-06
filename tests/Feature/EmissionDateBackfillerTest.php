<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Services\SharePoint\EmissionDateBackfiller;
use App\Services\SharePoint\SharePointClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmissionDateBackfillerTest extends TestCase
{
    use RefreshDatabase;

    private function document(string $appId, ?string $emittedAt = null, string $source = 'sharepoint'): Document
    {
        return Document::unguarded(fn () => Document::create([
            'company_id' => 'company-1',
            'documentable_type' => 'fornitore',
            'documentable_id' => 'f-1',
            'name' => "{$appId}.pdf",
            'status' => 'uploaded',
            'source_app' => $source,
            'app_id' => $appId,
            'app_drive_id' => 'D',
            'emitted_at' => $emittedAt,
        ]));
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sharepoint.tenant_id' => 't',
            'services.sharepoint.client_id' => 'c',
            'services.sharepoint.client_secret' => 's',
            'services.sharepoint.drive_id' => 'D',
        ]);

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok']),
            'graph.microsoft.com/v1.0/drives/D/items/a1*' => Http::response(['id' => 'a1', 'lastModifiedDateTime' => '2023-10-10T11:19:21Z']),
            'graph.microsoft.com/v1.0/drives/D/items/a2*' => Http::response(['id' => 'a2', 'lastModifiedDateTime' => '2026-08-28T14:38:54Z']),
            'graph.microsoft.com/v1.0/drives/D/items/gone*' => Http::response([], 404),
            'graph.microsoft.com/v1.0/drives/D/items/boom*' => Http::response([], 500),
        ]);
    }

    public function test_it_sets_emitted_at_from_the_file_modification_date(): void
    {
        $a1 = $this->document('a1');
        $a2 = $this->document('a2');

        $result = app(EmissionDateBackfiller::class)->run(commit: true);

        $this->assertSame(2, $result['updated']);
        $this->assertSame('2023-10-10', $a1->refresh()->emitted_at->toDateString());
        $this->assertSame('2026-08-28', $a2->refresh()->emitted_at->toDateString());
        $this->assertEqualsCanonicalizing(
            [['id' => (string) $a1->id, 'emitted_at' => '2023-10-10'], ['id' => (string) $a2->id, 'emitted_at' => '2026-08-28']],
            $result['rows'],
        );
    }

    public function test_dry_run_reports_the_dates_without_writing(): void
    {
        $a1 = $this->document('a1');

        $result = app(EmissionDateBackfiller::class)->run(commit: false);

        $this->assertSame(1, $result['updated']);
        $this->assertNull($a1->refresh()->emitted_at);
    }

    public function test_it_leaves_existing_dates_and_other_sources_untouched(): void
    {
        $dated = $this->document('a1', '2020-01-01');
        $local = $this->document('a2', null, 'local');

        $result = app(EmissionDateBackfiller::class)->run(commit: true);

        $this->assertSame(0, $result['updated']);
        $this->assertSame('2020-01-01', $dated->refresh()->emitted_at->toDateString());
        $this->assertNull($local->refresh()->emitted_at);
    }

    public function test_it_counts_missing_files_and_errors_without_stopping(): void
    {
        $this->document('gone');
        $this->document('boom');
        $a1 = $this->document('a1');

        $result = app(EmissionDateBackfiller::class)->run(commit: true);

        $this->assertSame(['updated' => 1, 'missing' => 1, 'errors' => 1], collect($result)->only(['updated', 'missing', 'errors'])->all());
        $this->assertSame('2023-10-10', $a1->refresh()->emitted_at->toDateString());
    }

    public function test_client_returns_null_for_a_missing_item(): void
    {
        $this->assertNull((new SharePointClient)->lastModified('gone'));
    }

    public function test_command_writes_a_sql_file(): void
    {
        $a1 = $this->document('a1');
        $path = sys_get_temp_dir().'/backfill-'.uniqid().'.sql';

        $this->artisan('sharepoint:backfill-emission-dates', ['--sql' => $path])->assertSuccessful();

        $this->assertSame(
            "UPDATE documents SET emitted_at = '2023-10-10' WHERE id = '{$a1->id}' AND emitted_at IS NULL;\n",
            file_get_contents($path),
        );
        $this->assertNull($a1->refresh()->emitted_at);
        unlink($path);
    }

    public function test_it_recalculates_expires_at_for_monitored_types(): void
    {
        DocumentType::unguarded(fn () => DocumentType::create(['id' => 3, 'name' => 'Visura', 'is_monitored' => true, 'duration' => 6, 'duration_unit' => 'months']));
        $document = $this->document('a1');
        $document->forceFill(['document_type_id' => 3])->saveQuietly();

        app(EmissionDateBackfiller::class)->run(commit: true);

        $this->assertSame('2023-10-10', $document->refresh()->emitted_at->toDateString());
        $this->assertSame('2024-04-10', $document->expires_at->toDateString());
    }

    public function test_it_soft_deletes_older_versions_of_the_same_type_at_the_next_emission_date(): void
    {
        DocumentType::unguarded(fn () => DocumentType::create(['id' => 3, 'name' => 'Visura']));
        $older = $this->document('a1');
        $newer = $this->document('a2');
        $unrelated = $this->document('a3');
        foreach ([$older, $newer] as $document) {
            $document->forceFill(['document_type_id' => 3])->saveQuietly();
        }
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok']),
            'graph.microsoft.com/v1.0/drives/D/items/a1*' => Http::response(['lastModifiedDateTime' => '2023-10-10T11:19:21Z']),
            'graph.microsoft.com/v1.0/drives/D/items/a2*' => Http::response(['lastModifiedDateTime' => '2026-08-28T14:38:54Z']),
            'graph.microsoft.com/v1.0/drives/D/items/a3*' => Http::response(['lastModifiedDateTime' => '2020-01-01T00:00:00Z']),
        ]);

        $result = app(EmissionDateBackfiller::class)->run(commit: true);

        $this->assertSame(1, $result['superseded']);
        $this->assertSame('2026-08-28', $older->refresh()->deleted_at->toDateString());
        $this->assertNull($newer->refresh()->deleted_at);
        $this->assertNull($unrelated->refresh()->deleted_at);
    }

    public function test_dry_run_does_not_supersede_anything(): void
    {
        DocumentType::unguarded(fn () => DocumentType::create(['id' => 3, 'name' => 'Visura']));
        foreach (['a1', 'a2'] as $appId) {
            $this->document($appId)->forceFill(['document_type_id' => 3])->saveQuietly();
        }

        $result = app(EmissionDateBackfiller::class)->run(commit: false);

        $this->assertSame(0, $result['superseded']);
        $this->assertSame(0, Document::onlyTrashed()->count());
    }
}
