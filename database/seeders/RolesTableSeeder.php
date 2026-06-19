<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RolesTableSeeder extends Seeder
{
    public function run()
    {
        Schema::disableForeignKeyConstraints();
        DB::table('roles')->truncate();
        Schema::enableForeignKeyConstraints();

        $roles = [
            ['id' => 1, 'name' => 'Admin', 'slug' => 'admin', 'description' => 'Accès total', 'created_at' => now()],
            ['id' => 2, 'name' => 'Assistant RH', 'slug' => 'assistant', 'description' => 'Gestion opérationnelle', 'created_at' => now()],
            ['id' => 4, 'name' => 'Manager', 'slug' => 'manager', 'description' => 'Manager avec évaluation', 'created_at' => now()],
            ['id' => 5, 'name' => 'Direction', 'slug' => 'direction', 'description' => 'Lecture seule', 'created_at' => now()],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                $role
            );
        }

        $this->command->info('✅ ' . count($roles) . ' rôles créés avec succès !');
    }
}
