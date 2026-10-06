<?php

namespace App\Jobs;

use App\Services\SharePoint\SharePointRealigner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class RunSharePointRealignment implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 7200;

    /** Evita due riscansioni sovrapposte. */
    public int $uniqueFor = 10800;

    public function __construct(public readonly bool $commit = true, public readonly bool $classify = false) {}

    public function handle(SharePointRealigner $realigner): void
    {
        $failed = collect($realigner->run($this->commit, $this->classify))->where('exit_code', '!=', 0);

        if ($failed->isNotEmpty()) {
            throw new RuntimeException('Passaggi falliti: '.$failed->pluck('step')->implode(', '));
        }
    }
}
