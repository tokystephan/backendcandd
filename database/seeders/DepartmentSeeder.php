<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DepartmentsTableSeeder extends Seeder
{
    /**
     * Exécution du seeder pour créer les départements
     */
    public function run()
    {
        // 🔄 Vider la table avant d'insérer (évite les doublons)
        Schema::disableForeignKeyConstraints();
        DB::table('departments')->truncate();
        Schema::enableForeignKeyConstraints();

        // 📋 Liste des départements à créer
        $departments = [
            [
                'id' => 1,
                'name' => 'Informatique',
                'description' => 'Développement logiciel, infrastructure IT et support technique',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Marketing',
                'description' => 'Communication, publicité, SEO et réseaux sociaux',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Ressources Humaines',
                'description' => 'Recrutement, paie, administration du personnel',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'Commercial',
                'description' => 'Ventes, business développement et relation clients',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'name' => 'Finance',
                'description' => 'Comptabilité, gestion financière et audit',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'name' => 'Logistique',
                'description' => 'Supply chain, transport et entreposage',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 7,
                'name' => 'Production',
                'description' => 'Fabrication, qualité et maintenance',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 8,
                'name' => 'Direction Générale',
                'description' => 'Direction stratégique et management',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // 💾 Insérer les départements
        foreach ($departments as $department) {
            DB::table('departments')->updateOrInsert(
                ['id' => $department['id']],  // Vérifier si l'ID existe
                $department                   // Sinon, insérer
            );
        }

        // Afficher un message dans la console
        $this->command->info('✅ ' . count($departments) . ' départements créés avec succès !');
    }
}