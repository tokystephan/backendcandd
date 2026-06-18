<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeSourceIdNullableInApplicationsTable extends Migration
{
    public function up()
    {
        Schema::table('applications', function (Blueprint $table) {
            // ✅ Rendre source_id nullable
            $table->foreignId('source_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('applications', function (Blueprint $table) {
            // ⚠️ Annuler (si besoin)
            $table->foreignId('source_id')->nullable(false)->change();
        });
    }
}