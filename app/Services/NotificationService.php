<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Post;
use App\Models\Application;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ========================================
 * NOTIFICATION SERVICE - RÈGLES PAR RÔLE
 * ========================================
 * 
 * ADMIN RH: Toutes les notifications
 * ASSISTANT RH: Notifications opérationnelles
 * MANAGER/CONSULTANT: Notifications de son département
 * DIRECTION: Notifications stratégiques
 */
class NotificationService
{
    // Constantes des rôles
    const ROLE_ADMIN = 'admin';
    const ROLE_ASSISTANT = 'assistant';
    const ROLE_MANAGER = 'manager';
    const ROLE_CONSULTANT = 'consultant';
    const ROLE_DIRECTION = 'direction';

    // Types de notifications
    const TYPE_NEW_APPLICATION = 'nouvelle_candidature';
    const TYPE_STATUS_CHANGED = 'changement_statut';
    const TYPE_INTERVIEW_SCHEDULED = 'entretien_planifie';
    const TYPE_REPORT_ADDED = 'compte_rendu';
    const TYPE_SHORTLISTED = 'candidat_shortliste';
    const TYPE_OFFER_TO_VALIDATE = 'offre_validation';
    const TYPE_OFFER_DECISION = 'decision_direction';
    const TYPE_POST_TO_VALIDATE = 'poste_validation';
    const TYPE_NEW_USER = 'nouvel_utilisateur';
    const TYPE_SYSTEM_ALERT = 'system_alert';
    const TYPE_INTERVIEW_REMINDER = 'rappel_entretien';

    // ==================== MÉTHODES DE BASE ====================

    /**
     * Créer une notification pour un utilisateur
     */
    public static function create($userId, $message, $type = 'info', $data = [])
    {
        return Notification::create([
            'user_id' => $userId,
            'message' => $message,
            'type' => $type,
            'data' => $data,
            'is_read' => false,
        ]);
    }

    /**
     * Créer une notification pour plusieurs utilisateurs
     */
    public static function createForUsers($userIds, $message, $type = 'info', $data = [])
    {
        if (empty($userIds)) return;

        foreach (array_unique(array_filter($userIds)) as $userId) {
            self::create($userId, $message, $type, $data);
        }
    }

    /**
     * Créer une notification pour tous les utilisateurs
     */
    public static function createForAll($message, $type = 'info', $data = [])
    {
        $userIds = User::pluck('id')->toArray();
        self::createForUsers($userIds, $message, $type, $data);
    }

    /**
     * Créer une notification par rôle
     */
    public static function createForRole($role, $message, $type = 'info', $data = [])
    {
        $roles = self::roleAliases($role);

        $userIds = User::whereHas('role', function($query) use ($roles) {
            $query->whereIn(DB::raw('LOWER(name)'), $roles);
        })->pluck('id')->toArray();

        self::createForUsers($userIds, $message, $type, $data);
    }

    // ==================== 1️⃣ NOUVELLE CANDIDATURE ====================

    /**
     * Notifier lors d'une nouvelle candidature
     * 
     * Admin RH: Reçoit la notification
     * Assistant RH: Reçoit la notification
     * Manager: Reçoit si c'est dans son département
     * Direction: ✗
     */
    public static function newApplication($applicationId)
    {
        try {
            $application = Application::with('post.department', 'candidate')->find($applicationId);
            if (!$application) return;

            $message = "📄 Nouvelle candidature de {$application->candidate->full_name} pour le poste {$application->post->title}";
            $data = [
                'type' => self::TYPE_NEW_APPLICATION,
                'application_id' => $applicationId,
                'candidate_id' => $application->candidate_id,
                'post_id' => $application->post_id,
                'department_id' => $application->post->department_id,
                'candidate_name' => $application->candidate->full_name,
                'post_title' => $application->post->title,
            ];

            // Admin RH
            self::createForRole(self::ROLE_ADMIN, $message, 'info', $data);

            // Assistant RH
            self::createForRole(self::ROLE_ASSISTANT, $message, 'info', $data);

            // Managers du département
            $managers = self::managerIdsForDepartment($application->post->department_id);
            
            self::createForUsers($managers, $message, 'info', $data);


        } catch (\Exception $e) {
            Log::error('NotificationService::newApplication - ' . $e->getMessage());
        }
    }

