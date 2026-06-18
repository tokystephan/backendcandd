<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'application_id' => 'required|exists:applications,id',
            'candidate_id' => 'required|exists:candidates,id',
            'event_type' => 'required|in:telephonique,rh,technique,metier,final,comite,autre',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'start_datetime' => 'required|date|after:now',
            'end_datetime' => 'required|date|after:start_datetime',
            'location_type' => 'required|in:presentiel,visio,telephone',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url|required_if:location_type,visio',
            'phone_number' => 'nullable|string|required_if:location_type,telephone',
            'participants' => 'required|array|min:1',
            'participants.*' => 'integer|exists:users,id',
        ];
    }

    public function messages()
    {
        return [
            'start_datetime.after' => 'La date de début doit être dans le futur.',
            'end_datetime.after' => 'La date de fin doit être après le début.',
            'participants.min' => 'Ajoutez au moins un participant.',
            'meeting_link.required_if' => 'Le lien de réunion est requis pour une visio.',
            'phone_number.required_if' => 'Le numéro de téléphone est requis.',
        ];
    }
}