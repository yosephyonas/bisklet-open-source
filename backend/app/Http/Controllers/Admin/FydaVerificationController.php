<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FydaVerification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FydaVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $query = FydaVerification::query()->with('user:id,first_name,last_name,email,phone');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('national_id')) {
            $query->where('national_id', 'like', '%' . $request->input('national_id') . '%');
        }

        if ($request->filled('user')) {
            $search = trim((string) $request->input('user'));
            if ($search !== '') {
                $query->whereHas('user', function (Builder $builder) use ($search) {
                    $builder->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }
        }

        $verifications = $query->latest('created_at')->paginate(20)->withQueryString();

        return view('admin.fyda.index', [
            'verifications' => $verifications,
            'filters' => $request->only(['status', 'national_id', 'user']),
        ]);
    }

    public function show(FydaVerification $verification): View
    {
        $verification->load('user:id,first_name,last_name,email,phone');

        return view('admin.fyda.show', [
            'verification' => $verification,
        ]);
    }
}
