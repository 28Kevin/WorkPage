<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La sesion del panel se cierra tras 15 minutos sin actividad. */
class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private function me(string $token)
    {
        // Cada peticion debe resolver el token de nuevo, como en produccion.
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/auth/me');
    }

    public function test_the_token_expires_after_fifteen_idle_minutes(): void
    {
        $token = User::factory()->create()->createToken('web')->plainTextToken;

        $this->travel(16)->minutes();

        $this->me($token)->assertUnauthorized();
    }

    public function test_activity_keeps_the_session_alive(): void
    {
        $token = User::factory()->create()->createToken('web')->plainTextToken;

        $this->travel(10)->minutes();
        $this->me($token)->assertOk();

        // Pasaron 20 minutos desde el ingreso, pero solo 10 desde el ultimo uso.
        $this->travel(10)->minutes();
        $this->me($token)->assertOk();
    }
}
