<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            
            // Foreign key vers users
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            
            // Contenu de la notification
            $table->string('message');
            $table->string('type')->default('info'); // info, success, warning, error
            $table->longText('data')->nullable(); // JSON data
            
            // État de lecture
            $table->boolean('is_read')->default(false);
            
            // Timestamps
            $table->timestamps();
            
            // Index pour améliorer les performances
            $table->index('user_id');
            $table->index('is_read');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
