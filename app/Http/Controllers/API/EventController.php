<?php

namespace App\Http\Controllers\API;  // ✅ CORRECTION 1: Ajout de /API

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\InterviewReport;
use App\Models\Statut;
use App\Http\Requests\StoreEventRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use Carbon\Carbon;

class EventController extends Controller
{
    /**
     * ✅ CORRECTION 2: Méthode roleName plus robuste avec fallback sur role_id
     */
    private function getRoleName($user): string
    {
        if (!$user) {
            return '';
        }
        
        // Utiliser role_id d'abord (plus fiable)
        $roleMapping = [
            1 => 'admin',
            2 => 'assistant',
            3 => 'consultant',
            4 => 'manager',
            5 => 'direction',
        ];
        
        if (isset($roleMapping[$user->role_id])) {
            return $roleMapping[$user->role_id];
        }
        
        // Fallback sur le nom du rôle
        $role = $user->role ? strtolower($user->role->name) : '';
        
        $aliases = [
            'manager' => 'manager',
            'responsable rh' => 'admin',
            'assistant rh' => 'assistant',
            'directeur rh' => 'direction',
            'directeur' => 'direction',
        ];
        
        return $aliases[$role] ?? $role;
    }
    
    /**
     * ✅ CORRECTION 3: Vérification des rôles simplifiée
     */
    private function hasAnyRole($user, array $allowedRoles): bool
    {
        if (!$user) {
            return false;
        }
        $roleName = $this->getRoleName($user);
        return in_array($roleName, $allowedRoles, true);
    }
    
    /**
     * ✅ CORRECTION 4: Vérification d'accès à un événement
     */
    private function canAccessEvent($user, Event $event): bool
    {
        if (!$user) {
            return false;
        }
        
        $roleName = $this->getRoleName($user);
        
        // Admin, Assistant, Direction ont accès à tous
        if (in_array($roleName, ['admin', 'assistant', 'direction'], true)) {
            return true;
        }
        
        // Consultant / Manager: seulement s'il est participant
        if (in_array($roleName, ['consultant', 'manager'], true)) {
            return $event->participants()->where('user_id', $user->id)->exists();
        }
        
        return false;
    }
    
    /**
     * ✅ CORRECTION 5: Vérification d'accès au compte rendu
     */
    private function canAccessReport($user, Event $event, bool $forExport = false): bool
    {
        if (!$user) {
            return false;
        }
        
        $roleName = $this->getRoleName($user);
        
        if (in_array($roleName, ['admin', 'assistant', 'direction'], true)) {
            return true;
        }
        
        if (in_array($roleName, ['consultant', 'manager'], true)) {
            return $this->isParticipant($event, $user->id);
        }
        
        return false;
    }
    
    // ==================== MÉTHODES PRINCIPALES ====================
    
