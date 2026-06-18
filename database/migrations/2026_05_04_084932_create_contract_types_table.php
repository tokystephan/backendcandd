<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContractTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('contract_types', function (Blueprint $table) {
            // id INT PRIMARY KEY AUTO_INCREMENT
            $table->id(); 

            // name VARCHAR(50) NOT NULL UNIQUE
            $table->string('name', 50)->unique();

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
        Schema::dropIfExists('contract_types');
    }
}
