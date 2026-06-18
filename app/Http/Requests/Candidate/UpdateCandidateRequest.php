<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCandidateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $candidateId = $this->route('candidate');
        return [
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'email'      => ['sometimes', 'email', Rule::unique('candidates')->ignore($candidateId)],
            'phone'      => 'nullable|string|max:20',
            'source'     => 'sometimes|string|max:100',
            'documents'  => 'nullable|array',
            'documents.*' => 'required|string|max:255',
            'cv'         => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'lm'         => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'skills'     => 'nullable|array',
            'skills.*.name' => 'required_with:skills|string',
            'skills.*.years' => 'nullable|numeric|min:0',
            'skills.*.level' => 'nullable|in:debutant,intermediaire,avance,expert',
        ];
    }
}