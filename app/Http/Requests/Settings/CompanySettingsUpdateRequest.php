<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanySettingsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('companies', 'slug')->ignore($companyId),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('companies', 'email')->ignore($companyId),
            ],
            'rif' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'website' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('slug') && $this->string('slug')->trim()->isEmpty()) {
            $this->merge(['slug' => null]);
        }

        if ($this->has('is_active')) {
            $value = $this->input('is_active');

            if ($value === '1' || $value === 1 || $value === true || $value === 'true') {
                $this->merge(['is_active' => true]);
            } elseif ($value === '0' || $value === 0 || $value === false || $value === 'false') {
                $this->merge(['is_active' => false]);
            }
        }
    }
}
