<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventsTable extends Migration
{
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->foreignId('candidate_id')->constrained()->onDelete('cascade');
            $table->enum('event_type', ['telephonique', 'rh', 'technique', 'metier', 'final', 'comite', 'autre']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_end')->nullable();
            $table->enum('location_type', ['presentiel', 'visio', 'telephone'])->default('presentiel');
            $table->string('location')->nullable();
            $table->string('meeting_link', 500)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->enum('status', ['planifie', 'confirme', 'annule', 'reporte', 'termine'])->default('planifie');
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('rescheduled_from')->nullable()->constrained('events');
            $table->foreignId('created_by')->constrained('users');
            $table->onDelete('cascade');
            $table->timestamps();

            $table->index(['start_datetime', 'end_datetime']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('events');
    }
}