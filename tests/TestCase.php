<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Lunar\Stripe\Facades\Stripe;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Aucun test ne doit joindre l'API Stripe réelle. Le SDK Stripe utilise son
        // propre client cURL (ni Http::fake() ni Http::preventStrayRequests() ne le
        // couvrent) : sans clé d'API, le rendu du composant `payment-form` lève une
        // ViewException — et une exception pendant un test peut faire fuiter la
        // transaction RefreshDatabase (cf. docs/workflow.md § « fuite de transaction »).
        // Stripe::fake() substitue un MockClient au client HTTP du SDK.
        if (class_exists(Stripe::class)) {
            Stripe::fake();
        }
    }
}
