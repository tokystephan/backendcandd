<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'department_id' => is_numeric($this->department_id) ? (int) $this->department_id : $this->department_id,
            'contract_type_id' => is_numeric($this->contract_type_id) ? (int) $this->contract_type_id : $this->contract_type_id,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'department_id' => 'required|integer|exists:departments,id',
            'contract_type_id' => 'required|integer|exists:contract_types,id',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'status' => 'nullable|in:ouvert,ferme,en_attente',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages()
    {
        return [
            'title.required' => 'Le titre du poste est obligatoire',
            'title.max' => 'Le titre du poste ne peut pas dépasser 255 caractères',
            'department_id.required' => 'Le département est obligatoire',
            'department_id.integer' => 'Le département doit être un nombre valide',
            'department_id.exists' => 'Le département sélectionné n\'existe pas',
            'contract_type_id.required' => 'Le type de contrat est obligatoire',
            'contract_type_id.integer' => 'Le type de contrat doit être un nombre valide',
            'contract_type_id.exists' => 'Le type de contrat sélectionné n\'existe pas',
            'status.in' => 'Le statut sélectionné est invalide',
        ];
    }
}