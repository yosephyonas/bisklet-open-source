<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->withCount(['rides', 'payments']);

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");
            });
        }

        $role = trim((string) $request->input('role', ''));
        if ($role !== '') {
            $query->where('role', $role);
        }

        $users = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'filters' => [
                'q' => $search,
                'role' => $role,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => new User(),
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50', 'unique:users,phone'],
            'national_id' => ['required', 'string', 'max:50', 'unique:users,national_id'],
            'date_of_birth' => ['nullable', 'date'],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, UserRole::cases()))],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'fyda_verified' => ['nullable', 'boolean'],
        ]);

        $user = new User();
        $user->fill([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'father_name' => $validated['father_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'national_id' => $validated['national_id'],
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'role' => $validated['role'],
        ]);
        $user->password = $validated['password'];
        if ($request->boolean('fyda_verified')) {
            $user->fyda_verified_at = Carbon::now();
        }

        $user->save();

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'User created successfully.');
    }

    public function show(User $user): View
    {
        $user->loadCount(['rides', 'payments']);

        $recentRides = $user->rides()
            ->with(['bike:id,code', 'startStation:id,name', 'endStation:id,name'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        $recentPayments = $user->payments()
            ->with(['ride:id,user_id,bike_id,total_amount,status', 'ride.bike:id,code'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        $fydaVerifications = $user->fydaVerifications()
            ->latest('created_at')
            ->limit(5)
            ->get();

        return view('admin.users.show', [
            'user' => $user,
            'recentRides' => $recentRides,
            'recentPayments' => $recentPayments,
            'fydaVerifications' => $fydaVerifications,
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50', Rule::unique('users', 'phone')->ignore($user->id)],
            'national_id' => ['required', 'string', 'max:50', Rule::unique('users', 'national_id')->ignore($user->id)],
            'date_of_birth' => ['nullable', 'date'],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, UserRole::cases()))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'fyda_verified' => ['nullable', 'boolean'],
        ]);

        $user->fill([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'father_name' => $validated['father_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'national_id' => $validated['national_id'],
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'role' => $validated['role'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        if ($request->boolean('fyda_verified')) {
            if ($user->fyda_verified_at === null) {
                $user->fyda_verified_at = Carbon::now();
            }
        } else {
            $user->fyda_verified_at = null;
        }

        $user->save();

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
