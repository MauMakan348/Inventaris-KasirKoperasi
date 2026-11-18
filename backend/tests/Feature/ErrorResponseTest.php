<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/test/boom', function (): never {
            throw new RuntimeException(
                'SQLSTATE[HY000]: General error: SELECT * FROM users WHERE password = secret'
            );
        });
    }

    #[Test]
    public function internal_error_hides_sql_and_stack_trace_when_debug_enabled(): void
    {
        config(['app.debug' => true]);

        $response = $this->getJson('/api/test/boom');

        $response->assertInternalServerError()
            ->assertJsonPath('error.code', 'INTERNAL_ERROR')
            ->assertJsonMissingPath('error.details');

        $this->assertSafeErrorBody($response->getContent());
    }

    #[Test]
    public function internal_error_hides_sql_and_stack_trace_when_debug_disabled(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/test/boom');

        $response->assertInternalServerError()
            ->assertJsonPath('error.code', 'INTERNAL_ERROR')
            ->assertJsonMissingPath('error.details');

        $this->assertSafeErrorBody($response->getContent());
    }

    /**
     * Assert the body leaks no internal diagnostics.
     */
    private function assertSafeErrorBody(string $body): void
    {
        foreach (['SQLSTATE', 'SELECT', 'Stack trace', 'RuntimeException', '.php'] as $needle) {
            $this->assertStringNotContainsString($needle, $body);
        }
    }
}
