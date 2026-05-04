<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['rif', 'name', 'slug', 'email', 'phone', 'address', 'logo', 'website', 'is_active'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $appends = ['logo_url'];

    /**
     * Public URL for the stored logo (requires `storage:link` and `public` disk).
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $path = $this->attributes['logo'] ?? null;

            if ($path === null || $path === '') {
                return null;
            }

            return Storage::disk('public')->url($path);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
