<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeGenerator
{
    /** Devuelve el QR como data URI listo para incrustar en el PDF. */
    public function dataUri(string $content, int $size = 220): string
    {
        $result = (new Builder(
            writer: new PngWriter(),
            data: $content,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 8,
        ))->build();

        return $result->getDataUri();
    }
}
