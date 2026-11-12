<?php

use App\Infrastructure\Shipping\FakeShippingProvider;

beforeEach(function (): void {
    FakeShippingProvider::reset();
});

it('requires authentication for shipping quotes', function (): void {
    $this->getJson('/api/v1/shipping/quotes?seller_id=1&weight_gram=1000')->assertStatus(401);
});

it('returns shipping options for a seller', function (): void {
    [$sellerUser, $seller] = makeSeller();

    $this->getJson(
        '/api/v1/shipping/quotes?seller_id='.$seller->getKey().'&destination_city=Bandung&weight_gram=1000&item_value=150000',
        authHeadersFor($sellerUser),
    )
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'flat')
        ->assertJsonPath('data.0.cost', 15000);
});

it('validates the quote request', function (): void {
    [$sellerUser] = makeSeller();

    $this->getJson('/api/v1/shipping/quotes', authHeadersFor($sellerUser))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'VALIDATION_FAILED');
});
