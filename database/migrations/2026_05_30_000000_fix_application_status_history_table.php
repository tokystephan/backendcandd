<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixApplicationStatusHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('application_status_history', function (Blueprint $table) {
            // Check if columns don't already exist before adding
            if (!Schema::hasColumn('application_status_history', 'application_id')) {
                $table->unsignedBigInteger('application_id')->nullable()->after('id');
                $table->foreign('application_id')
                    ->references('id')
                    ->on('applications')
                    ->onDelete('cascade');
            }

            if (!Schema::hasColumn('application_status_history', 'status_id')) {
                $table->unsignedBigInteger('status_id')->nullable()->after('application_id');
                $table->foreign('status_id')
                    ->references('id')
                    ->on('statuts')
                    ->onDelete('set null');
            }

            if (!Schema::hasColumn('application_status_history', 'status')) {
                
                $table->string('status')->nullable()->after('status_id');
            }

            if (!Schema::hasColumn('application_status_history', 'changed_by')) {
                $table->unsignedBigInteger('changed_by')->nullable()->after('status');
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
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('application_status_history', function (Blueprint $table) {
            // Drop foreign keys first
            if (Schema::hasColumn('application_status_history', 'application_id')) {
                $table->dropForeign(['application_id']);
                $table->dropColumn('application_id');
            }

            if (Schema::hasColumn('application_status_history', 'status_id')) {
                $table->dropForeign(['status_id']);
                $table->dropColumn('status_id');
            }

            $columns = ['status', 'changed_by', 'changed_by_name', 'note', 'notes'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('application_status_history', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
