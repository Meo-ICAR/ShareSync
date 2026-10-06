<?php

namespace Tests\Feature;

use App\Jobs\RunSharePointRealignment;
use App\Services\SharePoint\SharePointRealigner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SharePointRealignTest extends TestCase
{
    public function test_it_runs_every_step_in_order_with_the_commit_option(): void
    {
        Artisan::shouldReceive('call')->once()->ordered()->with('sharepoint:import-documents', ['--commit' => true])->andReturn(0);
        Artisan::shouldReceive('call')->once()->ordered()->with('sharepoint:import-documents', ['--employees' => true, '--commit' => true])->andReturn(0);
        Artisan::shouldReceive('call')->once()->ordered()->with('sharepoint:link-raccolte', ['--commit' => true, '--classify' => true])->andReturn(0);
        Artisan::shouldReceive('call')->once()->ordered()->with('sharepoint:backfill-emission-dates', ['--commit' => true])->andReturn(0);
        Artisan::shouldReceive('output')->times(4)->andReturn('ok');

        $results = app(SharePointRealigner::class)->run(commit: true, classify: true);

        $this->assertSame(
            ['import fornitori', 'import dipendenti', 'collegamento raccolte', 'allineamento date, scadenze e versioni'],
            array_column($results, 'step'),
        );
    }

    public function test_dry_run_does_not_pass_the_commit_option(): void
    {
        Artisan::shouldReceive('call')->times(4)->withArgs(fn (string $command, array $options): bool => ! array_key_exists('--commit', $options))->andReturn(0);
        Artisan::shouldReceive('output')->times(4)->andReturn('');

        app(SharePointRealigner::class)->run(commit: false);
    }

    public function test_a_failing_step_does_not_stop_the_following_ones(): void
    {
        Artisan::shouldReceive('call')->once()->with('sharepoint:import-documents', ['--commit' => true])->andThrow(new RuntimeException('Cartella radice non trovata'));
        Artisan::shouldReceive('call')->times(3)->andReturn(0);
        Artisan::shouldReceive('output')->times(3)->andReturn('ok');

        $results = app(SharePointRealigner::class)->run(commit: true);

        $this->assertSame([1, 0, 0, 0], array_column($results, 'exit_code'));
        $this->assertSame('Cartella radice non trovata', $results[0]['output']);
    }

    public function test_command_queues_the_job_when_asked(): void
    {
        Queue::fake();

        $this->artisan('sharepoint:realign', ['--commit' => true, '--queue' => true])->assertSuccessful();

        Queue::assertPushed(RunSharePointRealignment::class, fn (RunSharePointRealignment $job): bool => $job->commit === true && $job->classify === false);
    }

    public function test_command_fails_when_a_step_fails(): void
    {
        $this->mock(SharePointRealigner::class, fn ($mock) => $mock->shouldReceive('run')->with(true, false)->andReturn([
            ['step' => 'import fornitori', 'exit_code' => 0, 'output' => 'ok'],
            ['step' => 'collegamento raccolte', 'exit_code' => 1, 'output' => 'CSV non trovato'],
        ]));

        $this->artisan('sharepoint:realign', ['--commit' => true])->assertFailed();
    }

    public function test_job_throws_when_a_step_failed_so_it_lands_in_failed_jobs(): void
    {
        $this->mock(SharePointRealigner::class, fn ($mock) => $mock->shouldReceive('run')->with(true, false)->andReturn([
            ['step' => 'import fornitori', 'exit_code' => 1, 'output' => 'x'],
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('import fornitori');

        (new RunSharePointRealignment)->handle(app(SharePointRealigner::class));
    }

    public function test_job_is_unique_and_has_a_long_timeout(): void
    {
        $job = new RunSharePointRealignment;

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertGreaterThanOrEqual(3600, $job->timeout);
    }
}
