<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAssignedToToApplicationsTable extends Migration
{
    public function up()
    {
        Schema::table('applications', function (Blueprint $table) {
            if (!Schema::hasColumn('applications', 'assigned_to')) {
                $table->string('assigned_to')->nullable()->after('created_by');
                $table->index('assigned_to');
            }
        });
    }

    public function down()
    {
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'assigned_to')) {
                $table->dropIndex(['assigned_to']);
                $table->dropColumn('assigned_to');
            }
        });
    }
}
