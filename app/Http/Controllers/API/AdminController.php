<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Post;
use App\Models\Candidate;
use App\Models\User;
use App\Models\Source;
use App\Models\Department;
use App\Models\ContractType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Statut;
use App\Services\NotificationService;

class AdminController extends Controller
{
    /**
     * Tableau de bord administrateur
     */
    public function dashboard(Request $request)
    {
        $period = $request->get('period', 'month');

        $statusLabelMap = [
            'recue' => 'Reçue',
            'en_cours' => 'En cours',
            'entretien_rh' => 'Entretien RH',
            'entretien_technique' => 'Entretien technique',
            'acceptee' => 'Acceptée',
            'refusee' => 'Refusée',
        ];

        $totalApplications = Application::count();
        $received = Application::whereHas('currentStatus', function ($q) {
            $q->where('name', 'Reçue');
        })->count();
        $inProgress = Application::whereHas('currentStatus', function ($q) {
            $q->where('name', 'En cours');
        })->count();
        $accepted = Application::whereHas('currentStatus', function ($q) {
            $q->whereIn('name', ['Accepté', 'Acceptée', 'Acceptées']);
        })->count();
        $rejected = Application::whereHas('currentStatus', function ($q) {
            $q->whereIn('name', ['Refusé', 'Refusée', 'Refusées']);
        })->count();

        $stats = [
            'totalApplications' => $totalApplications,
            'received'          => $received,
            'inProgress'        => $inProgress,
            'accepted'          => $accepted,
            'rejected'          => $rejected,
            'openPosts'         => Post::where('status', 'ouvert')->count(),
            'totalCandidates'   => Candidate::count(),
            'activeUsers'       => User::where('is_active', true)->count(),
        ];

        // Évolution mensuelle (12 derniers mois)
        $monthlyTrend = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $year = $date->year;
            $month = $date->month;

            $applicationsCount = Application::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();

            $recruitmentsCount = Application::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereHas('currentStatus', function ($q) {
                    $q->whereIn('name', ['Accepté', 'Acceptée', 'Acceptées']);
                })->count();

            $monthlyTrend[] = [
                'month'        => $date->format('M'),
                'applications' => $applicationsCount,
                'recruitments' => $recruitmentsCount,
            ];
        }

        // Répartition par statut (pour le graphique à barres)
        $applicationsByStatus = [
            ['name' => 'Reçue', 'value' => $received, 'color' => '#22C55E'],
            ['name' => 'En cours', 'value' => $inProgress, 'color' => '#F59E0B'],
            ['name' => 'Acceptée', 'value' => $accepted, 'color' => '#2E7D32'],
            ['name' => 'Refusée', 'value' => $rejected, 'color' => '#EF4444'],
        ];

        // Répartition par département
        $departments = Department::withCount('posts')->get();
        $applicationsByDepartment = [];
        foreach ($departments as $dept) {
            $applicationsByDepartment[] = [
                'name'  => $dept->name,
                'value' => Application::whereHas('post', function ($q) use ($dept) {
                    $q->where('department_id', $dept->id);
                })->count(),
                'color' => '#2A5C8E',
            ];
        }

