<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Event;
use App\Models\Post;
use App\Models\Statut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class AssistantDashboardController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:2'); // Assistant role_id = 2
    }
    
    /**
     * ✅ Vérification du rôle (simplifiée et fiable)
     * Utilise role_id au lieu du nom
     */
    private function hasAccess($user): bool
    {
        if (!$user) return false;
        
        // Assistant (role_id=2) ou Admin (role_id=1)
        return in_array($user->role_id, [1, 2]);
    }
    
    /**
     * ✅ Dashboard principal
     */
    public function dashboard(Request $request)
    {
        $user = Auth::user();
        
        // Vérifier l'accès
        if (!$this->hasAccess($user)) {
            Log::warning('AssistantDashboard: Accès refusé', [
                'user_id' => $user->id ?? null,
                'role_id' => $user->role_id ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Vous devez avoir le rôle Assistant ou Admin.'
            ], 403);
        }
        
        try {
            // Statistiques de base
            $totalApplications = Application::count();
            $openPosts = Post::where('status', 'ouvert')->count();
            
            // Statistiques par statut (en utilisant current_status_id)
            $received = Application::where('current_status_id', 1)->count(); // Reçue
            $inProgress = Application::where('current_status_id', 2)->count(); // En cours
            $hrInterview = Application::where('current_status_id', 3)->count(); // Entretien RH
            $techInterview = Application::where('current_status_id', 4)->count(); // Entretien technique
            $accepted = Application::where('current_status_id', 5)->count(); // Acceptée
            $rejected = Application::where('current_status_id', 6)->count(); // Refusée
            
            // Entretiens
            $interviews = Event::where('status', 'planifie')->count();
            $todayEvents = Event::whereDate('start_datetime', now()->toDateString())->count();
            
            // Données pour le graphique
            $applicationsByStatus = [
                ['name' => 'Reçue', 'value' => $received, 'color' => '#22C55E'],
                ['name' => 'En cours', 'value' => $inProgress, 'color' => '#F59E0B'],
                ['name' => 'Entretien RH', 'value' => $hrInterview, 'color' => '#0EA5E9'],
                ['name' => 'Entretien technique', 'value' => $techInterview, 'color' => '#8B5CF6'],
                ['name' => 'Acceptée', 'value' => $accepted, 'color' => '#16A34A'],
                ['name' => 'Refusée', 'value' => $rejected, 'color' => '#DC2626'],
            ];
            
            Log::info('AssistantDashboard: Chargement réussi', [
                'user_id' => $user->id,
                'stats' => $totalApplications
            ]);
            
            return response()->json([
                'success' => true,
                'stats' => [
                    'totalApplications' => $totalApplications,
                    'received' => $received,
                    'inProgress' => $inProgress,
                    'hrInterview' => $hrInterview,
                    'techInterview' => $techInterview,
                    'accepted' => $accepted,
                    'rejected' => $rejected,
                    'interviews' => $interviews,
                    'openPosts' => $openPosts,
                    'todayEvents' => $todayEvents,
                ],
                'statusChart' => $applicationsByStatus,
            ]);
            
        } catch (\Exception $e) {
            Log::error('AssistantDashboard error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du dashboard: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ✅ Candidatures en attente
     */
    public function pendingApplications(Request $request)
    {
        $user = Auth::user();
        
        if (!$this->hasAccess($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.'
            ], 403);
        }
        
        try {
            $applications = Application::with(['candidate', 'post', 'currentStatus'])
                ->whereIn('current_status_id', [1, 2]) // Reçue (1) ou En cours (2)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($application) {
                    return [
                        'id' => $application->id,
                        'candidate_name' => $application->candidate 
                            ? trim($application->candidate->first_name . ' ' . $application->candidate->last_name) 
                            : 'Candidat inconnu',
                        'position' => $application->post 
                            ? $application->post->title 
                            : 'Poste inconnu',
                        'status' => $application->currentStatus 
                            ? ($application->currentStatus->name ?? 'N/A') 
                            : 'N/A',
                        'date' => $application->created_at ? $application->created_at->format('Y-m-d') : 'N/A',
                        'assigned_to' => $application->assigned_to ?? null,
                    ];
                });
            
            return response()->json([
                'success' => true,
                'applications' => $applications
            ]);
            
        } catch (\Exception $e) {
            Log::error('pendingApplications error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement'
            ], 500);
        }
    }
    
    /**
     * ✅ Agenda du jour
     */
    public function todayAgenda(Request $request)
    {
        $user = Auth::user();
        
        if (!$this->hasAccess($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.'
            ], 403);
        }
        
        try {
            $events = Event::with(['candidate', 'application.post'])
                ->whereDate('start_datetime', now()->toDateString())
                ->orderBy('start_datetime', 'asc')
                ->get()
                ->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'type' => $this->translateEventType($event->event_type),
                        'candidate' => $event->candidate 
                            ? trim($event->candidate->first_name . ' ' . $event->candidate->last_name) 
                            : 'Candidat inconnu',
                        'position' => optional($event->application->post)->title ?? 'Poste inconnu',
                        'time' => $event->start_datetime ? $event->start_datetime->format('H:i') : '',
                        'location' => $event->location ?? $event->meeting_link ?? $event->phone_number ?? 'Lieu non défini',
                        'status' => $event->status,
                    ];
                });
            
            return response()->json([
                'success' => true,
                'events' => $events
            ]);
            
        } catch (\Exception $e) {
            Log::error('todayAgenda error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement'
            ], 500);
        }
    }
    
    /**
     * ✅ Activités récentes
     */
    public function activities(Request $request)
    {
        $user = Auth::user();
        
        if (!$this->hasAccess($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.'
            ], 403);
        }
        
        try {
            $userFullName = trim($user->first_name . ' ' . $user->last_name);
            
            // Dernières candidatures créées par l'utilisateur
            $applications = Application::with('candidate')
                ->where('created_by', $user->id)
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($application) use ($userFullName) {
                    return [
                        'id' => 'app-' . $application->id,
                        'action' => 'Nouvelle candidature',
                        'candidate' => $application->candidate 
                            ? trim($application->candidate->first_name . ' ' . $application->candidate->last_name) 
                            : 'Candidat inconnu',
                        'user' => $userFullName,
                        'time' => $application->created_at->diffForHumans(),
                        'icon' => 'plus',
                        'created_at' => $application->created_at,
                    ];
                });
            
            // Derniers événements créés par l'utilisateur
            $events = Event::with('candidate')
                ->where('created_by', $user->id)
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($event) use ($userFullName) {
                    return [
                        'id' => 'event-' . $event->id,
                        'action' => 'Entretien planifié',
                        'candidate' => $event->candidate 
                            ? trim($event->candidate->first_name . ' ' . $event->candidate->last_name) 
                            : 'Candidat inconnu',
                        'user' => $userFullName,
                        'time' => $event->created_at->diffForHumans(),
                        'icon' => 'edit',
                        'created_at' => $event->created_at,
                    ];
                });
            
            // Fusionner et trier par date
            $activities = collect($applications)
                ->merge($events)
                ->sortByDesc('created_at')
                ->values()
                ->map(function ($item) {
                    unset($item['created_at']);
                    return $item;
                })
                ->take(10)
                ->all();
            
            return response()->json([
                'success' => true,
                'activities' => $activities
            ]);
            
        } catch (\Exception $e) {
            Log::error('activities error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement'
            ], 500);
        }
    }
    
    /**
     * ✅ Traiter une candidature (passer de Reçue à En cours)
     */
    public function processApplication($id)
    {
        $user = Auth::user();
        
        if (!$this->hasAccess($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.'
            ], 403);
        }
        
        try {
            $application = Application::with('currentStatus')->findOrFail($id);
            $currentStatusId = $application->current_status_id;
            
            // Vérifier que la candidature est dans un état traitable (Reçue ou En cours)
            if (!in_array($currentStatusId, [1, 2])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de traiter cette candidature. Statut actuel: ' . ($application->currentStatus->name ?? 'Inconnu')
                ], 400);
            }
            
            // Si la candidature est "Reçue", la passer à "En cours"
            if ($currentStatusId === 1) {
                $inProgressStatus = Statut::where('name', 'En cours')->first();
                if (!$inProgressStatus) {
                    // Créer le statut s'il n'existe pas
                    $inProgressStatus = Statut::create([
                        'name' => 'En cours',
                        'step_order' => 2,
                        'color' => '#F59E0B'
                    ]);
                }
                $application->current_status_id = $inProgressStatus->id;
            }
            
            $application->assigned_to = trim($user->first_name . ' ' . $user->last_name);
            $application->save();
            
            Log::info('Candidature traitée', [
                'application_id' => $id,
                'user_id' => $user->id,
                'new_status' => $application->current_status_id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Candidature traitée avec succès',
                'application' => [
                    'id' => $application->id,
                    'status_id' => $application->current_status_id,
                    'assigned_to' => $application->assigned_to
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('processApplication error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du traitement: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ✅ Traduire le type d'événement
     */
    private function translateEventType($type)
    {
        $map = [
            'telephonique' => 'Entretien téléphonique',
            'rh' => 'Entretien RH',
            'technique' => 'Entretien technique',
            'metier' => 'Entretien métier',
            'final' => 'Entretien final',
            'comite' => 'Entretien comité',
            'autre' => 'Autre entretien',
        ];
        
        return $map[$type] ?? ucfirst($type);
    }
}