<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Place;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isOwner() || $user->isAdmin());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $place = $this->route('place');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('places', 'name')
                    ->where('company_id', $place instanceof Place ? $place->company_id : null)
                    ->ignore($place instanceof Place ? $place->id : null)
                    ->withoutTrashed(),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['address', 'phone'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
