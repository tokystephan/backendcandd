<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{

    public function run(): void
    {
        // ✅ RÉCUPÉRER LE RÔLE ADMIN
        $adminRole = Role::where('name', 'Admin')->first();

        if (!$adminRole) {
            $this->command->error('❌ Le brôle Admin n\'existe pas. Exécutez d\'abord RolesTableSeeder.');
            return;
        }

        // ✅ VÉRIFIER SI UN ADMIN EXISTE DÉJÀ (PEU IMPORTE L'EMAIL)
        $adminExists = User::where('role_id', $adminRole->id)->exists();
        if ($adminExists) {
            $this->command->info('⚠️  Un compte Admin RH existe déjà. Aucun nouveau compte créé.');
            return;
        }

        
        //  CRÉER L'UNIQUE COMPTE ADMIN
        $admin = User::create([
            'username' => 'admin',
            'first_name' => 'Admin',
            'last_name' => 'System',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123456'),
            'approval_status' => 'approved',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->command->info(' Compte admin créé avec succès !');
        $this->command->line('');
        $this->command->line('📧 Email: admin@example.com');
        $this->command->line('🔑 Mot de passe: admin123456');
        $this->command->line('');
    }
}
