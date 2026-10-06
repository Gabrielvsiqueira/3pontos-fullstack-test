<?php

declare(strict_types=1);

it('answers the health check', function (): void {
    $this->get('/up')->assertOk();
});

it('renders the admin login page', function (): void {
    $this->get('/admin/login')->assertOk();
});