        // Dernières candidatures (5)
        $recentApplications = Application::with(['candidate', 'post', 'currentStatus'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function($app) {
                return [
                    'id'             => $app->id,
                    'candidate_name' => $app->candidate ? $app->candidate->first_name . ' ' . $app->candidate->last_name : 'N/A',
                    'position'       => $app->post ? $app->post->title : 'N/A',
                    'status'         => $app->currentStatus ? $app->currentStatus->name : 'N/A',
                    'date'           => $app->created_at->format('Y-m-d'),
                ];
            });

        // Derniers postes (5) – ✅ corrigé (fn → function)
        $recentPosts = Post::with(['department', 'contractType'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function($post) {
                return [
                    'id'          => $post->id,
                    'title'       => $post->title,
                    'department'  => $post->department ? $post->department->name : 'N/A',
                    'contract'    => $post->contractType ? $post->contractType->name : 'N/A',
                    'status'      => $post->status,
                    'candidates'  => $post->applications()->count(),
                    'created_at'  => $post->created_at->format('d/m/Y'),
                ];
            });

        // Sources de candidature (pour le dashboard)
        $sources = Source::all();
        $sourceChartData = [];
        foreach ($sources as $source) {
            $count = Application::where('source_id', $source->id)->count();
            if ($count > 0) {
                $sourceChartData[] = [
                    'name'       => $source->name,
                    'value'      => $count,
                    'percentage' => $totalApplications ? round(($count / $totalApplications) * 100) : 0,
                ];
            }
        }

        return response()->json([
            'stats'                     => $stats,
            'monthlyTrend'              => $monthlyTrend,
            'applicationsByStatus'      => $applicationsByStatus,
            'applicationsByDepartment'  => $applicationsByDepartment,
            'recentApplications'        => $recentApplications,
            'recentPosts'               => $recentPosts,
            'applicationsBySource'      => $sourceChartData,
        ]);
    }

    // ==================== GESTION DES UTILISATEURS ====================

    public function getUsers()
    {
        $users = User::with(['role', 'department'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->each(function($user) {
                $user->append('profile_image_url');
            });
        return response()->json(['users' => $users, 'count' => $users->count()]);
    }

    public function createUser(Request $request)
    {
        // On détermine le rôle choisi pour conditionner department_id
        $roleName = \App\Models\Role::where('id', $request->role_id)->value('name');

        $validatorRules = [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email',
            'role_id'    => 'required|integer|exists:roles,id',
            'is_active'  => 'sometimes|boolean',
            'approval_status' => 'sometimes|in:pending,approved,rejected',
            'password'   => 'required|string|min:6|confirmed',
        ];

        // Si manager => department_id requis et doit exister
        if (strtolower((string) $roleName) === 'manager') {
            $validatorRules['department_id'] = 'required|exists:departments,id';
        }

        $validator = Validator::make($request->all(), $validatorRules);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        $adminRoleId = $this->getAdminRoleId();
        if ($adminRoleId && (int) $request->role_id === $adminRoleId && $this->existsAnotherAdmin()) {
            return response()->json(['message' => 'Un seul compte Admin RH est autorisé.'], 422);
        }

        $createData = [
            'username'   => $this->makeUsernameFromEmail($request->email),
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'role_id'    => $request->role_id,
            'is_active'  => $request->boolean('is_active', true),
            'approval_status' => $request->get('approval_status', 'approved'),
            'password'   => Hash::make($request->password),
        ];

        // Sauvegarde department_id si manager
        if (strtolower((string) $roleName) === 'manager') {
            $createData['department_id'] = $request->department_id;
        }

        $user = User::create($createData);

        if ((int) $user->role_id === 4 && !empty($user->department_id)) {
            $this->syncManagerDepartment($user);
            $this->notifyDepartmentAssignment($user);
        }

        return response()->json(['message' => 'Utilisateur créé', 'user' => $user->load(['role', 'department'])->append('profile_image_url')], 201);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Si role_id change, on conditionne department_id selon le nouveau rôle
        $effectiveRoleId = $request->has('role_id') ? $request->role_id : $user->role_id;
        $roleName = \App\Models\Role::where('id', $effectiveRoleId)->value('name');

        $validatorRules = [
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'email'      => 'sometimes|email|unique:users,email,' . $id,
            'role_id'    => 'sometimes|integer|exists:roles,id',
            'is_active'  => 'sometimes|boolean',
            'approval_status' => 'sometimes|in:pending,approved,rejected',
        ];

        if (strtolower((string) $roleName) === 'manager') {
            // optionnel sur update, mais si fourni doit exister
            $validatorRules['department_id'] = 'sometimes|exists:departments,id';
        }

        if (
            strtolower((string) $roleName) === 'manager'
            && $request->get('approval_status') === 'approved'
            && !$request->filled('department_id')
            && empty($user->department_id)
        ) {
            $validatorRules['department_id'] = 'required|exists:departments,id';
        }

        $validator = Validator::make($request->all(), $validatorRules);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        $adminRoleId = $this->getAdminRoleId();
        if ($adminRoleId && $request->has('role_id') && (int) $request->role_id === $adminRoleId && $this->existsAnotherAdmin($id)) {
            return response()->json(['message' => 'Un seul compte Admin RH est autorisé.'], 422);
        }

        if ($adminRoleId && (int) $user->role_id === $adminRoleId && $request->has('role_id') && (int) $request->role_id !== $adminRoleId) {
            return response()->json(['message' => 'Le compte Admin RH unique ne peut pas perdre son rôle.'], 422);
        }

        $oldDepartmentId = $user->department_id;
        $oldRoleId = $user->role_id;
        $oldApprovalStatus = $user->approval_status;
        $updateFields = $request->only(['first_name', 'last_name', 'email', 'role_id', 'is_active', 'approval_status']);

        // department_id si manager
        if (strtolower((string) $roleName) === 'manager' && $request->has('department_id')) {
            $updateFields['department_id'] = $request->department_id;
        } elseif (strtolower((string) $roleName) !== 'manager') {
            $updateFields['department_id'] = null;
        }

        $user->update($updateFields);

        if ((int) $oldRoleId === 4 && ((int) $user->role_id !== 4 || (int) $oldDepartmentId !== (int) $user->department_id)) {
            Department::where('manager_id', $user->id)->update(['manager_id' => null]);
        }

        if ((int) $user->role_id === 4 && !empty($user->department_id)) {
            $this->syncManagerDepartment($user);
            if ($oldApprovalStatus !== 'approved' && $user->approval_status === 'approved') {
                $this->notifyManagerApproval($user);
            } elseif ((int) $oldDepartmentId !== (int) $user->department_id) {
                $this->notifyDepartmentAssignment($user);
            }
        }

        return response()->json(['message' => 'Utilisateur mis à jour', 'user' => $user->load(['role', 'department'])->append('profile_image_url')]);
    }

    public function deleteUser(Request $request, $id)
    {
        if ((int) $request->user()->id === (int) $id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte'], 403);
        }

        $user = User::findOrFail($id);
        $adminRoleId = $this->getAdminRoleId();
        if ($adminRoleId && (int) $user->role_id === $adminRoleId) {
            return response()->json(['message' => 'Le compte Admin RH unique ne peut pas être supprimé.'], 422);
        }

        $user->delete();
        return response()->json(['message' => 'Utilisateur supprimé']);
    }

    public function changeUserStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['is_active' => 'required|boolean']);
        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        if ((int) $request->user()->id === (int) $id && $request->boolean('is_active') === false) {
            return response()->json(['message' => 'Vous ne pouvez pas désactiver votre propre compte'], 403);
        }

        $user = User::findOrFail($id);
        $user->update(['is_active' => $request->boolean('is_active')]);
        return response()->json(['message' => 'Statut utilisateur mis à jour', 'user' => $user->load(['role', 'department'])->append('profile_image_url')]);
    }

    public function changeApprovalStatus(Request $request, $id)
    {
        $user = User::with(['role', 'department'])->findOrFail($id);

        $validatorRules = [
            'approval_status' => 'required|in:approved,rejected,pending',
            'department_id' => 'nullable|exists:departments,id',
        ];

        if ((int) $user->role_id === 4 && $request->approval_status === 'approved') {
            $validatorRules['department_id'] = 'required|exists:departments,id';
        }

        $validator = Validator::make($request->all(), $validatorRules);
        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        $updateData = [
            'approval_status' => $request->approval_status,
        ];

        if ((int) $user->role_id === 4 && $request->filled('department_id')) {
            $updateData['department_id'] = $request->department_id;
        }

        $user->update($updateData);

        if ((int) $user->role_id === 4 && $user->approval_status === 'approved') {
            $this->syncManagerDepartment($user);
            $this->notifyManagerApproval($user);
        }

        if ($user->approval_status === 'rejected') {
            NotificationService::create(
                $user->id,
                "Votre compte a été refusé par l'administrateur.",
                'error',
                ['type' => 'account_rejected']
            );
        }

        return response()->json([
            'message' => 'Statut de validation mis à jour',
            'user' => $user->fresh(['role', 'department'])->append('profile_image_url'),
        ]);
    }

    // ==================== AUTRES MÉTHODES ====================

    public function statistics(Request $request)
    {
        return response()->json([
            'average_delay_days' => 0,
            'conversion_rates'   => [],
            'period'             => $request->get('period', 'month'),
        ]);
    }

    public function export(Request $request)
    {
        $type = $request->get('type', 'applications');
        if ($type === 'applications') {
            $statusMap = [
                'recue' => 'Reçue',
                'en_cours' => 'En cours',
                'entretien_rh' => 'Entretien RH',
                'entretien_technique' => 'Entretien technique',
                'acceptee' => 'Accepté',
                'refusee' => 'Refusé',
            ];

            $data = Application::with(['candidate', 'post'])->get();
            $csvData = [['ID', 'Candidat', 'Email', 'Poste', 'Statut', 'Date candidature']];
            foreach ($data as $app) {
                $csvData[] = [
                    $app->id,
                    $app->candidate ? $app->candidate->full_name : 'N/A',
                    $app->candidate ? $app->candidate->email : 'N/A',
                    $app->post ? $app->post->title : 'N/A',
                    $statusMap[$app->status] ?? ucfirst($app->status),
                    $app->created_at->format('d/m/Y'),
                ];
            }
            $filename = 'applications_' . date('Y-m-d') . '.csv';
            $handle = fopen('php://temp', 'w+');
            foreach ($csvData as $row) fputcsv($handle, $row);
            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);
            return response($content, 200)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }
        return response()->json(['message' => 'Export non disponible'], 400);
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    private function isDirectionOrAdmin(Request $request): bool
    {
        $role = strtolower(optional($request->user()->role)->name ?? '');
        return in_array($role, ['admin', 'direction'], true);
    }

    private function makeUsernameFromEmail(string $email): string
    {
        $base = preg_replace('/[^a-z0-9_]/', '_', strtolower(strstr($email, '@', true) ?: 'user'));
        $candidate = $base ?: 'user';
        $suffix = 1;
        while (User::where('username', $candidate)->exists()) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }
        return $candidate;
    }

