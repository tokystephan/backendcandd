<?php

namespace App\Http\Controllers\API;

use App\Models\Notification;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    /**
     * GET /notifications
     * Récupérer toutes les notifications de l'utilisateur
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            $notifications = Notification::forUser($userId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'count' => count($notifications),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /notifications/unread/count
     * Récupérer le nombre de notifications non lues
     */
    public function unreadCount(Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            $count = Notification::forUser($userId)
                ->unread()
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'count' => $count,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /notifications/{id}
     * Récupérer une notification spécifique
     */
    public function show($id, Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            $notification = Notification::forUser($userId)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $notification,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Notification non trouvée',
            ], 404);
        }
    }

    /**
     * PATCH /notifications/{id}/read
     * Marquer une notification comme lue
     */
    public function markAsRead($id, Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            $notification = Notification::forUser($userId)
                ->findOrFail($id);
            
            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marquée comme lue',
                'data' => $notification,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la mise à jour',
            ], 500);
        }
    }

    /**
     * PATCH /notifications/mark-all-read
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            Notification::forUser($userId)
                ->unread()
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Toutes les notifications marquées comme lues',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /notifications/{id}
     * Supprimer une notification
     */
    public function destroy($id, Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            $notification = Notification::forUser($userId)
                ->findOrFail($id);
            
            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification supprimée',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la suppression',
            ], 500);
        }
    }

    /**
     * DELETE /notifications
     * Supprimer toutes les notifications
     */
    public function destroyAll(Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            Notification::forUser($userId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Toutes les notifications supprimées',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /notifications
     * Créer une notification (pour le backend uniquement)
     */
    public function store(Request $request)
    {
        try {
            $roleName = strtolower(optional($request->user()->role)->name ?? '');
            if (!in_array($roleName, ['admin', 'responsable rh'], true)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Action non autorisée',
                ], 403);
            }

            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'message' => 'required|string',
                'type' => 'nullable|string|in:info,success,warning,error',
                'data' => 'nullable|array',
            ]);

            $notification = Notification::create([
                'user_id' => $validated['user_id'],
                'message' => $validated['message'],
                'type' => $validated['type'] ?? 'info',
                'data' => $validated['data'] ?? [],
                'is_read' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification créée',
                'data' => $notification,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