    // ==================== 2️⃣ CHANGEMENT DE STATUT ====================

    /**
     * Notifier lors d'un changement de statut
     * 
     * Admin RH: Reçoit la notification
     * Assistant RH: Reçoit la notification
     * Manager: ✗
     * Direction: ✗
     */
    public static function statusChanged($applicationId, $oldStatus, $newStatus)
    {
        try {
            $application = Application::with('post.department', 'candidate')->find($applicationId);
            if (!$application) return;

            $message = "📊 Statut modifié pour {$application->candidate->full_name} : {$oldStatus} → {$newStatus}";
            $data = [
                'type' => self::TYPE_STATUS_CHANGED,
                'application_id' => $applicationId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'candidate_name' => $application->candidate->full_name,
            ];

            // Admin RH
            self::createForRole(self::ROLE_ADMIN, $message, 'info', $data);

            // Assistant RH
            self::createForRole(self::ROLE_ASSISTANT, $message, 'info', $data);

            // Direction seulement si la candidature passe en acceptée.
            if (self::isAcceptedStatus($newStatus)) {
                self::createForRole(self::ROLE_DIRECTION, $message, 'success', $data);
            }

        } catch (\Exception $e) {
            Log::error('NotificationService::statusChanged - ' . $e->getMessage());
        }
    }

    // ==================== 3️⃣ ENTRETIEN PLANIFIÉ ====================

    /**
     * Notifier lors d'un entretien planifié
     * 
     * Admin RH: Reçoit la notification
     * Assistant RH: Reçoit la notification
     * Manager: Reçoit si participant
     * Direction: ✗
     */
    public static function interviewScheduled($eventId)
    {
        try {
            $event = Event::with(['participants.user.role', 'application.candidate', 'application.post', 'candidate'])->find($eventId);
            if (!$event) return;

            // Déterminer le candidat et le poste
            $candidateName = $event->candidate?->full_name
                ?? $event->application?->candidate?->full_name
                ?? 'Candidat';
            $postTitle = $event->application?->post?->title ?? 'Poste';

            $message = "📅 Entretien planifié avec {$candidateName} pour {$postTitle} - {$event->start_datetime->format('d/m/Y H:i')}";
            $data = [
                'type' => self::TYPE_INTERVIEW_SCHEDULED,
                'event_id' => $eventId,
                'event_title' => $event->title,
                'start_datetime' => $event->start_datetime,
                'end_datetime' => $event->end_datetime,
                'candidate_name' => $candidateName,
                'post_title' => $postTitle,
            ];

            $participantIds = $event->participants->pluck('user_id')->toArray();
            $finalInterview = in_array($event->event_type, ['final', 'comite'], true);

            $recipientIds = $participantIds;

            if ($finalInterview) {
                $recipientIds = array_merge($recipientIds, self::userIdsForRole(self::ROLE_DIRECTION));
            }

            self::createForUsers($recipientIds, $message, 'info', $data);

        } catch (\Exception $e) {
            Log::error('NotificationService::interviewScheduled - ' . $e->getMessage());
        }
    }

    // ==================== 4️⃣ COMPTE RENDU AJOUTÉ ====================

