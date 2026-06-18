<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationComment;
use App\Models\ApplicationStatusHistory;
use App\Models\Statut;
use App\Services\NotificationService;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    private $statusMap = [
        'recue' => 'Reçue',
        'en_cours' => 'En cours',
        'entretien_rh' => 'Entretien RH',
        'entretien_technique' => 'Entretien technique',
        'acceptee' => 'Acceptée',
        'refusee' => 'Refusée',
    ];

    public function index(Request $request)
    {
        $query = Application::with(['candidate', 'post.department', 'post.contractType', 'currentStatus', 'source', 'creator'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $statusLabel = $this->statusMap[$request->status] ?? $request->status;
            $query->whereHas('currentStatus', function ($statusQuery) use ($statusLabel) {
                $statusQuery->where('name', $statusLabel);
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->whereHas('candidate', function ($candidateQuery) use ($search) {
                        $candidateQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('post', function ($postQuery) use ($search) {
                        $postQuery->where('title', 'like', "%{$search}%");
                    });
            });
        }

        $applications = $query->get();
        $formatted = $applications->map(function (Application $application) {
            return $this->formatApplication($application);
        });

        return response()->json($formatted);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($this->isConsultant($user)) {
            return response()->json(['message' => 'Les consultants ne peuvent pas créer de candidature.'], 403);
        }

        $data = $this->validatedData($request);
        $status = $this->resolveStatus($data['status'] ?? 'recue');

        $application = Application::create([
            'candidate_id' => $data['candidate_id'],
            'post_id' => $data['post_id'],
            'source_id' => $data['source_id'] ?? null,
            'expected_salary' => $data['expected_salary'] ?? null,
            'notes' => $data['notes'] ?? null,
            'internal_note' => $data['notes'] ?? null,
            'current_status_id' => $status->id,
            'assigned_to' => $data['assigned_to'] ?? null,
            'created_by' => $user->id,
        ]);

        $this->recordStatusHistory($application, $status, 'Création de la candidature');

        try {
            NotificationService::newApplication($application->id);
        } catch (\Exception $e) {
            // ne pas bloquer la création si la notification échoue
        }

        return response()->json($this->formatApplication($application->fresh($this->relations())), 201);
    }

    public function show($id)
    {
        $application = Application::with($this->relations())->findOrFail($id);
        return response()->json($this->formatApplication($application));
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if ($this->isConsultant($user)) {
            return response()->json(['message' => 'Les consultants ne peuvent pas modifier une candidature.'], 403);
        }

        $application = Application::findOrFail($id);
        $data = $this->validatedData($request, true);

        if (array_key_exists('candidate_id', $data)) {
            $application->candidate_id = $data['candidate_id'];
        }
        if (array_key_exists('post_id', $data)) {
            $application->post_id = $data['post_id'];
        }
        if (array_key_exists('source_id', $data)) {
            $application->source_id = $data['source_id'];
        }
        if (array_key_exists('expected_salary', $data)) {
            $application->expected_salary = $data['expected_salary'];
        }
        if (array_key_exists('notes', $data)) {
            $application->notes = $data['notes'];
            $application->internal_note = $data['notes'];
        }
        if (array_key_exists('assigned_to', $data)) {
            $application->assigned_to = $data['assigned_to'];
        }

        $oldStatusName = $application->currentStatus?->name ?? 'Inconnu';
        $statusChanged = false;
        $newStatus = null;

        if (!empty($data['status'])) {
            $status = $this->resolveStatus($data['status']);
            if ((int) $application->current_status_id !== (int) $status->id) {
                $application->current_status_id = $status->id;
                $this->recordStatusHistory($application, $status, 'Modification de la candidature');
                $statusChanged = true;
                $newStatus = $status;
            }
        }

        $application->save();

        if ($statusChanged && $newStatus) {
            try {
                NotificationService::statusChanged($application->id, $oldStatusName, $newStatus->name);
            } catch (\Exception $e) {
                // ne pas bloquer la modification si la notification échoue
            }
        }

        return response()->json($this->formatApplication($application->fresh($this->relations())));
    }

    public function destroy($id)
    {
        $user = auth()->user();
        if ($this->isConsultant($user)) {
            return response()->json(['message' => 'Les consultants ne peuvent pas supprimer une candidature.'], 403);
        }

        Application::findOrFail($id)->delete();

        return response()->json(['message' => 'Candidature supprimée avec succès.']);
    }

    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|string',
            'note' => 'nullable|string',
            'changed_by' => 'nullable|string',
        ]);

        $application = Application::with('currentStatus')->findOrFail($id);
        $oldStatusName = $application->currentStatus?->name ?? 'Inconnu';
        $status = $this->resolveStatus($data['status']);
        $application->current_status_id = $status->id;
        $application->save();

        $this->recordStatusHistory($application, $status, $data['note'] ?? null, $data['changed_by'] ?? null);

        try {
            NotificationService::statusChanged($application->id, $oldStatusName, $status->name);
        } catch (\Exception $e) {
            // ignore
        }

        return response()->json($this->formatApplication($application->fresh($this->relations())));
    }

    public function proposeOffer(Request $request, $id)
    {
        $user = auth()->user();
        if (!$this->hasAnyRole($user, ['admin', 'assistant'])) {
            return response()->json(['message' => 'Seuls l\'Admin RH et l\'Assistant RH peuvent proposer une offre.'], 403);
        }

        if (!$request->filled('salary') && $request->filled('offer_salary')) {
            $request->merge(['salary' => $request->input('offer_salary')]);
        }
        if (!$request->filled('expires_at') && $request->filled('offer_expires_at')) {
            $request->merge(['expires_at' => $request->input('offer_expires_at')]);
        }

        $data = $request->validate([
            'salary' => 'required|numeric|min:0',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $application = Application::with(['candidate', 'post'])->findOrFail($id);
        $application->update([
            'offer_proposed' => true,
            'offer_salary' => $data['salary'],
            'offer_expires_at' => $data['expires_at'] ?? now()->addHours(48),
        ]);

        try {
            NotificationService::offerToValidate(
                $application->id,
                $application->candidate?->full_name ?? trim(($application->candidate?->first_name ?? '') . ' ' . ($application->candidate?->last_name ?? '')),
                $application->offer_salary,
                $application->post?->title ?? 'Poste',
                $application->offer_expires_at
            );
        } catch (\Exception $e) {
            // ne pas bloquer la proposition si la notification échoue
        }

        return response()->json([
            'message' => 'Offre proposée et envoyée à la Direction pour validation',
            'application' => $this->formatApplication($application->fresh($this->relations())),
        ]);
    }

    public function validateOffer(Request $request, $id)
    {
        $user = auth()->user();
        if (!$this->hasAnyRole($user, ['direction'])) {
            return response()->json(['message' => 'Seule la Direction peut valider ou refuser une offre.'], 403);
        }

        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'note' => 'nullable|string',
        ]);

        $application = Application::with(['candidate', 'post'])->findOrFail($id);
        $status = $this->resolveStatus($data['action'] === 'approve' ? 'acceptee' : 'refusee');
        $application->current_status_id = $status->id;
        $application->save();
        $this->recordStatusHistory($application, $status, $data['note'] ?? 'Décision Direction');

        if ($data['action'] === 'approve') {
            try {
                NotificationService::offerDecision(
                    $application->id,
                    $application->candidate->full_name ?? $application->candidate->first_name . ' ' . $application->candidate->last_name,
                    'accepted',
                    $data['note'] ?? null
                );
            } catch (\Exception $e) {}
            return response()->json(['message' => 'Offre validée', 'application' => $this->formatApplication($application->fresh($this->relations()))], 200);
        }

        try {
            NotificationService::offerDecision(
                $application->id,
                $application->candidate->full_name ?? $application->candidate->first_name . ' ' . $application->candidate->last_name,
                'rejected',
                $data['note'] ?? null
            );
        } catch (\Exception $e) {}

        return response()->json(['message' => 'Offre rejetée', 'application' => $this->formatApplication($application->fresh($this->relations()))], 200);
    }

    public function comments($id)
    {
        Application::findOrFail($id);
        return response()->json(
            ApplicationComment::where('application_id', $id)->orderByDesc('created_at')->get()
        );
    }

    public function addComment(Request $request, $id)
    {
        $application = Application::findOrFail($id);
        $data = $request->validate([
            'content' => 'required|string',
            'author' => 'nullable|string',
        ]);

        $user = auth()->user();
        $comment = ApplicationComment::create([
            'application_id' => $application->id,
            'user_id' => $user ? $user->id : null,
            'author' => $data['author'] ?? $this->userName($user),
            'content' => $data['content'],
        ]);

        // Notifications: prévenir les autres utilisateurs ayant accès (sauf l'auteur)
        try {
            $recipientIds = [];
            if ($application->creator && $application->creator->id !== ($user->id ?? null)) {
                $recipientIds[] = $application->creator->id;
            }

            $hrUsers = User::whereHas('role', function ($query) {
                $query->whereIn(DB::raw('LOWER(name)'), ['admin', 'assistant', 'responsable rh', 'assistant rh']);
            })->pluck('id')->toArray();
            
            foreach ($hrUsers as $hrUserId) {
                if ($hrUserId !== ($user->id ?? null)) $recipientIds[] = $hrUserId;
            }

            if ($application->assigned_to) {
                $assignedTo = trim((string) $application->assigned_to);
                $assignedUsers = User::where(function ($query) use ($assignedTo) {
                    if (is_numeric($assignedTo)) {
                        $query->orWhere('id', (int) $assignedTo);
                    }

                    $query->orWhere('username', $assignedTo)
                        ->orWhere('email', $assignedTo)
                        ->orWhereRaw("TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) = ?", [$assignedTo]);
                })->pluck('id')->toArray();

                foreach ($assignedUsers as $assignedUserId) {
                    if ($assignedUserId !== ($user->id ?? null)) $recipientIds[] = $assignedUserId;
                }
            }

            $recipientIds = array_values(array_unique(array_filter($recipientIds)));
            if (!empty($recipientIds)) {
                NotificationService::createForUsers($recipientIds, "💬 Nouveau commentaire sur la candidature {$application->id}", 'info', [
                    'type' => 'nouveau_commentaire',
                    'application_id' => $application->id,
                    'comment' => substr($comment->content, 0, 200),
                ]);
            }
        } catch (\Exception $e) {
            // ignore
        }

        return response()->json($comment, 201);
    }

    public function deleteComment($applicationId, $commentId)
    {
        Application::findOrFail($applicationId);
        ApplicationComment::where('application_id', $applicationId)->findOrFail($commentId)->delete();

        return response()->json(['message' => 'Commentaire supprimé avec succès.']);
    }

    private function validatedData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'candidate_id' => "{$required}|integer|exists:candidates,id",
            'post_id' => "{$required}|integer|exists:posts,id",
            'status' => "{$required}|string",
            'source_id' => 'nullable|integer|exists:sources,id',
            'expected_salary' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'comments' => 'nullable|string',
            'assigned_to' => 'nullable|string|max:255',
        ]);
    }

    private function relations(): array
    {
        return [
            'candidate',
            'post.department',
            'post.contractType',
            'currentStatus',
            'source',
            'creator',
            'statusHistory.statusRecord',
            'comments',
        ];
    }

    private function resolveStatus(string $status): Statut
    {
        $label = $this->statusMap[$status] ?? $status;
        return Statut::firstOrCreate(
            ['name' => $label],
            ['label' => $label]
        );
    }

    // ✅ Correction: utiliser name au lieu de label
    private function statusSlug(?Statut $status): ?string
    {
        if (!$status) return null;
        
        $name = $status->name;
        $slug = array_search($name, $this->statusMap, true);
        
        return $slug ?: strtolower(str_replace([' ', 'é', 'è', 'ê', 'à', 'ù', 'ç'], ['_', 'e', 'e', 'e', 'a', 'u', 'c'], $name));
    }

    // ✅ Correction: supprimer assigned_to
    private function formatApplication(Application $application): array
    {
        $statusSlug = $this->statusSlug($application->currentStatus);
        $data = $application->toArray();
        $data['status'] = $statusSlug;
        $data['status_label'] = $application->currentStatus?->name ?? null;
        $data['assigned_to'] = $application->assigned_to ?: ($application->creator ? $this->userName($application->creator) : null);

        if ($application->statusHistory) {
            $statusHistory = $application->statusHistory->map(function ($history) {
                $historyStatus = $history->status ?: $this->statusSlug($history->statusRecord);
                $historyStatusLabel = $history->statusRecord?->name
                    ?: (isset($this->statusMap[$historyStatus]) ? $this->statusMap[$historyStatus] : $historyStatus);

                return [
                    'id' => $history->id,
                    'status' => $historyStatus,
                    'new_status' => $historyStatus,
                    'status_label' => $historyStatusLabel,
                    'changed_by' => $history->changed_by_name,
                    'note' => $history->note ?: $history->notes,
                    'notes' => $history->notes ?: $history->note,
                    'changed_at' => $history->changed_at ?? $history->created_at,
                    'created_at' => $history->created_at,
                ];
            });
            $data['statusHistory'] = $statusHistory->values()->toArray();
        } else {
            $data['statusHistory'] = [];
        }

        return $data;
    }

    // ✅ Correction: utiliser status_id au lieu de status
    private function recordStatusHistory(Application $application, Statut $status, ?string $note = null, ?string $changedByName = null): void
    {
        $user = auth()->user();

        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'status_id' => $status->id,
            'status' => $this->statusSlug($status),
            'changed_by' => $user ? $user->id : null,
            'changed_by_name' => $changedByName ?: $this->userName($user),
            'note' => $note,
            'notes' => $note,
            'changed_at' => now(),
        ]);
    }

    private function userName($user): ?string
    {
        if (!$user) return null;
        return trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->username ?? $user->email);
    }

    private function isConsultant($user): bool
    {
        if (!$user) return false;
        
        $role = $user->role;
        $roleName = is_object($role) ? $role->name : $role;
        $normalized = strtolower((string) $roleName);

        return in_array($normalized, ['consultant', 'manager'], true);
    }
}
