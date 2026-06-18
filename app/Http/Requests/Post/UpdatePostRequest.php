<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
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
        if ($this->has('department_id') && is_numeric($this->department_id)) {
            $this->merge(['department_id' => (int) $this->department_id]);
        }

        if ($this->has('contract_type_id') && is_numeric($this->contract_type_id)) {
            $this->merge(['contract_type_id' => (int) $this->contract_type_id]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules()
    {
        return [
            'title' => 'sometimes|string|max:255',
            'department_id' => 'sometimes|integer|exists:departments,id',
            'contract_type_id' => 'sometimes|integer|exists:contract_types,id',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'status' => 'sometimes|in:ouvert,ferme,en_attente',
            'skills' => 'nullable|array',
        ];
    }

    public function messages()
    {
        return [
            'department_id.integer' => 'Le département doit être un nombre valide',
            'department_id.exists' => 'Le département sélectionné n\'existe pas',
            'contract_type_id.integer' => 'Le type de contrat doit être un nombre valide',
            'contract_type_id.exists' => 'Le type de contrat sélectionné n\'existe pas',
            'status.in' => 'Le statut sélectionné est invalide',
        ];
    }
}