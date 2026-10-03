<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['action' => ['required', 'in:submit,review,approve,return'], 'lock_version' => ['required', 'integer', 'min:0'], 'comment' => ['required_if:action,return', 'nullable', 'string', 'max:2000']];
    }

    public function attributes(): array
    {
        return ['comment' => 'ulasan', 'lock_version' => 'versi rekod'];
    }
}
