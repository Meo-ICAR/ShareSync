<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->comment('Anagrafica delle tipologie documentali con regole di validazione, AI e GDPR');

            $table->id()->comment('ID intero autoincrementante');
            $table->string('name')->nullable()->comment('Nome documento');
            $table->string('description')->nullable()->comment('Descrizione aggiuntiva');
            $table->string('code')->nullable()->comment('Codice univoco mnemonico');
            $table->string('codegroup')->nullable()->comment('Raggruppa documenti simili');
            $table->string('slug')->nullable()->comment('Slug univoco per URL');
            $table->string('regex_pattern')->nullable()->comment('Pattern di validazione');
            $table->integer('priority')->default(0)->comment('Priorità di ordinamento');
            $table->string('phase')->nullable()->comment('Fase di processo');
            $table->boolean('is_person')->default(true)->comment('Documento inerente Persona');
            $table->boolean('is_company')->default(false)->comment('Documento inerente Azienda');
            $table->boolean('is_employee')->default(false)->comment('Richiesto ai dipendenti');
            $table->boolean('is_agent')->default(false)->comment('Richiesto agli agenti');
            $table->boolean('is_principal')->default(false)->comment('Richiesto alle mandanti');
            $table->boolean('is_client')->default(false)->comment('Richiesto ai clienti');
            $table->boolean('is_practice')->default(false)->comment('Legato a una pratica');
            $table->string('trigger_field')->nullable()->comment('Campo del modello da controllare');
            $table->boolean('is_signed')->default(false)->comment('Deve essere firmato');
            $table->boolean('is_monitored')->default(false)->comment('Scadenza monitorata nel tempo');
            $table->foreignId('renewed_by_id')->nullable()->comment('ID del documento che lo rinnova')->constrained('document_types')->nullOnDelete();
            $table->string('document_url')->nullable()->comment('URL pubblico o percorso del documento sul web/storage');
            $table->integer('training_hours')->nullable()->comment('Ore di formazione richieste');
            $table->enum('training_organization', ['interna', 'OAM', 'IVASS', 'PRIVACY'])->nullable()->comment('Formazione per organizzazione');
            $table->integer('duration')->nullable()->comment('Valore della validità');
            $table->string('duration_unit')->default('days')->comment('Unità di misura: hours, days, months, years');
            $table->string('nature')->nullable()->default('incoming')->comment('Tipo flusso: incoming, template_fillable, compliance');
            $table->enum('doctype', ['modulo', 'informativa', 'procedura', 'template'])->nullable()->comment('Tipo documento: modulo, procedura, template');
            $table->string('cellposition')->nullable()->comment('Posizione della cella in cui si trova il documento');
            $table->string('emitted_by')->nullable()->comment('Ente di rilascio predefinito');
            $table->boolean('is_sensible')->default(false)->comment('Contiene dati sensibili');
            $table->boolean('is_template')->default(false)->comment('Forniamo noi il template');
            $table->boolean('is_stored')->default(false)->comment('Richiede conservazione sostitutiva');
            $table->string('regex')->nullable()->comment('Pattern regex per classificazione');
            $table->boolean('is_endMonth')->default(false)->comment('Approssima data a fine mese');
            $table->boolean('is_AiAbstract')->default(false)->comment('Ask AI to make abstract');
            $table->boolean('is_AiCheck')->default(false)->comment('AI conformity required');
            $table->text('AiPattern')->nullable()->comment('How AI can detect document is of this type');
            $table->unsignedTinyInteger('min_confidence')->default(70)->comment('Soglia minima per suggerire il tipo');
            $table->boolean('allow_auto_verification')->default(false)->comment('Valida da solo se confidence alta');
            $table->json('notify_days_before')->nullable()->comment('Es. [30, 15, 5] giorni prima');
            $table->unsignedTinyInteger('retention_years')->nullable()->comment('GDPR retention policy (anni)');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('is_versioned')->nullable()->default(false)->comment('Mantieni lo storico');
            $table->string('document_typable')->nullable()->comment('Alias del modello polimorfo a cui si applica il documento');
            $table->string('trigger_state')->nullable()->comment('filled, empty, equals');
            $table->string('trigger_value')->nullable()->comment('Il valore specifico da controllare');
            $table->string('exclude_field')->nullable()->comment('Campo del modello da escludere se valorizzato');
            $table->string('exclude_state')->nullable()->comment('filled, empty, equals');
            $table->string('exclude_value')->nullable()->comment('Il valore specifico da controllare');
            $table->integer('expire_days_before')->nullable()->comment('Numero di giorni prima della scadenza per inviare la notifica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
