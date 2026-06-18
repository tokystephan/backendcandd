<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEvaluationsTable extends Migration
{
    public function up()
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('technical')->default(0);
            $table->integer('communication')->default(0);
            $table->integer('motivation')->default(0);
            $table->integer('culture')->default(0);
            $table->enum('recommendation', ['favorable', 'reserve', 'defavorable'])->default('reserve');
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            
            // Un consultant ne peut évaluer qu'une fois par candidature
            $table->unique(['application_id', 'user_id']);
            
            // Index pour les performances
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('evaluations');
    }
}