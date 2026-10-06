<?php

namespace App\Services\SharePoint;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Riscansione completa di SharePoint: import di fornitori e dipendenti, collegamento delle raccolte
 * e allineamento di data di emissione, scadenza e versioni. Riutilizzabile per i riallineamenti futuri.
 */
class SharePointRealigner
{
    /**
     * @return list<array{step: string, exit_code: int, output: string}>
     */
    public function run(bool $commit, bool $classify = false): array
    {
        $commitOption = $commit ? ['--commit' => true] : [];

        $steps = [
            'import fornitori' => ['sharepoint:import-documents', $commitOption],
            'import dipendenti' => ['sharepoint:import-documents', ['--employees' => true] + $commitOption],
            'collegamento raccolte' => ['sharepoint:link-raccolte', $commitOption + ($classify ? ['--classify' => true] : [])],
            'allineamento date, scadenze e versioni' => ['sharepoint:backfill-emission-dates', $commitOption],
        ];

        $results = [];

        foreach ($steps as $step => [$command, $options]) {
            try {
                $exitCode = Artisan::call($command, $options);
                $output = trim(Artisan::output());
            } catch (Throwable $e) {
                $exitCode = 1;
                $output = $e->getMessage();
            }

            Log::info("SharePoint realign: {$step}", ['exit_code' => $exitCode, 'commit' => $commit, 'output' => $output]);
            $results[] = ['step' => $step, 'exit_code' => $exitCode, 'output' => $output];
        }

        return $results;
    }
}
