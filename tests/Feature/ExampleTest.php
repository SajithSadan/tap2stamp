<?php

test('the landing page renders the Landing Inertia component', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Landing'));
});
