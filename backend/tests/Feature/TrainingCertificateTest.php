<?php

namespace Tests\Feature;

use App\Models\TrainingCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Certificado de capacitacion: formulario, consecutivo propio, PDF calcado del
 * documento de guia y pagina publica a la que lleva el QR.
 */
class TrainingCertificateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'participant_name' => 'Ángel Ernesto Simanca Cortina',
            'document_number' => '1082370757',
            'course' => 'Aparejador',
            'city' => 'Bogotá',
            'start_date' => '2026-10-04',
            'end_date' => '2026-10-04',
            'hours' => 8,
            'signed_date' => '2026-10-04',
            'company_nit' => '901.482.951-5',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function create(array $overrides = []): array
    {
        return $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/certificates', $this->payload($overrides))
            ->assertCreated()
            ->json('data');
    }

    /** PNG de 1x1 para las pruebas del logo. */
    private function logo(): string
    {
        $canvas = imagecreatetruecolor(1, 1);
        ob_start();
        imagepng($canvas);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    public function test_the_module_is_restricted_to_authenticated_users(): void
    {
        $this->getJson('/api/certificates')->assertUnauthorized();
        $this->postJson('/api/certificates', $this->payload())->assertUnauthorized();
    }

    public function test_the_serial_follows_the_format_of_the_guide_document(): void
    {
        $first = $this->create();
        $second = $this->create(['document_number' => '1082370758']);

        // El documento de guia numera "S.S-1370": prefijo y numero, sin anio.
        $this->assertSame('S.S-1', $first['order_code']);
        $this->assertSame('S.S-2', $second['order_code']);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/certificates/next-order-number')
            ->assertOk()
            ->assertJsonPath('next_order_code', 'S.S-3');
    }

    public function test_creation_validates_the_form(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/certificates', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'participant_name', 'document_number', 'course', 'city',
                'start_date', 'end_date', 'hours', 'signed_date',
            ]);

        // La formacion no puede terminar antes de empezar.
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/certificates', $this->payload([
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-04',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }

    public function test_the_logo_and_the_nit_are_stored_for_the_public_page(): void
    {
        $certificate = $this->create(['company_logo' => $this->logo()]);

        $this->assertSame('901.482.951-5', $certificate['company']['nit']);
        $this->assertStringStartsWith('data:image/png;base64,', $certificate['company']['logo']);
    }

    public function test_the_qr_points_to_the_certificate_page(): void
    {
        $certificate = $this->create();

        $this->assertStringContainsString(
            '/certificado/'.$certificate['verification']['code'],
            $certificate['verification']['url'],
        );
    }

    public function test_the_public_page_confirms_the_certification_with_logo_and_nit(): void
    {
        $certificate = $this->create(['company_logo' => $this->logo()]);

        $response = $this->getJson('/api/public/certificates/'.$certificate['verification']['code'])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('document_type', TrainingCertificate::TYPE)
            ->assertJsonPath('certificate.order_code', $certificate['order_code'])
            ->assertJsonPath('certificate.course', 'Aparejador')
            ->assertJsonPath('certificate.hours', 8)
            ->assertJsonPath('company.nit', '901.482.951-5');

        $this->assertStringStartsWith('data:image/png;base64,', $response->json('company.logo'));

        // Los datos personales van enmascarados, como en la otra consulta.
        $this->assertSame('******0757', $response->json('certificate.document_number'));
    }

    public function test_the_public_page_rejects_an_unknown_code(): void
    {
        $this->getJson('/api/public/certificates/CODIGO-FALSO-XX')
            ->assertNotFound()
            ->assertJsonPath('valid', false);
    }

    public function test_an_annulled_certificate_still_answers_the_printed_qr(): void
    {
        $certificate = $this->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/certificates/{$certificate['id']}", ['reason' => 'Emitido por error'])
            ->assertOk();

        $this->getJson('/api/public/certificates/'.$certificate['verification']['code'])
            ->assertStatus(410)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('annulled', true);
    }

    public function test_editing_keeps_the_serial_and_the_verification_code(): void
    {
        $certificate = $this->create();

        $updated = $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/certificates/{$certificate['id']}", $this->payload(['hours' => 16]))
            ->assertOk()
            ->json('data');

        $this->assertSame($certificate['order_code'], $updated['order_code']);
        $this->assertSame($certificate['verification']['code'], $updated['verification']['code']);
        $this->assertSame(16, $updated['hours']);
    }

    public function test_an_annulled_certificate_cannot_be_edited(): void
    {
        $certificate = $this->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/certificates/{$certificate['id']}", ['reason' => 'Datos incorrectos'])
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/certificates/{$certificate['id']}", $this->payload())
            ->assertConflict();
    }

    public function test_the_pdf_reproduces_the_layout_of_the_guide_document(): void
    {
        $certificate = $this->create();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->get("/api/certificates/{$certificate['id']}/pdf");

        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);

        $stream = $this->pageStream($content);

        // Carta apaisada y una sola pagina, como el original.
        $this->assertSame(1, preg_match_all('~/Type\s*/Page[^s]~', $content));
        $this->assertStringContainsString('792', $stream);

        // Fondo a pagina completa y QR de 80x80 en (150, 80): las mismas
        // posiciones que trae el documento de guia.
        $this->assertStringContainsString('792.000 0 0 612.000 0.000 0.000 cm', $stream);
        $this->assertStringContainsString('80.000 0 0 80.000 150.000 80.000 cm', $stream);

        // Lineas base del original, en coordenadas PDF.
        foreach (['382.000', '336.000', '238.000', '188.000', '176.000', '41.000', '13.000'] as $baseline) {
            $this->assertStringContainsString(" {$baseline} Td", $stream);
        }

        // El consecutivo se imprime abajo a la derecha, anclado como el original.
        $this->assertStringContainsString('562.150 13.000 Td', $stream);
        $this->assertStringContainsString('681.660 13.000 Td', $stream);
    }

    public function test_a_long_name_is_shrunk_instead_of_overflowing_the_page(): void
    {
        $certificate = $this->create([
            'participant_name' => 'María Fernanda Villalobos Echeverría De La Espriella Montenegro',
        ]);

        $stream = $this->pageStream(
            $this->actingAs($this->admin(), 'sanctum')
                ->get("/api/certificates/{$certificate['id']}/pdf")
                ->getContent(),
        );

        preg_match('~BT ([\d.]+) 382\.000 Td /F1 ([\d.]+) Tf~', $stream, $m);

        $this->assertNotEmpty($m, 'No se encontró la línea del nombre.');
        // Las tipografias sustitutas son mas anchas que las del original: el
        // nombre se encoge para no salirse del area util.
        $this->assertLessThan(38.0, (float) $m[2]);
        $this->assertGreaterThanOrEqual(86.0, (float) $m[1]);
    }

    public function test_the_downloaded_file_is_named_after_the_participant(): void
    {
        $certificate = $this->create();

        $disposition = (string) $this->actingAs($this->admin(), 'sanctum')
            ->get("/api/certificates/{$certificate['id']}/pdf")
            ->headers->get('content-disposition');

        $expected = rawurlencode('Certificado Ángel Ernesto Simanca Cortina.pdf');

        $this->assertStringContainsString("filename*=utf-8''{$expected}", $disposition);
    }

    public function test_the_listing_hides_annulled_certificates_but_can_filter_them(): void
    {
        $certificate = $this->create();
        $this->create(['document_number' => '9999999', 'participant_name' => 'Ana María Torres Gil']);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/certificates/{$certificate['id']}", ['reason' => 'Reemplazado'])
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/certificates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.document_number', '9999999');

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/certificates?status=annulled')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.annulled', true);
    }

    /** Descomprime el stream de contenido de la pagina. */
    private function pageStream(string $pdf): string
    {
        $offset = 0;

        while (($start = strpos($pdf, 'stream', $offset)) !== false) {
            $end = strpos($pdf, 'endstream', $start);

            if ($end === false) {
                break;
            }

            $data = ltrim(substr($pdf, $start + 6, $end - $start - 6), "\r\n");
            $inflated = @gzuncompress($data) ?: @gzinflate($data);

            if ($inflated !== false && strlen($inflated) < 200000 && str_contains($inflated, 'BT ')) {
                return $inflated;
            }

            $offset = $end + 9;
        }

        return '';
    }
}
