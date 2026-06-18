<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAvailabilityTable extends Migration
{
    public function up()
    {
        Schema::create('availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('availability_type', ['recurrente', 'exceptionnelle', 'indisponible'])->default('recurrente');
            $table->tinyInteger('day_of_week')->nullable()->comment('1=Lundi, 7=Dimanche');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('specific_date')->nullable();
            $table->time('specific_start')->nullable();
            $table->time('specific_end')->nullable();
            $table->dateTime('unavailable_start')->nullable();
            $table->dateTime('unavailable_end')->nullable();
            $table->string('unavailable_reason')->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'valid_from', 'valid_until']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('availability');
    }
}