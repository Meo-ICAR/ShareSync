<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Services\SharePoint\RaccolteScanner;
use App\Services\SharePoint\SharePointFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RaccolteScannerTest extends TestCase
{
    use RefreshDatabase;

    private function csv(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'racc');
        file_put_contents($path, "Raccolta;Elementi;DriveId;WebUrl\nAgos;0;dA;u\nFornitori;5;dF;u\nMotum;1;dM;u\n");

        return $path;
    }

    public function test_keeps_only_raccolte_with_elementi(): void
    {
        $rows = (new RaccolteScanner)->raccolte($this->csv());

        $this->assertSame(['Fornitori', 'Motum'], array_column($rows, 'raccolta'));
        $this->assertSame('dF', $rows[0]['drive_id']);
    }

    public function test_reports_files_without_matching_type(): void
    {
        DocumentType::unguarded(fn () => DocumentType::create(['id' => 1, 'name' => 'Visura camerale']));
        $file = fn ($name, $path) => new SharePointFile('i', $name, $path, null, null, 1);

        $out = (new RaccolteScanner)->unmatched([
            $file('Visura camerale 2024.pdf', 'Acme/Visura camerale 2024.pdf'),
            $file('Accordo quadro.pdf', 'Acme/Accordo quadro.pdf'),
        ], DocumentType::all());

        $this->assertCount(1, $out);
        $this->assertSame('Accordo quadro.pdf', $out[0]['name']);
        $this->assertSame('Acme', $out[0]['folder']);
    }

    public function test_skips_files_in_historical_folders(): void
    {
        $file = fn ($path) => new SharePointFile('i', basename($path), $path, null, null, 1);

        $out = (new RaccolteScanner)->unmatched([
            $file('ACCORDI CESSATI/Fides/Manuale.pdf'),
            $file('ACCORDI RECEDUTI/BNL/Accordo.pdf'),
            $file('Attivi/Accordo.pdf'),
        ], DocumentType::all());

        $this->assertSame(['Attivi/Accordo.pdf'], array_column($out, 'path'));
    }

    public function test_command_scans_each_raccolta_and_writes_report(): void
    {
        config(['services.sharepoint.tenant_id' => 't', 'services.sharepoint.client_id' => 'c', 'services.sharepoint.client_secret' => 's']);
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok']),
            'graph.microsoft.com/v1.0/drives/dF/items/root/children*' => Http::response(['value' => [
                ['id' => 'a', 'name' => 'Contratto X.pdf', 'file' => [], 'size' => 1],
            ]]),
            'graph.microsoft.com/v1.0/drives/dM/items/root/children*' => Http::response(['value' => []]),
        ]);

        $this->artisan('sharepoint:scan-raccolte', ['--csv' => $this->csv()])->assertSuccessful();

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'drives/dA/'));
    }
}
