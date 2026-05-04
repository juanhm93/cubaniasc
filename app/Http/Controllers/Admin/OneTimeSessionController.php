<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOneTimeSessionRequest;
use App\Models\OneTimeSession;
use App\Models\Place;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OneTimeSessionController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = $request->user()?->company_id;

        $sessions = OneTimeSession::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->with([
                'place:id,name',
                'teacher:id,name',
            ])
            ->withCount('attendees')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (OneTimeSession $session): array => [
                'id' => $session->id,
                'name' => $session->name,
                'type' => $session->type,
                'starts_at' => $session->starts_at?->toIso8601String(),
                'ends_at' => $session->ends_at?->toIso8601String(),
                'price' => $session->price !== null ? (string) $session->price : null,
                'is_active' => $session->is_active,
                'place_name' => $session->place?->name,
                'teacher_name' => $session->teacher?->name,
                'attendees_count' => $session->attendees_count,
            ]);

        return Inertia::render('admin/one-time-sessions/index', [
            'sessions' => $sessions,
        ]);
    }

    public function create(Request $request): Response
    {
        $companyId = $request->user()?->company_id;

        $places = Place::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $teachers = User::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('admin/one-time-sessions/create', [
            'places' => $places,
            'teachers' => $teachers,
        ]);
    }

    public function store(StoreOneTimeSessionRequest $request): RedirectResponse
    {
        $companyId = $request->user()?->company_id;

        if ($companyId === null) {
            abort(403, 'Tu usuario debe estar asociado a una academia.');
        }

        $data = $request->validated();

        OneTimeSession::query()->create([
            'company_id' => $companyId,
            'place_id' => $data['place_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'type' => $data['type'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('admin.one-time-sessions.index');
    }
}
