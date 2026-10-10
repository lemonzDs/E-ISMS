<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\User;
use App\Services\WorkspaceAlerts;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.register', ['departments' => Department::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed'],
        ], [
            'required' => ':attribute diperlukan.',
            'email.email' => 'Masukkan alamat e-mel yang sah.',
            'email.unique' => 'E-mel ini sudah didaftarkan. Hubungi pentadbir untuk semakan akaun.',
            'department_id.exists' => 'Pilih bahagian yang sah.',
            'password.min' => 'Kata laluan mesti sekurang-kurangnya 12 aksara.',
            'password.max' => 'Kata laluan tidak boleh melebihi 72 aksara.',
            'password.confirmed' => 'Pengesahan kata laluan tidak sepadan.',
        ], ['name' => 'Nama penuh', 'email' => 'E-mel', 'department_id' => 'Bahagian', 'password' => 'Kata laluan']);

        DB::transaction(function () use ($data) {
            $user = User::create($data + ['role' => 'officer', 'is_active' => false, 'registration_pending' => true]);
            AuditEvent::create([
                'actor_id' => $user->id,
                'action' => 'user.registration_requested',
                'after' => $user->only(['id', 'name', 'email', 'department_id', 'role', 'is_active', 'registration_pending']),
                'created_at' => now(),
            ]);
            app(WorkspaceAlerts::class)->registration($user);
        });

        return redirect()->route('login')->with('status', 'Permohonan akaun diterima. Akaun belum aktif sehingga pentadbir menyemak dan meluluskan permohonan anda. Hubungi pentadbir untuk semakan status.');
    }
}
