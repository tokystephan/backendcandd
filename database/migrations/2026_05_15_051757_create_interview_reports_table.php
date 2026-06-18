<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInterviewReportsTable extends Migration
{
    public function up()
    {
        Schema::create('interview_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->text('evaluation_notes');
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->text('next_steps')->nullable();
            $table->text('recommendation')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('interview_reports');
    }
}