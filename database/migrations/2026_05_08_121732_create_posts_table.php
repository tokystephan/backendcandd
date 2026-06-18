<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            // Clé primaire
            $table->id();
            
            // Colonnes principales
            $table->string('title');                    // Intitulé du poste
            $table->text('description')->nullable();    // Description du poste
            $table->text('requirements')->nullable();   // Prérequis / compétences requises
            
            // Clés étrangères
            // Note: constrained() suppose que la table existe au moment de la migration.
            // Pour éviter les erreurs lors d'un "fresh" partiel, on crée les contraintes explicitement.
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('contract_type_id')->constrained('contract_types')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            
            // Statut du poste
            $table->enum('status', ['ouvert', 'ferme', 'en_attente'])->default('ouvert');
            
            // Dates
            $table->timestamp('closed_at')->nullable();   // Date de fermeture du poste
            $table->boolean('is_archived')->default(false); // Archivé ou non
            
            $table->timestamps();      // created_at, updated_at
            $table->softDeletes();     // deleted_at (soft delete)
        });
    }

    /**
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('posts');
    }
}
