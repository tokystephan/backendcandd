<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insérer les rôles par défaut
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Admin', 'slug' => 'admin', 'description' => 'Accès total', 'created_at' => now()],
            ['id' => 2, 'name' => 'Assistant RH', 'slug' => 'assistant', 'description' => 'Gestion opérationnelle', 'created_at' => now()],
            ['id' => 3, 'name' => 'Consultant', 'slug' => 'consultant', 'description' => 'Consultation par département', 'created_at' => now()],
            ['id' => 4, 'name' => 'Manager', 'slug' => 'manager', 'description' => 'Manager avec évaluation', 'created_at' => now()],
            ['id' => 5, 'name' => 'Direction', 'slug' => 'direction', 'description' => 'Lecture seule', 'created_at' => now()],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('roles');
    }
};