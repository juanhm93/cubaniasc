<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserRoleController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/users', [
            'users' => User::query()
                ->with(['role:id,name,slug', 'company:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role_id', 'company_id', 'status', 'is_owner']),
            'roles' => Role::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'can_run_owner_maintenance' => $request->user()?->isOwner() ?? false,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->isOwner()) {
            abort(403);
        }

        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $user->role_id = (int) $validated['role_id'];
        $user->save();

        return back()->with('success', 'Role updated successfully.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->isOwner()) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'pending'])],
        ]);

        $user->status = $validated['status'];
        $user->save();

        return back()->with('success', 'User status updated successfully.');
    }
}
