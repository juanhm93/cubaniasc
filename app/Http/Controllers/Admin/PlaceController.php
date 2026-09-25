<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaceRequest;
use App\Http\Requests\UpdatePlaceRequest;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlaceController extends Controller
{
    /**
     * List the places of the user's company with their course count.
     */
    public function index(Request $request): Response
    {
        $companyId = $request->user()?->company_id;

        $places = Place::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->withCount('courses')
            ->orderBy('name')
            ->get(['id', 'name', 'address', 'phone'])
            ->map(fn (Place $place): array => [
                'id' => $place->id,
                'name' => $place->name,
                'address' => $place->address,
                'phone' => $place->phone,
                'courses_count' => $place->courses_count,
            ])
            ->values()
            ->all();

        return Inertia::render('admin/places/index', [
            'places' => $places,
        ]);
    }

    /**
     * Create a place for the user's company.
     */
    public function store(StorePlaceRequest $request): RedirectResponse
    {
        $companyId = $request->user()?->company_id;

        if ($companyId === null) {
            abort(403, 'Tu usuario debe estar asociado a una academia.');
        }

        Place::query()->create([
            ...$request->validated(),
            'company_id' => $companyId,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Lugar creado.',
        ]);

        return redirect()->route('admin.places.index');
    }

    /**
     * Update a place's name, address and phone.
     */
    public function update(UpdatePlaceRequest $request, Place $place): RedirectResponse
    {
        $this->authorizePlaceCompany($request, $place);

        $place->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Lugar actualizado.',
        ]);

        return redirect()->route('admin.places.index');
    }

    /**
     * Soft-delete a place. Places still used by courses cannot be deleted.
     */
    public function destroy(Request $request, Place $place): RedirectResponse
    {
        $this->authorizePlaceCompany($request, $place);

        if ($place->courses()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar un lugar que tiene cursos asignados.',
            ]);

            return redirect()->route('admin.places.index');
        }

        $place->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Lugar eliminado.',
        ]);

        return redirect()->route('admin.places.index');
    }

    private function authorizePlaceCompany(Request $request, Place $place): void
    {
        $companyId = $request->user()?->company_id;

        if ($companyId !== null && (int) $place->company_id !== (int) $companyId) {
            abort(403);
        }
    }
}
