<?php

declare(strict_types=1);

it('refuses without the right token', function (): void {
    config(['services.opcache.flush_token' => 'secret']);

    $this->get('/opcache-flush')->assertForbidden();
    $this->get('/opcache-flush?token=nope')->assertForbidden();
});

it('refuses everything while no token is configured', function (): void {
    config(['services.opcache.flush_token' => '']);

    $this->get('/opcache-flush?token=')->assertForbidden();
});

it('flushes with the right token', function (): void {
    config(['services.opcache.flush_token' => 'secret']);

    $this->get('/opcache-flush?token=secret')->assertOk();
});
