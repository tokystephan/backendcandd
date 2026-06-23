<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DirectionDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:5'); // Direction role_id = 5
    }
    
    /**
     * Dashboard principal - Vue globale
     */
    public function dashboard(Request $request)
    {
        $user = Auth::user();
        
        try {
            // Statistiques globales
            $totalApplications = Application::count();
            $totalCandidates = \App\Models\Candidate::count();
            $totalPosts = Post::count();
            $openPosts = Post::where('status', 'ouvert')->count();
            $activeUsers = User::where('is_active', true)->count();
            
            // Statistiques par statut
            $received = Application::where('current_status_id', 1)->count();
            $inProgress = Application::where('current_status_id', 2)->count();
            $hrInterview = Application::where('current_status_id', 3)->count();
            $techInterview = Application::where('current_status_id', 4)->count();
            $accepted = Application::where('current_status_id', 5)->count();
            $rejected = Application::where('current_status_id', 6)->count();
            
            // Taux de conversion
            $conversionRate = $totalApplications > 0 
                ? round(($accepted / $totalApplications) * 100, 2) 
                : 0;
            
            // Entretiens à venir
            $upcomingInterviews = Event::where('start_datetime', '>', now())
                ->where('status', 'planifie')
                ->count();
            
            // Entretiens aujourd'hui
            $todayInterviews = Event::whereDate('start_datetime', today())->count();
            
            // Candidatures ce mois
            $applicationsThisMonth = Application::whereMonth('created_at', now()->month)->count();
            
            return response()->json([
                'success' => true,
                'stats' => [
                    'total_applications' => $totalApplications,
                    'total_candidates' => $totalCandidates,
                    'total_posts' => $totalPosts,
                    'open_posts' => $openPosts,
                    'active_users' => $activeUsers,
                    'received' => $received,
                    'in_progress' => $inProgress,
                    'hr_interview' => $hrInterview,
                    'tech_interview' => $techInterview,
                    'accepted' => $accepted,
                    'rejected' => $rejected,
                    'conversion_rate' => $conversionRate,
                    'upcoming_interviews' => $upcomingInterviews,
                    'today_interviews' => $todayInterviews,
                    'applications_this_month' => $applicationsThisMonth,
                ],
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role_name' => 'direction',
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('DirectionDashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du dashboard'
            ], 500);
        }
    }
    
    /**
     * Statistiques par département
     */
    public function departmentStats(Request $request)
    {
        try {
            $departments = Department::withCount(['posts', 'applications'])
                ->get()
                ->map(function ($dept) {
                    // Calculer les candidatures acceptées par département
                    $acceptedCount = Application::whereHas('post', function($q) use ($dept) {
                        $q->where('department_id', $dept->id);
                    })->where('current_status_id', 5)->count();
                    
                    $totalCount = $dept->applications_count;
                    $conversionRate = $totalCount > 0 ? round(($acceptedCount / $totalCount) * 100, 2) : 0;
                    
                    return [
                        'id' => $dept->id,
                        'name' => $dept->name,
                        'posts_count' => $dept->posts_count,
                        'applications_count' => $totalCount,
                        'accepted_count' => $acceptedCount,
                        'conversion_rate' => $conversionRate,
                    ];
                });
            
            return response()->json([
                'success' => true,
                'departments' => $departments
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement'
            ], 500);
        }
    }
    
    /**
     * Statistiques par recruteur
     */
    public function recruiterStats(Request $request)
    {
        try {
            $recruiters = User::whereIn('role_id', [1, 2, 3, 4])
                ->withCount(['applications' => function($q) {
                    $q->where('created_by', DB::raw('users.id'));
                }])
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => trim($user->first_name . ' ' . $user->last_name),
                        'email' => $user->email,
                        'role_name' => $user->role->name ?? 'Inconnu',
                        'applications_count' => $user->applications_count ?? 0,
                    ];
                });
            
            return response()->json([
                'success' => true,
                'recruiters' => $recruiters
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement'
            ], 500);
        }
    }
    
    /**
     * Évolution des recrutements (mois par mois)
     */
    public function recruitmentTrend(Request $request)
    {
        try {
            $last12Months = collect(range(0, 11))->map(function ($i) {
                $date = now()->subMonths($i);
                return [
                    'month' => $date->format('Y-m'),
                    'month_name' => $date->format('M Y'),
                    'count' => Application::whereYear('created_at', $date->year)
                        ->whereMonth('created_at', $date->month)
                        ->count(),
                ];
            })->reverse()->values();
            
            return response()->json([
                'success' => true,
                'trend' => $last12Months
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement'
            ], 500);
        }
    }
    
    /**
     * Statistiques globales (export)
     */
    public function globalStats(Request $request)
    {
        try {
            // Statistiques par statut (pour graphique)
            $statusStats = DB::table('applications')
                ->join('statuses', 'applications.current_status_id', '=', 'statuses.id')
                ->select('statuses.name', DB::raw('count(*) as total'))
                ->groupBy('statuses.id', 'statuses.name')
                ->get();
            
            // Statistiques par source
            $sourceStats = DB::table('applications')
                ->join('sources', 'applications.source_id', '=', 'sources.id')
                ->select('sources.name', DB::raw('count(*) as total'))
                ->groupBy('sources.id', 'sources.name')
                ->get();
            
            return response()->json([
                'success' => true,
                'by_status' => $statusStats,
                'by_source' => $sourceStats,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement'
            ], 500);
        }
    }

    /**
     * ✅ NEW: Statistiques complètes pour le dashboard Direction
     * Accepte un paramètre 'period': month, quarter, year
     */
    public function directionStatistics(Request $request)
    {
        try {
            $period = $request->query('period', 'month');
            $user = Auth::user();

            // Déterminer la plage de dates selon la période
            $startDate = match($period) {
                'quarter' => now()->startOfQuarter(),
                'year' => now()->startOfYear(),
                default => now()->startOfMonth(),
            };

            // KPIs principaux
            $totalApplications = Application::count();
            $totalApplicationsPeriod = Application::where('created_at', '>=', $startDate)->count();
            
            // Candidatures par statut
            $applicationsByStatus = DB::table('applications')
                ->join('statuses', 'applications.current_status_id', '=', 'statuses.id')
                ->select('statuses.name as status', DB::raw('count(*) as count'))
                ->groupBy('statuses.id', 'statuses.name')
                ->get();

            // Candidatures par département
            $applicationsByDepartment = DB::table('applications')
                ->join('posts', 'applications.post_id', '=', 'posts.id')
                ->join('departments', 'posts.department_id', '=', 'departments.id')
                ->select('departments.name as department', DB::raw('count(*) as count'))
                ->groupBy('departments.id', 'departments.name')
                ->get();

            // Candidatures par source
            $applicationsBySource = DB::table('applications')
                ->join('sources', 'applications.source_id', '=', 'sources.id')
                ->select('sources.name as source', DB::raw('count(*) as count'))
                ->groupBy('sources.id', 'sources.name')
                ->get();

            // Tendance utilisée par le graphe: candidatures + recrutements acceptés.
            $monthlyTrend = $this->buildRecruitmentTrend($period);

            // Taux de conversion
            $acceptedCount = Application::whereHas('currentStatus', function ($q) {
                $q->whereIn('name', ['Accepté', 'Acceptée', 'Acceptées']);
            })->count();
            $successRate = $totalApplications > 0 ? round(($acceptedCount / $totalApplications) * 100, 2) : 0;

            // Postes ouverts
            $openPosts = Post::where('status', 'ouvert')->count();

            // Moyenne de délai (en jours)
            $averageDelay = DB::table('applications')
                ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_delay')
                ->value('avg_delay');
            $averageDelay = round($averageDelay ?? 0, 1);

            return response()->json([
                'success' => true,
                'totalApplications' => $totalApplications,
                'totalApplicationsPeriod' => $totalApplicationsPeriod,
                'totalRecruitments' => $acceptedCount,
                'pending' => Application::whereIn('current_status_id', [1, 2])->count(),
                'successRate' => $successRate,
                'openPosts' => $openPosts,
                'averageDelay' => $averageDelay,
                'applicationsByStatus' => $applicationsByStatus,
                'applicationsByDepartment' => $applicationsByDepartment,
                'applicationsBySource' => $applicationsBySource,
                'monthlyTrend' => $monthlyTrend,
                'pendingValidations' => [],
                'recentRecruitments' => Application::latest()->limit(5)->get(),
            ]);

        } catch (\Exception $e) {
            Log::error('Direction statistics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NEW: Exporte les statistiques direction
     */
    public function exportDirectionStats(Request $request)
    {
        try {
            $type = $request->input('type', 'applications');

            // Récupérer les données à exporter
            if ($type === 'applications') {
                $headers = ['ID', 'Candidat', 'Poste', 'Département', 'Statut', 'Créé le', 'Modifié le'];
                $data = Application::with(['post.department', 'candidate', 'currentStatus'])
                    ->get()
                    ->map(function ($app) {
                        return [
                            'ID' => $app->id,
                            'Candidat' => $app->candidate->full_name ?? 'N/A',
                            'Poste' => $app->post->title ?? 'N/A',
                            'Département' => $app->post->department->name ?? 'N/A',
                            'Statut' => $app->currentStatus->name ?? 'N/A',
                            'Créé le' => $app->created_at?->format('d/m/Y'),
                            'Modifié le' => $app->updated_at?->format('d/m/Y'),
                        ];
                    });

                // Créer le CSV
                $csv = fopen('php://memory', 'r+');
                fwrite($csv, "\xEF\xBB\xBF");
                fputcsv($csv, $headers, ';');
                foreach ($data as $row) {
                    fputcsv($csv, $row, ';');
                }
                rewind($csv);
                $content = stream_get_contents($csv);
                fclose($csv);

                return response($content, 200, [
                    'Content-Type' => 'text/csv; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="export_' . date('Y-m-d_His') . '.csv"',
                ]);
            }

            return response()->json(['message' => 'Type d\'export invalide'], 400);

        } catch (\Exception $e) {
            Log::error('Export error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export'
            ], 500);
        }
    }

    private function buildRecruitmentTrend(string $period)
    {
        $acceptedStatusNames = ['Accepté', 'Acceptée', 'Acceptées'];
        $now = now();

        if ($period === 'year') {
            return collect(range(4, 0))->map(function ($i) use ($acceptedStatusNames, $now) {
                $year = $now->copy()->subYears($i)->year;

                return [
                    'month' => (string) $year,
                    'month_name' => (string) $year,
                    'applications' => Application::whereYear('created_at', $year)->count(),
                    'recruitments' => Application::whereYear('created_at', $year)
                        ->whereHas('currentStatus', function ($q) use ($acceptedStatusNames) {
                            $q->whereIn('name', $acceptedStatusNames);
                        })
                        ->count(),
                ];
            })->values();
        }

        if ($period === 'quarter') {
            $currentQuarter = (int) ceil($now->month / 3);

            return collect(range(3, 0))->map(function ($i) use ($acceptedStatusNames, $now, $currentQuarter) {
                $quarter = $currentQuarter - $i;
                $year = $now->year;

                while ($quarter <= 0) {
                    $quarter += 4;
                    $year--;
                }

                $startMonth = (($quarter - 1) * 3) + 1;
                $endMonth = $startMonth + 2;

                $applicationsQuery = Application::whereYear('created_at', $year)
                    ->whereMonth('created_at', '>=', $startMonth)
                    ->whereMonth('created_at', '<=', $endMonth);

                return [
                    'month' => "T{$quarter} {$year}",
                    'month_name' => "T{$quarter} {$year}",
                    'applications' => (clone $applicationsQuery)->count(),
                    'recruitments' => (clone $applicationsQuery)
                        ->whereHas('currentStatus', function ($q) use ($acceptedStatusNames) {
                            $q->whereIn('name', $acceptedStatusNames);
                        })
                        ->count(),
                ];
            })->values();
        }

        return collect(range(11, 0))->map(function ($i) use ($acceptedStatusNames, $now) {
            $date = $now->copy()->subMonths($i);
            $applicationsQuery = Application::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month);

            return [
                'month' => $date->format('Y-m'),
                'month_name' => $date->format('M Y'),
                'applications' => (clone $applicationsQuery)->count(),
                'recruitments' => (clone $applicationsQuery)
                    ->whereHas('currentStatus', function ($q) use ($acceptedStatusNames) {
                        $q->whereIn('name', $acceptedStatusNames);
                    })
                    ->count(),
            ];
        })->values();
    }

    /**
     * ✅ NEW: Approuve une validation (Manager)
     */
    public function approveValidation(Request $request, $id)
    {
        try {
            // Placeholder pour la logique d'approbation
            return response()->json([
                'success' => true,
                'message' => 'Validation approuvée'
            ]);

        } catch (\Exception $e) {
            Log::error('Approve validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'approbation'
            ], 500);
        }
    }

    /**
     * ✅ NEW: Rejette une validation (Manager)
     */
    public function rejectValidation(Request $request, $id)
    {
        try {
            // Placeholder pour la logique de rejet
            return response()->json([
                'success' => true,
                'message' => 'Validation rejetée'
            ]);

        } catch (\Exception $e) {
            Log::error('Reject validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rejet'
            ], 500);
        }
    }
}
