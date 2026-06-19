<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $managerRole = DB::table('roles')->where('slug', 'manager')->first();

        if (!$managerRole) {
            DB::table('roles')->updateOrInsert(
                ['id' => 4],
                [
                    'name' => 'Manager',
                    'slug' => 'manager',
                    'description' => 'Manager avec évaluation',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $managerRole = DB::table('roles')->where('slug', 'manager')->first();
        }

        $consultantRole = DB::table('roles')->where('slug', 'consultant')->first();

        if ($consultantRole && $managerRole) {
            DB::table('users')
                ->where('role_id', $consultantRole->id)
                ->update(['role_id' => $managerRole->id]);

            DB::table('roles')->where('id', $consultantRole->id)->delete();
        }
    }

    public function down(): void
    {
        DB::table('roles')->updateOrInsert(
            ['id' => 3],
            [
                'name' => 'Consultant',
                'slug' => 'consultant',
                'description' => 'Consultation par département',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};
