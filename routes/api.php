<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\CandidateController;
use App\Http\Controllers\API\ConsultantDashboardController;
use App\Http\Controllers\API\AssistantDashboardController;
use App\Http\Controllers\API\DirectionDashboardController;  
use App\Http\Controllers\API\PostController;
use App\Models\Department;
use App\Models\ContractType;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\API\ApplicationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rôles disponibles:
| - role_id 1: Admin (accès total, pas de département)
| - role_id 2: Assistant RH (pas de département)
| - role_id 3: Consultant (département REQUIS)
| - role_id 4: Manager (département REQUIS)
| - role_id 5: Direction (pas de département, lecture seule)
|
*/

// ==================== ROUTES PUBLIQUES ====================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
});

// Routes pour les listes déroulantes publiques
Route::get('/departments', function() {
    return Department::all();
});
Route::get('/contract-types', function() {
    return ContractType::all();
});
Route::get('/sources', [StatisticsController::class, 'sources']);

// ==================== ROUTES PROTÉGÉES (AUTHENTIFICATION REQUISE) ====================
Route::middleware('auth:sanctum')->group(function () {
    
    // ========== AUTHENTIFICATION ==========
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/check-department', [AuthController::class, 'checkDepartment']);

    // ========== ROUTES UTILISATEURS (Admin uniquement) ==========
    Route::prefix('users')->middleware('role:1')->group(function () {
        Route::get('/participants', [UserController::class, 'participants']);
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::patch('/{id}/status', [UserController::class, 'changeStatus']);
        Route::get('/roles/list', [UserController::class, 'getRoles']);
    });

    // ========== ROUTE AVATAR UTILISATEUR (Tous les utilisateurs authentifiés) ==========
    Route::post('/users/avatar/upload', [UserController::class, 'uploadAvatar']);

    // ========== ROUTES NOTIFICATIONS (Tous utilisateurs authentifiés) ==========
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread/count', [NotificationController::class, 'unreadCount']);
        Route::get('/{id}', [NotificationController::class, 'show']);
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('/', [NotificationController::class, 'store']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/', [NotificationController::class, 'destroyAll']);
    });

    // ========== ROUTES ADMIN (role_id = 1) ==========
    Route::middleware('role:1')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/users', [AdminController::class, 'getUsers']);
        Route::get('/statistics', [AdminController::class, 'statistics']);
        Route::post('/export', [AdminController::class, 'export']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::patch('/users/{id}/status', [AdminController::class, 'changeUserStatus']);
        Route::patch('/users/{id}/approval', [AdminController::class, 'changeApprovalStatus']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
    });

    // ========== ROUTES DIRECTION (role_id = 5) ==========
    // ✅ CORRECTION: Route direction avec son propre préfixe
    Route::middleware('role:5')->prefix('direction')->group(function () {
        Route::get('/dashboard', [DirectionDashboardController::class, 'dashboard']);
        Route::get('/statistics/departments', [DirectionDashboardController::class, 'departmentStats']);
        Route::get('/statistics/recruiters', [DirectionDashboardController::class, 'recruiterStats']);
        Route::get('/statistics/trend', [DirectionDashboardController::class, 'recruitmentTrend']);
        Route::get('/statistics/global', [DirectionDashboardController::class, 'globalStats']);
    });

    // route DELETE ADMIN SEULEMENT 
    Route::middleware(['auth:sanctum', 'role:1'])->prefix('admin')->group(function () {
    // Suppression d'un candidat (vérification des dépendances)
    Route::delete('/candidates/{id}', [CandidateController::class, 'destroy']);
    // Suppression forcée (cascade)
    Route::delete('/candidates/{id}/force', [CandidateController::class, 'forceDelete']);
});

    // ========== ROUTES CONSULTANT (role_id = 3) ==========
    Route::middleware('role:3')->prefix('consultant')->group(function () {
        Route::get('/dashboard', [ConsultantDashboardController::class, 'getDashboard']);
        Route::get('/posts', [ConsultantDashboardController::class, 'getPosts']);
        Route::get('/candidates-to-evaluate', [ConsultantDashboardController::class, 'getCandidatesToEvaluate']);
        Route::get('/events', [ConsultantDashboardController::class, 'getEvents']);
        Route::get('/interviews', [ConsultantDashboardController::class, 'getInterviews']);
        Route::get('/performance', [ConsultantDashboardController::class, 'getPerformance']);
        Route::post('/evaluate/{applicationId}', [ConsultantDashboardController::class, 'submitEvaluation']);
        Route::post('/interviews/{interviewId}/respond', [ConsultantDashboardController::class, 'respondToInterview']);
    });

    // ========== ROUTES MANAGER (role_id = 4) ==========
    Route::middleware('role:4')->prefix('manager')->group(function () {
        Route::get('/dashboard', [ConsultantDashboardController::class, 'getDashboard']);
        Route::get('/posts', [ConsultantDashboardController::class, 'getPosts']);
        Route::get('/candidates-to-evaluate', [ConsultantDashboardController::class, 'getCandidatesToEvaluate']);
        Route::get('/events', [ConsultantDashboardController::class, 'getEvents']);
        Route::get('/interviews', [ConsultantDashboardController::class, 'getInterviews']);
        Route::get('/performance', [ConsultantDashboardController::class, 'getPerformance']);
        Route::post('/evaluate/{applicationId}', [ConsultantDashboardController::class, 'submitEvaluation']);
        Route::post('/interviews/{interviewId}/respond', [ConsultantDashboardController::class, 'respondToInterview']);
        Route::post('/applications/{applicationId}/final-decision', [ConsultantDashboardController::class, 'finalDecision']);
    });

    // ========== ROUTES ASSISTANT RH (role_id = 2) ==========
    Route::middleware('role:2')->prefix('assistant')->group(function () {
        Route::get('/dashboard', [AssistantDashboardController::class, 'dashboard']);
        Route::get('/applications/pending', [AssistantDashboardController::class, 'pendingApplications']);
        Route::get('/agenda/today', [AssistantDashboardController::class, 'todayAgenda']);
        Route::get('/activities', [AssistantDashboardController::class, 'activities']);
        Route::post('/applications/{id}/process', [AssistantDashboardController::class, 'processApplication']);
    });

    // ========== ROUTES CANDIDATS (Admin et Assistant) ==========
    Route::middleware('role:1,2')->group(function () {
        Route::apiResource('candidates', CandidateController::class);
        Route::get('candidates/search', [CandidateController::class, 'search']);
        Route::post('candidates/{id}/skills', [CandidateController::class, 'addSkill']);
        Route::delete('candidates/{candidateId}/skills/{skillId}', [CandidateController::class, 'removeSkill']);
    });

    // ========== ROUTES POSTES ==========
    Route::middleware('role:1,2,3,4,5')->group(function () {
        Route::get('posts', [PostController::class, 'index']);
        Route::get('posts/{post}', [PostController::class, 'show']);
        Route::get('posts/{id}/applications', [PostController::class, 'applications']);
    });

    Route::middleware('role:1,2')->group(function () {
        Route::post('posts', [PostController::class, 'store']);
        Route::put('posts/{post}', [PostController::class, 'update']);
        Route::patch('posts/{post}', [PostController::class, 'update']);
        Route::delete('posts/{post}', [PostController::class, 'destroy']);
        Route::post('posts/{id}/close', [PostController::class, 'close']);
        Route::post('posts/{id}/open', [PostController::class, 'open']);
        Route::patch('posts/{id}/archive', [PostController::class, 'archive']);
        Route::patch('posts/{id}/restore', [PostController::class, 'restore']);
        Route::post('posts/{id}/skills', [PostController::class, 'addSkill']);
        Route::delete('posts/{id}/skills/{skillId}', [PostController::class, 'removeSkill']);
    });

    // ========== ROUTES ÉVÉNEMENTS (Admin, Assistant, Consultant, Manager, Direction lecture) ==========
    Route::middleware('role:1,2,3,4,5')->group(function () {
        Route::apiResource('events', EventController::class);
        Route::patch('events/{event}/status', [EventController::class, 'updateStatus']);
        Route::post('events/{event}/report', [EventController::class, 'storeReport']);
        Route::delete('events/{event}/report', [EventController::class, 'destroyReport']);
        Route::post('events/{event}/report/validate', [EventController::class, 'validateReport']);
        Route::get('events/{event}/report/export', [EventController::class, 'exportReport']);
        Route::post('events/{event}/validate-offer', [EventController::class, 'validateOffer']);
    });

    // ========== ROUTES CANDIDATURES (Tous rôles) ==========
    Route::middleware('role:1,2,3,4,5')->group(function () {
        Route::apiResource('applications', ApplicationController::class);
        Route::put('applications/{id}/status', [ApplicationController::class, 'updateStatus']);
        Route::get('applications/{id}/comments', [ApplicationController::class, 'comments']);
        Route::post('applications/{id}/comments', [ApplicationController::class, 'addComment']);
        Route::delete('applications/{applicationId}/comments/{commentId}', [ApplicationController::class, 'deleteComment']);
    });

    // ========== ROUTES OFFRE (Proposer et valider - Direction et Admin) ==========
    Route::middleware('role:1,5')->group(function () {
        Route::post('applications/{id}/propose-offer', [ApplicationController::class, 'proposeOffer']);
        Route::post('applications/{id}/validate-offer', [ApplicationController::class, 'validateOffer']);
    });

    // ========== ROUTES STATISTIQUES (Admin, Assistant, Direction) ==========
    Route::middleware('role:1,2,5')->group(function () {
        Route::get('/statistics', [StatisticsController::class, 'index']);
        Route::get('/statistics/recruitment/{type}', [StatisticsController::class, 'recruitment']);
        Route::get('/statistics/sources', [StatisticsController::class, 'sources']);
        Route::get('/statistics/departments', [StatisticsController::class, 'departments']);
        Route::get('/statistics/recruiters', [StatisticsController::class, 'recruiters']);
    });

    // ========== ROUTES STATISTIQUES DIRECTION SPÉCIFIQUES (Direction only) ==========
    Route::middleware('role:5')->group(function () {
        Route::get('/statistics/direction', [DirectionDashboardController::class, 'directionStatistics']);
        Route::post('/statistics/direction/export', [DirectionDashboardController::class, 'exportDirectionStats']);
        Route::post('/statistics/direction/validations/{id}/approve', [DirectionDashboardController::class, 'approveValidation']);
        Route::post('/statistics/direction/validations/{id}/reject', [DirectionDashboardController::class, 'rejectValidation']);
    });
});
