<?php

it('exposes a JSON service descriptor at the root', function (): void {
    $this->getJson('/')
        ->assertOk()
        ->assertJsonPath('name', 'ZENTRO API')
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('version', 'v1')
        ->assertJsonStructure(['name', 'tagline', 'status', 'environment', 'version', 'api_base', 'health', 'docs', 'time']);
});

it('points the descriptor at the API base and health endpoint', function (): void {
    $response = $this->getJson('/')->assertOk();

    expect($response->json('api_base'))->toEndWith('/api/v1')
        ->and($response->json('health'))->toEndWith('/up');
});
