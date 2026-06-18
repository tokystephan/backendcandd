<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * GET ALL USERS - Récupérer tous les utilisateurs
     */
    public function index(Request $request)
    {
        try {
            // ✅ VÉRIFIER QUE L'UTILISATEUR EST ADMIN
            if ($request->user()->role->name !== 'Admin') {
                return response()->json([
                    'message' => 'Accès refusé. Seuls les admins peuvent voir les utilisateurs.'
                ], 403);
            }

            // ✅ RÉCUPÉRER TOUS LES UTILISATEURS AVEC PAGINATION ET LEURS AVATARS
            $users = User::with('role')
                ->paginate(15)
                ->through(function ($user) {
                    // ✅ S'ASSURER QUE profile_image_url EST INCLUS
                    return $user->append('profile_image_url');
                });

            // ✅ RESTRUCTURER LA RÉPONSE POUR LE FRONTEND
            return response()->json([
                'message' => 'Utilisateurs récupérés',
                'data' => $users->items(),
                'users' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get users error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des utilisateurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET PARTICIPANT USERS - Récupérer les utilisateurs internes pouvant être participants
     */
    public function participants(Request $request)
    {
        try {
            $allowedRoleNames = ['Admin', 'Assistant', 'Manager', 'Direction', 'Consultant', 'Recruteur', 'RH'];
            $roleIds = Role::whereIn('name', $allowedRoleNames)->pluck('id')->toArray();

            $users = User::whereIn('role_id', $roleIds)
                ->where('is_active', true)
                ->with('role')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'role' => $user->role ? $user->role->name : null,
                        'profile_image_url' => $user->profile_image_url,
                    ];
                });

            return response()->json($users);
        } catch (\Exception $e) {
            Log::error('Get participant users error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des participants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET USER BY ID - Récupérer un utilisateur par ID
     */
    public function show(Request $request, $id)
    {
        try {
            // ✅ VÉRIFIER QUE L'UTILISATEUR EST ADMIN
            if ($request->user()->role->name !== 'Admin') {
                return response()->json([
                    'message' => 'Accès refusé.'
                ], 403);
            }

            $user = User::with('role')
                ->findOrFail($id)
                ->append('profile_image_url');

            return response()->json([
                'message' => 'Utilisateur trouvé',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }
    }

    /**
     * UPDATE USER - Mettre à jour un utilisateur
     */
    public function update(Request $request, $id)
    {
        try {
            // ✅ VÉRIFIER QUE L'UTILISATEUR EST ADMIN
            if ($request->user()->role->name !== 'Admin') {
                return response()->json([
                    'message' => 'Accès refusé.'
                ], 403);
            }

            // ✅ VALIDATION
            $validator = Validator::make($request->all(), [
                'username' => 'sometimes|string|max:50|unique:users,username,' . $id,
                'first_name' => 'sometimes|string|max:100',
                'last_name' => 'sometimes|string|max:100',
                'email' => 'sometimes|string|email|unique:users,email,' . $id,
                'role_id' => 'sometimes|integer|exists:roles,id',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // ✅ RÉCUPÉRER L'UTILISATEUR
            $user = User::findOrFail($id);

            // ✅ EMPÊCHER LA MODIFICATION DE L'ADMIN PRINCIPAL
            if ($user->id === $request->user()->id && $request->has('role_id')) {
                if ($request->role_id !== $user->role_id) {
                    return response()->json([
                        'message' => 'Vous ne pouvez pas modifier votre propre rôle'
                    ], 403);
                }
            }

            // ✅ METTRE À JOUR
            $user->update($request->only([
                'username',
                'first_name',
                'last_name',
                'email',
                'role_id',
                'is_active'
            ]));

            return response()->json([
                'message' => 'Utilisateur mis à jour',
                'data' => $user->load('role')->append('profile_image_url')
            ]);

        } catch (\Exception $e) {
            Log::error('Update user error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE USER - Supprimer un utilisateur
     */
    public function destroy(Request $request, $id)
    {
        try {
            // ✅ VÉRIFIER QUE L'UTILISATEUR EST ADMIN
            if ($request->user()->role->name !== 'Admin') {
                return response()->json([
                    'message' => 'Accès refusé.'
                ], 403);
            }

            // ✅ EMPÊCHER LA SUPPRESSION DE L'ADMIN PRINCIPAL
            if ($id === $request->user()->id) {
                return response()->json([
                    'message' => 'Vous ne pouvez pas supprimer votre propre compte'
                ], 403);
            }

            // ✅ RÉCUPÉRER ET SUPPRIMER L'UTILISATEUR
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json([
                'message' => 'Utilisateur supprimé'
            ]);

        } catch (\Exception $e) {
            Log::error('Delete user error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * CHANGE USER STATUS - Activer/Désactiver un utilisateur
     */
    public function changeStatus(Request $request, $id)
    {
        try {
            // ✅ VÉRIFIER QUE L'UTILISATEUR EST ADMIN
            if ($request->user()->role->name !== 'Admin') {
                return response()->json([
                    'message' => 'Accès refusé.'
                ], 403);
            }

            // ✅ VALIDATION
            $validator = Validator::make($request->all(), [
                'is_active' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            // ✅ EMPÊCHER LA DÉSACTIVATION DE L'ADMIN PRINCIPAL
            if ($id === $request->user()->id && !$request->is_active) {
                return response()->json([
                    'message' => 'Vous ne pouvez pas désactiver votre propre compte'
                ], 403);
            }

            // ✅ METTRE À JOUR LE STATUT
            $user = User::findOrFail($id);
            $user->update(['is_active' => $request->is_active]);

            return response()->json([
                'message' => 'Statut de l\'utilisateur mis à jour',
                'data' => $user->load('role')->append('profile_image_url')
            ]);

        } catch (\Exception $e) {
            Log::error('Change status error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET ALL ROLES - Récupérer tous les rôles
     */
    public function getRoles(Request $request)
    {
        try {
            // ✅ VÉRIFIER QUE L'UTILISATEUR EST ADMIN
            if ($request->user()->role->name !== 'Admin') {
                return response()->json([
                    'message' => 'Accès refusé.'
                ], 403);
            }

            $roles = Role::all();

            return response()->json([
                'message' => 'Rôles récupérés',
                'data' => $roles
            ]);

        } catch (\Exception $e) {
            Log::error('Get roles error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des rôles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * UPLOAD USER AVATAR - Télécharger un avatar utilisateur
     */
    public function uploadAvatar(Request $request)
    {
        try {
            // ✅ VALIDATION
            $validator = Validator::make($request->all(), [
                'profile_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120' // 5MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            // ✅ RÉCUPÉRER L'UTILISATEUR CONNECTÉ
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $file = $request->file('profile_image');
            if (!$file) {
                return response()->json([
                    'message' => 'Aucun fichier reçu'
                ], 422);
            }

            // ✅ SUPPRIMER L'ANCIENNE IMAGE SI ELLE EXISTE
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }

            // ✅ STOCKER LA NOUVELLE IMAGE
            $filename = $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $storagePath = 'avatars/' . $filename;
            Storage::disk('public')->makeDirectory('avatars');
            $stored = Storage::disk('public')->putFileAs('avatars', $file, $filename);

            if (!$stored) {
                throw new \Exception('Impossible de stocker le fichier avatar.');
            }

            $user->update([
                'profile_image' => $storagePath
            ]);

            // Recharger l'utilisateur pour avoir les données à jour
            $user = $user->fresh()->load('role');

            return response()->json([
                'message' => 'Avatar mis à jour avec succès',
                'data' => $user,
                'profile_image_url' => asset('storage/' . $storagePath)
            ]);

        } catch (\Exception $e) {
            Log::error('Upload avatar error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors du téléchargement de l\'avatar',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}