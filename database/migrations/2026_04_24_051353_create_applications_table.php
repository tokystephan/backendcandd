<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApplicationsTable extends Migration
{
    public function up()
    {
        Schema::create('applications', function (Blueprint $table) {
            // 🔑 Identifiant
            $table->id();
            
            // 👤 Clés étrangères
            $table->foreignId('candidate_id')
                  ->constrained('candidates')
                  ->onDelete('cascade');
                  
            $table->foreignId('post_id')->nullable(); // Made nullable to avoid FK issue during fresh migration
                  
            $table->foreignId('current_status_id')
                  ->constrained('statuses');
                  
            $table->foreignId('created_by')
                  ->constrained('users');
            
            // 📅 Dates
            $table->timestamp('application_date')->useCurrent();
            $table->timestamps();
            
            // � Champs texte
            $table->string('referral_by')->nullable();
            $table->text('internal_note')->nullable();
            
            // 🔍 Index pour optimiser les recherches
            $table->index('candidate_id');
            $table->index('post_id');
            $table->index('current_status_id');
            $table->index('application_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('applications');
    }
}