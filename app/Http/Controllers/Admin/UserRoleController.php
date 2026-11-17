<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserRoleController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();

        $users = User::query()
            ->with(['role:id,name,slug', 'company:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id', 'company_id', 'status', 'is_owner'])
            ->map(fn (User $user): array => [
                ...$user->only(['id', 'name', 'email', 'role_id', 'status', 'is_owner', 'role', 'company']),
                // Same rule the policy enforces; the page only hides what would be rejected.
                'can_manage' => $actor->can('updateStatus', $user),
            ])
            ->values();

        return Inertia::render('admin/users', [
            'users' => $users,
            'roles' => Role::query()
                ->when(! $actor->isOwner(), fn ($query) => $query->where('slug', '!=', RoleSlug::Admin->value))
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'can_run_owner_maintenance' => $actor->isOwner(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $role = Role::query()->findOrFail((int) $validated['role_id']);

        Gate::authorize('updateRole', [$user, $role]);

        $user->forceFill(['role_id' => $role->id])->save();

        return back()->with('success', 'Role updated successfully.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('updateStatus', $user);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'pending'])],
        ]);

        $user->forceFill(['status' => $validated['status']])->save();

        return back()->with('success', 'User status updated successfully.');
    }
}
