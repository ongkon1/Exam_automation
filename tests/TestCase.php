<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The transcript pipeline calls Speaklar and OpenAI. Without this, a test that
        // forgets to fake them silently hits the real APIs.
        Http::preventStrayRequests();
    }
}
