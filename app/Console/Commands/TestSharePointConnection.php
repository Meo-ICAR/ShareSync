<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestSharePointConnection extends Command
{
    protected $signature = 'sharepoint:test';
    protected $description = 'Verifica la validità della connessione verso SharePoint tramite Microsoft Graph API';

    public function handle()
    {
        $tenantId = config('services.sharepoint.tenant_id');
        $clientId = config('services.sharepoint.client_id');
        $clientSecret = config('services.sharepoint.client_secret');

        // 1. Verifica presenza variabili
        if (!$tenantId || !$clientId || !$clientSecret) {
            $this->error('ERRORE: Una o più variabili d\'ambiente (SHARE_TENANT_ID, SHARE_CLIENT_ID, SHARE_SECRET_ID) non sono presenti nel file .env.');
            return Command::FAILURE;
        }

        $this->info("Connessione in corso a Microsoft Entra ID per il Tenant: {$tenantId}...");

        // 2. Richiesta Token di Autenticazione (OAuth 2.0 Client Credentials)
        $tokenResponse = Http::asForm()->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'scope'         => 'https://graph.microsoft.com/.default',
            'grant_type'    => 'client_credentials',
        ]);

        if ($tokenResponse->failed()) {
            $this->error("\n❌ Autenticazione Fallita.");
            $this->line("Dettaglio Errore HTTP {$tokenResponse->status()}:");
            $this->line($tokenResponse->body());
            return Command::FAILURE;
        }

        $accessToken = $tokenResponse->json('access_token');
        $this->info("✔ Token di accesso ottenuto con successo.");

        // 3. Test Chiamata API su SharePoint (Lettura del sito Root)
        $this->info("Verifica autorizzazioni su SharePoint...");

        $graphResponse = Http::withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/sites/root');

        if ($graphResponse->failed()) {
            $this->error("\n❌ Errore durante la chiamata a SharePoint.");
            
            if ($graphResponse->status() === 403) {
                $this->warn("Errore 403: Forbidden.");
                $this->warn("Il token è valido ma l'App non ha i permessi per accedere al sito SharePoint. Verifica di aver concesso i permessi (es. Sites.Read.All) e l'Admin Consent su Entra ID.");
            } else {
                $this->line("Dettaglio HTTP {$graphResponse->status()}: " . $graphResponse->body());
            }
            return Command::FAILURE;
        }

        $siteData = $graphResponse->json();

        $this->info("\n✅ CONNESSIONE RIUSCITA!");
        $this->table(
            ['Parametro', 'Valore'],
            [
                ['Sito SharePoint', $siteData['displayName'] ?? 'N/D'],
                ['URL Web', $siteData['webUrl'] ?? 'N/D'],
                ['Site ID', $siteData['id'] ?? 'N/D'],
            ]
        );

        return Command::SUCCESS;
    }
}