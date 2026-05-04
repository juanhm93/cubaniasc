<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\PreRegistration;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Payments workspace: students table and month context.
     */
    public function index(Request $request): Response
    {
        $monthKey = $request->query('month');

        if (! is_string($monthKey) || ! preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $monthKey = now()->format('Y-m');
        }

        try {
            $monthCarbon = Carbon::createFromFormat('Y-m', $monthKey)->startOfMonth();
        } catch (\Throwable) {
            $monthKey = now()->format('Y-m');
            $monthCarbon = now()->startOfMonth();
        }

        $monthStart = $monthCarbon->copy()->startOfMonth();
        $monthEnd = $monthCarbon->copy()->endOfMonth();

        $calendarMonths = [];

        for ($m = 1; $m <= 12; $m++) {
            $calendarMonths[] = [
                'value' => str_pad((string) $m, 2, '0', STR_PAD_LEFT),
                'label' => ucfirst(Carbon::createFromDate(2000, $m, 1)->locale('es')->translatedFormat('F')),
            ];
        }

        $yearStart = (int) now()->year;
        $calendarYears = [];

        for ($y = $yearStart; $y <= $yearStart + 2; $y++) {
            $calendarYears[] = [
                'value' => (string) $y,
                'label' => (string) $y,
            ];
        }

        $preRegistrations = PreRegistration::query()
            ->orderByDesc('id')
            ->get(['id', 'name', 'email', 'phone'])
            ->map(fn (PreRegistration $row): array => [
                'id' => $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'phone' => $row->phone,
            ])
            ->values()
            ->all();

        $enrollments = Enrollment::query()
            ->where('status', EnrollmentStatus::Active)
            ->with([
                'student:id,name,email',
                'course' => fn ($query) => $query->select('id', 'level_id')->with('level:id,name'),
            ])
            ->orderBy('id')
            ->get();

        $lastOverall = DB::table('payments')
            ->whereNull('deleted_at')
            ->selectRaw('student_id, course_id, MAX(COALESCE(paid_at, created_at)) as dt')
            ->groupBy('student_id', 'course_id')
            ->get()
            ->keyBy(fn ($row): string => $row->student_id.'_'.$row->course_id);

        $lastInMonth = DB::table('payments')
            ->whereNull('deleted_at')
            ->whereRaw('COALESCE(paid_at, created_at) BETWEEN ? AND ?', [$monthStart, $monthEnd])
            ->selectRaw('student_id, course_id, MAX(COALESCE(paid_at, created_at)) as dt')
            ->groupBy('student_id', 'course_id')
            ->get()
            ->keyBy(fn ($row): string => $row->student_id.'_'.$row->course_id);

        $rows = $enrollments->map(function (Enrollment $enrollment) use ($lastOverall, $lastInMonth): array {
            $key = $enrollment->student_id.'_'.$enrollment->course_id;

            $overall = $lastOverall->get($key);
            $inMonth = $lastInMonth->get($key);

            $courseLabel = $enrollment->course?->level?->name ?? ('Curso #'.$enrollment->course_id);

            return [
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'course_id' => $enrollment->course_id,
                'student_name' => $enrollment->student?->name ?? '',
                'student_email' => $enrollment->student?->email ?? '',
                'course_label' => $courseLabel,
                'last_payment_at' => $this->formatOptionalDate($overall?->dt ?? null),
                'last_payment_in_selected_month_at' => $this->formatOptionalDate($inMonth?->dt ?? null),
            ];
        })->values()->all();

        return Inertia::render('admin/payments', [
            'calendarMonths' => $calendarMonths,
            'calendarYears' => $calendarYears,
            'selectedMonth' => $monthKey,
            'rows' => $rows,
            'preRegistrations' => $preRegistrations,
        ]);
    }

    /**
     * Store a payment row (reference and/or receipt; cash exempt from both).
     */
    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $data = $request->validatedForPayment();

        $receiptPath = null;

        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('payments/receipts', 'public');
        }

        $method = strtolower(trim((string) ($data['method'] ?? '')));

        if ($method === 'efectivo') {
            $data['reference'] = null;
            $receiptPath = null;
        }

        $paidAt = isset($data['paid_at'])
            ? Carbon::parse((string) $data['paid_at'])
            : now();

        $dueAt = isset($data['due_at'])
            ? Carbon::parse((string) $data['due_at'])
            : null;

        Payment::query()->create([
            'amount' => $data['amount'],
            'reference' => $data['reference'] ?? null,
            'receipt_path' => $receiptPath,
            'course_id' => $data['course_id'],
            'student_id' => $data['student_id'],
            'status' => $data['status'] ?? PaymentStatus::Paid,
            'due_at' => $dueAt,
            'paid_at' => $paidAt,
            'method' => $method,
            'notes' => $data['notes'] ?? null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pago registrado.',
        ]);

        $returnMonth = $request->validated('return_month');

        return redirect()->route('admin.payments.index', [
            'month' => is_string($returnMonth) && preg_match('/^\d{4}-\d{2}$/', $returnMonth)
                ? $returnMonth
                : now()->format('Y-m'),
        ]);
    }

    private function formatOptionalDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->toIso8601String();
    }
}
