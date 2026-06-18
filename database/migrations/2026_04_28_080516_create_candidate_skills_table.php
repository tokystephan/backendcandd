<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCandidateSkillsTable extends Migration
{
    public function up()
    {
        Schema::create('candidate_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->onDelete('cascade');
            $table->string('skill_name', 100);
            $table->decimal('experience_years', 3, 1)->default(0);
            $table->enum('level', ['debutant', 'intermediaire', 'avance', 'expert'])->default('intermediaire');
            $table->timestamps();
        });
    } 

    public function down()
    {
        Schema::dropIfExists('candidate_skills');
    }
}