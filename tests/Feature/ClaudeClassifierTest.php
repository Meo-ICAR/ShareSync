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

        $r = (new ClaudeClassifier)->classify('Rossi/3 - X/a.pdf', $this->candidates);

        $this->assertSame([['document_type_id' => 1, 'confidence' => 93]], $r);
        Http::assertSent(fn ($req) => $req->hasHeader('x-api-key', 'k')
            && $req['model'] === 'm'
            && str_contains($req['messages'][0]['content'], 'Rossi/3 - X/a.pdf'));
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
