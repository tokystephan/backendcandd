<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Post;
use App\Models\Event;
use App\Models\Source;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    /**
     * Vue d'ensemble – Cartes KPI pour le dashboard
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // 1. Total des candidatures
        $totalApplications = Application::count();
        
        // 2. Candidatures en attente (statut "Reçue" ou "En cours")
        $pending = Application::whereHas('currentStatus', function ($query) {
                $query->whereIn('name', ['Reçue', 'En cours']);
            })->count();
        
        // 3. Entretiens planifiés
        $scheduledInterviews = Event::where('status', 'planifie')->count();
        
        // 4. Taux de conversion (candidatures avec entretien réalisé)
        $completedInterviews = Event::where('status', 'termine')->count();
        $conversionRate = $totalApplications > 0 
            ? round(($completedInterviews / $totalApplications) * 100, 1) 
            : 0;
        
        // 5. Répartition par statut (pour le composant RecruitmentStats)
        $byStatus = $this->getStatusDistribution();
        
        // 6. Répartition par département (pour SourceStats)
        $byDepartment = $this->getDepartmentDistribution();
        
        // 7. Évolution récente (optionnel)
        $recentTrend = $this->getRecentTrend();
        
        return response()->json([
            'totalApplications' => $totalApplications,
            'pending' => $pending,
            'scheduledInterviews' => $scheduledInterviews,
            'conversionRate' => $conversionRate,
            'byStatus' => $byStatus,
            'byDepartment' => $byDepartment,
            'recentTrend' => $recentTrend,
        ]);
    }
    
    /**
     * Statistiques de recrutement (timeline ou top posts)
     * 
     * @param string $type - 'timeline' ou 'top-posts'
     * @return \Illuminate\Http\JsonResponse
     */
    public function recruitment($type)
    {
        if ($type === 'timeline') {
            // Évolution mensuelle des candidatures
            $timeline = $this->getMonthlyTimeline();
            return response()->json($timeline);
        }
        
        if ($type === 'top-posts') {
            // Top 5 postes les plus demandés
            $topPosts = $this->getTopPosts();
            return response()->json($topPosts);
        }
        
        return response()->json([]);
    }
    
    /**
     * Statistiques par source de candidature
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function sources()
    {
        $sources = Source::withCount('applications')
            ->where('is_active', true)
            ->orderBy('applications_count', 'desc')
            ->get()
            ->map(function($source) {
                return [
                    'id' => $source->id,
                    'name' => $source->name,
                    'count' => $source->applications_count,
                ];
            });
        
        return response()->json($sources);
    }
    
    /**
     * Statistiques par département
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function departments()
    {
        $departments = Department::withCount('posts')
            ->withCount('applications')
            ->get()
            ->map(function($department) {
                return [
                    'name' => $department->name,
                    'posts_count' => $department->posts_count,
                    'applications_count' => $department->applications_count,
                    'fill_rate' => $department->posts_count > 0 
                        ? round(($department->applications_count / $department->posts_count), 1)
                        : 0,
                ];
            });
        
        return response()->json($departments);
    }
    
    /**
     * Performance des recruteurs (utilisateurs)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function recruiters()
    {
        $recruiters = User::whereHas('role', function($query) {
                $query->whereIn('name', ['recruteur', 'assistant', 'admin']);
            })
            ->withCount(['applicationsCreated', 'eventsCreated'])
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'applications_count' => $user->applications_created_count,
                    'interviews_count' => $user->events_created_count,
                ];
            });
        
        return response()->json($recruiters);
    }
    
    // ========== MÉTHODES PRIVÉES (logique métier) ==========
    
    /**
     * Répartition des candidatures par statut
     * 
     * @return array
     */
    private function getStatusDistribution()
    {
        $statusMap = [
            'recue' => ['status' => 'Reçue', 'color' => '#22C55E'],
            'en_cours' => ['status' => 'En cours', 'color' => '#F59E0B'],
            'entretien_rh' => ['status' => 'Entretien RH', 'color' => '#10B981'],
            'entretien_technique' => ['status' => 'Entretien technique', 'color' => '#8B5CF6'],
            'acceptee' => ['status' => 'Acceptée', 'color' => '#06B6D4'],
            'refusee' => ['status' => 'Refusée', 'color' => '#EF4444'],
        ];

        return collect($statusMap)->map(function($meta, $status) {
            return [
                'status' => $meta['status'],
                'count' => Application::whereHas('currentStatus', function ($query) use ($meta) {
                        $query->where('name', $meta['status']);
                    })->count(),
                'color' => $meta['color'],
            ];
        })->values()->toArray();
    }
    
    /**
     * Répartition des candidatures par département
     * 
     * @return array
     */
    private function getDepartmentDistribution()
    {
        return Department::withCount('applications')
            ->orderBy('applications_count', 'desc')
            ->get()
            ->pluck('applications_count', 'name')
            ->toArray();
    }
    
    /**
     * Évolution mensuelle des candidatures sur l'année en cours
     * 
     * @return array
     */
    private function getMonthlyTimeline()
    {
        $currentYear = date('Y');
        
        $data = Application::select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as total')
            )
            ->whereYear('created_at', $currentYear)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');
        
        // Créer un tableau pour les 12 mois
        $timeline = [];
        for ($month = 1; $month <= 12; $month++) {
            $timeline[] = [
                'month' => $this->getMonthName($month),
                'total' => $data[$month]->total ?? 0,
            ];
        }
        
        return $timeline;
    }
    
    /**
     * Top 5 des postes les plus demandés
     * 
     * @return array
     */
    private function getTopPosts()
    {
        return Application::select('post_id', DB::raw('COUNT(*) as total'))
            ->with('post:id,title')
            ->groupBy('post_id')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get()
            ->map(function($item) {
                return [
                    'title' => $item->post->title ?? 'Poste inconnu',
                    'total' => $item->total,
                ];
            })
            ->toArray();
    }
    
    /**
     * Tendance récente (évolution vs mois précédent)
     * 
     * @return array
     */
    private function getRecentTrend()
    {
        $currentMonth = Application::whereMonth('created_at', now()->month)->count();
        $previousMonth = Application::whereMonth('created_at', now()->subMonth()->month)->count();
        
        $percentage = $previousMonth > 0 
            ? round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1)
            : ($currentMonth > 0 ? 100 : 0);
        
        return [
            'percentage' => $percentage,
            'direction' => $percentage >= 0 ? 'up' : 'down',
        ];
    }
    
    /**
     * Couleur associée à un statut (si non définie en base)
     * 
     * @param string $statusName
     * @return string
     */
    private function getStatusColor($statusName)
    {
        $colors = [
            'Reçue' => '#6366f1',
            'En cours de traitement' => '#f59e0b',
            'Entretien RH' => '#10b981',
            'Entretien technique' => '#8b5cf6',
            'Acceptée' => '#06b6d4',
            'Refusée' => '#ef4444',
        ];
        
        return $colors[$statusName] ?? '#64748b';
    }
    
    /**
     * Nom du mois en français (abréviation)
     * 
     * @param int $month
     * @return string
     */
    private function getMonthName($month)
    {
        $months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        return $months[$month - 1];
    }
}
