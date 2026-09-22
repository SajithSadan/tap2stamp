<?php

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
