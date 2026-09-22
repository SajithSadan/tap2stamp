<?php

use App\Enums\UserRole;
use App\Models\User;

test('a request with no token is rejected', function () {
    config(['deploy.migrate_token' => 'the-real-token']);

    $response = $this->postJson('/deploy/migrate');

    $response->assertForbidden();
});

test('a request with the wrong token is rejected', function () {
    config(['deploy.migrate_token' => 'the-real-token']);

    $response = $this->postJson('/deploy/migrate', [], [
        'Authorization' => 'Bearer wrong-token',
    ]);

    $response->assertForbidden();
});

test('the route refuses to run even with a token if none is configured', function () {
    config(['deploy.migrate_token' => null]);

    $response = $this->postJson('/deploy/migrate', [], [
        'Authorization' => 'Bearer anything',
    ]);

    $response->assertForbidden();
});

test('a request with the correct token runs the migration and succeeds', function () {
    config(['deploy.migrate_token' => 'the-real-token']);

    $response = $this->postJson('/deploy/migrate', [], [
        'Authorization' => 'Bearer the-real-token',
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);
    $response->assertJsonStructure(['status', 'output']);
});

test('seed-admin rejects an unauthenticated request the same way migrate does', function () {
    config(['deploy.migrate_token' => 'the-real-token']);

    $this->postJson('/deploy/seed-admin')->assertForbidden();
});

test('seed-admin refuses to run when ADMIN_EMAIL/ADMIN_PASSWORD are not configured', function () {
    config(['deploy.migrate_token' => 'the-real-token', 'deploy.admin_email' => null, 'deploy.admin_password' => null]);

    $response = $this->postJson('/deploy/seed-admin', [], ['Authorization' => 'Bearer the-real-token']);

    $response->assertStatus(422);
});

test('seed-admin creates the admin account and is idempotent on a second call', function () {
    config([
        'deploy.migrate_token' => 'the-real-token',
        'deploy.admin_email' => 'admin@example.com',
        'deploy.admin_password' => 'a-strong-password',
    ]);

    $first = $this->postJson('/deploy/seed-admin', [], ['Authorization' => 'Bearer the-real-token']);
    $first->assertOk();
    $first->assertJson(['status' => 'ok', 'created' => true]);

    $admin = User::where('email', 'admin@example.com')->firstOrFail();
    expect($admin->role)->toBe(UserRole::Admin);

    $second = $this->postJson('/deploy/seed-admin', [], ['Authorization' => 'Bearer the-real-token']);
    $second->assertOk();
    $second->assertJson(['status' => 'ok', 'created' => false]);

    expect(User::where('email', 'admin@example.com')->count())->toBe(1);
});
