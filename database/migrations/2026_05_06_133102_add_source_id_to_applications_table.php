<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSourceIdToApplicationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
 public function up(): void
{
    Schema::table('applications', function (Blueprint $table) {
        // ✅ Ajout de la colonne source_id (nullable)
        $table->foreignId('source_id')
              ->nullable()                    // ← Accepte NULL (bonne pratique)
              ->after('id')                   // ← Place la colonne après 'id'
              ->constrained('sources')        // ← Clé étrangère vers table 'sources'
              ->onDelete('cascade');          // ← Si source supprimée, candidature aussi
    });
}

public function down(): void
{
    Schema::table('applications', function (Blueprint $table) {
        $table->dropForeign(['source_id']);   // ← Supprime d'abord la contrainte
        $table->dropColumn('source_id');      // ← Puis supprime la colonne
    });
}
}