    private function getAdminRoleId(): ?int
    {
        $adminRole = \App\Models\Role::whereRaw('LOWER(name) = ?', ['admin'])->first();
        return $adminRole ? (int) $adminRole->id : null;
    }

    private function existsAnotherAdmin(?int $exceptUserId = null): bool
    {
        $adminRoleId = $this->getAdminRoleId();
        if (!$adminRoleId) return false;
        $query = User::where('role_id', $adminRoleId);
        if ($exceptUserId !== null) $query->where('id', '!=', $exceptUserId);
        return $query->exists();
    }

    private function syncManagerDepartment(User $user): void
    {
        Department::where('manager_id', $user->id)
            ->where('id', '!=', $user->department_id)
            ->update(['manager_id' => null]);

        Department::where('id', $user->department_id)->update(['manager_id' => $user->id]);
    }

    private function notifyDepartmentAssignment(User $user): void
    {
        $department = Department::find($user->department_id);
        if (!$department) {
            return;
        }

        NotificationService::create(
            $user->id,
            "Votre département {$department->name} a été assigné par l'administrateur.",
            'success',
            [
                'type' => 'department_assigned',
                'department_id' => $department->id,
                'department_name' => $department->name,
                'dashboard_url' => '/dashboard/manager',
            ]
        );
    }

