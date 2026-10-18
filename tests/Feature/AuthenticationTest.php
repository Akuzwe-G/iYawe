<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_login_screen_and_public_registration_disabled(): void
    {
        $this->get('/login')->assertOk()->assertSee('iYawe')->assertSee('Sign In');
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_successful_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'StrongPassword123!']);
        $this->post('/login', ['email' => $user->email, 'password' => 'StrongPassword123!'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_failed_and_disabled_login(): void
    {
        $user = User::factory()->create(['active' => false, 'password' => 'StrongPassword123!']);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'StrongPassword123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'limited@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'limited@example.test', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Too many attempts. Try again shortly.']);
    }

    public function test_disabled_account_loses_existing_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->forceFill(['active' => false])->save();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_password_reset_notification_and_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $token = Password::createToken($user);
        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))->assertOk();
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'UpdatedPassword123', 'password_confirmation' => 'UpdatedPassword123'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('UpdatedPassword123', $user->fresh()->password));
    }

    public function test_language_switch_persists_for_guests_and_users(): void
    {
        $this->post('/language', ['locale' => 'rw'])->assertSessionHas('locale', 'rw');
        $this->get('/login')->assertSee('Injira')->assertSee('lang="rw"', false);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/language', ['locale' => 'rw']);
        $this->assertSame('rw', $user->fresh()->locale);
        $this->get('/dashboard')->assertOk()->assertSee('Ahabanza');
        $this->post('/language', ['locale' => 'bad'])->assertSessionHasErrors('locale');
    }
}
