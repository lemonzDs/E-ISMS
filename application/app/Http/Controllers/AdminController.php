<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\Document;
use App\Models\Risk;
use App\Models\RiskAction;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin.users', ['users' => User::with('department')->orderBy('name')->paginate(20), 'departments' => Department::orderBy('name')->get()]);
    }

    public function departments(): View
    {
        return view('admin.departments', ['departments' => Department::orderBy('name')->get()]);
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30', 'unique:departments,code'], 'name' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($request, $data) {
            $department = Department::create($data);
            $this->audit($request, 'department.created', ['department_id' => $department->id] + $data);
        });

        return back()->with('status', 'Bahagian ditambah.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $this->validateUser($request);
        DB::transaction(function () use ($request, $data) {
            $user = User::create($data);
            $this->audit($request, 'user.created', $user->only(['id', 'name', 'email', 'role', 'department_id', 'is_active']));
        });

        return back()->with('status', 'Akaun ditambah. Sampaikan kata laluan melalui saluran dalaman yang diluluskan.');
    }

    public function editUser(User $user): View
    {
        return view('admin.edit-user', ['account' => $user, 'departments' => Department::orderBy('name')->get(), 'pendingOwnership' => Document::where('owner_id', $user->id)->whereHas('latestVersion', fn ($query) => $query->where('status', '!=', 'approved'))->count()]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $this->validateUser($request, $user);
        if ($user->id === $request->user()->id && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            return back()->withErrors(['role' => 'Anda tidak boleh menukar peranan atau menyahaktifkan akaun sendiri.'])->withInput($request->except('password'));
        }
        DB::transaction(function () use ($request, $user, $data) {
            User::where('role', 'admin')->where('is_active', true)->lockForUpdate()->get();
            $locked = User::lockForUpdate()->findOrFail($user->id);
            if (RiskAction::where('assignee_id', $locked->id)->where('status', '!=', 'verified')->exists() && ((int) $data['department_id'] !== $locked->department_id || ! in_array($data['role'], ['officer', 'coordinator'], true))) {
                throw ValidationException::withMessages(['role' => 'Pengguna mempunyai tindakan rawatan belum selesai. Tukar pelaksana tindakan dahulu sebelum menukar bahagian atau peranan.']);
            }
            if (Risk::where('owner_id', $locked->id)->exists() && ((int) $data['department_id'] !== $locked->department_id || ! in_array($data['role'], ['officer', 'coordinator'], true))) {
                throw ValidationException::withMessages(['role' => 'Pengguna masih memiliki risiko. Pemindahan pemilik risiko perlu diselesaikan sebelum menukar bahagian atau peranan.']);
            }
            $pending = Document::where('owner_id', $locked->id)->whereHas('latestVersion', fn ($query) => $query->where('status', '!=', 'approved'))->lockForUpdate()->get();
            if ($pending->isNotEmpty()) {
                if ((int) $data['department_id'] !== $locked->department_id) {
                    throw ValidationException::withMessages(['department_id' => 'Pengguna masih memiliki dokumen belum diluluskan. Selesaikan dokumen sebelum menukar bahagian.']);
                }
                if (! in_array($data['role'], ['officer', 'coordinator'], true)) {
                    throw ValidationException::withMessages(['role' => 'Peranan baharu menghalang pengguna menyelesaikan dokumen miliknya. Selesaikan dokumen dahulu.']);
                }
            }
            if ($locked->role === 'admin' && $locked->is_active && ($data['role'] !== 'admin' || ! $data['is_active']) && User::where('role', 'admin')->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Sekurang-kurangnya satu pentadbir aktif diperlukan.']);
            }
            $before = $locked->only(['id', 'name', 'email', 'role', 'department_id', 'is_active']);
            $locked->update($data);
            AuditEvent::create(['actor_id' => $request->user()->id, 'action' => 'user.updated', 'before' => $before, 'after' => $locked->only(['id', 'name', 'email', 'role', 'department_id', 'is_active']), 'created_at' => now()]);
        });

        return redirect()->route('admin.users')->with('status', 'Akaun dikemas kini.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)], 'password' => [$user ? 'nullable' : 'required', 'string', 'min:12', 'max:255'], 'role' => ['required', 'in:admin,coordinator,officer,approver'], 'department_id' => ['required', 'integer', 'exists:departments,id'], 'is_active' => ['required', 'boolean']]);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }

    private function audit(Request $request, string $action, array $after): void
    {
        AuditEvent::create(['actor_id' => $request->user()->id, 'action' => $action, 'after' => $after, 'created_at' => now()]);
    }
}
