<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostSkill;

class PostService
{
    /**
     * Create a new post with skills.
     */
    public function createPost(array $data)
    {
        $skills = $data['skills'] ?? [];
        unset($data['skills']);

        $post = Post::create($data);

        foreach ($skills as $skill) {
            $post->skills()->create($skill);
        }

        return $post->load(['department', 'contractType', 'skills']);
    }

    /**
     * Update a post with skills.
     */
    public function updatePost(Post $post, array $data)
    {
        $skills = $data['skills'] ?? null;
        unset($data['skills']);

        $post->update($data);

        if ($skills !== null) {
            $post->skills()->delete();
            foreach ($skills as $skill) {
                $post->skills()->create($skill);
            }
        }

        return $post->fresh(['department', 'contractType', 'skills']);
    }

    /**
     * Get posts statistics.
     */
    public function getStatistics()
    {
        return [
            'total' => Post::count(),
            'open' => Post::open()->count(),
            'closed' => Post::where('status', Post::STATUS_CLOSED)->count(),
            'by_department' => Post::selectRaw('department_id, count(*) as total')
                ->groupBy('department_id')
                ->with('department')
                ->get(),
        ];
    }
}