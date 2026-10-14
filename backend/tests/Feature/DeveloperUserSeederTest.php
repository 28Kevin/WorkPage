<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DeveloperUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** La cuenta de desarrollo se crea sembrando, sin correr comandos a mano. */
class DeveloperUserSeederTest extends TestCase
{
    use RefreshDatabase;

    private function withEnv(?string $email, ?string $password, ?string $name = null): void
    {
        $_ENV['DEVELOPER_EMAIL'] = $_SERVER['DEVELOPER_EMAIL'] = $email;
        $_ENV['DEVELOPER_PASSWORD'] = $_SERVER['DEVELOPER_PASSWORD'] = $password;
        $_ENV['DEVELOPER_NAME'] = $_SERVER['DEVELOPER_NAME'] = $name;

        putenv($email === null ? 'DEVELOPER_EMAIL' : "DEVELOPER_EMAIL={$email}");
        putenv($password === null ? 'DEVELOPER_PASSWORD' : "DEVELOPER_PASSWORD={$password}");
        putenv($name === null ? 'DEVELOPER_NAME' : "DEVELOPER_NAME={$name}");
    }

    protected function tearDown(): void
    {
        $this->withEnv(null, null, null);

        parent::tearDown();
    }

    public function test_it_creates_the_account_from_the_environment(): void
    {
        $this->withEnv('dev@centromedico.test', 'una-clave-larga', 'Equipo de desarrollo');

        $this->seed(DeveloperUserSeeder::class);

        $user = User::where('email', 'dev@centromedico.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('Equipo de desarrollo', $user->name);
        $this->assertSame(User::ROLE_DEVELOPER, $user->role);
        $this->assertTrue($user->isDeveloper());
        $this->assertTrue(Hash::check('una-clave-larga', $user->password));
    }

    public function test_the_seeded_account_reaches_the_dashboard(): void
    {
        $this->withEnv('dev@centromedico.test', 'una-clave-larga');

        $this->seed(DeveloperUserSeeder::class);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'dev@centromedico.test',
            'password' => 'una-clave-larga',
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/dev/usage')->assertOk();
    }

    public function test_without_the_variables_it_creates_nothing(): void
    {
        // Una cuenta de desarrollo con credenciales conocidas, sembrada en
        // cada despliegue, seria una puerta abierta en producción.
        $this->withEnv(null, null);

        $this->seed(DeveloperUserSeeder::class);

        $this->assertSame(0, User::where('role', User::ROLE_DEVELOPER)->count());
    }

    public function test_with_the_email_but_no_password_it_creates_nothing(): void
    {
        $this->withEnv('dev@centromedico.test', null);

        $this->seed(DeveloperUserSeeder::class);

        $this->assertDatabaseMissing('users', ['email' => 'dev@centromedico.test']);
    }

    public function test_seeding_again_updates_the_account_instead_of_duplicating_it(): void
    {
        $this->withEnv('dev@centromedico.test', 'clave-inicial');
        $this->seed(DeveloperUserSeeder::class);

        $this->withEnv('dev@centromedico.test', 'clave-nueva');
        $this->seed(DeveloperUserSeeder::class);

        $this->assertSame(1, User::where('email', 'dev@centromedico.test')->count());
        $this->assertTrue(Hash::check('clave-nueva', User::where('email', 'dev@centromedico.test')->value('password')));
    }
}
