<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Post;
use App\Models\Statut;
use App\Models\ApplicationStatusHistory;
use App\Models\InterviewReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class ConsultantDashboardController extends Controller
{
    public function getDashboard()
    {
        return $this->dashboard();
    }

    /**
     * Récupérer les statistiques du tableau de bord consultant
     */
    public function dashboard()
    {
        $user = Auth::user();
        
        // Debug: logger les informations de l'utilisateur
        Log::info('Consultant Dashboard - User:', [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'role_id' => $user->role_id ?? 'NOT SET',
            'department_id' => $user->department_id ?? 'NOT SET',
        ]);

        // ✅ CORRECTION #1: Vérifier aussi par role_id
        if (!$this->hasRole($user, ['consultant', 'admin', 'manager'])) {
            return response()->json([
                'message' => 'Accès refusé. Rôle insuffisant.',
                'debug' => [
                    'expected_roles' => ['consultant', 'admin', 'manager'],
                    'user_role' => $user->role ?? 'NULL',
                    'user_role_id' => $user->role_id ?? 'NULL',
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'solution' => 'Assurez-vous que role_id = 3 dans la table users'
                ]
            ], 403);
        }

        if ($this->managerNeedsDepartment($user)) {
            return response()->json($this->emptyManagerPayload());
        }

        $visibleApplicationsQuery = $this->visibleApplicationsQuery($user);

        // Nombre de postes actifs (ouverts)
        $myPosts = Post::where(function (Builder $query) use ($user) {
                $query->where('created_by', $user->id);
                if ($this->userHasDepartment($user)) {
                    $query->orWhere('department_id', $user->department_id);
                }
                $query->orWhereHas('applications', $this->visibleApplicationsQuery($user));
            })
            ->where('status', 'ouvert')
            ->count();

        // Nombre de candidatures en attente d'évaluation
        $pendingEvaluations = Application::where($visibleApplicationsQuery)
            ->whereHas('currentStatus', function ($query) {
                $query->whereIn('name', ['Reçue', 'En cours', 'Entretien technique', 'Entretien RH']);
            })
            ->count();

        // Nombre d'entretiens à venir (cette semaine)
        $upcomingEvents = Event::where($this->visibleEventsQuery($user))
            ->where('start_datetime', '>=', now())
            ->where('start_datetime', '<=', now()->addDays(7))
            ->count();

        // Nombre total de candidatures à évaluer
        $toEvaluate = Application::where($visibleApplicationsQuery)
            ->count();

        return response()->json([
            'myPosts' => $myPosts,
            'pendingEvaluations' => $pendingEvaluations,
            'upcomingEvents' => $upcomingEvents,
            'toEvaluate' => $toEvaluate
        ]);
    }

   /**
 * Récupérer les postes du consultant - VERSION CORRIGÉE
 */
public function getPosts()
{
    try {
        $user = Auth::user();
        
        if (!$this->hasRole($user, ['consultant', 'admin', 'manager'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        if ($this->managerNeedsDepartment($user)) {
            return response()->json([]);
        }

        $posts = Post::where(function (Builder $query) use ($user) {
                $query->where('created_by', $user->id);
                if ($this->userHasDepartment($user)) {
                    $query->orWhere('department_id', $user->department_id);
                }
                $query->orWhereHas('applications', $this->visibleApplicationsQuery($user));
            })
            ->with('department', 'contractType')
            ->get()
            ->map(function ($post) {
                // ✅ CORRECTION : Compter les candidatures sans erreur
                $applicationsCount = $post->applications()->count();
                
                // ✅ CORRECTION : Compter les entretiens de manière sécurisée
                try {
                    // Méthode 1 : Vérifier si la relation existe
                    $interviewsCount = 0;
                    if (method_exists($post, 'applications') && $post->applications()->exists()) {
                        // Compter les applications qui ont des événements
                        $interviewsCount = $post->applications()
                            ->whereHas('events', function($q) {
                                $q->whereNotNull('id');
                            })
                            ->count();
                    }
                } catch (\Exception $e) {
                    Log::warning('Erreur comptage entretiens: ' . $e->getMessage());
                    $interviewsCount = 0;
                }

                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'department' => $post->department ? $post->department->name : 'N/A',
                    'contract' => $post->contractType ? $post->contractType->name : 'N/A',
                    'status' => $post->status,
                    'candidates' => $applicationsCount,
                    'interviews' => $interviewsCount,
                    'created_at' => $post->created_at->format('Y-m-d')
                ];
            });

        return response()->json($posts);
        
    } catch (\Exception $e) {
        Log::error('Erreur getPosts: ' . $e->getMessage());
        
        // Retourner un tableau vide en cas d'erreur
        return response()->json([]);
    }
}
    /**
     * Récupérer les candidatures à évaluer
     */
    public function getCandidatesToEvaluate()
    {
        $user = Auth::user();
        
        // ✅ CORRECTION #3: Vérification unifiée
        if (!$this->hasRole($user, ['consultant', 'admin', 'manager'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        if ($this->managerNeedsDepartment($user)) {
            return response()->json([]);
        }

        $visibleApplicationsQuery = $this->visibleApplicationsQuery($user);

        $candidates = Application::where($visibleApplicationsQuery)
            ->with(['candidate', 'post', 'currentStatus', 'events.report'])
            ->get()
            ->map(function ($application) {
                $statusLabel = $application->currentStatus ? ($application->currentStatus->label ?? $application->currentStatus->name) : $this->mapApplicationStatus($application->status);
                
                $evaluation = null;
                $event = $application->events()->first();
                if ($event && $event->report) {
                    $report = $event->report;
                    $recommendation = $report->recommendation ?? 'reserve';
                    
                    $notes = $report->evaluation_notes ?? '';
                    
                    preg_match('/technique[s]?\s*[:•-]\s*(\d)\/5/i', $notes, $techMatch);
                    preg_match('/communication\s*[:•-]\s*(\d)\/5/i', $notes, $commMatch);
                    preg_match('/motivation\s*[:•-]\s*(\d)\/5/i', $notes, $motMatch);
                    preg_match('/culture\s*[:•-]\s*(\d)\/5/i', $notes, $cultureMatch);

                    if (!isset($techMatch[1])) {
                        preg_match_all('/(\d)\/5/', $notes, $allNumbers);
                        if (count($allNumbers[1]) >= 4) {
                            $techMatch[1] = $allNumbers[1][0] ?? null;
                            $commMatch[1] = $allNumbers[1][1] ?? null;
                            $motMatch[1] = $allNumbers[1][2] ?? null;
                            $cultureMatch[1] = $allNumbers[1][3] ?? null;
                        }
                    }

                    $evaluation = [
                        'recommendation' => $recommendation,
                        'strengths' => $report->strengths,
                        'weaknesses' => $report->weaknesses,
                        'comment' => $notes,
                        'technical' => isset($techMatch[1]) ? (int) $techMatch[1] : null,
                        'communication' => isset($commMatch[1]) ? (int) $commMatch[1] : null,
                        'motivation' => isset($motMatch[1]) ? (int) $motMatch[1] : null,
                        'culture' => isset($cultureMatch[1]) ? (int) $cultureMatch[1] : null,
                        'event_id' => $event->id,
                        'report_id' => $report->id
                    ];
                }

                return [
                    'id' => $application->id,
                    'candidate_id' => $application->candidate_id,
                    'name' => optional($application->candidate)->full_name ?? 'N/A',
                    'position' => optional($application->post)->title ?? 'N/A',
                    'status' => $statusLabel,
                    'date' => $application->created_at->format('Y-m-d'),
                    'application_id' => $application->id,
                    'evaluation' => $evaluation
                ];
            });

        return response()->json($candidates);
    }

    /**
     * Récupérer les événements du consultant
     */
    public function getEvents()
    {
        $user = Auth::user();
        
        // ✅ CORRECTION #4: Vérification unifiée
        if (!$this->hasRole($user, ['consultant', 'admin', 'manager'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        if ($this->managerNeedsDepartment($user)) {
            return response()->json([]);
        }

        $events = Event::where($this->visibleEventsQuery($user))
            ->with(['candidate', 'application.post'])
            ->orderBy('start_datetime', 'asc')
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $this->mapEventType($event->event_type),
                    'candidate' => ($event->candidate ? $event->candidate->full_name : 'N/A'),
                    'position' => ($event->application && $event->application->post) ? $event->application->post->title : '',
                    'datetime' => optional($event->start_datetime) ? $event->start_datetime->format('Y-m-d H:i') : null,
                    'location' => $event->location,
                    'status' => $event->status,
                    'notes' => $event->notes
                ];
            });

        return response()->json($events);
    }

    /**
     * Récupérer les données de performance
     */
    public function getPerformance()
    {
        $user = Auth::user();
        
        // ✅ CORRECTION #5: Vérification unifiée
        if (!$this->hasRole($user, ['consultant', 'admin', 'manager'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        if ($this->managerNeedsDepartment($user)) {
            return response()->json([]);
        }

        $visibleApplicationsQuery = $this->visibleApplicationsQuery($user);

        $stats = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->format('M');

            $evaluated = Application::where($visibleApplicationsQuery)
                ->whereMonth('updated_at', $date->month)
                ->whereYear('updated_at', $date->year)
                ->whereHas('currentStatus', function ($query) {
                    $query->whereIn('name', ['Entretien RH', 'Entretien technique', 'Accepté', 'Acceptée', 'Refusé', 'Refusée']);
                })
                ->count();

            $accepted = Application::where($visibleApplicationsQuery)
                ->whereMonth('updated_at', $date->month)
                ->whereYear('updated_at', $date->year)
                ->whereHas('currentStatus', function ($query) {
                    $query->whereIn('name', ['Accepté', 'Acceptée']);
                })
                ->count();

            $stats[] = [
                'month' => $month,
                'evaluated' => $evaluated,
                'accepted' => $accepted
            ];
        }

        return response()->json($stats);
    }

    /**
     * Soumettre une évaluation
     */
    public function submitEvaluation(Request $request, $applicationId)
    {
        $user = Auth::user();
        
        // ✅ CORRECTION #6: Vérification unifiée
        if (!$this->hasRole($user, ['consultant', 'admin', 'manager'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $application = Application::findOrFail($applicationId);

        if (!$this->applicationIsAssignedTo($application, $user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'technical' => 'required|integer|between:0,5',
            'communication' => 'required|integer|between:0,5',
            'motivation' => 'required|integer|between:0,5',
            'culture' => 'required|integer|between:0,5',
            'recommendation' => 'required|in:favorable,reserve,defavorable',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'comment' => 'nullable|string'
        ]);

        $scores = [
            $validated['technical'],
            $validated['communication'],
            $validated['motivation'],
            $validated['culture']
        ];
        $average = round(array_sum($scores) / count($scores), 2);
        
        $evaluationNotes = "Score moyen: {$average}/5\n";
        $evaluationNotes .= "Technique: {$validated['technical']}/5\n";
        $evaluationNotes .= "Communication: {$validated['communication']}/5\n";
        $evaluationNotes .= "Motivation: {$validated['motivation']}/5\n";
        $evaluationNotes .= "Culture: {$validated['culture']}/5";
        
        if ($validated['comment']) {
            $evaluationNotes .= "\n\nCommentaire: {$validated['comment']}";
        }

        $event = $application->events()->first();
        if (!$event) {
            $event = Event::create([
                'application_id' => $application->id,
                'candidate_id' => $application->candidate_id,
                'event_type' => 'autre',
                'title' => 'Évaluation consultant',
                'status' => 'termine',
                'start_datetime' => now(),
                'end_datetime' => now()->addHour(),
                'location_type' => 'presentiel',
                'location' => 'Bureau',
                'created_by' => $user->id,
                'description' => 'Évaluation effectuée par consultant'
            ]);
        }

        $report = InterviewReport::updateOrCreate(
            ['event_id' => $event->id],
            [
                'evaluation_notes' => $evaluationNotes,
                'strengths' => $validated['strengths'] ?? null,
                'weaknesses' => $validated['weaknesses'] ?? null,
                'recommendation' => $validated['recommendation'],
                'created_by' => $user->id
            ]
        );

        $currentStatus = $application->currentStatus;
        $nextStatusName = null;

        if ($validated['recommendation'] === 'favorable') {
            $nextStatusName = 'Entretien technique';
        } elseif ($validated['recommendation'] === 'defavorable') {
            $nextStatusName = 'Refusée';
        }

        if ($nextStatusName) {
            $nextStatus = Statut::where('name', $nextStatusName)->first();
            if ($nextStatus) {
                $application->current_status_id = $nextStatus->id;
                $application->save();

                ApplicationStatusHistory::create([
                    'application_id' => $application->id,
                    'status' => $nextStatusName,
                    'changed_by' => $user->id,
                    'notes' => "Évaluation consultant: {$validated['recommendation']}"
                ]);
            }
        }

        return response()->json([
            'message' => 'Évaluation enregistrée avec succès',
            'application' => $application,
            'report' => $report
        ]);
    }

    /**
     * Récupérer les entretiens du consultant (invitations)
     */
    public function getInterviews()
    {
        $user = Auth::user();
        
        // ✅ CORRECTION #7: Vérification unifiée
        if (!$this->hasRole($user, ['consultant', 'admin', 'manager'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $interviews = Event::whereHas('participants', function (Builder $query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->with(['candidate', 'application.post', 'participants.user', 'report', 'creator'])
            ->orderBy('start_datetime', 'desc')
            ->get()
            ->map(function ($event) {
                $currentUserParticipant = $event->participants()->where('user_id', auth()->id())->first();
                $candidate = $event->candidate;
                
                return [
                    'id' => $event->id,
                    'title' => $this->mapEventType($event->event_type) ?? 'Entretien',
                    'candidate' => $candidate ? [
                        'id' => $candidate->id,
                        'name' => $candidate->full_name ?? ($candidate->first_name . ' ' . $candidate->last_name),
                        'first_name' => $candidate->first_name ?? null,
                        'last_name' => $candidate->last_name ?? null,
                    ] : null,
                    'candidat' => $candidate ? ($candidate->full_name ?? ($candidate->first_name . ' ' . $candidate->last_name)) : null,
                    'position' => $event->application && $event->application->post ? $event->application->post->title : null,
                    'post_title' => $event->application && $event->application->post ? $event->application->post->title : null,
                    'application_id' => $event->application_id,
                    'type_entretien' => $event->event_type,
                    'event_type' => $event->event_type,
                    'start_datetime' => $event->start_datetime?->format('Y-m-d H:i'),
                    'datetime' => $event->start_datetime?->format('Y-m-d H:i'),
                    'location_type' => $event->location_type,
                    'location' => $event->location,
                    'meeting_link' => $event->meeting_link,
                    'phone_number' => $event->phone_number,
                    'description' => $event->description,
                    'status' => $event->status,
                    'statut' => $event->status,
                    'notes' => $event->notes,
                    'report' => $event->report,
                    'compte_rendu' => $event->report,
                    'participant_response' => $currentUserParticipant ? $currentUserParticipant->invitation_status : 'pending',
                    'is_organizer' => $currentUserParticipant ? $currentUserParticipant->is_organizer : false,
                ];
            });

        return response()->json($interviews);
    }

    /**
     * Répondre à une invitation d'entretien
     */
    public function respondToInterview(Request $request, $interviewId)
    {
        $user = Auth::user();
        
        // ✅ CORRECTION #8: Vérification unifiée
        if (!$this->hasRole($user, ['consultant', 'admin', 'manager'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $validated = $request->validate([
            'response' => 'required|in:accept,refuse,tentative',
        ]);

        $event = Event::findOrFail($interviewId);

        $participant = $event->participants()->where('user_id', $user->id)->first();
        
        if (!$participant) {
            return response()->json(['message' => 'Vous n\'êtes pas participant à cet entretien.'], 403);
        }

        $statusMap = [
            'accept' => 'accepted',
            'refuse' => 'declined',
            'tentative' => 'pending',
        ];

        $participant->update([
            'invitation_status' => $statusMap[$validated['response']]
        ]);

        return response()->json([
            'message' => 'Réponse enregistrée avec succès',
            'event' => $event->load(['participants.user', 'candidate', 'application.post', 'report']),
            'participant_response' => $statusMap[$validated['response']]
        ]);
    }

    public function finalDecision(Request $request, $applicationId)
    {
        $user = Auth::user();

        if (!$this->hasRole($user, ['admin', 'manager'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $application = Application::findOrFail($applicationId);

        if (!$this->applicationIsAssignedTo($application, $user)) {
            return response()->json(['message' => 'Vous n\'avez pas accès à cette candidature.'], 403);
        }

        $validated = $request->validate([
            'decision' => 'required|in:accepted,rejected,acceptée,refusée,acceptee,refusee',
            'notes' => 'nullable|string',
        ]);

        $accepted = in_array($validated['decision'], ['accepted', 'acceptée', 'acceptee'], true);
        $statusName = $accepted ? 'Acceptée' : 'Refusée';
        $status = Statut::whereIn('name', $accepted ? ['Acceptée', 'Accepté'] : ['Refusée', 'Refusé'])->first();

        if ($status) {
            $application->current_status_id = $status->id;
            $application->save();
        }

        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'status' => $statusName,
            'changed_by' => $user->id,
            'notes' => $validated['notes'] ?? "Décision manager: {$statusName}",
        ]);

        return response()->json([
            'message' => 'Décision finale enregistrée',
            'application' => $application->fresh(['candidate', 'post', 'currentStatus']),
        ]);
    }

    /**
     * Map des statuts d'application
     */
    private function mapApplicationStatus($status)
    {
        $mapping = [
            'received' => 'Reçue',
            'reçue' => 'Reçue',
            'phone_screening' => 'En cours',
            'en_cours' => 'En cours',
            'technical_interview' => 'Entretien technique',
            'entretien_technique' => 'Entretien technique',
            'hr_interview' => 'Entretien RH',
            'entretien_rh' => 'Entretien RH',
            'final_interview' => 'Entretien final',
            'entretien_final' => 'Entretien final',
            'accepted' => 'Acceptée',
            'acceptée' => 'Acceptée',
            'rejected' => 'Refusée',
            'refusée' => 'Refusée',
        ];
        return $mapping[$status] ?? $status;
    }

    /**
     * Map des types d'événements
     */
    private function mapEventType($type)
    {
        $mapping = [
            'technical' => 'technique',
            'hr' => 'rh',
            'committee' => 'comite',
            'final' => 'final',
            'telephonique' => 'telephonique',
            'metier' => 'metier',
            'comite' => 'comite',
            'autre' => 'autre',
        ];
        
        if (!isset($mapping[$type])) {
            Log::warning('Unknown event type: ' . $type);
            return 'autre';
        }
        
        return $mapping[$type];
    }

    /**
     * Calculer la note moyenne
     */
    private function calculateRating($evaluation)
    {
        if (!$evaluation) return 0;
        $scores = array_filter([
            $evaluation['technical'] ?? 0,
            $evaluation['communication'] ?? 0,
            $evaluation['motivation'] ?? 0,
            $evaluation['culture'] ?? 0,
        ]);
        return count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
    }

    /**
     * Query pour les applications visibles
     */
    private function visibleApplicationsQuery($user): callable
    {
        $values = $this->assignmentValues($user);

        return function (Builder $query) use ($user, $values) {
            if (Schema::hasColumn('applications', 'assigned_to')) {
                $query->whereIn('assigned_to', $values);
            }
            
            $query->orWhereHas('events.participants', function (Builder $participantQuery) use ($user) {
                $participantQuery->where('user_id', $user->id);
            })
            ->orWhereHas('post', function (Builder $postQuery) use ($user) {
                $postQuery->where('created_by', $user->id);
                if ($this->userHasDepartment($user)) {
                    $postQuery->orWhere('department_id', $user->department_id);
                }
            });
        };
    }

    /**
     * Query pour les événements visibles
     */
    private function visibleEventsQuery($user): callable
    {
        return function (Builder $query) use ($user) {
            $query->whereHas('participants', function (Builder $participantQuery) use ($user) {
                $participantQuery->where('user_id', $user->id);
            })->orWhereHas('application', $this->visibleApplicationsQuery($user));
        };
    }

    /**
     * Vérifier si une application est assignée au consultant
     */
    private function applicationIsAssignedTo(Application $application, $user): bool
    {
        if (in_array((string) $application->assigned_to, $this->assignmentValues($user), true)) {
            return true;
        }

        if ($application->events()->whereHas('participants', function (Builder $query) use ($user) {
            $query->where('user_id', $user->id);
        })->exists()) {
            return true;
        }

        return $application->post()
            ->where(function (Builder $query) use ($user) {
                $query->where('created_by', $user->id);
                if ($this->userHasDepartment($user)) {
                    $query->orWhere('department_id', $user->department_id);
                }
            })
            ->exists();
    }

    /**
     * Valeurs d'assignation pour les requêtes
     */
    private function assignmentValues($user): array
    {
        $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return array_values(array_unique(array_filter([
            (string) $user->id,
            $fullName,
            $user->username ?? null,
            $user->email ?? null,
        ])));
    }

    /**
     * ✅ CORRECTION #9: Vérification des rôles (la plus importante !)
     * Maintenant vérifie à la fois le champ 'role' ET 'role_id'
     */
    private function hasRole($user, array $allowedRoles): bool
    {
        if (!$user) return false;
        
        // 1. Vérifier par role_id (prioritaire)
        if (isset($user->role_id) && !is_null($user->role_id)) {
            // Mapping role_id vers nom de rôle
            $roleIdToName = [
                1 => 'admin',
                2 => 'assistant',
                3 => 'consultant',
                4 => 'manager',
                5 => 'direction',
            ];
            
            $roleNameFromId = $roleIdToName[$user->role_id] ?? null;
            
            if ($roleNameFromId && in_array($roleNameFromId, $allowedRoles)) {
                Log::info('hasRole - ACCEPTÉ via role_id', [
                    'user_id' => $user->id,
                    'role_id' => $user->role_id,
                    'role_name' => $roleNameFromId
                ]);
                return true;
            }
        }
        
        // 2. Fallback: vérifier par le champ 'role' textuel
        $role = strtolower($user->role ?? '');
        
        $aliases = [
            'responsable rh' => 'admin',
            'assistant rh' => 'assistant',
            'directeur rh' => 'direction',
            'directeur' => 'direction',
        ];
        
        $normalized = $aliases[$role] ?? $role;
        
        $result = in_array($normalized, $allowedRoles, true);
        
        Log::info('hasRole - Résultat', [
            'user_id' => $user->id,
            'user_role_text' => $role,
            'user_role_id' => $user->role_id ?? 'NULL',
            'normalized' => $normalized,
            'allowed_roles' => $allowedRoles,
            'result' => $result ? 'ACCEPTÉ' : 'REFUSÉ'
        ]);
        
        return $result;
    }

    /**
     * Vérifier si l'utilisateur a un département
     */
    private function userHasDepartment($user): bool
    {
        return Schema::hasColumn('users', 'department_id') && !empty($user->department_id);
    }

    private function managerNeedsDepartment($user): bool
    {
        return (int) ($user->role_id ?? 0) === 4 && !$this->userHasDepartment($user);
    }

    private function emptyManagerPayload(): array
    {
        return [
            'myPosts' => 0,
            'pendingEvaluations' => 0,
            'upcomingEvents' => 0,
            'toEvaluate' => 0,
            'needsDepartment' => true,
            'message' => 'Aucun département assigné. Le dashboard sera activé après assignation par un administrateur.',
        ];
    }
}
