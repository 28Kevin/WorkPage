<?php

namespace Tests\Feature;

use App\Mail\DatabaseBackupMail;
use App\Models\BackupRun;
use App\Models\User;
use App\Support\MailDelivery;
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

    /** El respaldo solo lo maneja una cuenta de desarrollo. */
    private function developer(): User
    {
        return User::factory()->create(['role' => User::ROLE_DEVELOPER]);
    }

    public function test_it_requires_a_session(): void
    {
        $this->postJson('/api/backups')->assertUnauthorized();

        Mail::assertNothingSent();
    }

    public function test_an_administrator_cannot_run_a_backup(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/backups')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->getJson('/api/backups')->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_the_download_needs_a_developer_account(): void
    {
        $this->getJson('/api/backups/download')->assertUnauthorized();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]), 'sanctum')
            ->getJson('/api/backups/download')
            ->assertForbidden();
    }

    public function test_a_failed_download_is_recorded_as_such(): void
    {
        /*
         * En las pruebas la base es SQLite en memoria: no hay nada que volcar,
         * asi que el comando falla. Lo que importa es que el fallo quede
         * anotado en el historial y marcado como descarga, no como correo.
         */
        $this->actingAs($this->developer(), 'sanctum')
            ->getJson('/api/backups/download')
            ->assertStatus(409);

        $run = BackupRun::latest('id')->first();

        $this->assertSame(BackupRun::FAILED, $run->status);
        $this->assertSame(BackupRun::DOWNLOAD, $run->channel);
        $this->assertNotEmpty($run->error);
    }

    public function test_the_download_does_not_depend_on_the_mail_being_configured(): void
    {
        // Sin correo configurado, enviar falla; descargar no tiene por que.
        config()->set('mail.default', 'log');

        $this->actingAs($this->developer(), 'sanctum')
            ->getJson('/api/backups/download')
            // Falla por el volcado, no por el correo: el mensaje no lo menciona.
            ->assertStatus(409)
            ->assertJsonPath('message', fn (string $m) => ! str_contains($m, 'correo'));
    }

    public function test_the_history_separates_downloads_from_mails(): void
    {
        $developer = $this->developer();

        $this->actingAs($developer, 'sanctum')->getJson('/api/backups/download')->assertStatus(409);
        $this->actingAs($developer, 'sanctum')
            ->postJson('/api/backups', ['email' => 'kevin@ejemplo.test'])
            ->assertStatus(409);

        $channels = collect(
            $this->actingAs($developer, 'sanctum')->getJson('/api/backups')->json('runs'),
        )->pluck('channel')->all();

        $this->assertSame([BackupRun::MAIL, BackupRun::DOWNLOAD], $channels);
    }

    public function test_the_recipient_must_be_a_valid_address(): void
    {
        $this->actingAs($this->developer(), 'sanctum')
            ->postJson('/api/backups', ['email' => 'no-es-un-correo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Mail::assertNothingSent();
    }

    public function test_it_reports_a_clear_error_when_the_dump_fails(): void
    {
        // En las pruebas la base es SQLite en memoria: no hay archivo que volcar,
        // así que el comando falla y el panel debe explicarlo en vez de reventar.
        $this->actingAs($this->developer(), 'sanctum')
            ->postJson('/api/backups', ['email' => 'kevin@ejemplo.test'])
            ->assertStatus(409)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'respaldo'));

        Mail::assertNothingSent();
    }

    public function test_a_failed_attempt_is_recorded(): void
    {
        // Un respaldo que falla sin dejar rastro es el peor caso: nadie se
        // entera de que lleva semanas sin haber una copia.
        $this->actingAs($this->developer(), 'sanctum')
            ->postJson('/api/backups', ['email' => 'kevin@ejemplo.test'])
            ->assertStatus(409);

        $run = BackupRun::latest('id')->first();

        $this->assertNotNull($run);
        $this->assertSame(BackupRun::FAILED, $run->status);
        $this->assertFalse($run->delivered);
        $this->assertNotEmpty($run->error);
    }

    public function test_the_panel_reports_the_state_of_the_mail_and_the_last_attempts(): void
    {
        $this->actingAs($this->developer(), 'sanctum')
            ->postJson('/api/backups', ['email' => 'kevin@ejemplo.test'])
            ->assertStatus(409);

        $this->actingAs($this->developer(), 'sanctum')
            ->getJson('/api/backups')
            ->assertOk()
            ->assertJsonStructure([
                'mail' => ['deliverable', 'mailer', 'reason'],
                'last_successful_at',
                'runs' => [['status', 'delivered', 'mailer', 'error', 'created_at']],
            ])
            ->assertJsonPath('runs.0.status', BackupRun::FAILED)
            // Sin un respaldo bueno, la fecha va vacia en vez de inventada.
            ->assertJsonPath('last_successful_at', null);
    }

    public function test_the_log_driver_is_not_reported_as_a_delivery(): void
    {
        /*
         * Con MAIL_MAILER=log, Mail::send() termina sin error y no sale nada
         * del servidor. Informarlo como enviado daba por cubierto un respaldo
         * que nunca salio: es el fallo que se vio en el panel.
         */
        config()->set('mail.default', 'log');

        $check = MailDelivery::check();

        $this->assertFalse($check['deliverable']);
        $this->assertNotEmpty($check['reason']);
    }

    public function test_an_smtp_without_a_host_is_not_a_delivery_either(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', '');

        $this->assertFalse(MailDelivery::check()['deliverable']);
    }

    public function test_a_configured_smtp_counts_as_a_delivery(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', 'smtp.ejemplo.test');
        config()->set('mail.from.address', 'avisos@ejemplo.test');

        $check = MailDelivery::check();

        $this->assertTrue($check['deliverable']);
        $this->assertNull($check['reason']);
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
