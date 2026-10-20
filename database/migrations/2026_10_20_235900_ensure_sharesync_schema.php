<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Schema completo del database `sharesync`, rigenerato dal server MySQL.
 *
 * Idempotente: crea solo le tabelle, colonne, indici e chiavi esterne che mancano,
 * quindi si puo' rieseguire su un database gia' popolato senza errori e senza toccare i dati.
 * Le chiavi esterne che i dati storici non rispettano vengono saltate e segnalate nel log.
 */
return new class extends Migration
{
    public function getConnection()
    {
        return null;
    }

    public function up(): void
    {
        $db = DB::connection($this->getConnection());
        $schema = $db->getDatabaseName();
        $db->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->tables() as $table => $def) {
                $exists = $db->selectOne('select 1 as x from information_schema.tables where table_schema = ? and table_name = ?', [$schema, $table]);
                if (! $exists) {
                    $db->statement($def['create']);

                    continue;
                }
                foreach ($def['columns'] as $column => $sql) {
                    $has = $db->selectOne('select 1 as x from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?', [$schema, $table, $column]);
                    if (! $has) {
                        if (stripos($sql, 'AUTO_INCREMENT') !== false && ! $db->selectOne("select 1 as x from information_schema.statistics where table_schema = ? and table_name = ? and index_name = 'PRIMARY'", [$schema, $table])) {
                            $sql .= ", ADD PRIMARY KEY (`{$column}`)";
                        }
                        $db->statement("ALTER TABLE `{$table}` ADD COLUMN {$sql}");
                    }
                }
                foreach ($def['indexes'] as $index => $sql) {
                    $has = $db->selectOne('select 1 as x from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?', [$schema, $table, $index]);
                    if (! $has) {
                        $this->tryStatement($db, "ALTER TABLE `{$table}` ADD {$sql}", "{$table}.{$index}");
                    }
                }
            }

            foreach ($this->tables() as $table => $def) {
                foreach ($def['foreign'] as $name => $sql) {
                    $has = $db->selectOne("select 1 as x from information_schema.table_constraints where table_schema = ? and table_name = ? and constraint_name = ? and constraint_type = 'FOREIGN KEY'", [$schema, $table, $name]);
                    if (! $has) {
                        $this->tryStatement($db, "ALTER TABLE `{$table}` ADD {$sql}", "{$table}.{$name}");
                    }
                }
            }
        } finally {
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // Non si elimina nulla: lo schema e i dati sono condivisi.
    }

    private function tryStatement($db, string $sql, string $label): void
    {
        try {
            $db->statement($sql);
        } catch (\Throwable $e) {
            Log::warning("Migration sharesync: saltato {$label}: ".$e->getMessage());
        }
    }

    /** @return array<string, array{create: string, columns: array<string, string>, indexes: array<string, string>, foreign: array<string, string>}> */
    private function tables(): array
    {
        return [
        'cache' => [
            'create' => 'CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'key' => '`key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'value' => '`value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL AFTER `key`',
                'expiration' => '`expiration` bigint NOT NULL AFTER `value`',
            ],
            'indexes' => [
                'cache_expiration_index' => 'KEY `cache_expiration_index` (`expiration`)',
            ],
            'foreign' => [
            ],
        ],
        'cache_locks' => [
            'create' => 'CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'key' => '`key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'owner' => '`owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL AFTER `key`',
                'expiration' => '`expiration` bigint NOT NULL AFTER `owner`',
            ],
            'indexes' => [
                'cache_locks_expiration_index' => 'KEY `cache_locks_expiration_index` (`expiration`)',
            ],
            'foreign' => [
            ],
        ],
        'document_types' => [
            'create' => 'CREATE TABLE `document_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID intero autoincrementante\',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome documento\',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Descrizione aggiuntiva\',
  `code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice univoco mnemonico\',
  `codegroup` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Raggruppa documenti simili\',
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Slug univoco per URL\',
  `regex_pattern` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Pattern di validazione\',
  `priority` int NOT NULL DEFAULT \'0\' COMMENT \'Priorità di ordinamento\',
  `phase` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Fase di processo\',
  `is_person` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Documento inerente Persona\',
  `is_company` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Documento inerente Azienda\',
  `is_employee` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Richiesto ai dipendenti\',
  `is_agent` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Richiesto agli agenti\',
  `is_principal` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Richiesto alle mandanti\',
  `is_client` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Richiesto ai clienti\',
  `is_practice` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Legato a una pratica\',
  `trigger_field` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da controllare\',
  `is_signed` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Deve essere firmato\',
  `is_monitored` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Scadenza monitorata nel tempo\',
  `renewed_by_id` bigint unsigned DEFAULT NULL COMMENT \'ID del documento che lo rinnova\',
  `document_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'URL pubblico o percorso del documento sul web/storage\',
  `training_hours` int DEFAULT NULL COMMENT \'Ore di formazione richieste\',
  `training_organization` enum(\'interna\',\'OAM\',\'IVASS\',\'PRIVACY\') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Formazione per organizzazione\',
  `duration` int DEFAULT NULL COMMENT \'Valore della validità\',
  `duration_unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'days\' COMMENT \'Unità di misura: hours, days, months, years\',
  `nature` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT \'incoming\' COMMENT \'Tipo flusso: incoming, template_fillable, compliance\',
  `doctype` enum(\'modulo\',\'informativa\',\'procedura\',\'template\') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Tipo documento: modulo, procedura, template\',
  `cellposition` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Posizione della cella in cui si trova il documento\',
  `emitted_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Ente di rilascio predefinito\',
  `is_sensible` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Contiene dati sensibili\',
  `is_template` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Forniamo noi il template\',
  `is_stored` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Richiede conservazione sostitutiva\',
  `regex` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Pattern regex per classificazione\',
  `is_endMonth` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Approssima data a fine mese\',
  `is_AiAbstract` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Ask AI to make abstract\',
  `is_AiCheck` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'AI conformity required\',
  `AiPattern` text COLLATE utf8mb4_unicode_ci COMMENT \'How AI can detect document is of this type\',
  `min_confidence` tinyint unsigned NOT NULL DEFAULT \'70\' COMMENT \'Soglia minima per suggerire il tipo\',
  `allow_auto_verification` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Valida da solo se confidence alta\',
  `notify_days_before` json DEFAULT NULL COMMENT \'Es. [30, 15, 5] giorni prima\',
  `retention_years` tinyint unsigned DEFAULT NULL COMMENT \'GDPR retention policy (anni)\',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `deleted_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_versioned` tinyint(1) DEFAULT \'0\' COMMENT \'Mantieni lo storico\',
  `document_typable` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Alias del modello polimorfo a cui si applica il documento\',
  `trigger_state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\',
  `trigger_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\',
  `exclude_field` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da escludere se valorizzato\',
  `exclude_state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\',
  `exclude_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\',
  `expire_days_before` int DEFAULT NULL COMMENT \'Numero di giorni prima della scadenza per inviare la notifica\',
  PRIMARY KEY (`id`),
  KEY `document_types_renewed_by_id_foreign` (`renewed_by_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Anagrafica delle tipologie documentali con regole di validazione, AI e GDPR\'',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT \'ID intero autoincrementante\' FIRST',
                'name' => '`name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome documento\' AFTER `id`',
                'description' => '`description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Descrizione aggiuntiva\' AFTER `name`',
                'code' => '`code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice univoco mnemonico\' AFTER `description`',
                'codegroup' => '`codegroup` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Raggruppa documenti simili\' AFTER `code`',
                'slug' => '`slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Slug univoco per URL\' AFTER `codegroup`',
                'regex_pattern' => '`regex_pattern` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Pattern di validazione\' AFTER `slug`',
                'priority' => '`priority` int NOT NULL DEFAULT \'0\' COMMENT \'Priorità di ordinamento\' AFTER `regex_pattern`',
                'phase' => '`phase` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Fase di processo\' AFTER `priority`',
                'is_person' => '`is_person` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Documento inerente Persona\' AFTER `phase`',
                'is_company' => '`is_company` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Documento inerente Azienda\' AFTER `is_person`',
                'is_employee' => '`is_employee` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Richiesto ai dipendenti\' AFTER `is_company`',
                'is_agent' => '`is_agent` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Richiesto agli agenti\' AFTER `is_employee`',
                'is_principal' => '`is_principal` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Richiesto alle mandanti\' AFTER `is_agent`',
                'is_client' => '`is_client` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Richiesto ai clienti\' AFTER `is_principal`',
                'is_practice' => '`is_practice` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Legato a una pratica\' AFTER `is_client`',
                'trigger_field' => '`trigger_field` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da controllare\' AFTER `is_practice`',
                'is_signed' => '`is_signed` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Deve essere firmato\' AFTER `trigger_field`',
                'is_monitored' => '`is_monitored` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Scadenza monitorata nel tempo\' AFTER `is_signed`',
                'renewed_by_id' => '`renewed_by_id` bigint unsigned DEFAULT NULL COMMENT \'ID del documento che lo rinnova\' AFTER `is_monitored`',
                'document_url' => '`document_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'URL pubblico o percorso del documento sul web/storage\' AFTER `renewed_by_id`',
                'training_hours' => '`training_hours` int DEFAULT NULL COMMENT \'Ore di formazione richieste\' AFTER `document_url`',
                'training_organization' => '`training_organization` enum(\'interna\',\'OAM\',\'IVASS\',\'PRIVACY\') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Formazione per organizzazione\' AFTER `training_hours`',
                'duration' => '`duration` int DEFAULT NULL COMMENT \'Valore della validità\' AFTER `training_organization`',
                'duration_unit' => '`duration_unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'days\' COMMENT \'Unità di misura: hours, days, months, years\' AFTER `duration`',
                'nature' => '`nature` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT \'incoming\' COMMENT \'Tipo flusso: incoming, template_fillable, compliance\' AFTER `duration_unit`',
                'doctype' => '`doctype` enum(\'modulo\',\'informativa\',\'procedura\',\'template\') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Tipo documento: modulo, procedura, template\' AFTER `nature`',
                'cellposition' => '`cellposition` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Posizione della cella in cui si trova il documento\' AFTER `doctype`',
                'emitted_by' => '`emitted_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Ente di rilascio predefinito\' AFTER `cellposition`',
                'is_sensible' => '`is_sensible` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Contiene dati sensibili\' AFTER `emitted_by`',
                'is_template' => '`is_template` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Forniamo noi il template\' AFTER `is_sensible`',
                'is_stored' => '`is_stored` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Richiede conservazione sostitutiva\' AFTER `is_template`',
                'regex' => '`regex` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Pattern regex per classificazione\' AFTER `is_stored`',
                'is_endMonth' => '`is_endMonth` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Approssima data a fine mese\' AFTER `regex`',
                'is_AiAbstract' => '`is_AiAbstract` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Ask AI to make abstract\' AFTER `is_endMonth`',
                'is_AiCheck' => '`is_AiCheck` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'AI conformity required\' AFTER `is_AiAbstract`',
                'AiPattern' => '`AiPattern` text COLLATE utf8mb4_unicode_ci COMMENT \'How AI can detect document is of this type\' AFTER `is_AiCheck`',
                'min_confidence' => '`min_confidence` tinyint unsigned NOT NULL DEFAULT \'70\' COMMENT \'Soglia minima per suggerire il tipo\' AFTER `AiPattern`',
                'allow_auto_verification' => '`allow_auto_verification` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Valida da solo se confidence alta\' AFTER `min_confidence`',
                'notify_days_before' => '`notify_days_before` json DEFAULT NULL COMMENT \'Es. [30, 15, 5] giorni prima\' AFTER `allow_auto_verification`',
                'retention_years' => '`retention_years` tinyint unsigned DEFAULT NULL COMMENT \'GDPR retention policy (anni)\' AFTER `notify_days_before`',
                'created_by' => '`created_by` bigint unsigned DEFAULT NULL AFTER `retention_years`',
                'updated_by' => '`updated_by` bigint unsigned DEFAULT NULL AFTER `created_by`',
                'deleted_by' => '`deleted_by` bigint unsigned DEFAULT NULL AFTER `updated_by`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `deleted_by`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'deleted_at' => '`deleted_at` timestamp NULL DEFAULT NULL AFTER `updated_at`',
                'is_versioned' => '`is_versioned` tinyint(1) DEFAULT \'0\' COMMENT \'Mantieni lo storico\' AFTER `deleted_at`',
                'document_typable' => '`document_typable` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Alias del modello polimorfo a cui si applica il documento\' AFTER `is_versioned`',
                'trigger_state' => '`trigger_state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\' AFTER `document_typable`',
                'trigger_value' => '`trigger_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\' AFTER `trigger_state`',
                'exclude_field' => '`exclude_field` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Campo del modello da escludere se valorizzato\' AFTER `trigger_value`',
                'exclude_state' => '`exclude_state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'filled, empty, equals\' AFTER `exclude_field`',
                'exclude_value' => '`exclude_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Il valore specifico da controllare\' AFTER `exclude_state`',
                'expire_days_before' => '`expire_days_before` int DEFAULT NULL COMMENT \'Numero di giorni prima della scadenza per inviare la notifica\' AFTER `exclude_value`',
            ],
            'indexes' => [
                'document_types_renewed_by_id_foreign' => 'KEY `document_types_renewed_by_id_foreign` (`renewed_by_id`)',
            ],
            'foreign' => [
                'document_types_renewed_by_id_foreign' => 'CONSTRAINT `document_types_renewed_by_id_foreign` FOREIGN KEY (`renewed_by_id`) REFERENCES `document_types` (`id`) ON DELETE SET NULL',
            ],
        ],
        'documents' => [
            'create' => 'CREATE TABLE `documents` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `documentable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `documentable_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'uploaded\',
  `sync_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'local\',
  `source_app` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'local\',
  `app_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_drive_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_etag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emitted_at` date DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `ai_confidence_score` tinyint unsigned DEFAULT NULL,
  `spatie_collection` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'default\',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `documents_company_id_index` (`company_id`),
  KEY `documents_document_type_id_index` (`document_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `id`',
                'documentable_type' => '`documentable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL AFTER `company_id`',
                'documentable_id' => '`documentable_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL AFTER `documentable_type`',
                'document_type_id' => '`document_type_id` bigint unsigned DEFAULT NULL AFTER `documentable_id`',
                'name' => '`name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `document_type_id`',
                'document_url' => '`document_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `name`',
                'status' => '`status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'uploaded\' AFTER `document_url`',
                'sync_status' => '`sync_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'local\' AFTER `status`',
                'source_app' => '`source_app` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'local\' AFTER `sync_status`',
                'app_id' => '`app_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `source_app`',
                'app_drive_id' => '`app_drive_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `app_id`',
                'app_etag' => '`app_etag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `app_drive_id`',
                'emitted_at' => '`emitted_at` date DEFAULT NULL AFTER `app_etag`',
                'expires_at' => '`expires_at` date DEFAULT NULL AFTER `emitted_at`',
                'metadata' => '`metadata` json DEFAULT NULL AFTER `expires_at`',
                'ai_confidence_score' => '`ai_confidence_score` tinyint unsigned DEFAULT NULL AFTER `metadata`',
                'spatie_collection' => '`spatie_collection` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'default\' AFTER `ai_confidence_score`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `spatie_collection`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'deleted_at' => '`deleted_at` timestamp NULL DEFAULT NULL AFTER `updated_at`',
            ],
            'indexes' => [
                'documents_company_id_index' => 'KEY `documents_company_id_index` (`company_id`)',
                'documents_document_type_id_index' => 'KEY `documents_document_type_id_index` (`document_type_id`)',
            ],
            'foreign' => [
            ],
        ],
        'failed_jobs' => [
            'create' => 'CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'uuid' => '`uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'connection' => '`connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL AFTER `uuid`',
                'queue' => '`queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL AFTER `connection`',
                'payload' => '`payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL AFTER `queue`',
                'exception' => '`exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL AFTER `payload`',
                'failed_at' => '`failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `exception`',
            ],
            'indexes' => [
                'failed_jobs_uuid_unique' => 'UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)',
                'failed_jobs_connection_queue_failed_at_index' => 'KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)',
            ],
            'foreign' => [
            ],
        ],
        'fornitoris' => [
            'create' => 'CREATE TABLE `fornitoris` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'ID univoco del fornitore/agente\',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome del referente\',
  `stipulated_at` date DEFAULT NULL COMMENT \'Data stipula contratto collaborazione\',
  `pec` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Indirizzo Posta Elettronica Certificata\',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Descrizione\',
  `email_private` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supervisor_type` enum(\'no\',\'si\',\'filiale\') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'no\' COMMENT \'Se supervisore indicare e specificare se di filiale\',
  `oam` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Oam\',
  `oam_at` date DEFAULT NULL COMMENT \'Data iscrizione OAM\',
  `oam_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Denominazione sociale registrata in OAM\',
  `numero_iscrizione_rui` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Numero iscrizione OAM\',
  `ivass` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice di iscrizione IVASS\',
  `ivass_at` date DEFAULT NULL COMMENT \'Data iscrizione IVASS\',
  `dismissed_at` date DEFAULT NULL COMMENT \'Data cessazione rapporto\',
  `ivass_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Denominazione IVASS\',
  `ivass_section` enum(\'A\',\'B\',\'C\',\'D\',\'E\') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Sezione IVASS\',
  `type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Agente / Mediatore / Consulente / Call Center\',
  `is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Indica se agente è attualmente convenzionato\',
  `is_art108` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Esente art. 108 - ex art. 128-novies TUB\',
  `company_branch_id` int unsigned DEFAULT NULL COMMENT \'Filiale di riferimento\',
  `coordinated_type` int unsigned DEFAULT NULL COMMENT \'ID del dipendente coordinatore\',
  `coordinated_id` int unsigned DEFAULT NULL COMMENT \'ID dell\'\'agente coordinatore\',
  `user_id` bigint unsigned DEFAULT NULL COMMENT \'ID dell\'\'utente collegato\',
  `oam_dismissed_at` date DEFAULT NULL COMMENT \'Data revoca OAM\',
  `welcome_bonus` decimal(10,2) DEFAULT NULL COMMENT \'Premio benvenuto\',
  `campagna` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice campagna\',
  `available_at` date DEFAULT NULL COMMENT \'Data disponibilità agente\',
  `budget` decimal(10,2) DEFAULT NULL COMMENT \'Budget agente\',
  `codice` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coge` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `natoil` date DEFAULT NULL COMMENT \'Data di nascita\',
  `indirizzo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Indirizzo\',
  `comune` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Comune di residenza\',
  `cap` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice di avviamento postale\',
  `prov` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Provincia\',
  `tel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Numero di telefono\',
  `coordinatore` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome del coordinatore di riferimento\',
  `piva` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Partita IVA\',
  `cf` char(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice fiscale\',
  `nomecoge` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome per la contabilità\',
  `nomefattura` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome da utilizzare in fattura\',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anticipo` decimal(15,2) DEFAULT NULL COMMENT \'Quota fissa mensile erogata come anticipo provvigionale\',
  `enasarco` enum(\'no\',\'monomandatario\',\'plurimandatario\',\'societa\') COLLATE utf8mb4_unicode_ci DEFAULT \'plurimandatario\' COMMENT \'Tipo di mandato ENASARCO\',
  `anticipo_residuo` decimal(15,2) DEFAULT NULL COMMENT \'Debito residuo dell\'\'agente verso il mediatore per anticipi da recuperare\',
  `contributo` decimal(15,2) DEFAULT NULL COMMENT \'Importo del contributo spese\',
  `contributo_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT \'Contributo spese\' COMMENT \'Descrizione del contributo spese\',
  `anticipo_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT \'Anticipo attuale\' COMMENT \'Descrizione dell\'\'anticipo\',
  `issubfornitore` tinyint DEFAULT NULL COMMENT \'1 se l\'\'agente opera tramite un altro intermediario\',
  `operatore` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iscollaboratore` tinyint(1) DEFAULT NULL COMMENT \'Indica se è un collaboratore (1) o meno (0)\',
  `isdipendente` tinyint(1) DEFAULT \'0\' COMMENT \'Indica se è un dipendente (1) o meno (0)\',
  `regione` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `citta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `contributoperiodicita` smallint DEFAULT NULL COMMENT \'Frequenza addebito costi (1=Mensile, 3=Trimestrale, etc.)\',
  `contributodalmese` date DEFAULT NULL,
  `fornitorirole_id` int DEFAULT NULL,
  `branch_id` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fornitoris_piva_index` (`piva`),
  KEY `fornitoris_company_id_index` (`company_id`),
  KEY `fornitoris_fornitorirole_id_index` (`fornitorirole_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\'Anagrafica Agenti di commercio / Collaboratori (Fornitori di servizi)\'',
            'columns' => [
                'id' => '`id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT \'ID univoco del fornitore/agente\' FIRST',
                'name' => '`name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `id`',
                'nome' => '`nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome del referente\' AFTER `name`',
                'stipulated_at' => '`stipulated_at` date DEFAULT NULL COMMENT \'Data stipula contratto collaborazione\' AFTER `nome`',
                'pec' => '`pec` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Indirizzo Posta Elettronica Certificata\' AFTER `stipulated_at`',
                'description' => '`description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Descrizione\' AFTER `pec`',
                'email_private' => '`email_private` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `description`',
                'supervisor_type' => '`supervisor_type` enum(\'no\',\'si\',\'filiale\') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'no\' COMMENT \'Se supervisore indicare e specificare se di filiale\' AFTER `email_private`',
                'oam' => '`oam` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Oam\' AFTER `supervisor_type`',
                'oam_at' => '`oam_at` date DEFAULT NULL COMMENT \'Data iscrizione OAM\' AFTER `oam`',
                'oam_name' => '`oam_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Denominazione sociale registrata in OAM\' AFTER `oam_at`',
                'numero_iscrizione_rui' => '`numero_iscrizione_rui` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Numero iscrizione OAM\' AFTER `oam_name`',
                'ivass' => '`ivass` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice di iscrizione IVASS\' AFTER `numero_iscrizione_rui`',
                'ivass_at' => '`ivass_at` date DEFAULT NULL COMMENT \'Data iscrizione IVASS\' AFTER `ivass`',
                'dismissed_at' => '`dismissed_at` date DEFAULT NULL COMMENT \'Data cessazione rapporto\' AFTER `ivass_at`',
                'ivass_name' => '`ivass_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Denominazione IVASS\' AFTER `dismissed_at`',
                'ivass_section' => '`ivass_section` enum(\'A\',\'B\',\'C\',\'D\',\'E\') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Sezione IVASS\' AFTER `ivass_name`',
                'type' => '`type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Agente / Mediatore / Consulente / Call Center\' AFTER `ivass_section`',
                'is_active' => '`is_active` tinyint(1) NOT NULL DEFAULT \'1\' COMMENT \'Indica se agente è attualmente convenzionato\' AFTER `type`',
                'is_art108' => '`is_art108` tinyint(1) NOT NULL DEFAULT \'0\' COMMENT \'Esente art. 108 - ex art. 128-novies TUB\' AFTER `is_active`',
                'company_branch_id' => '`company_branch_id` int unsigned DEFAULT NULL COMMENT \'Filiale di riferimento\' AFTER `is_art108`',
                'coordinated_type' => '`coordinated_type` int unsigned DEFAULT NULL COMMENT \'ID del dipendente coordinatore\' AFTER `company_branch_id`',
                'coordinated_id' => '`coordinated_id` int unsigned DEFAULT NULL COMMENT \'ID dell\'\'agente coordinatore\' AFTER `coordinated_type`',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL COMMENT \'ID dell\'\'utente collegato\' AFTER `coordinated_id`',
                'oam_dismissed_at' => '`oam_dismissed_at` date DEFAULT NULL COMMENT \'Data revoca OAM\' AFTER `user_id`',
                'welcome_bonus' => '`welcome_bonus` decimal(10,2) DEFAULT NULL COMMENT \'Premio benvenuto\' AFTER `oam_dismissed_at`',
                'campagna' => '`campagna` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice campagna\' AFTER `welcome_bonus`',
                'available_at' => '`available_at` date DEFAULT NULL COMMENT \'Data disponibilità agente\' AFTER `campagna`',
                'budget' => '`budget` decimal(10,2) DEFAULT NULL COMMENT \'Budget agente\' AFTER `available_at`',
                'codice' => '`codice` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `budget`',
                'coge' => '`coge` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `codice`',
                'natoil' => '`natoil` date DEFAULT NULL COMMENT \'Data di nascita\' AFTER `coge`',
                'indirizzo' => '`indirizzo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Indirizzo\' AFTER `natoil`',
                'comune' => '`comune` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Comune di residenza\' AFTER `indirizzo`',
                'cap' => '`cap` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice di avviamento postale\' AFTER `comune`',
                'prov' => '`prov` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Provincia\' AFTER `cap`',
                'tel' => '`tel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Numero di telefono\' AFTER `prov`',
                'coordinatore' => '`coordinatore` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome del coordinatore di riferimento\' AFTER `tel`',
                'piva' => '`piva` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Partita IVA\' AFTER `coordinatore`',
                'cf' => '`cf` char(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Codice fiscale\' AFTER `piva`',
                'nomecoge' => '`nomecoge` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome per la contabilità\' AFTER `cf`',
                'nomefattura' => '`nomefattura` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT \'Nome da utilizzare in fattura\' AFTER `nomecoge`',
                'email' => '`email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `nomefattura`',
                'anticipo' => '`anticipo` decimal(15,2) DEFAULT NULL COMMENT \'Quota fissa mensile erogata come anticipo provvigionale\' AFTER `email`',
                'enasarco' => '`enasarco` enum(\'no\',\'monomandatario\',\'plurimandatario\',\'societa\') COLLATE utf8mb4_unicode_ci DEFAULT \'plurimandatario\' COMMENT \'Tipo di mandato ENASARCO\' AFTER `anticipo`',
                'anticipo_residuo' => '`anticipo_residuo` decimal(15,2) DEFAULT NULL COMMENT \'Debito residuo dell\'\'agente verso il mediatore per anticipi da recuperare\' AFTER `enasarco`',
                'contributo' => '`contributo` decimal(15,2) DEFAULT NULL COMMENT \'Importo del contributo spese\' AFTER `anticipo_residuo`',
                'contributo_description' => '`contributo_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT \'Contributo spese\' COMMENT \'Descrizione del contributo spese\' AFTER `contributo`',
                'anticipo_description' => '`anticipo_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT \'Anticipo attuale\' COMMENT \'Descrizione dell\'\'anticipo\' AFTER `contributo_description`',
                'issubfornitore' => '`issubfornitore` tinyint DEFAULT NULL COMMENT \'1 se l\'\'agente opera tramite un altro intermediario\' AFTER `anticipo_description`',
                'operatore' => '`operatore` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `issubfornitore`',
                'iscollaboratore' => '`iscollaboratore` tinyint(1) DEFAULT NULL COMMENT \'Indica se è un collaboratore (1) o meno (0)\' AFTER `operatore`',
                'isdipendente' => '`isdipendente` tinyint(1) DEFAULT \'0\' COMMENT \'Indica se è un dipendente (1) o meno (0)\' AFTER `iscollaboratore`',
                'regione' => '`regione` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `isdipendente`',
                'citta' => '`citta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `regione`',
                'deleted_at' => '`deleted_at` timestamp NULL DEFAULT NULL AFTER `citta`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `deleted_at`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
                'company_id' => '`company_id` bigint unsigned DEFAULT NULL AFTER `updated_at`',
                'contributoperiodicita' => '`contributoperiodicita` smallint DEFAULT NULL COMMENT \'Frequenza addebito costi (1=Mensile, 3=Trimestrale, etc.)\' AFTER `company_id`',
                'contributodalmese' => '`contributodalmese` date DEFAULT NULL AFTER `contributoperiodicita`',
                'fornitorirole_id' => '`fornitorirole_id` int DEFAULT NULL AFTER `contributodalmese`',
                'branch_id' => '`branch_id` bigint DEFAULT NULL AFTER `fornitorirole_id`',
            ],
            'indexes' => [
                'fornitoris_piva_index' => 'KEY `fornitoris_piva_index` (`piva`)',
                'fornitoris_company_id_index' => 'KEY `fornitoris_company_id_index` (`company_id`)',
                'fornitoris_fornitorirole_id_index' => 'KEY `fornitoris_fornitorirole_id_index` (`fornitorirole_id`)',
            ],
            'foreign' => [
            ],
        ],
        'job_batches' => [
            'create' => 'CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'name' => '`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'total_jobs' => '`total_jobs` int NOT NULL AFTER `name`',
                'pending_jobs' => '`pending_jobs` int NOT NULL AFTER `total_jobs`',
                'failed_jobs' => '`failed_jobs` int NOT NULL AFTER `pending_jobs`',
                'failed_job_ids' => '`failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL AFTER `failed_jobs`',
                'options' => '`options` mediumtext COLLATE utf8mb4_unicode_ci AFTER `failed_job_ids`',
                'cancelled_at' => '`cancelled_at` int DEFAULT NULL AFTER `options`',
                'created_at' => '`created_at` int NOT NULL AFTER `cancelled_at`',
                'finished_at' => '`finished_at` int DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'jobs' => [
            'create' => 'CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'queue' => '`queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'payload' => '`payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL AFTER `queue`',
                'attempts' => '`attempts` smallint unsigned NOT NULL AFTER `payload`',
                'reserved_at' => '`reserved_at` int unsigned DEFAULT NULL AFTER `attempts`',
                'available_at' => '`available_at` int unsigned NOT NULL AFTER `reserved_at`',
                'created_at' => '`created_at` int unsigned NOT NULL AFTER `available_at`',
            ],
            'indexes' => [
                'jobs_queue_index' => 'KEY `jobs_queue_index` (`queue`)',
            ],
            'foreign' => [
            ],
        ],
        'password_reset_tokens' => [
            'create' => 'CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'email' => '`email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'token' => '`token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL AFTER `email`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `token`',
            ],
            'indexes' => [
            ],
            'foreign' => [
            ],
        ],
        'sessions' => [
            'create' => 'CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL FIRST',
                'user_id' => '`user_id` bigint unsigned DEFAULT NULL AFTER `id`',
                'ip_address' => '`ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `user_id`',
                'user_agent' => '`user_agent` text COLLATE utf8mb4_unicode_ci AFTER `ip_address`',
                'payload' => '`payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL AFTER `user_agent`',
                'last_activity' => '`last_activity` int NOT NULL AFTER `payload`',
            ],
            'indexes' => [
                'sessions_user_id_index' => 'KEY `sessions_user_id_index` (`user_id`)',
                'sessions_last_activity_index' => 'KEY `sessions_last_activity_index` (`last_activity`)',
            ],
            'foreign' => [
            ],
        ],
        'users' => [
            'create' => 'CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'columns' => [
                'id' => '`id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST',
                'name' => '`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL AFTER `id`',
                'email' => '`email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL AFTER `name`',
                'email_verified_at' => '`email_verified_at` timestamp NULL DEFAULT NULL AFTER `email`',
                'password' => '`password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL AFTER `email_verified_at`',
                'remember_token' => '`remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `password`',
                'created_at' => '`created_at` timestamp NULL DEFAULT NULL AFTER `remember_token`',
                'updated_at' => '`updated_at` timestamp NULL DEFAULT NULL AFTER `created_at`',
            ],
            'indexes' => [
                'users_email_unique' => 'UNIQUE KEY `users_email_unique` (`email`)',
            ],
            'foreign' => [
            ],
        ],
        ];
    }
};
