<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La tabella esiste già nel DB di sviluppo con più colonne: qui si crea
        // solo il sottoinsieme usato, e solo se manca (es. test su sqlite).
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
            $table->date('emitted_at')->nullable();
            $table->date('expires_at')->nullable();
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