    /**
     * Notifier quand un compte rendu est ajouté
     * 
     * Admin RH: Reçoit la notification
     * Assistant RH: Reçoit la notification
     * Manager: Reçoit si évaluateur
     * Direction: ✗
     */
    public static function reportAdded($reportId, $evaluatorName, $recommendation, $evaluatorId = null)
    {
        try {
            $message = "📝 Compte rendu ajouté par {$evaluatorName} - Recommandation: {$recommendation}";
            $data = [
                'type' => self::TYPE_REPORT_ADDED,
                'report_id' => $reportId,
                'evaluator_name' => $evaluatorName,
                'recommendation' => $recommendation,
            ];

            // Admin RH
            self::createForRole(self::ROLE_ADMIN, $message, 'info', $data);

            // Assistant RH
            self::createForRole(self::ROLE_ASSISTANT, $message, 'info', $data);

            // Évaluateur concerné, si fourni par l'appelant.
            if ($evaluatorId) {
                self::createForUsers([$evaluatorId], $message, 'info', $data);
            }

        } catch (\Exception $e) {
            Log::error('NotificationService::reportAdded - ' . $e->getMessage());
        }
    }

    // ==================== 5️⃣ CANDIDAT SHORTLISTÉ ====================

    /**
     * Notifier quand un candidat est shortlisté
     * 
     * Admin RH: Reçoit la notification
     * Assistant RH: Reçoit la notification
     * Manager: Reçoit si dans son département
     * Direction: ✗
     */
    public static function candidateShortlisted($applicationId, $candidateName, $postTitle, $departmentId)
    {
        try {
            $message = "⭐ {$candidateName} a été shortlisté pour {$postTitle}";
            $data = [
                'type' => self::TYPE_SHORTLISTED,
                'application_id' => $applicationId,
                'candidate_name' => $candidateName,
                'post_title' => $postTitle,
                'department_id' => $departmentId,
            ];

            // Admin RH
            self::createForRole(self::ROLE_ADMIN, $message, 'success', $data);

            // Assistant RH
            self::createForRole(self::ROLE_ASSISTANT, $message, 'success', $data);

            // Managers du département
            $managers = self::managerIdsForDepartment($departmentId);
            
            self::createForUsers($managers, $message, 'success', $data);

        } catch (\Exception $e) {
            Log::error('NotificationService::candidateShortlisted - ' . $e->getMessage());
        }
    }

    // ==================== 6️⃣ OFFRE À VALIDER ====================

    /**
     * Notifier la Direction qu'une offre attend validation
     * 
     * Admin RH: Reçoit la notification
     * Assistant RH: Reçoit la notification
     * Manager: ✗
     * Direction: Reçoit (HAUTE PRIORITÉ + EMAIL)
     */
    public static function offerToValidate($applicationId, $candidateName, $salary, $positionTitle, $expiresAt)
    {
        try {
            $message = "✅ Offre d'embauche à valider pour {$candidateName} - {$positionTitle} - {$salary}€";
            $data = [
                'type' => self::TYPE_OFFER_TO_VALIDATE,
                'application_id' => $applicationId,
                'candidate_name' => $candidateName,
                'salary' => $salary,
                'position_title' => $positionTitle,
                'expires_at' => $expiresAt,
                'priority' => 'high',
                'channel' => ['notification', 'email'],
            ];

            // Admin RH
            self::createForRole(self::ROLE_ADMIN, $message, 'warning', $data);

            // Assistant RH
            self::createForRole(self::ROLE_ASSISTANT, $message, 'warning', $data);

            // Direction (HAUTE PRIORITÉ)
            self::createForRole(self::ROLE_DIRECTION, $message, 'warning', $data);

        } catch (\Exception $e) {
            Log::error('NotificationService::offerToValidate - ' . $e->getMessage());
        }
    }

    // ==================== 7️⃣ DÉCISION DIRECTION ====================

