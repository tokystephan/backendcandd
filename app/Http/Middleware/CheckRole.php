<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckRole
{
    /**
     * Rôles autorisés pour accéder à la route
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$allowedRoles  (ex: 'role:1,2' ou 'role:admin,assistant')
     */
    public function handle(Request $request, Closure $next, ...$allowedRoles)
    {
        $user = $request->user();
        
        // ========== 1. VÉRIFICATION AUTHENTIFICATION ==========
        if (!$user) {
            Log::warning('CheckRole: Utilisateur non authentifié', [
                'path' => $request->path(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié. Veuillez vous connecter.',
                'code' => 'UNAUTHENTICATED'
            ], 401);
        }
        
        // ========== 2. VÉRIFICATION COMPTE ACTIF ==========
        if (!$user->is_active) {
            Log::warning('CheckRole: Compte désactivé', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est désactivé. Veuillez contacter l\'administrateur.',
                'code' => 'ACCOUNT_DISABLED'
            ], 403);
        }
        
        // ========== 3. SI AUCUN RÔLE SPÉCIFIÉ ==========
        if (empty($allowedRoles)) {
            Log::info('CheckRole: Aucun rôle requis - accès autorisé', [
                'user_id' => $user->id,
                'path' => $request->path()
            ]);
            return $next($request);
        }
        
        // ========== 4. CONVERSION DES RÔLES EN IDs ==========
        $allowedRoleIds = $this->convertRolesToIds($allowedRoles);
        
        // Log pour debug
        Log::info('CheckRole: Vérification des droits', [
            'user_id' => $user->id,
            'user_role_id' => $user->role_id,
            'allowed_roles_input' => $allowedRoles,
            'allowed_role_ids' => $allowedRoleIds,
            'path' => $request->path()
        ]);
        
        // ========== 5. VÉRIFICATION DU RÔLE ==========
        if (!in_array($user->role_id, $allowedRoleIds)) {
            Log::warning('CheckRole: Rôle non autorisé', [
                'user_id' => $user->id,
                'user_role_id' => $user->role_id,
                'required_roles' => $allowedRoleIds,
                'path' => $request->path()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Vous n\'avez pas les droits nécessaires.',
                'code' => 'FORBIDDEN',
                'required_roles' => $allowedRoles,
                'your_role' => $this->getRoleName($user->role_id),
                'your_role_id' => $user->role_id
            ], 403);
        }
        
        // ========== 6. VÉRIFICATION DÉPARTEMENT ==========
        // Le manager doit pouvoir ouvrir son dashboard même sans département:
        // les contrôleurs renvoient alors des données vides et un état à afficher.
        if ($user->role_id === 3 && !$this->hasDepartment($user)) {
            Log::warning('CheckRole: Consultant sans département', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            // On retourne 200 mais avec un warning pour que le dashboard affiche un message
            return response()->json([
                'success' => false,
                'warning' => true,
                'message' => 'Aucun département assigné à votre compte consultant. Veuillez contacter l\'administrateur.',
                'code' => 'NO_DEPARTMENT'
            ], 403);
        }
        
        // ========== 7. ACCÈS AUTORISÉ ==========
        return $next($request);
    }
    
    /**
     * Convertir les noms de rôles en IDs
     * 
     * @param array $roles
     * @return array
     */
    private function convertRolesToIds(array $roles): array
    {
        $roleMapping = [
            'admin' => 1,
            'assistant' => 2,
            'consultant' => 3,
            'manager' => 4,
            'direction' => 5,
        ];
        
        $ids = [];
        foreach ($roles as $role) {
            // Si c'est déjà un nombre
            if (is_numeric($role)) {
                $ids[] = (int)$role;
            }
            // Si c'est un nom de rôle (slug)
            elseif (isset($roleMapping[strtolower($role)])) {
                $ids[] = $roleMapping[strtolower($role)];
            }
            // Sinon, essayer de trouver dans la base de données
            else {
                $roleModel = \App\Models\Role::where('slug', $role)
                    ->orWhere('name', $role)
                    ->first();
                if ($roleModel) {
                    $ids[] = $roleModel->id;
                }
            }
        }
        
        return array_unique($ids);
    }
    
    /**
     * Obtenir le nom du rôle à partir de l'ID
     * 
     * @param int $roleId
     * @return string
     */
    private function getRoleName(int $roleId): string
    {
        $roleMapping = [
            1 => 'admin',
            2 => 'assistant',
            3 => 'consultant',
            4 => 'manager',
            5 => 'direction',
        ];
        
        return $roleMapping[$roleId] ?? 'inconnu';
    }
    
    /**
     * Vérifier si l'utilisateur a un département
     * 
     * @param \App\Models\User $user
     * @return bool
     */
    private function hasDepartment($user): bool
    {
        // Vérifier si la méthode existe dans le modèle User
        if (method_exists($user, 'hasDepartment')) {
            return $user->hasDepartment();
        }
        
        // Fallback: vérifier directement
        return !is_null($user->department_id) && !is_null($user->department);
    }
}
