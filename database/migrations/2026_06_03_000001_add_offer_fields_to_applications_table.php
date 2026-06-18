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
        Schema::table('applications', function (Blueprint $table) {
            if (!Schema::hasColumn('applications', 'offer_proposed')) {
                $table->boolean('offer_proposed')->default(false)->after('internal_note');
            }
            if (!Schema::hasColumn('applications', 'offer_salary')) {
                $table->decimal('offer_salary', 12, 2)->nullable()->after('offer_proposed');
            }
            if (!Schema::hasColumn('applications', 'offer_expires_at')) {
                $table->timestamp('offer_expires_at')->nullable()->after('offer_salary');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'offer_expires_at')) {
                $table->dropColumn('offer_expires_at');
            }
            if (Schema::hasColumn('applications', 'offer_salary')) {
                $table->dropColumn('offer_salary');
            }
            if (Schema::hasColumn('applications', 'offer_proposed')) {
                $table->dropColumn('offer_proposed');
            }
        });
    }
};
