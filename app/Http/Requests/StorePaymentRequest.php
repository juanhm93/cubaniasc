<?php

namespace App\Http\Requests;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Enrollment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', Rule::in(['efectivo', 'transferencia', 'otro'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'image', 'max:4096'],
            'paid_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'return_month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedForPayment(): array
    {
        return Arr::only($this->validated(), [
            'student_id',
            'course_id',
            'amount',
            'method',
            'reference',
            'paid_at',
            'due_at',
            'notes',
            'status',
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $studentId = (int) $this->input('student_id');
            $courseId = (int) $this->input('course_id');

            $hasEnrollment = Enrollment::query()
                ->where('student_id', $studentId)
                ->where('course_id', $courseId)
                ->where('status', EnrollmentStatus::Active)
                ->exists();

            if (! $hasEnrollment) {
                $validator->errors()->add(
                    'course_id',
                    'El alumno no tiene una inscripción activa en este curso.'
                );

                return;
            }

            $method = strtolower(trim((string) $this->input('method', '')));

            if ($method === 'efectivo') {
                return;
            }

            $hasReference = $this->filled('reference') && trim((string) $this->input('reference')) !== '';
            $hasReceipt = $this->hasFile('receipt');

            if (! $hasReference && ! $hasReceipt) {
                $validator->errors()->add(
                    'reference',
                    'Indica una referencia o adjunta un comprobante (excepto para pagos en efectivo).'
                );
            }
        });
    }
}
