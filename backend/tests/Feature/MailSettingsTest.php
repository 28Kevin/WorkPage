<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\MailDelivery;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Credenciales del servidor de correo configuradas desde el panel. */
class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        return User::factory()->create(['role' => User::ROLE_DEVELOPER]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'host' => 'smtp.ejemplo.test',
            'port' => 587,
            'username' => 'avisos@ejemplo.test',
            'password' => 'la-clave-del-correo',
            'encryption' => 'tls',
            'from_address' => 'avisos@ejemplo.test',
            'from_name' => 'Centro Médico',
        ], $overrides);
    }

    public function test_only_a_developer_account_reaches_the_settings(): void
    {
        $this->getJson('/api/mail-settings')->assertUnauthorized();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/mail-settings')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->putJson('/api/mail-settings', $this->payload())->assertForbidden();
    }

    public function test_it_saves_the_settings_and_reports_that_the_mail_can_be_delivered(): void
    {
        $this->actingAs($this->developer(), 'sanctum')
            ->putJson('/api/mail-settings', $this->payload())
            ->assertOk()
            ->assertJsonPath('settings.host', 'smtp.ejemplo.test')
            ->assertJsonPath('settings.username', 'avisos@ejemplo.test')
            ->assertJsonPath('settings.has_password', true)
            ->assertJsonPath('status.deliverable', true);
    }

    public function test_the_password_never_comes_back_to_the_browser(): void
    {
        $this->actingAs($this->developer(), 'sanctum')
            ->putJson('/api/mail-settings', $this->payload())
            ->assertOk();

        $response = $this->actingAs($this->developer(), 'sanctum')
            ->getJson('/api/mail-settings')
            ->assertOk();

        // Ni el campo ni el valor en ninguna parte de la respuesta.
        $this->assertArrayNotHasKey('password', $response->json('settings'));
        $this->assertStringNotContainsString('la-clave-del-correo', $response->getContent());
        $this->assertTrue($response->json('settings.has_password'));
    }

    public function test_the_password_is_stored_encrypted(): void
    {
        $this->actingAs($this->developer(), 'sanctum')
            ->putJson('/api/mail-settings', $this->payload())
            ->assertOk();

        $stored = Setting::where('key', 'mail.password')->value('value');

        // Si la base se filtra, la credencial no queda a la vista.
        $this->assertNotSame('la-clave-del-correo', $stored);
        $this->assertStringNotContainsString('la-clave-del-correo', (string) $stored);
        $this->assertNotEmpty($stored);
    }

    public function test_saving_without_a_password_keeps_the_stored_one(): void
    {
        $developer = $this->developer();

        $this->actingAs($developer, 'sanctum')->putJson('/api/mail-settings', $this->payload())->assertOk();

        // El formulario nunca recibe la contrasenia, asi que no puede reenviarla:
        // guardar otro campo no debe dejar el correo sin credenciales.
        $this->actingAs($developer, 'sanctum')
            ->putJson('/api/mail-settings', $this->payload(['password' => null, 'from_name' => 'Otro nombre']))
            ->assertOk()
            ->assertJsonPath('settings.from_name', 'Otro nombre')
            ->assertJsonPath('settings.has_password', true);

        MailSettings::apply();

        $this->assertSame('la-clave-del-correo', config('mail.mailers.smtp.password'));
    }

    public function test_the_stored_settings_override_the_environment(): void
    {
        config()->set('mail.default', 'log');

        $this->actingAs($this->developer(), 'sanctum')->putJson('/api/mail-settings', $this->payload())->assertOk();

        MailSettings::apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.ejemplo.test', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('tls', config('mail.mailers.smtp.encryption'));
        $this->assertSame('avisos@ejemplo.test', config('mail.from.address'));

        // Y la comprobacion previa ve lo mismo que usara el envio.
        $this->assertTrue(MailDelivery::check()['deliverable']);
    }

    public function test_without_a_host_it_does_not_touch_the_environment(): void
    {
        config()->set('mail.default', 'log');

        MailSettings::apply();

        $this->assertSame('log', config('mail.default'));
        $this->assertFalse(MailDelivery::check()['deliverable']);
    }

    public function test_the_password_can_be_removed(): void
    {
        $developer = $this->developer();

        $this->actingAs($developer, 'sanctum')->putJson('/api/mail-settings', $this->payload())->assertOk();

        $this->actingAs($developer, 'sanctum')
            ->deleteJson('/api/mail-settings/password')
            ->assertOk();

        $this->assertFalse(MailSettings::hasPassword());
    }

    public function test_it_validates_the_form(): void
    {
        $this->actingAs($this->developer(), 'sanctum')
            ->putJson('/api/mail-settings', [
                'port' => 99999,
                'encryption' => 'inventado',
                'from_address' => 'no-es-un-correo',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['port', 'encryption', 'from_address']);
    }

    public function test_the_test_message_refuses_when_the_mail_is_not_set_up(): void
    {
        config()->set('mail.default', 'log');

        $this->actingAs($this->developer(), 'sanctum')
            ->postJson('/api/mail-settings/test', ['email' => 'kevin@ejemplo.test'])
            ->assertUnprocessable();
    }
}