    private function notifyManagerApproval(User $user): void
    {
        $department = Department::find($user->department_id);

        NotificationService::create(
            $user->id,
            $department
                ? "Votre compte manager a été validé. Département assigné : {$department->name}."
                : "Votre compte manager a été validé.",
            'success',
            [
                'type' => 'manager_account_approved',
                'department_id' => $department?->id,
                'department_name' => $department?->name,
                'dashboard_url' => '/dashboard/manager',
            ]
        );
    }

    // Pour la direction (si nécessaire) – ✅ corrigé (fn → function)
    public function directionStatistics(Request $request)
    {
        if (!$this->isDirectionOrAdmin($request)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $period = $request->get('period', 'month');
        $allowedPeriods = ['month', 'quarter', 'year'];
        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'month';
        }

        $totalApplications = Application::count();
        $pending = Application::pending()->count();
        $totalRecruitments = Application::whereHas('currentStatus', function ($q) {
            $q->whereIn('name', ['Accepté', 'Acceptée', 'Acceptées']);
        })->count();

        $applicationsByDepartment = Department::orderBy('name')->get()->map(function ($dept) {
            return [
                'name' => $dept->name,
                'value' => Application::whereHas('post', function ($q) use ($dept) {
                    $q->where('department_id', $dept->id);
                })->count(),
            ];
        })->toArray();

        $applicationsBySource = Source::all()->map(function ($source) use ($totalApplications) {
            $count = Application::where('source_id', $source->id)->count();
            return [
                'name' => $source->name,
                'value' => $count,
                'percentage' => $totalApplications > 0 ? round(($count / $totalApplications) * 100) : 0,
            ];
        })->filter(function ($item) {
            return $item['value'] > 0;
        })->values()->toArray();

        return response()->json([
            'totalApplications' => $totalApplications,
            'pending' => $pending,
            'totalRecruitments' => $totalRecruitments,
            'successRate' => $totalApplications > 0 ? round(($totalRecruitments / $totalApplications) * 100) : 0,
            'monthlyTrend' => $this->buildTrendData($period),
            'applicationsByDepartment' => $applicationsByDepartment,
            'applicationsBySource' => $applicationsBySource,
            'pendingValidations' => [],
        ]);
    }

