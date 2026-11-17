<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'amount',
    'reference',
    'receipt_path',
    'course_id',
    'student_id',
    'status',
    'due_at',
    'paid_at',
    'method',
    'notes',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Receipts hold personal and financial data, so they live on the private disk.
     */
    public const RECEIPT_DISK = 'local';

    /**
     * Where receipts were stored before they became private.
     */
    public const LEGACY_RECEIPT_DISK = 'public';

    public const RECEIPT_DIRECTORY = 'payments/receipts';

    /**
     * @var list<string>
     */
    protected $appends = ['receipt_url'];

    /**
     * Authenticated URL of the receipt; only staff with the payments ability can open it.
     */
    protected function receiptUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $path = $this->attributes['receipt_path'] ?? null;

            if ($path === null || $path === '' || ! $this->exists) {
                return null;
            }

            return route('admin.payments.receipt', $this);
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
