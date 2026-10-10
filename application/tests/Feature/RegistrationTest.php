<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['name' => 'Pegawai Ujian', 'email' => 'daftar@example.test', 'department_id' => Department::create(['name' => 'Bahagian Ujian', 'code' => 'UJIAN'])->id, 'password' => 'KataLaluanUjian123', 'password_confirmation' => 'KataLaluanUjian123'];
    }

    public function test_registration_waits_for_admin_and_does_not_accept_privileges(): void
    {
        $data = $this->payload();
        $this->post('/register', $data + ['role' => 'admin', 'is_active' => true, 'registration_pending' => false, 'email_verified_at' => now()])->assertRedirect('/login')->assertSessionHas('status');
        $this->assertGuest();
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->registration_pending);
        $this->assertSame('officer', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $audit = AuditEvent::where('action', 'user.registration_requested')->firstOrFail();
        $this->assertSame($user->id, $audit->actor_id);
        $this->assertArrayNotHasKey('password', $audit->after);
        $this->post('/login', ['email' => $user->email, 'password' => $data['password']])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->get('/documents')->assertRedirect('/login');
    }

    public function test_admin_can_verify_department_and_activate_registration_with_audit(): void
    {
        $data = $this->payload();
        $this->post('/register', $data)->assertRedirect('/login');
        $user = User::where('email', $data['email'])->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'department_id' => $data['department_id']]);
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Menunggu kelulusan');
        $this->get('/admin/users/'.$user->id.'/edit')->assertOk()->assertSee('Sahkan identiti');
        $this->put('/admin/users/'.$user->id, [...$data, 'role' => 'officer', 'is_active' => 1, 'password' => ''])->assertRedirect('/admin/users');
        $this->assertTrue($user->fresh()->is_active);
        $this->assertFalse($user->fresh()->registration_pending);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.registration_approved', 'actor_id' => $admin->id]);
        $this->post('/logout');
        $this->post('/login', ['email' => $data['email'], 'password' => $data['password']])->assertRedirect('/documents');
        $this->assertAuthenticatedAs($user);
    }

    #[TestWith(['officer'])]
    #[TestWith(['coordinator'])]
    #[TestWith(['approver'])]
    public function test_non_admin_cannot_activate_registration(string $role): void
    {
        $data = $this->payload();
        $this->post('/register', $data);
        $user = User::where('email', $data['email'])->firstOrFail();
        $actor = User::factory()->create(['role' => $role, 'is_active' => true, 'department_id' => $data['department_id']]);
        $this->actingAs($actor)->put('/admin/users/'.$user->id, [...$data, 'role' => 'officer', 'is_active' => 1])->assertForbidden();
        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseMissing('audit_events', ['action' => 'user.registration_approved']);
    }

    #[TestWith(['password', 'short', 'password'])]
    #[TestWith(['password_confirmation', 'different-password', 'password'])]
    #[TestWith(['email', 'invalid-address', 'email'])]
    #[TestWith(['department_id', 9999, 'department_id'])]
    #[TestWith(['name', '', 'name'])]
    public function test_invalid_registration_does_not_create_account(string $field, mixed $value, string $error): void
    {
        $data = $this->payload();
        $data[$field] = $value;
        $this->from('/register')->post('/register', $data)->assertRedirect('/register')->assertSessionHasErrors($error)->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_duplicate_email_is_normalized_and_preserves_existing_account(): void
    {
        $data = $this->payload();
        $this->post('/register', $data)->assertRedirect('/login');
        $this->post('/register', [...$data, 'email' => 'DAFTAR@example.test'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_registration_is_rate_limited_by_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [])->assertSessionHasErrors();
        }
        $this->post('/register', [])->assertStatus(429);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rolls_back_when_audit_fails(): void
    {
        $data = $this->payload();
        AuditEvent::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            $this->post('/register', $data)->assertStatus(500);
            $this->assertDatabaseCount('users', 0);
        } finally {
            AuditEvent::flushEventListeners();
        }
    }

    public function test_guest_pages_share_identity_and_registration_link(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log masuk pegawai')->assertSee('jata-pahang.png')->assertSee('ppsas.jpg')->assertSee(route('register'));
        $this->get('/register')->assertOk()->assertSee('Daftar pegawai')->assertSee('Hantar permohonan');
    }
}
