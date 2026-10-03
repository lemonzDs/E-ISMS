<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ['file' => [$this->isMethod('put') ? 'nullable' : 'required', 'file', 'mimes:pdf,docx', 'max:10240']];
        if ($this->routeIs('documents.store')) {
            $rules['code'] = ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._\/-]+$/', 'unique:documents,code'];
        }
        if (! $this->routeIs('documents.versions.store')) {
            $rules['title'] = ['required', 'string', 'max:255'];
        }
        if ($this->isMethod('put')) {
            $rules['lock_version'] = ['required', 'integer', 'min:0'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return ['code' => 'kod dokumen', 'title' => 'tajuk', 'file' => 'fail', 'lock_version' => 'versi rekod'];
    }
}
