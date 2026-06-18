<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventParticipantsTable extends Migration
{
    public function up()
    {
        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained();
            $table->boolean('is_organizer')->default(false);
            $table->string('role_in_interview')->nullable();
            $table->enum('invitation_status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->text('response_comment')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->boolean('actual_attendance')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_participants');
    }
}