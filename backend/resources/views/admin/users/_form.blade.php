<div class="grid gap-6 md:grid-cols-2">
    <div>
        <label for="first_name" class="block text-sm font-medium text-slate-700">First name</label>
        <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="last_name" class="block text-sm font-medium text-slate-700">Last name</label>
        <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="father_name" class="block text-sm font-medium text-slate-700">Father name</label>
        <input type="text" id="father_name" name="father_name" value="{{ old('father_name', $user->father_name) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="phone" class="block text-sm font-medium text-slate-700">Phone</label>
        <input type="text" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="national_id" class="block text-sm font-medium text-slate-700">National ID</label>
        <input type="text" id="national_id" name="national_id" value="{{ old('national_id', $user->national_id) }}" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="date_of_birth" class="block text-sm font-medium text-slate-700">Date of birth</label>
        <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', optional($user->date_of_birth)->toDateString()) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="role" class="block text-sm font-medium text-slate-700">Role</label>
        <select id="role" name="role" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
            @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected(old('role', $user->role?->value ?? $user->role) === $role->value)>{{ ucfirst($role->value) }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="mt-6 grid gap-6 md:grid-cols-2">
    <div>
        <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
        <input type="password" id="password" name="password" @if (! $user->exists) required @endif class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20" placeholder="{{ $user->exists ? 'Leave blank to keep current password' : '' }}">
    </div>
    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirm password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" @if (! $user->exists) required @endif class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
</div>

<div class="mt-6 flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
    <input type="checkbox" id="fyda_verified" name="fyda_verified" value="1" @checked(old('fyda_verified', $user->fyda_verified_at !== null)) class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900/40">
    <label for="fyda_verified" class="text-sm text-slate-600">Mark FYDA verification as complete</label>
</div>
