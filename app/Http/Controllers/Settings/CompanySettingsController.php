<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CompanySettingsUpdateRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    /**
     * Show company settings (owners can edit; admins read-only).
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        if ($user === null || (! $user->isOwner() && ! $user->isAdmin())) {
            abort(403);
        }

        $company = $this->resolveCompanyForViewer($user);

        return Inertia::render('settings/company', [
            'company' => $company,
            'canEdit' => $user->isOwner(),
        ]);
    }

    /**
     * Create or update the owner's company record.
     */
    public function update(CompanySettingsUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isOwner()) {
            abort(403);
        }

        $validated = $request->validated();
        $payload = Arr::except($validated, ['logo']);

        $logoPath = null;

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('companies/logos', 'public');
        }

        DB::transaction(function () use ($user, $payload, $logoPath): void {
            if ($user->company_id !== null) {
                $company = Company::query()->findOrFail($user->company_id);

                $slugInput = $payload['slug'] ?? null;
                if (is_string($slugInput) && trim($slugInput) !== '') {
                    $slug = Str::slug($slugInput);
                } else {
                    $slug = $company->slug;
                }

                $attributes = [
                    'rif' => $payload['rif'] ?? null,
                    'name' => $payload['name'],
                    'slug' => $this->ensureUniqueSlug($slug, $company->id),
                    'email' => $payload['email'],
                    'phone' => $payload['phone'] ?? null,
                    'address' => $payload['address'] ?? null,
                    'website' => $payload['website'] ?? null,
                    'is_active' => $payload['is_active'] ?? true,
                ];

                if ($logoPath !== null) {
                    if ($company->logo) {
                        Storage::disk('public')->delete($company->logo);
                    }
                    $attributes['logo'] = $logoPath;
                }

                $company->fill($attributes);
                $company->save();

                return;
            }

            $baseSlug = $payload['slug'] ?? Str::slug($payload['name']);
            if (! is_string($baseSlug) || $baseSlug === '') {
                $baseSlug = 'company';
            }

            $slug = $this->ensureUniqueSlug(Str::slug($baseSlug), null);

            $company = Company::query()->create([
                'rif' => $payload['rif'] ?? null,
                'name' => $payload['name'],
                'slug' => $slug,
                'email' => $payload['email'],
                'phone' => $payload['phone'] ?? null,
                'address' => $payload['address'] ?? null,
                'logo' => $logoPath,
                'website' => $payload['website'] ?? null,
                'is_active' => $payload['is_active'] ?? true,
            ]);

            $user->company_id = $company->id;
            $user->save();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Datos de la compañía guardados.',
        ]);

        return to_route('settings.company.edit');
    }

    private function resolveCompanyForViewer(User $user): ?Company
    {
        if ($user->company_id !== null) {
            return Company::query()->find($user->company_id);
        }

        if ($user->isAdmin()) {
            return Company::query()->orderBy('id')->first();
        }

        return null;
    }

    private function ensureUniqueSlug(string $slug, ?int $ignoreId): string
    {
        $candidate = $slug;

        if ($candidate === '') {
            $candidate = 'company';
        }

        $suffix = 1;

        while (Company::withTrashed()
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
