<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCourseRequest extends FormRequest
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
            'level_id' => ['required', 'integer', 'exists:levels,id'],
            'place_id' => [
                'required',
                'integer',
                Rule::exists('places', 'id')->where(function ($query): void {
                    $companyId = $this->user()?->company_id;

                    if ($companyId !== null) {
                        $query->where('company_id', $companyId);
                    }
                }),
            ],
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query): void {
                    $companyId = $this->user()?->company_id;

                    if ($companyId !== null) {
                        $query->where('company_id', $companyId);
                    }
                }),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.weekday' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5, 6, 7])],
            'slots.*.starts_at' => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'slots.*.ends_at' => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $slots = $this->input('slots', []);

            if (! is_array($slots)) {
                return;
            }

            foreach ($slots as $index => $slot) {
                if (! is_array($slot)) {
                    continue;
                }

                $start = $this->normalizeTimeString((string) ($slot['starts_at'] ?? ''));
                $end = $this->normalizeTimeString((string) ($slot['ends_at'] ?? ''));

                if ($start === '' || $end === '') {
                    continue;
                }

                $startSec = strtotime('1970-01-01 '.$start);
                $endSec = strtotime('1970-01-01 '.$end);

                if ($startSec === false || $endSec === false) {
                    continue;
                }

                if ($endSec <= $startSec) {
                    $validator->errors()->add(
                        "slots.{$index}.ends_at",
                        'La hora de fin debe ser posterior al inicio.'
                    );
                }
            }
        });
    }

    /**
     * @return array<int, array{weekday: int, starts_at: string, ends_at: string}>
     */
    public function validatedSlots(): array
    {
        /** @var array<int, array{weekday: int|string, starts_at: string, ends_at: string}> $slots */
        $slots = $this->validated()['slots'];

        $out = [];

        foreach ($slots as $slot) {
            $out[] = [
                'weekday' => (int) $slot['weekday'],
                'starts_at' => $this->normalizeTimeString((string) $slot['starts_at']),
                'ends_at' => $this->normalizeTimeString((string) $slot['ends_at']),
            ];
        }

        return $out;
    }

    private function normalizeTimeString(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value.':00';
        }

        return $value;
    }
}
