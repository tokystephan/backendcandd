<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeletedAtToPostsTable extends Migration
{
    public function up()
    {
        Schema::hasTable('posts') && Schema::table('posts', function (Blueprint $table) {
            // ajoute la colonne deleted_at
            $table->softDeletes();
        });
    }


    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}