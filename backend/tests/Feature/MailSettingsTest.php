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
        // Laravel 13 lee 'scheme', no 'encryption'.
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame('avisos@ejemplo.test', config('mail.from.address'));

        // Y la comprobacion previa ve lo mismo que usara el envio.
        $this->assertTrue(MailDelivery::check()['deliverable']);
    }

    public function test_ssl_uses_the_implicit_tls_scheme(): void
    {
        /*
         * 'smtps' es lo que activa el cifrado implicito del puerto 465. Con la
         * clave equivocada el puerto 465 se queda esperando sin cifrar.
         */
        $this->actingAs($this->developer(), 'sanctum')
            ->putJson('/api/mail-settings', $this->payload(['encryption' => 'ssl', 'port' => 465]))
            ->assertOk();

        MailSettings::apply();

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
    }

    public function test_resend_sends_over_https_instead_of_smtp(): void
    {
        /*
         * Render bloquea los puertos de SMTP saliente en los planes gratuitos:
         * la conexion a smtp.gmail.com:587 caduca sin llegar a autenticar.
         * Resend viaja por HTTPS, que no esta bloqueado en ninguna parte.
         */
        $this->actingAs($this->developer(), 'sanctum')
            ->putJson('/api/mail-settings', [
                'transport' => 'resend',
                'api_key' => 're_la-clave-de-api',
                'from_address' => 'avisos@ejemplo.test',
                'from_name' => 'Centro Médico',
            ])
            ->assertOk()
            ->assertJsonPath('settings.transport', 'resend')
            ->assertJsonPath('settings.has_api_key', true)
            ->assertJsonPath('status.deliverable', true);

        MailSettings::apply();

        $this->assertSame('resend', config('mail.default'));
        $this->assertSame('re_la-clave-de-api', config('services.resend.key'));
        $this->assertSame('avisos@ejemplo.test', config('mail.from.address'));
    }

    public function test_the_api_key_is_stored_encrypted_and_never_returned(): void
    {
        $this->actingAs($this->developer(), 'sanctum')
            ->putJson('/api/mail-settings', ['transport' => 'resend', 'api_key' => 're_la-clave-de-api'])
            ->assertOk();

        $stored = Setting::where('key', 'mail.api_key')->value('value');

        $this->assertNotSame('re_la-clave-de-api', $stored);
        $this->assertNotEmpty($stored);

        $response = $this->actingAs($this->developer(), 'sanctum')->getJson('/api/mail-settings');

        $this->assertStringNotContainsString('re_la-clave-de-api', $response->getContent());
        $this->assertArrayNotHasKey('api_key', $response->json('settings'));
    }

    public function test_resend_without_a_key_is_not_a_delivery(): void
    {
        $this->actingAs($this->developer(), 'sanctum')
            ->putJson('/api/mail-settings', ['transport' => 'resend'])
            ->assertOk()
            ->assertJsonPath('status.deliverable', false);
    }

    public function test_an_invented_transport_is_rejected(): void
    {
        $this->actingAs($this->developer(), 'sanctum')
            ->putJson('/api/mail-settings', ['transport' => 'paloma-mensajera'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('transport');
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
