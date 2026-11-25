<?php

namespace Tests;

use App\Models\Part;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return array<string, string>
     */
    protected function serviceHeaders(): array
    {
        return ['X-Service-Key' => 'test-key'];
    }

    /**
     * @param int $available
     * @return Part
     */
    protected function makePart(int $available = 5): Part
    {
        return Part::query()->create(['name' => 'Part '.uniqid(), 'available' => $available]);
    }
}
