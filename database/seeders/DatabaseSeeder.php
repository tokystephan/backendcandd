<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Créer les rôles d'abord
        $this->call([
            RolesTableSeeder::class,
            AdminUserSeeder::class,  
            DepartmentsTableSeeder::class,  
            ContractTypesTableSeeder::class,  
        ]);
    }
}