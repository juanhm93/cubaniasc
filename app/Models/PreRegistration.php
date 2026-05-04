<?php

namespace App\Models;

use Database\Factories\PreRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'email', 'phone', 'agree', 'message'])]
class PreRegistration extends Model
{
    /** @use HasFactory<PreRegistrationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agree' => 'boolean',
        ];
    }
}
