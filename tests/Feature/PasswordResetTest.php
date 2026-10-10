<?php

use App\Enums\UserRole;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(fn () => Notification::fake());

function resetOwner(array $attributes = []): User
{
    $owner = User::factory()->create(['role' => UserRole::Owner, 'email' => 'olive@example.test', ...$attributes]);
    Shop::factory()->create(['user_id' => $owner->id]);

    return $owner;
}

test('the forgot password page opens for guests', function () {
    $this->get('/forgot-password')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
});

test('asking for a link emails the account a reset link', function () {
    $owner = resetOwner();

    $this->post('/forgot-password', ['email' => 'olive@example.test'])
        ->assertSessionHas('status', PasswordResetLinkController::SENT);

    Notification::assertSentTo($owner, ResetPassword::class, function (ResetPassword $notification) use ($owner) {
        $mail = $notification->toMail($owner);

        return $mail->subject === 'Reset your TaDa Tap password'
            && str_contains($mail->actionUrl, '/reset-password/'.$notification->token)
            && str_contains($mail->actionUrl, 'email='.urlencode('olive@example.test'));
    });
});

test('an unknown email gets the same answer, and nothing is sent', function () {
    resetOwner();

    $this->post('/forgot-password', ['email' => 'nobody@example.test'])
        ->assertSessionHas('status', PasswordResetLinkController::SENT);

    Notification::assertNothingSent();
});

test('asking twice within a minute sends one email but answers the same', function () {
    $owner = resetOwner();

    $this->post('/forgot-password', ['email' => $owner->email]);
    $this->post('/forgot-password', ['email' => $owner->email])->assertSessionHas('status', PasswordResetLinkController::SENT);

    Notification::assertSentToTimes($owner, ResetPassword::class, 1);
});

test('the link opens the new password page with the email filled in', function () {
    $this->get('/reset-password/abc123?email=olive%40example.test')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Auth/ResetPassword')
        ->where('token', 'abc123')
        ->where('email', 'olive@example.test'));
});

test('a valid link sets the new password and logs the owner in', function () {
    $owner = resetOwner(['password' => Hash::make('old-password')]);
    $token = Password::createToken($owner);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $owner->email,
        'password' => 'brand-new-pass',
        'password_confirmation' => 'brand-new-pass',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($owner);
    expect(Hash::check('brand-new-pass', $owner->fresh()->password))->toBeTrue();

    // The link only works once.
    auth()->logout();
    $this->post('/reset-password', [
        'token' => $token, 'email' => $owner->email, 'password' => 'another-pass1', 'password_confirmation' => 'another-pass1',
    ])->assertSessionHasErrors('email');
});

test('an owner who only used Google can add a password this way', function () {
    $owner = resetOwner(['google_id' => 'g-123', 'password' => null]);
    $token = Password::createToken($owner);

    $this->post('/reset-password', [
        'token' => $token, 'email' => $owner->email, 'password' => 'my-first-pass', 'password_confirmation' => 'my-first-pass',
    ])->assertRedirect('/dashboard');

    expect(Hash::check('my-first-pass', $owner->fresh()->password))->toBeTrue();
});

test('a wrong or expired link, or a weak password, is refused', function () {
    $owner = resetOwner(['password' => Hash::make('old-password')]);

    $this->post('/reset-password', [
        'token' => 'not-a-real-token', 'email' => $owner->email, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
    ])->assertSessionHasErrors(['email' => 'This reset link has expired or was already used. Ask for a new one.']);

    $this->post('/reset-password', [
        'token' => Password::createToken($owner), 'email' => $owner->email, 'password' => 'short', 'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
    expect(Hash::check('old-password', $owner->fresh()->password))->toBeTrue();
});

test('the reset is recorded in the activity log', function () {
    $owner = resetOwner();

    $this->post('/reset-password', [
        'token' => Password::createToken($owner), 'email' => $owner->email, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
    ]);

    $this->assertDatabaseHas('activity_logs', ['action' => 'auth.password_reset', 'user_id' => $owner->id]);
});

test('signed-in users are sent away from the reset pages', function () {
    $this->actingAs(resetOwner())->get('/forgot-password')->assertRedirect();
});
