<?php

namespace Tests\Feature;

use App\Support\PdfAssets;
use Tests\TestCase;

/** La marca de agua y el sello llegan en JPEG y hay que darles transparencia. */
class PdfAssetsTest extends TestCase
{
    private function decode(string $dataUri): \GdImage
    {
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);

        $image = imagecreatefromstring(base64_decode(explode(',', $dataUri, 2)[1]));

        $this->assertNotFalse($image);

        return $image;
    }

    /** Alfa de GD: 0 es opaco y 127 completamente transparente. */
    private function alphaAt(\GdImage $image, int $x, int $y): int
    {
        return (imagecolorat($image, $x, $y) >> 24) & 0x7F;
    }

    public function test_the_watermark_loses_its_white_background(): void
    {
        $image = $this->decode(PdfAssets::watermark());

        // La esquina es fondo blanco en el JPEG original.
        $this->assertSame(127, $this->alphaAt($image, 0, 0));

        // El centro del caduceo conserva algo de tinta.
        $center = $this->alphaAt($image, (int) (imagesx($image) / 2), (int) (imagesy($image) / 2));
        $this->assertLessThan(127, $center);
    }

    public function test_the_watermark_is_downscaled_for_the_certificate(): void
    {
        $image = $this->decode(PdfAssets::watermark());

        // El original mide 614 px de ancho y en el PDF se imprime a unos 3 cm.
        $this->assertLessThanOrEqual(480, imagesx($image));
    }

    public function test_the_signature_stamp_is_available_and_transparent(): void
    {
        $image = $this->decode(PdfAssets::signature());

        $this->assertSame(127, $this->alphaAt($image, 0, 0));
        $this->assertGreaterThan(100, imagesx($image));
    }

    public function test_the_conversion_is_cached(): void
    {
        $first = PdfAssets::watermark();
        $second = PdfAssets::watermark();

        $this->assertSame($first, $second);
    }
}
