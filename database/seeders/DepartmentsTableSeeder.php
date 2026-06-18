<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $departments = [
            'IT',
            'Ressources Humaines (RH)',
            'Marketing',
            'Commercial',
            'Finance',
            'Logistique',
            'Direction',
        ];

        foreach ($departments as $dept) {
            Department::create(['name' => $dept]);
        }
    }
}