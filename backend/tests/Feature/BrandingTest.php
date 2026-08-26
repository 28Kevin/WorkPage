<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'identity' => [
                'app_name' => 'Nueva Salud Integral',
                'tagline' => 'Evaluaciones médicas ocupacionales',
                'logo' => null,
            ],
            'theme' => [
                'brand_color' => '#1d4ed8',
                'accent_color' => '#0891b2',
                'font_heading' => 'Montserrat',
                'font_body' => 'Open Sans',
                'radius' => '0.5rem',
            ],
            'center' => [
                'name' => 'NUEVA SALUD INTEGRAL IPS SAS',
                'nit' => '900526144-5',
                'address' => 'CRA 24 N. 9-76',
                'phone' => '+57 601 000 0000',
                'email' => 'contacto@nuevasalud.test',
            ],
        ], $overrides);
    }

    public function test_branding_is_public_and_falls_back_to_defaults(): void
    {
        $response = $this->getJson('/api/branding');

        $response->assertOk()
            ->assertJsonPath('branding.theme.brand_color', '#2563eb')
            ->assertJsonPath('branding.identity.logo', null);

        // La paleta se deriva del color base, no se guarda.
        $this->assertCount(10, $response->json('branding.theme.palette'));
    }

    public function test_the_physician_name_is_optional_and_empty_by_default(): void
    {
        $this->getJson('/api/branding')
            ->assertOk()
            ->assertJsonPath('branding.center.physician_name', '');

        // Se puede guardar sin él: el sello impreso ya lleva nombre y registro.
        $payload = $this->payload();
        unset($payload['center']['physician_name']);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson('/api/branding', $payload)
            ->assertOk()
            ->assertJsonPath('branding.center.physician_name', '');
    }

    public function test_guests_cannot_update_branding(): void
    {
        $this->putJson('/api/branding', $this->payload())->assertUnauthorized();

        $this->assertDatabaseCount('settings', 0);
    }

    public function test_admin_updates_branding_and_it_becomes_public(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson('/api/branding', $this->payload())
            ->assertOk()
            ->assertJsonPath('branding.theme.brand_color', '#1d4ed8')
            ->assertJsonPath('branding.center.name', 'NUEVA SALUD INTEGRAL IPS SAS');

        $this->assertDatabaseHas('settings', [
            'key' => 'theme.font_heading',
            'value' => 'Montserrat',
        ]);

        $this->getJson('/api/branding')
            ->assertJsonPath('branding.identity.app_name', 'Nueva Salud Integral')
            ->assertJsonPath('branding.theme.radius', '0.5rem');
    }

    public function test_invalid_color_and_font_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson('/api/branding', $this->payload([
                'theme' => ['brand_color' => 'azul', 'font_body' => 'Open Sans, sans-serif'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['theme.brand_color', 'theme.font_body']);
    }

    public function test_logo_must_be_an_image_data_uri(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson('/api/branding', $this->payload([
                'identity' => ['logo' => 'https://ejemplo.test/logo.png'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identity.logo']);
    }

    public function test_palette_stays_ordered_from_light_to_dark(): void
    {
        $palette = Branding::palette('#2563eb');

        $this->assertSame(['50', '100', '200', '300', '400', '500', '600', '700', '800', '900'],
            array_map('strval', array_keys($palette)));

        // Cada tono debe ser mas oscuro que el anterior.
        $luma = fn (string $hex) => array_sum(sscanf($hex, '#%02x%02x%02x'));

        $previous = PHP_INT_MAX;

        foreach ($palette as $hex) {
            $this->assertLessThan($previous, $luma($hex));
            $previous = $luma($hex);
        }
    }

    public function test_saving_clears_the_settings_cache(): void
    {
        Setting::map();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson('/api/branding', $this->payload())
            ->assertOk();

        $this->assertSame('Montserrat', Setting::map()['theme.font_heading']);
    }
}
