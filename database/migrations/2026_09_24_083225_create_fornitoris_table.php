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
        Schema::create('fornitoris', function (Blueprint $table) {
            $table->comment('Anagrafica Agenti di commercio / Collaboratori (Fornitori di servizi)');

            $table->uuid('id')->primary()->comment('ID univoco del fornitore/agente');
            $table->string('name')->nullable();
            $table->string('nome')->nullable()->comment('Nome del referente');
            $table->date('stipulated_at')->nullable()->comment('Data stipula contratto collaborazione');
            $table->string('pec')->nullable()->comment('Indirizzo Posta Elettronica Certificata');
            $table->string('description')->nullable()->comment('Descrizione');
            $table->string('email_private')->nullable();
            $table->enum('supervisor_type', ['no', 'si', 'filiale'])->default('no')->comment('Se supervisore indicare e specificare se di filiale');
            $table->string('oam', 30)->nullable()->comment('Oam');
            $table->date('oam_at')->nullable()->comment('Data iscrizione OAM');
            $table->string('oam_name')->nullable()->comment('Denominazione sociale registrata in OAM');
            $table->string('numero_iscrizione_rui', 50)->nullable()->comment('Numero iscrizione OAM');
            $table->string('ivass', 30)->nullable()->comment('Codice di iscrizione IVASS');
            $table->date('ivass_at')->nullable()->comment('Data iscrizione IVASS');
            $table->date('dismissed_at')->nullable()->comment('Data cessazione rapporto');
            $table->string('ivass_name')->nullable()->comment('Denominazione IVASS');
            $table->enum('ivass_section', ['A', 'B', 'C', 'D', 'E'])->nullable()->comment('Sezione IVASS');
            $table->string('type', 30)->nullable()->comment('Agente / Mediatore / Consulente / Call Center');
            $table->boolean('is_active')->default(true)->comment('Indica se agente è attualmente convenzionato');
            $table->boolean('is_art108')->default(false)->comment('Esente art. 108 - ex art. 128-novies TUB');
            $table->unsignedInteger('company_branch_id')->nullable()->comment('Filiale di riferimento');
            $table->unsignedInteger('coordinated_type')->nullable()->comment('ID del dipendente coordinatore');
            $table->unsignedInteger('coordinated_id')->nullable()->comment('ID dell\'agente coordinatore');
            $table->unsignedBigInteger('user_id')->nullable()->comment('ID dell\'utente collegato');
            $table->date('oam_dismissed_at')->nullable()->comment('Data revoca OAM');
            $table->decimal('welcome_bonus', 10, 2)->nullable()->comment('Premio benvenuto');
            $table->string('campagna')->nullable()->comment('Codice campagna');
            $table->date('available_at')->nullable()->comment('Data disponibilità agente');
            $table->decimal('budget', 10, 2)->nullable()->comment('Budget agente');
            $table->string('codice')->nullable();
            $table->string('coge')->nullable();
            $table->date('natoil')->nullable()->comment('Data di nascita');
            $table->string('indirizzo')->nullable()->comment('Indirizzo');
            $table->string('comune')->nullable()->comment('Comune di residenza');
            $table->string('cap')->nullable()->comment('Codice di avviamento postale');
            $table->string('prov')->nullable()->comment('Provincia');
            $table->string('tel')->nullable()->comment('Numero di telefono');
            $table->string('coordinatore')->nullable()->comment('Nome del coordinatore di riferimento');
            $table->string('piva', 20)->nullable()->index()->comment('Partita IVA');
            $table->char('cf', 16)->nullable()->comment('Codice fiscale');
            $table->string('nomecoge')->nullable()->comment('Nome per la contabilità');
            $table->string('nomefattura')->nullable()->comment('Nome da utilizzare in fattura');
            $table->string('email')->nullable();
            $table->decimal('anticipo', 15, 2)->nullable()->comment('Quota fissa mensile erogata come anticipo provvigionale');
            $table->enum('enasarco', ['no', 'monomandatario', 'plurimandatario', 'societa'])->nullable()->default('plurimandatario')->comment('Tipo di mandato ENASARCO');
            $table->decimal('anticipo_residuo', 15, 2)->nullable()->comment('Debito residuo dell\'agente verso il mediatore per anticipi da recuperare');
            $table->decimal('contributo', 15, 2)->nullable()->comment('Importo del contributo spese');
            $table->string('contributo_description')->nullable()->default('Contributo spese')->comment('Descrizione del contributo spese');
            $table->string('anticipo_description')->nullable()->default('Anticipo attuale')->comment('Descrizione dell\'anticipo');
            $table->tinyInteger('issubfornitore')->nullable()->comment('1 se l\'agente opera tramite un altro intermediario');
            $table->string('operatore')->nullable();
            $table->boolean('iscollaboratore')->nullable()->comment('Indica se è un collaboratore (1) o meno (0)');
            $table->boolean('isdipendente')->nullable()->default(false)->comment('Indica se è un dipendente (1) o meno (0)');
            $table->string('regione')->nullable();
            $table->string('citta')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->char('company_id', 36)->nullable()->default('5c044917-15b3-4471-90c9-38061fcca754')->index();
            $table->smallInteger('contributoperiodicita')->nullable()->comment('Frequenza addebito costi (1=Mensile, 3=Trimestrale, etc.)');
            $table->date('contributodalmese')->nullable();
            $table->integer('fornitorirole_id')->nullable()->index();
            $table->bigInteger('branch_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fornitoris');
    }
};
