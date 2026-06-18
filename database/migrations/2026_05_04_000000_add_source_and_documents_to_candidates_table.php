<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSourceAndDocumentsToCandidatesTable extends Migration
{
    public function up()
    {
        Schema::table('candidates', function (Blueprint $table) {
            if (!Schema::hasColumn('candidates', 'source')) {
                $table->string('source', 100)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('candidates', 'documents')) {
                $table->json('documents')->nullable()->after('motivation_letter_path');
            }
        });
    }

    public function down()
    {
        Schema::table('candidates', function (Blueprint $table) {
            if (Schema::hasColumn('candidates', 'documents')) {
                $table->dropColumn('documents');
            }
            if (Schema::hasColumn('candidates', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
}
