<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * REGISTER - Inscription utilisateur
     */
    public function register(Request $request)
    {
        // ✅ VALIDATION
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:users',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:150|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role_name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // ✅ INTERDIRE LA CRÉATION D'ADMIN VIA INSCRIPTION PUBLIQUE
            $requestedRole = strtolower(trim((string) $request->role_name));
            if ($requestedRole === 'admin' || $requestedRole === 'administrateur') {
                return response()->json([
                    'message' => 'Le rôle Admin RH ne peut pas être choisi à l\'inscription.'
                ], 403);
            }

            // ✅ AUTORISER LES RÔLES OUVERTS À L'INSCRIPTION
            $allowedPublicRoles = ['assistant', 'assistant rh', 'consultant', 'manager', 'direction'];
            if (!in_array($requestedRole, $allowedPublicRoles, true)) {
                return response()->json([
                    'message' => 'Rôle invalide',
                    'errors' => ['role_name' => ['Rôle invalide']] 
                ], 422);
            }

            // ✅ NORMALISER L'EMAIL
            $normalizedEmail = strtolower(trim($request->email));

            // ✅ RÉCUPÉRER LE RÔLE DE FAÇON INSENSIBLE À LA CASSE
            $role = Role::whereRaw('LOWER(name) = ?', [$requestedRole])
                ->orWhereRaw('LOWER(slug) = ?', [$requestedRole])
                ->first();
            
            if (!$role) {
                return response()->json([
                    'message' => 'Rôle invalide',
                    'errors' => ['role_name' => ['Rôle invalide']] 
                ], 422);
            }

            $isManagerRegistration = $role->slug === 'manager';

            // ✅ CRÉER L'UTILISATEUR
            $user = User::create([
                'username' => $request->username,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $normalizedEmail,
                'password' => Hash::make($request->password),
                'role_id' => $role->id,
                'is_active' => true,
                'approval_status' => $isManagerRegistration ? 'pending' : 'approved'
            ]);

            if ($isManagerRegistration) {
                NotificationService::createForRole(
                    'admin',
                    "Nouveau compte manager à valider : {$user->full_name}",
                    'warning',
                    [
                        'type' => 'manager_approval_requested',
                        'user_id' => $user->id,
                        'user_name' => $user->full_name,
                        'email' => $user->email,
                        'admin_url' => '/admin/users',
                    ]
                );
            }

            $token = $isManagerRegistration ? null : $user->createToken('auth_token')->plainTextToken;

            // ✅ RÉPONSE CORRIGÉE
            return response()->json([
                'success' => true,
                'message' => $isManagerRegistration
                    ? 'Compte manager créé. Il est en attente de validation par un administrateur.'
                    : 'Utilisateur créé avec succès',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role_name' => $this->getRoleName($user->role_id), // ✅ CORRIGÉ
                    'department_id' => $user->department_id,
                    'is_active' => $user->is_active,
                    'approval_status' => $user->approval_status,
                ],
                'token' => $token,
                'requires_approval' => $isManagerRegistration
            ], 201);

        } catch (\Exception $e) {
            Log::error('Register error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du compte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NOUVELLE MÉTHODE : Normaliser le nom du rôle
     */
    private function getRoleName($roleId)
    {
        $roleMapping = [
            1 => 'admin',
            2 => 'assistant',
            3 => 'consultant',
            4 => 'manager',
            5 => 'direction',
        ];
        
        return $roleMapping[$roleId] ?? 'user';
    }

    /**
     * LOGIN - Connexion utilisateur
     */
    public function login(Request $request)
    {
        // ✅ VALIDATION
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // ✅ NORMALISER L'EMAIL
            $normalizedEmail = strtolower(trim($request->email));
            
            // ✅ CHERCHER L'UTILISATEUR
            $user = User::with(['role', 'department'])->where('email', $normalizedEmail)->first();

            // ✅ VÉRIFIER LE MOT DE PASSE
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email ou mot de passe incorrect'
                ], 401);
            }

            // ✅ VÉRIFIER QUE LE COMPTE EST ACTIF
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte est désactivé. Veuillez contacter l\'administrateur.'
                ], 403);
            }

            // ✅ VÉRIFIER LE STATUT D'APPROBATION
            if ($user->approval_status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte est en attente de validation par l\'administrateur.',
                    'status' => $user->approval_status
                ], 403);
            }

            // ✅ Le manager peut se connecter sans département pour voir un dashboard vide.
            // Le consultant garde l'obligation stricte de département.
            $requiresDepartment = $user->role_id === 3;
            
            if ($requiresDepartment) {
                if (is_null($user->department_id)) {
                    Log::warning('Login tenté sans département', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'role_id' => $user->role_id
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => '⚠️ Aucun département n\'est assigné à votre compte.',
                        'action' => 'Veuillez contacter l\'administrateur pour assigner votre département.',
                        'code' => 'NO_DEPARTMENT_ASSIGNED'
                    ], 403);
                }
                
                $department = Department::find($user->department_id);
                if (!$department) {
                    Log::error('Département inexistant', [
                        'user_id' => $user->id,
                        'department_id' => $user->department_id
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => '❌ Le département assigné n\'existe pas.',
                        'action' => 'Contactez l\'administrateur pour corriger votre configuration.',
                        'code' => 'DEPARTMENT_NOT_FOUND'
                    ], 403);
                }
            }

            // ✅ SUPPRIMER LES ANCIENS TOKENS
            $user->tokens()->delete();

            // ✅ GÉNÉRER UN NOUVEAU TOKEN
            $token = $user->createToken('auth_token')->plainTextToken;

            // ✅ METTRE À JOUR LA DATE DE CONNEXION
            $user->update(['last_login' => now()]);

            // ✅ CONSTRUCTION DE LA RÉPONSE
            $departmentName = $user->department ? $user->department->name : null;
            
            // ✅ RÉCUPÉRER LE NOM DU RÔLE NORMALISÉ
            $roleName = $this->getRoleName($user->role_id);

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role_name' => $roleName, 
                    'department_id' => $user->department_id,
                    'department_name' => $departmentName,
                    'is_active' => $user->is_active,
                    'approval_status' => $user->approval_status,
                    'profile_image_url' => $user->profile_image_url ?? null,
                ],
                'token' => $token
            ]);

        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * LOGOUT - Déconnexion
     */
    public function logout(Request $request)
    {
        try {
            if ($request->user() && $request->user()->currentAccessToken()) {
                $request->user()->currentAccessToken()->delete();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion'
            ], 500);
        }
    }

    /**
     * ME - Récupérer l'utilisateur connecté
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }
            
            if (!$user->is_active) {
                if ($user->currentAccessToken()) {
                    $user->currentAccessToken()->delete();
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte est désactivé'
                ], 401);
            }

            $user->load(['role', 'department']);
            
            $departmentName = $user->department ? $user->department->name : null;
            $roleName = $this->getRoleName($user->role_id);

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role_name' => $roleName,  
                    'department_id' => $user->department_id,
                    'department_name' => $departmentName,
                    'is_active' => $user->is_active,
                    'profile_image_url' => $user->profile_image_url ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Me error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération'
            ], 500);
        }
    }

    /**
     * Vérifier si l'utilisateur a un département valide
     */
    public function checkDepartment(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }
            
            $requiresDepartment = in_array($user->role_id, [3, 4]);
            $hasValidDepartment = false;
            $department = null;
            $message = null;
            
            if ($requiresDepartment) {
                if (is_null($user->department_id)) {
                    $message = 'Aucun département assigné à votre compte';
                } else {
                    $department = Department::find($user->department_id);
                    if (!$department) {
                        $message = 'Le département assigné n\'existe pas';
                    } else {
                        $hasValidDepartment = true;
                    }
                }
            } else {
                $hasValidDepartment = true;
            }
            
            return response()->json([
                'success' => true,
                'has_valid_department' => $hasValidDepartment,
                'requires_department' => $requiresDepartment,
                'department' => $department ? [
                    'id' => $department->id,
                    'name' => $department->name,
                    'description' => $department->description
                ] : null,
                'message' => $message,
                'user_role_id' => $user->role_id,
                'user_role_name' => $this->getRoleName($user->role_id),
                'user_department_id' => $user->department_id
            ]);
            
        } catch (\Exception $e) {
            Log::error('Check department error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification'
            ], 500);
        }
    }
}
