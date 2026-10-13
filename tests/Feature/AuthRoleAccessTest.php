<?php

namespace Tests\Feature;

use App\Models\AssessmentSession;
use App\Models\DeviceType;
use App\Models\User;
use Database\Seeders\ExpertSystemSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_dashboard_and_user_cannot(): void
    {
        $this->seed([ExpertSystemSeeder::class, UserSeeder::class]);

        $admin = User::query()->where('email', 'admin@tilik.test')->firstOrFail();
        $user = User::query()->where('email', 'user@tilik.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_guest_sees_shared_login_link_and_is_redirected_to_login_for_history(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('aria-label="Masuk untuk pengguna dan admin"', false);

        $this->get(route('assessments.history'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_sign_in_through_the_shared_login_route_and_view_history(): void
    {
        $this->seed([ExpertSystemSeeder::class, UserSeeder::class]);

        $this->post(route('login'), [
            'email' => 'admin@tilik.test',
            'password' => 'admin123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get(route('assessments.history'))
            ->assertOk()
            ->assertSee('Riwayat asesmen');
    }

    public function test_user_can_login_and_browser_dashboard_and_history_is_scoped_to_their_account(): void
    {
        $this->seed([ExpertSystemSeeder::class, UserSeeder::class]);

        $user = User::query()->where('email', 'user@tilik.test')->firstOrFail();
        $otherUser = User::factory()->create([
            'email' => 'other@tilik.test',
            'role' => User::ROLE_USER,
        ]);
        $deviceType = DeviceType::query()->firstOrFail();

        AssessmentSession::query()->create([
            'user_id' => $user->id,
            'device_type_id' => $deviceType->id,
            'status' => 'evaluated',
            'device_details' => ['label' => 'User Device'],
            'started_at' => now(),
        ]);

        AssessmentSession::query()->create([
            'user_id' => $otherUser->id,
            'device_type_id' => $deviceType->id,
            'status' => 'evaluated',
            'device_details' => ['label' => 'Other Device'],
            'started_at' => now(),
        ]);

        $this->post(route('login'), [
            'email' => 'user@tilik.test',
            'password' => 'user123',
        ])->assertRedirect(route('dashboard'));

        $this->actingAs($user)
            ->get(route('assessments.history'))
            ->assertOk()
            ->assertSee('User Device')
            ->assertDontSee('Other Device');
    }

    public function test_register_creates_a_user_and_redirects_to_dashboard(): void
    {
        $this->seed(ExpertSystemSeeder::class);

        $this->post(route('register'), [
            'name' => 'New User',
            'email' => 'newuser@tilik.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@tilik.test',
            'role' => User::ROLE_USER,
        ]);
    }
}
