<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRemindersTable extends Migration
{
    public function up()
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('reminder_type', ['email', 'notification', 'sms']);
            $table->integer('reminder_time_minutes');
            $table->text('reminder_content')->nullable();
            $table->boolean('is_sent')->default(false);
            $table->dateTime('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['is_sent', 'reminder_time_minutes']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('reminders');
    }
}