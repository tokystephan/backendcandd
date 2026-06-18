<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table des étiquettes (Tags)
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('color', 20)->default('#3498db');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });

        // Table Pivot reliant les Tags aux Candidatures (Applications)
        Schema::create('application_tags', function (Blueprint $table) {
            $table->foreignId('application_id')->constrained('applications')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['application_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_tags');
        Schema::dropIfExists('tags');
    }
};