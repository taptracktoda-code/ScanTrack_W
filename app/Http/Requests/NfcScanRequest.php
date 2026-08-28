<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class NfcScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uid' => ['required_without:nfc_uid', 'string', 'max:255'],
            'nfc_uid' => ['required_without:uid', 'string', 'max:255'],
            'terminal_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'uid.required_without' => 'NFC UID is required.',
            'nfc_uid.required_without' => 'NFC UID is required.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Invalid request.',
            'errors' => $validator->errors(),
        ], 422));
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('uid') && $this->filled('nfc_uid')) {
            $this->merge(['uid' => $this->input('nfc_uid')]);
        }
    }
}
