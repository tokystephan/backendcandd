<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostSkillsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('post_skills', function (Blueprint $table) {
            // id INT PRIMARY KEY AUTO_INCREMENT
            $table->id(); 

            // post_id column; FK will be added later (after posts table exists)
            $table->unsignedBigInteger('post_id');
            $table->index('post_id');


            // skill_name VARCHAR(100) NOT NULL
            $table->string('skill_name', 100);

            // is_required BOOLEAN DEFAULT TRUE
            $table->boolean('is_required')->default(true);

            // level VARCHAR(20) DEFAULT 'intermediaire'
            $table->string('level', 20)->default('intermediaire');

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
        Schema::dropIfExists('post_skills');
    }
}
