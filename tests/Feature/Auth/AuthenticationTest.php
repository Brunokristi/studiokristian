<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\StaffMagicLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSee('id="staff-login"', false)
            ->assertDontSee('sm:max-w-md');
    }

    public function test_admin_can_request_a_passwordless_login_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_admin' => true]);

        $response = $this->postJson('/login', ['email' => $user->email]);

        $this->assertGuest();
        $response->assertOk()->assertJsonStructure(['message']);
        Notification::assertSentTo($user, StaffMagicLinkNotification::class);
    }

    public function test_unknown_email_receives_the_same_generic_response(): void
    {
        Notification::fake();

        $this->postJson('/login', ['email' => 'unknown@example.test'])
            ->assertOk()->assertJsonStructure(['message']);

        $this->assertGuest();
        Notification::assertNothingSent();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
