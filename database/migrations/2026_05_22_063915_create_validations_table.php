<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->nullable()->constrained('posts')->onDelete('cascade');
            $table->foreignId('application_id')->nullable()->constrained('applications')->onDelete('cascade');
            $table->foreignId('validator_id')->constrained('users');
            $table->enum('validation_type', ['poste', 'annonce', 'offre', 'candidature']);
            $table->enum('status', ['en_attente', 'valide', 'refuse'])->default('en_attente');
            $table->text('comment')->nullable();
            $table->dateTime('validated_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Application de la contrainte d'exclusion (Soit l'un, soit l'autre, pas les deux)
        DB::statement('ALTER TABLE validations ADD CONSTRAINT check_post_or_application 
            CHECK ((post_id IS NOT NULL AND application_id IS NULL) OR (post_id IS NULL AND application_id IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('validations');
    }
};