<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AdminAuditLog::query()->with('user:id,first_name,last_name,email');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                        $userQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $method = strtoupper(trim((string) $request->input('method', '')));
        if ($method !== '') {
            $query->where('method', $method);
        }

        $logs = $query->latest()->paginate(25)->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => [
                'q' => $search,
                'method' => $method,
            ],
            'methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],
        ]);
    }
}
