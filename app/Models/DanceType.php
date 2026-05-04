<?php

namespace App\Models;

use Database\Factories\DanceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'sort_order'])]
class DanceType extends Model
{
    /** @use HasFactory<DanceTypeFactory> */
    use HasFactory, SoftDeletes;

    public function levels(): HasMany
    {
        return $this->hasMany(Level::class);
    }
}
