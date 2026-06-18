<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_status_history', function (Blueprint $table) {
            if (!Schema::hasColumn('application_status_history', 'application_id')) {
                $table->foreignId('application_id')->nullable()->after('id')->constrained('applications')->nullOnDelete();
            }
            if (!Schema::hasColumn('application_status_history', 'status_id')) {
                $table->foreignId('status_id')->nullable()->after('application_id')->constrained('statuses')->nullOnDelete();
            }
            if (!Schema::hasColumn('application_status_history', 'status')) {
                $table->string('status')->nullable()->after('status_id');
            }
            if (!Schema::hasColumn('application_status_history', 'changed_by')) {
                $table->foreignId('changed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('application_status_history', 'changed_by_name')) {
                $table->string('changed_by_name')->nullable()->after('changed_by');
            }
            if (!Schema::hasColumn('application_status_history', 'note')) {
                $table->text('note')->nullable()->after('changed_by_name');
            }
            if (!Schema::hasColumn('application_status_history', 'notes')) {
                $table->text('notes')->nullable()->after('note');
            }
        });

        Schema::table('application_comments', function (Blueprint $table) {
            if (!Schema::hasColumn('application_comments', 'application_id')) {
                $table->foreignId('application_id')->nullable()->after('id')->constrained('applications')->nullOnDelete();
            }
            if (!Schema::hasColumn('application_comments', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('application_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('application_comments', 'author')) {
                $table->string('author')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('application_comments', 'content')) {
                $table->text('content')->nullable()->after('author');
            }
        });
    }

    public function down(): void
    {
        Schema::table('application_comments', function (Blueprint $table) {
            foreach (['content', 'author'] as $column) {
                if (Schema::hasColumn('application_comments', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('application_comments', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
            if (Schema::hasColumn('application_comments', 'application_id')) {
                $table->dropConstrainedForeignId('application_id');
            }
        });

        Schema::table('application_status_history', function (Blueprint $table) {
            foreach (['notes', 'note', 'changed_by_name', 'status'] as $column) {
                if (Schema::hasColumn('application_status_history', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('application_status_history', 'changed_by')) {
                $table->dropConstrainedForeignId('changed_by');
            }
            if (Schema::hasColumn('application_status_history', 'status_id')) {
                $table->dropConstrainedForeignId('status_id');
            }
            if (Schema::hasColumn('application_status_history', 'application_id')) {
                $table->dropConstrainedForeignId('application_id');
            }
        });
    }
};