    /**
     * Notifier du résultat de validation d'offre
     * 
     * Admin RH: Reçoit la notification
     * Assistant RH: Reçoit la notification
     * Manager: Reçoit si c'était son candidat
     * Direction: Reçoit (sa propre décision)
     */
    public static function offerDecision($applicationId, $candidateName, $decision, $comment = null)
    {
        try {
            $emoji = ($decision === 'accepted') ? '🏆 Acceptée' : '❌ Refusée';
            $message = "{$emoji} Offre pour {$candidateName}";
            if ($comment) $message .= " - {$comment}";

            $type = ($decision === 'accepted') ? 'success' : 'error';
            $data = [
                'type' => self::TYPE_OFFER_DECISION,
                'application_id' => $applicationId,
                'candidate_name' => $candidateName,
                'decision' => $decision,
                'comment' => $comment,
            ];

            // Admin RH
            self::createForRole(self::ROLE_ADMIN, $message, $type, $data);

            // Assistant RH
            self::createForRole(self::ROLE_ASSISTANT, $message, $type, $data);

            // Manager/consultant assigné à la candidature si l'information existe.
            $application = Application::find($applicationId);
            if ($application?->assigned_to) {
                $assignedUsers = self::usersMatchingAssignment($application->assigned_to);
                self::createForUsers($assignedUsers, $message, $type, $data);
            }

            // Direction (qui a fait la décision)
            self::createForRole(self::ROLE_DIRECTION, $message, $type, $data);

        } catch (\Exception $e) {
            Log::error('NotificationService::offerDecision - ' . $e->getMessage());
        }
    }

    // ==================== 8️⃣ POSTE À VALIDER ====================

    /**
     * Notifier quand un poste cadre/direction est créé
     * 
     * Admin RH: Reçoit la notification
     * Assistant RH: ✗
     * Manager: ✗
     * Direction: Reçoit si cadre/direction (HAUTE PRIORITÉ + EMAIL)
     */
    public static function postToValidate($postId, $postTitle, $departmentName, $level = null)
    {
        try {
            $post = Post::find($postId);
            if (!$post) return;

            // Le formulaire actuel n'a pas de champ "level"; le statut "en_attente"
            // représente donc le cas à valider par la Direction.
            $normalizedLevel = strtolower((string) $level);
            $isSeniorRole = in_array($normalizedLevel, ['cadre', 'direction', 'manager', 'senior'], true);
            $requiresValidation = $post->status === Post::STATUS_PENDING || $isSeniorRole;
            
            if (!$requiresValidation) return;

            $message = "📋 Poste cadre à valider : {$postTitle} ({$departmentName})";
            $data = [
                'type' => self::TYPE_POST_TO_VALIDATE,
                'post_id' => $postId,
                'post_title' => $postTitle,
                'department_name' => $departmentName,
                'level' => $level ?: $post->status,
                'priority' => 'high',
                'channel' => ['notification', 'email'],
            ];

            // Admin RH + Direction
            self::createForRole(self::ROLE_ADMIN, $message, 'info', $data);
            self::createForRole(self::ROLE_DIRECTION, $message, 'info', $data);

        } catch (\Exception $e) {
            Log::error('NotificationService::postToValidate - ' . $e->getMessage());
        }
    }

    // ==================== 9️⃣ NOUVEL UTILISATEUR ====================

    /**
     * Notifier quand un nouvel utilisateur s'inscrit
     * 
     * Admin RH: Reçoit la notification
     * Others: ✗
     */
    public static function newUser($userId, $userName, $userEmail, $roleName)
    {
        try {
            $message = "👤 Nouvel utilisateur: {$userName} ({$userEmail}) - Rôle: {$roleName}";
            $data = [
                'type' => self::TYPE_NEW_USER,
                'user_id' => $userId,
                'user_name' => $userName,
                'user_email' => $userEmail,
                'role_name' => $roleName,
            ];

            // Admin RH uniquement
            self::createForRole(self::ROLE_ADMIN, $message, 'info', $data);

        } catch (\Exception $e) {
            Log::error('NotificationService::newUser - ' . $e->getMessage());
        }
    }

    // ==================== 🔟 ALERTE SYSTÈME ====================

    /**
     * Notifier d'une alerte système/technique
     * 
     * Admin RH: Reçoit (NOTIFICATION + EMAIL)
     * Others: ✗
     */
    public static function systemAlert($title, $description, $severity = 'warning')
    {
        try {
            $message = "⚠️ Alerte système: {$title}";
            $data = [
                'type' => self::TYPE_SYSTEM_ALERT,
                'title' => $title,
                'description' => $description,
                'severity' => $severity,
                'channel' => ['notification', 'email'],
            ];

            // Admin RH uniquement
            self::createForRole(self::ROLE_ADMIN, $message, 'error', $data);

        } catch (\Exception $e) {
            Log::error('NotificationService::systemAlert - ' . $e->getMessage());
        }
    }

