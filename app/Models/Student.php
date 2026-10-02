<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'dni',
    'email',
    'birthday',
    'phone',
    'address',
    'city',
    'state',
    'zip',
    'country',
    'emergency_contact_name',
    'emergency_contact_phone',
])]
class Student extends Authenticatable
{
    /** @use HasFactory<StudentFactory> */
    use HasApiTokens, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reviewSessions(): HasMany
    {
        return $this->hasMany(ReviewSession::class);
    }

    public function figureViews(): HasMany
    {
        return $this->hasMany(StudentFigureView::class);
    }

    public function streak(): HasOne
    {
        return $this->hasOne(StudentStreak::class);
    }
}
