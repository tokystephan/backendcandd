<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statistics', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('stat_type', 50);
            $table->decimal('stat_value', 15, 2);
            $table->json('context')->nullable();
            $table->timestamp('calculated_at')->useCurrent();

            $table->index(['stat_date', 'stat_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistics');
    }
};