    /**
     * GET /api/events
     * ✅ CORRECTION 6: Ajout de logs et meilleure gestion des erreurs
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            Log::warning('EventController.index: Utilisateur non authentifié');
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
                'events' => [],
                'stats' => [
                    'total' => 0,
                    'planifie' => 0,
                    'termine' => 0,
                    'annule' => 0,
                ],
            ], 401);
        }

        try {
            $query = Event::with(['candidate', 'application.post', 'participants.user', 'report', 'creator']);

            // Filtres
            if ($request->type) {
                $query->where('event_type', $request->type);
            }
            if ($request->status) {
                $query->where('status', $request->status);
            }
            if ($request->application_id) {
                $query->where('application_id', $request->application_id);
            }

            $roleName = $this->getRoleName($user);
            
            // ✅ CORRECTION 7: Inclure 'manager' dans les rôles autorisés
            if (!in_array($roleName, ['admin', 'assistant', 'consultant', 'manager', 'direction'], true)) {
                Log::warning('EventController.index: Rôle non autorisé', [
                    'user_id' => $user->id,
                    'role_name' => $roleName
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.',
                    'events' => [],
                    'stats' => []
                ], 403);
            }

            // Consultants: voir seulement leurs événements
            if ($roleName === 'consultant') {
                $query->whereHas('participants', function ($participantQuery) use ($user) {
                    $participantQuery->where('user_id', $user->id);
                });
            }

            $events = $query->orderBy('start_datetime', 'desc')->get();

            $stats = [
                'total' => $events->count(),
                'planifie' => $events->where('status', 'planifie')->count(),
                'termine' => $events->where('status', 'termine')->count(),
                'annule' => $events->where('status', 'annule')->count(),
            ];

            Log::info('EventController.index: Succès', [
                'user_id' => $user->id,
                'events_count' => $events->count()
            ]);

            return response()->json([
                'success' => true,
                'events' => $events,
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('EventController.index error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur',
                'events' => [],
                'stats' => []
            ], 500);
        }
    }

    /**
     * GET /api/events/{id}
     */
    public function show(Event $event)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié'
            ], 401);
        }
        
        if (!$this->canAccessEvent($user, $event)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé à cet entretien.'
            ], 403);
        }

        $event->load(['candidate', 'application.post', 'participants.user', 'report.author', 'creator']);
        
        return response()->json([
            'success' => true,
            'event' => $event
        ]);
    }

    /**
     * POST /api/events
     */
    public function store(StoreEventRequest $request)
    {
        $user = auth()->user();
        
        if (!$this->hasAnyRole($user, ['admin', 'assistant'])) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls l\'Admin RH et l\'Assistant RH peuvent planifier un entretien.'
            ], 403);
        }

        try {
            $data = $request->validated();
            
            if (isset($data['participants']) && !$this->participantsAllowedForEventType($data['event_type'], $data['participants'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'La Direction ne peut être invitée que sur un entretien final.'
                ], 422);
            }

            $data['created_by'] = $user->id;
            $event = null;

            DB::transaction(function () use ($data, &$event) {
                $event = Event::create($data);
                if (isset($data['participants']) && is_array($data['participants'])) {
                    foreach ($data['participants'] as $userId) {
                        EventParticipant::create([
                            'event_id' => $event->id,
                            'user_id' => $userId,
                            'is_organizer' => $userId === auth()->id(),
                            'invitation_status' => 'pending',
                        ]);
                    }
                }
            });
            
            return response()->json([
                'success' => true,
                'message' => 'Entretien créé avec succès',
                'event' => $event->load(['participants.user', 'candidate', 'application.post', 'report', 'creator'])
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('EventController.store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création'
            ], 500);
        }
    }

    /**
     * PUT /api/events/{id}
     */
    public function update(Request $request, Event $event)
    {
        $user = auth()->user();
        
        if (!$this->hasAnyRole($user, ['admin', 'assistant'])) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls l\'Admin RH et l\'Assistant RH peuvent modifier un entretien.'
            ], 403);
        }

        try {
            $data = $request->validate([
                'title' => 'sometimes|string|max:255',
                'start_datetime' => 'sometimes|date',
                'end_datetime' => 'sometimes|date|after:start_datetime',
                'location_type' => 'sometimes|in:presentiel,visio,telephone',
                'location' => 'nullable|string',
                'meeting_link' => 'nullable|url',
                'phone_number' => 'nullable|string',
                'description' => 'nullable|string',
                'status' => 'sometimes|in:planifie,confirme,annule,reporte,termine',
            ]);

            $event->update($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Entretien mis à jour',
                'event' => $event->load(['participants.user', 'candidate', 'application.post'])
            ]);
            
        } catch (\Exception $e) {
            Log::error('EventController.update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * DELETE /api/events/{id}
     */
    public function destroy(Event $event)
    {
        $user = auth()->user();
        
        if (!$this->hasAnyRole($user, ['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Seul l\'Admin RH peut supprimer un entretien.'
            ], 403);
        }

        try {
            $event->delete();
            return response()->json([
                'success' => true,
                'message' => 'Événement supprimé'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * PATCH /api/events/{id}/status
     */
    public function updateStatus(Request $request, Event $event)
    {
        $user = auth()->user();
        
        if (!$this->hasAnyRole($user, ['admin', 'assistant'])) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls l\'Admin RH et l\'Assistant RH peuvent modifier le statut.'
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:planifie,confirme,annule,reporte,termine'
        ]);

        try {
            $event->update(['status' => $request->status]);
            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour',
                'event' => $event
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * POST /api/events/{event}/report
     * Crée ou met à jour le compte-rendu d'un entretien
     */
    public function storeReport(Request $request, Event $event)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
        }

        // Seuls Admin/Assistant ou un Consultant participant peuvent écrire
        $roleName = $this->getRoleName($user);
        $isConsultantParticipant = $roleName === 'consultant' && $this->isParticipant($event, $user->id);

        if (!($this->hasAnyRole($user, ['admin', 'assistant']) || $isConsultantParticipant)) {
            return response()->json(['success' => false, 'message' => 'Accès refusé pour ajouter un compte-rendu.'], 403);
        }

        if (!$this->reportStatusAllowsWriting($event)) {
            return response()->json(['success' => false, 'message' => 'Le statut de l\'événement ne permet pas d\'écrire un compte-rendu.'], 422);
        }

        $data = $request->validate([
            'evaluation_notes' => 'nullable|string',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'next_steps' => 'nullable|string',
            'recommendation' => 'nullable|string',
        ]);

        try {
            $report = InterviewReport::where('event_id', $event->id)->first();

            if (!$report) {
                $data['event_id'] = $event->id;
                $data['created_by'] = $user->id;
                $report = InterviewReport::create($data);
            } else {
                // Ne pas écraser created_by
                $report->fill($data);
                $report->save();
            }

            // Notification
            try {
                $evaluatorName = $user->full_name ?? $user->name ?? 'Utilisateur';
                NotificationService::reportAdded($report->id, $evaluatorName, $report->recommendation ?? '', $user->id);
            } catch (\Exception $nex) {
                Log::warning('Notification/reportAdded failed: ' . $nex->getMessage());
            }

            return response()->json(['success' => true, 'message' => 'Compte-rendu enregistré', 'report' => $report]);

        } catch (\Exception $e) {
            Log::error('EventController.storeReport error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur lors de l\'enregistrement du compte-rendu'], 500);
        }
    }

    /**
     * GET /api/events/{event}/report/export
     */
    public function exportReport(Event $event)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
        }

        if (!$this->canAccessEvent($user, $event)) {
            return response()->json(['success' => false, 'message' => 'Accès refusé à cet entretien.'], 403);
        }

        $report = InterviewReport::where('event_id', $event->id)->first();
        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Aucun compte-rendu trouvé pour cet entretien.'], 404);
        }

        if (!$this->canAccessReport($user, $event, true)) {
            return response()->json(['success' => false, 'message' => 'Accès refusé à l\'export du compte-rendu.'], 403);
        }

        $event->load(['candidate', 'application.post', 'report', 'participants.user']);
        $candidateName = $event->candidate?->full_name ?? trim(($event->candidate?->first_name ?? '') . ' ' . ($event->candidate?->last_name ?? '')) ?: 'N/A';
        $postTitle = $event->application?->post?->title ?? 'N/A';
        $eventDate = $event->start_datetime ? Carbon::parse($event->start_datetime)->format('d/m/Y H:i') : 'N/A';
        $reportAuthor = $report->author?->full_name ?? $report->author?->name ?? 'N/A';
        $filename = sprintf('compte-rendu-entretien-%s.txt', $event->id);

        $contentLines = [
            "Compte rendu d'entretien #{$event->id}",
            "----------------------------------------",
            "Candidat: {$candidateName}",
            "Poste: {$postTitle}",
            "Date entretien: {$eventDate}",
            "Auteur: {$reportAuthor}",
            "Validé: " . ($report->validated_at ? $report->validated_at : 'Non'),
            "",
            "=== Résumé / Évaluation ===",
            $report->evaluation_notes ?: 'Non renseigné',
            "",
            "=== Recommandation ===",
            $report->recommendation ?: 'Non renseigné',
            "",
            "=== Points positifs ===",
            $report->strengths ?: 'Non renseigné',
            "",
            "=== Points d'amélioration ===",
            $report->weaknesses ?: 'Non renseigné',
            "",
            "=== Commentaires supplémentaires ===",
            $report->next_steps ?: 'Non renseigné',
        ];

        $content = implode("\n", $contentLines);

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    // ... (Garder les autres méthodes storeReport, destroyReport, validateReport, 
    // exportReport, validateOffer, et les méthodes privées telles quelles 
    // en changeant seulement les appels à $this->roleName() par $this->getRoleName()
    // et $this->hasAnyRole() qui est déjà corrigé)

    /**
     * Vérifier si l'utilisateur est participant
     */
    private function isParticipant(Event $event, int $userId): bool
    {
        return $event->participants()->where('user_id', $userId)->exists();
    }

    /**
     * Vérifier si le statut permet l'écriture du compte rendu
     */
    private function reportStatusAllowsWriting(Event $event): bool
    {
        return in_array($event->status, ['confirme', 'termine'], true);
    }

    /**
     * Vérifier que les participants sont autorisés pour le type d'événement
     */
    private function participantsAllowedForEventType(string $eventType, array $participantIds): bool
    {
        if ($eventType === 'final') {
            return true;
        }

        return !\App\Models\User::whereIn('id', $participantIds)
            ->whereHas('role', function ($query) {
                $query->whereRaw('LOWER(name) IN (?, ?, ?)', ['direction', 'directeur', 'directeur rh']);
            })
            ->exists();
    }
}