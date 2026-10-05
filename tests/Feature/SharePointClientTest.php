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
