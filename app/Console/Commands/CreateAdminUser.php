<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    /**
     * Nom de la commande.
     *
     * @var string
     */
    protected $signature = 'admin:create 
                            {--email= : Email de l\'administrateur}
                            {--password= : Mot de passe}';

    /**
     * Description de la commande.
     *
     * @var string
     */
    protected $description = 'Crée un utilisateur administrateur';

    public function handle()
    {
        // Récupérer le rôle admin
        $adminRole = Role::where('name', 'Admin')->first();
        
        if (!$adminRole) {
            $this->error('Le rôle admin n\'existe pas. Lancez d\'abord les seeders.');
            return 1;
        }

        // Récupérer les informations
        $email = $this->option('email') ?? $this->ask('Email de l\'administrateur');
        $password = $this->option('password') ?? $this->secret('Mot de passe');

        // Créer l'utilisateur
        $user = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Système',
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->info('✅ Administrateur créé avec succès !');
        $this->info("Email: {$email}");
        
        return 0;
    }
}