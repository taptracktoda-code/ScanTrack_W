<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class QrScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qr_token' => ['required_without:qr_identifier', 'string', 'max:32'],
            'qr_identifier' => ['required_without:qr_token', 'string', 'max:32'],
            'terminal_id' => ['nullable', 'string', 'max:255'],
            'queue_action' => ['sometimes', 'in:join,exit'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Invalid QR scan request.',
            'errors' => $validator->errors(),
        ], 422));
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('qr_token') && $this->filled('qr_identifier')) {
            $this->merge(['qr_token' => $this->input('qr_identifier')]);
        }

        if (! $this->filled('qr_identifier') && $this->filled('qr_token')) {
            $this->merge(['qr_identifier' => $this->input('qr_token')]);
        }
    }
}
