<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login is throttled after five attempts from the same address', function () {
    expect(config('database.default'))->toBe('sqlite');

    $payload = [
        'email' => 'nobody@store.test',
        'password' => 'secret123',
    ];

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/login', $payload)->assertUnprocessable();
    }

    $this->postJson('/api/login', $payload)->assertTooManyRequests();
});
