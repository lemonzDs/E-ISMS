<?php

namespace App\Http\Requests;

use App\Models\Risk;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RiskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('risk')
            ? $this->user()->can('update', $this->route('risk'))
            : $this->user()->can('create', Risk::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['title' => ['required', 'string', 'max:255'], 'asset_process' => ['required', 'string', 'max:255'], 'likelihood' => ['required', 'integer', 'between:1,5'], 'impact' => ['required', 'integer', 'between:1,5']];
        foreach (['threat', 'vulnerability', 'consequence', 'existing_controls', 'rationale'] as $field) {
            $rules[$field] = ['required', 'string', 'max:5000'];
        }
        if ($this->route('risk')) {
            $rules['lock_version'] = ['required', 'integer', 'min:0'];
        }

        return $rules;
    }
}
