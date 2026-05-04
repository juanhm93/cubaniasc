<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOneTimeSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['workshop', 'private_class', 'event'])],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
            'place_id' => [
                'nullable',
                'integer',
                Rule::exists('places', 'id')->where(function ($query): void {
                    $companyId = $this->user()?->company_id;

                    if ($companyId !== null) {
                        $query->where('company_id', $companyId);
                    }
                }),
            ],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query): void {
                    $companyId = $this->user()?->company_id;

                    if ($companyId !== null) {
                        $query->where('company_id', $companyId);
                    }
                }),
            ],
        ];
    }
}