    // ==================== 1️⃣1️⃣ RAPPELS D'ENTRETIEN ====================

    /**
     * Rappel 30 min avant un entretien
     * 
     * Manager: Reçoit si participant (NOTIFICATION + EMAIL)
     * Direction: Reçoit si entretien final
     */
    public static function interviewReminder($eventId, $candidateName, $minutesBefore = 30)
    {
        try {
            $event = Event::with('participants.user.role')->find($eventId);
            if (!$event) return;

            $message = "⏰ Rappel: Entretien avec {$candidateName} dans {$minutesBefore} minutes";
            $data = [
                'type' => self::TYPE_INTERVIEW_REMINDER,
                'event_id' => $eventId,
                'candidate_name' => $candidateName,
                'minutes_before' => $minutesBefore,
                'channel' => ['notification', 'email'],
            ];

            // Participants managers/consultants.
            if ($event->participants && $event->participants->count() > 0) {
                $participantIds = $event->participants->pluck('user_id')->toArray();
                $managerParticipantIds = self::filterUserIdsByRoles($participantIds, [
                    self::ROLE_MANAGER,
                    self::ROLE_CONSULTANT,
                ]);
                self::createForUsers($managerParticipantIds, $message, 'warning', $data);
            }

            if (in_array($event->event_type, ['final', 'comite'], true)) {
                self::createForRole(self::ROLE_DIRECTION, $message, 'warning', $data);
            }

        } catch (\Exception $e) {
            Log::error('NotificationService::interviewReminder - ' . $e->getMessage());
        }
    }

    // ==================== 📊 RAPPORTS ====================

    /**
     * Rapport quotidien pour Admin RH
     * 
     * Admin RH: Uniquement (EMAIL)
     */
    public static function dailyReport($summary)
    {
        try {
            $message = "📊 Rapport quotidien - Synthèse activité du jour";
            $data = [
                'type' => 'daily_report',
                'summary' => $summary,
                'channel' => ['email'],
            ];

            self::createForRole(self::ROLE_ADMIN, $message, 'info', $data);

        } catch (\Exception $e) {
            Log::error('NotificationService::dailyReport - ' . $e->getMessage());
        }
    }

    /**
     * Rapport hebdomadaire
     * 
     * Manager: Lundi (EMAIL)
     * Direction: Lundi (EMAIL)
     */
    public static function weeklyReport($recipientRole, $summary)
    {
        try {
            $message = "📊 Rapport hebdomadaire";
            $data = [
                'type' => 'weekly_report',
                'summary' => $summary,
                'channel' => ['email'],
            ];

            self::createForRole($recipientRole, $message, 'info', $data);

        } catch (\Exception $e) {
            Log::error('NotificationService::weeklyReport - ' . $e->getMessage());
        }
    }

    /**
     * Rapport mensuel
     * 
     * Direction: 1er du mois (EMAIL)
     */
    public static function monthlyReport($summary, $graphics = [])
    {
        try {
            $message = "📈 Rapport mensuel - Bilan complet + graphiques";
            $data = [
                'type' => 'monthly_report',
                'summary' => $summary,
                'graphics' => $graphics,
                'channel' => ['email'],
            ];

            self::createForRole(self::ROLE_DIRECTION, $message, 'info', $data);

        } catch (\Exception $e) {
            Log::error('NotificationService::monthlyReport - ' . $e->getMessage());
        }
    }

    // ==================== ⚠️ ALERTES RECRUTEMENT ====================

