<?php

test('the landing page returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
