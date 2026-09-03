<?php

namespace Tests\Feature;

use App\Mail\DatabaseBackupMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Respaldo de la base enviado por correo desde el panel. */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
    }

    public function test_it_requires_a_session(): void
    {
        $this->postJson('/api/backups')->assertUnauthorized();

        Mail::assertNothingSent();
    }

    public function test_the_recipient_must_be_a_valid_address(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/backups', ['email' => 'no-es-un-correo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Mail::assertNothingSent();
    }

    public function test_it_reports_a_clear_error_when_the_dump_fails(): void
    {
        // En las pruebas la base es SQLite en memoria: no hay archivo que volcar,
        // así que el comando falla y el panel debe explicarlo en vez de reventar.
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/backups', ['email' => 'kevin@ejemplo.test'])
            ->assertStatus(409)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'respaldo'));

        Mail::assertNothingSent();
    }

    public function test_the_mail_carries_the_archive_as_an_attachment(): void
    {
        $mailable = new DatabaseBackupMail('/tmp/respaldo.zip', 'respaldo-2026-01-01.zip', 402 * 1024);

        $this->assertStringContainsString('Respaldo de la base', $mailable->envelope()->subject);
        $this->assertSame('mail.database-backup', $mailable->content()->markdown);

        $attachment = $mailable->attachments()[0];

        $this->assertNotNull($attachment);
    }

    public function test_the_command_fails_cleanly_instead_of_throwing(): void
    {
        // Mismo camino que el panel: sin base que volcar debe salir con error
        // controlado, no con una excepción sin manejar.
        $this->artisan('respaldo:enviar', ['correo' => 'kevin@ejemplo.test'])
            ->assertExitCode(1);

        Mail::assertNothingSent();
    }

    public function test_the_configured_recipient_is_never_empty(): void
    {
        // Si quedara vacío, spatie lanza un error críptico al armar su config.
        $this->assertNotEmpty(config('backup.notifications.mail.to'));
    }
}
