<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepartmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up()
    {
        Schema::create('departments', function (Blueprint $table) {
            // id INT PRIMARY KEY AUTO_INCREMENT
            $table->id(); 

            // name VARCHAR(100) NOT NULL UNIQUE
            $table->string('name', 100)->unique();

            
            // On utilise foreignId pour correspondre au type BigInt par défaut de Laravel
            $table->foreignId('manager_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            // description TEXT
            $table->text('description')->nullable();

            // created_at & updated_at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('departments');
    }
}