    private function buildTrendData(string $period): array
    {
        $trend = [];
        $now = now();

        if ($period === 'year') {
            for ($i = 4; $i >= 0; $i--) {
                $year = $now->copy()->subYears($i)->year;
                $applicationsCount = Application::whereYear('created_at', $year)->count();
                $recruitmentsCount = Application::whereYear('created_at', $year)
                    ->whereHas('currentStatus', function ($q) {
                        $q->whereIn('name', ['Accepté', 'Acceptée', 'Acceptées']);
                    })->count();
                $trend[] = [
                    'month' => (string) $year,
                    'applications' => $applicationsCount,
                    'recruitments' => $recruitmentsCount,
                ];
            }
            return $trend;
        }

        if ($period === 'quarter') {
            $currentYear = $now->year;
            $currentQuarter = ceil($now->month / 3);
            for ($i = 3; $i >= 0; $i--) {
                $quarter = $currentQuarter - $i;
                $year = $currentYear;
                while ($quarter <= 0) {
                    $quarter += 4;
                    $year -= 1;
                }
                $startMonth = ($quarter - 1) * 3 + 1;
                $endMonth = $startMonth + 2;
                $applicationsCount = Application::whereYear('created_at', $year)
                    ->whereMonth('created_at', '>=', $startMonth)
                    ->whereMonth('created_at', '<=', $endMonth)
                    ->count();
                $recruitmentsCount = Application::whereYear('created_at', $year)
                    ->whereMonth('created_at', '>=', $startMonth)
                    ->whereMonth('created_at', '<=', $endMonth)
                    ->whereHas('currentStatus', function ($q) {
                        $q->whereIn('name', ['Accepté', 'Acceptée', 'Acceptées']);
                    })->count();
                $trend[] = [
                    'month' => "T{$quarter} {$year}",
                    'applications' => $applicationsCount,
                    'recruitments' => $recruitmentsCount,
                ];
            }
            return $trend;
        }

        for ($i = 11; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $year = $date->year;
            $month = $date->month;
            $applicationsCount = Application::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();
            $recruitmentsCount = Application::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereHas('currentStatus', function ($q) {
                    $q->whereIn('name', ['Accepté', 'Acceptée', 'Acceptées']);
                })->count();
            $trend[] = [
                'month' => $date->format('M'),
                'applications' => $applicationsCount,
                'recruitments' => $recruitmentsCount,
            ];
        }

        return $trend;
    }

    public function directionExport(Request $request)
    {
        if (!$this->isDirectionOrAdmin($request)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        return $this->export($request);
    }

    public function approveValidation(Request $request, $id)
    {
        if (!$this->isDirectionOrAdmin($request)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $application = Application::findOrFail((int) $id);
        $acceptedStatus = Statut::where('name', 'Accepté')->first();
        
        if ($acceptedStatus) {
            $application->update(['current_status_id' => $acceptedStatus->id]);
        }

        Log::info('Validation approuvée', [
            'id' => $id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Validation approuvée',
            'application' => $application->fresh(),
        ]);
    }

    public function rejectValidation(Request $request, $id)
    {
        if (!$this->isDirectionOrAdmin($request)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $application = Application::findOrFail((int) $id);
        $rejectedStatus = Statut::where('name', 'Refusé')->first();
        
        if ($rejectedStatus) {
            $application->update(['current_status_id' => $rejectedStatus->id]);
        }

        Log::info('Validation refusée', [
            'id' => $id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Validation refusée',
            'application' => $application->fresh(),
        ]);
    }
}