    /**
     * Alerte pour recrutement bloqué > 15 jours
     * 
     * Direction: Reçoit uniquement
     */
    public static function recruitmentBlockedAlert($applicationId, $candidateName, $daysBblocked)
    {
        try {
            $message = "⚠️ Alerte recrutement: {$candidateName} bloqué depuis {$daysBblocked} jours";
            $data = [
                'type' => 'recruitment_blocked',
                'application_id' => $applicationId,
                'candidate_name' => $candidateName,
                'days_blocked' => $daysBblocked,
            ];

            // Direction uniquement
            self::createForRole(self::ROLE_DIRECTION, $message, 'error', $data);

        } catch (\Exception $e) {
            Log::error('NotificationService::recruitmentBlockedAlert - ' . $e->getMessage());
        }
    }

    // ==================== UTILITAIRES ====================

    /**
     * Obtenir le nombre de notifications non lues d'un utilisateur
     */
    public static function unreadCount($userId)
    {
        return Notification::forUser($userId)->unread()->count();
    }

    /**
     * Obtenir toutes les notifications d'un utilisateur
     */
    public static function getUserNotifications($userId, $limit = 50)
    {
        return Notification::forUser($userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Marquer toutes les notifications d'un utilisateur comme lues
     */
    public static function markUserAllAsRead($userId)
    {
        Notification::forUser($userId)->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Supprimer toutes les notifications d'un utilisateur
     */
    public static function deleteUserAllNotifications($userId)
    {
        Notification::forUser($userId)->delete();
    }

    /**
     * Supprimer les notifications anciennes de plus de X jours
     */
    public static function deleteOldNotifications($days = 30)
    {
        Notification::where('created_at', '<', now()->subDays($days))->delete();
    }

    private static function roleAliases($role): array
    {
        $normalized = strtolower((string) $role);
        $aliases = [
            self::ROLE_ADMIN => ['admin', 'responsable rh'],
            self::ROLE_ASSISTANT => ['assistant', 'assistant rh'],
            self::ROLE_MANAGER => ['manager', 'consultant'],
            self::ROLE_CONSULTANT => ['consultant', 'manager'],
            self::ROLE_DIRECTION => ['direction', 'directeur', 'directeur rh'],
        ];

        return $aliases[$normalized] ?? [$normalized];
    }

    private static function filterUserIdsByRoles(array $userIds, array $roles): array
    {
        if (empty($userIds)) return [];

        $roleNames = collect($roles)
            ->flatMap(fn ($role) => self::roleAliases($role))
            ->unique()
            ->values()
            ->all();

        return User::whereIn('id', array_unique($userIds))
            ->whereHas('role', function ($query) use ($roleNames) {
                $query->whereIn(DB::raw('LOWER(name)'), $roleNames);
            })
            ->pluck('id')
            ->toArray();
    }

    private static function managerIdsForDepartment($departmentId): array
    {
        if (!$departmentId || !Schema::hasColumn('users', 'department_id')) {
            return [];
        }

        $query = User::whereHas('role', function ($roleQuery) {
            $roleQuery->whereIn(DB::raw('LOWER(name)'), self::roleAliases(self::ROLE_MANAGER));
        });

        return $query->where('department_id', $departmentId)->pluck('id')->toArray();
    }

    private static function userIdsForRole($role): array
    {
        $roles = self::roleAliases($role);

        return User::whereHas('role', function ($query) use ($roles) {
            $query->whereIn(DB::raw('LOWER(name)'), $roles);
        })->pluck('id')->toArray();
    }

    private static function usersMatchingAssignment($assignment): array
    {
        $assignment = trim((string) $assignment);
        if ($assignment === '') return [];

        return User::where(function ($query) use ($assignment) {
            if (is_numeric($assignment)) {
                $query->orWhere('id', (int) $assignment);
            }

            $query->orWhere('username', $assignment)
                ->orWhere('email', $assignment)
                ->orWhereRaw("TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) = ?", [$assignment]);
        })->pluck('id')->toArray();
    }

    private static function isAcceptedStatus($status): bool
    {
        return in_array(strtolower(trim((string) $status)), ['acceptée', 'acceptee', 'accepted', 'accepté', 'accepte'], true);
    }
}
