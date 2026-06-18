<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreCandidateRequest;
use App\Http\Requests\Candidate\UpdateCandidateRequest;
use App\Models\Candidate;
use App\Models\CandidateSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CandidateController extends Controller
{
    /**
     * Liste des candidats (paginate + recherche)
     */
    public function index(Request $request)
    {
        $query = Candidate::with('skills');

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        $candidates = $query->latest()->paginate(15);
        return response()->json($candidates);
    }

    /**
     * Créer un candidat
     */
    public function store(StoreCandidateRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('cv')) {
            $data['cv_path'] = $request->file('cv')->store('cvs', 'public');
        }

        if ($request->hasFile('lm')) {
            $data['motivation_letter_path'] = $request->file('lm')->store('motivation_letters', 'public');
        }

        $candidate = Candidate::create($data);

        if (!empty($data['skills'])) {
            foreach ($data['skills'] as $skill) {
                $candidate->skills()->create([
                    'skill_name' => $skill['name'],
                    'experience_years' => $skill['years'] ?? 0,
                    'level' => $skill['level'] ?? 'intermediaire',
                ]);
            }
        }

        return response()->json($candidate->load('skills'), 201);
    }

    /**
     * Afficher un candidat
     */
    public function show($id)
    {
        $candidate = Candidate::with(['skills', 'applications.post', 'candidateDocuments'])->findOrFail($id);

        $responseData = $candidate->toArray();
        $responseData['cv_url'] = $candidate->cv_path ? asset('storage/' . ltrim($candidate->cv_path, '/')) : null;
        $responseData['motivation_letter_url'] = $candidate->motivation_letter_path ? asset('storage/' . ltrim($candidate->motivation_letter_path, '/')) : null;
        $responseData['document_records'] = $candidate->candidateDocuments->map(function ($doc) {
            return [
                'id' => $doc->id,
                'type' => $doc->document_type,
                'name' => $doc->file_name,
                'download_url' => $doc->file_path ? asset('storage/' . ltrim($doc->file_path, '/')) : null,
                'mime_type' => $doc->mime_type,
            ];
        })->toArray();

        return response()->json($responseData);
    }

    /**
     * Mettre à jour un candidat
     */
    public function update(UpdateCandidateRequest $request, $id)
    {
        $candidate = Candidate::findOrFail($id);
        $data = $request->validated();

        if ($request->hasFile('cv')) {
            if ($candidate->cv_path) {
                Storage::disk('public')->delete($candidate->cv_path);
            }
            $data['cv_path'] = $request->file('cv')->store('cvs', 'public');
        }

        if ($request->hasFile('lm')) {
            if ($candidate->motivation_letter_path) {
                Storage::disk('public')->delete($candidate->motivation_letter_path);
            }
            $data['motivation_letter_path'] = $request->file('lm')->store('motivation_letters', 'public');
        }

        $candidate->update($data);

        if ($request->has('skills')) {
            $candidate->skills()->delete();
            foreach ($request->skills as $skill) {
                $candidate->skills()->create([
                    'skill_name' => $skill['name'],
                    'experience_years' => $skill['years'] ?? 0,
                    'level' => $skill['level'] ?? 'intermediaire',
                ]);
            }
        }

        return response()->json($candidate->load('skills'));
    }

    /**
     * Supprimer un candidat
     */
    public function destroy($id)
    {
        $candidate = Candidate::findOrFail($id);

        if ($candidate->cv_path) {
            Storage::disk('public')->delete($candidate->cv_path);
        }

        if ($candidate->motivation_letter_path) {
            Storage::disk('public')->delete($candidate->motivation_letter_path);
        }

        $candidate->delete();
        return response()->json(['message' => 'Candidat supprimé']);
    }

    /**
     * Recherche rapide (autocomplete)
     */
    public function search(Request $request)
    {
        $term = $request->get('q');
        $candidates = Candidate::search($term)->limit(10)->get(['id', 'first_name', 'last_name', 'email']);
        return response()->json($candidates);
    }

    /**
     * Ajouter une compétence à un candidat
     */
    public function addSkill(Request $request, $id)
    {
        $candidate = Candidate::findOrFail($id);
        $skill = $candidate->skills()->create($request->validate([
            'skill_name' => 'required|string|max:100',
            'experience_years' => 'nullable|numeric|min:0',
            'level' => 'nullable|in:debutant,intermediaire,avance,expert',
        ]));
        return response()->json($skill, 201);
    }

    /**
     * Supprimer une compétence
     */
    public function removeSkill($candidateId, $skillId)
    {
        $skill = CandidateSkill::where('candidate_id', $candidateId)->where('id', $skillId)->firstOrFail();
        $skill->delete();
        return response()->json(['message' => 'Compétence supprimée']);
    }
}
