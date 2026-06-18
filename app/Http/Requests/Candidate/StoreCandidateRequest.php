<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class StoreCandidateRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Géré par middleware
    }

    public function rules()
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:candidates,email',
            'phone'      => 'nullable|string|max:20',
            'source'     => 'required|string|max:100',
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