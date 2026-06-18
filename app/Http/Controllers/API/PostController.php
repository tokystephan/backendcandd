<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    /**
     * Display a listing of the posts.
     */
    public function index(Request $request)
    {
        $query = Post::with(['department', 'contractType', 'creator', 'skills']);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('status')) {
            if ($request->status === 'archive') {
                $query->where('is_archived', true);
            } else {
                $query->where('status', $request->status)
                      ->where('is_archived', false);
            }
        } else {
            $query->where('is_archived', false);
        }

        if ($request->filled('contract_type_id')) {
            $query->where('contract_type_id', $request->contract_type_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', '%' . $search . '%')
                    ->orWhereHas('department', function ($departmentQuery) use ($search) {
                        $departmentQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('contractType', function ($contractQuery) use ($search) {
                        $contractQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        $posts = $query->latest()->paginate(15);
        return response()->json($posts);
    }

    /**
     * Store a newly created post – VERSION CORRIGÉE
     */
    public function store(StorePostRequest $request)
    {
        // 🔍 Vérifier les données reçues (debug)
        Log::info('Création poste - Données reçues:', $request->all());

        // ✅ Récupérer les données validées
        $data = $request->validated();

        // ✅ Vérifier que les IDs sont bien présents et numériques
        if (empty($data['department_id']) || !is_numeric($data['department_id'])) {
            return response()->json([
                'message' => 'Le département est requis et doit être un ID valide'
            ], 422);
        }

        if (empty($data['contract_type_id']) || !is_numeric($data['contract_type_id'])) {
            return response()->json([
                'message' => 'Le type de contrat est requis et doit être un ID valide'
            ], 422);
        }

        // ✅ Vérifier que l'utilisateur est connecté
        if (!auth()->check()) {
            return response()->json([
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $data['created_by'] = auth()->id();

        // ✅ Conversion explicite en entier (sécurité)
        $data['department_id'] = (int) $data['department_id'];
        $data['contract_type_id'] = (int) $data['contract_type_id'];

        try {
            $post = Post::create($data);

            // Ajout des compétences
            if (!empty($data['skills']) && is_array($data['skills'])) {
                foreach ($data['skills'] as $skill) {
                    $post->skills()->create([
                        'skill_name' => $skill['skill_name'],
                        'is_required' => $skill['is_required'] ?? true,
                        'level' => $skill['level'] ?? 'intermediaire',
                    ]);
                }
            }

            // Notification: si c'est un poste cadre/direction
            try {
                $post->load('department');
                $level = $data['level'] ?? 'junior';
                \App\Services\NotificationService::postToValidate(
                    $post->id,
                    $post->title,
                    $post->department->name,
                    $level
                );
            } catch (\Exception $e) {
                // ignore
            }

            return response()->json([
                'message' => 'Poste créé avec succès',
                'post' => $post->load(['department', 'contractType', 'skills'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur création poste: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la création du poste',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified post.
     */
    public function show($id)
    {
        $post = Post::with([
            'department', 'contractType', 'skills', 'creator',
            'applications' => function($q) {
                $q->with(['candidate', 'currentStatus']);
                $q->latest()->limit(10);
            }
        ])->findOrFail($id);

        return response()->json($post);
    }

    /**
     * Update the specified post.
     */
    public function update(UpdatePostRequest $request, $id)
    {
        $post = Post::findOrFail($id);
        $post->update($request->validated());

        if ($request->has('skills')) {
            $post->skills()->delete();
            foreach ($request->skills as $skill) {
                $post->skills()->create([
                    'skill_name' => $skill['skill_name'],
                    'is_required' => $skill['is_required'] ?? true,
                    'level' => $skill['level'] ?? 'intermediaire',
                ]);
            }
        }

        return response()->json([
            'message' => 'Poste mis à jour',
            'post' => $post->fresh(['department', 'contractType', 'skills'])
        ]);
    }

    /**
     * Remove the specified post.
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);

        if ($post->applications()->count() > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer un poste avec des candidatures associées'
            ], 422);
        }

        $post->delete();
        return response()->json(['message' => 'Poste supprimé']);
    }

    /**
     * Close a post.
     */
    public function close($id)
    {
        $post = Post::findOrFail($id);
        $post->close();

        return response()->json([
            'message' => 'Poste fermé',
            'post' => $post
        ]);
    }

    /**
     * Open a post.
     */
    public function open($id)
    {
        $post = Post::findOrFail($id);
        $post->open();

        return response()->json([
            'message' => 'Poste rouvert',
            'post' => $post
        ]);
    }

    /**
     * Archive a post.
     */
    public function archive($id)
    {
        $post = Post::findOrFail($id);
        $post->update(['is_archived' => true]);

        return response()->json([
            'message' => 'Poste archivé',
            'post' => $post
        ]);
    }

    /**
     * Restore an archived post.
     */
    public function restore($id)
    {
        $post = Post::findOrFail($id);
        $post->update(['is_archived' => false]);

        return response()->json([
            'message' => 'Poste restauré',
            'post' => $post
        ]);
    }

    /**
     * Add a skill to a post.
     */
    public function addSkill(Request $request, $id)
    {
        $request->validate([
            'skill_name' => 'required|string',
            'is_required' => 'nullable|boolean',
            'level' => 'nullable|in:debutant,intermediaire,avance,expert',
        ]);

        $post = Post::findOrFail($id);
        $skill = $post->skills()->create($request->all());

        return response()->json(['skill' => $skill], 201);
    }

    /**
     * Remove a skill from a post.
     */
    public function removeSkill($id, $skillId)
    {
        $post = Post::findOrFail($id);
        $skill = $post->skills()->findOrFail($skillId);
        $skill->delete();

        return response()->json(['message' => 'Compétence supprimée']);
    }

    /**
     * Get applications for a post.
     */
    public function applications($id)
    {
        $post = Post::findOrFail($id);
        $applications = $post->applications()
            ->with(['candidate', 'currentStatus'])
            ->latest()
            ->paginate(15);

        return response()->json($applications);
    }
}