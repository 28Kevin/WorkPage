<?php

namespace App\Services;

use App\Models\BillingAccount;
use App\Support\Branding;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;
use Illuminate\Support\Str;

/** Cuenta de cobro en PDF: hoja carta, texto centrado y sin logo. */
class BillingAccountPdfGenerator
{
    public function make(BillingAccount $account): PdfWrapper
    {
        return Pdf::loadView('pdf.billing-account', [
            'account' => $account,
            // Datos del emisor, configurados una vez en el panel.
            'biller' => Branding::all()['biller'],
        ])->setPaper('letter');
    }

    public function filename(BillingAccount $account): string
    {
        $company = $this->sanitizeForFilename((string) $account->company_name);

        return trim('Cuenta de cobro '.$account->number_code.' - '.$company, ' -').'.pdf';
    }

    private function sanitizeForFilename(string $value): string
    {
        $clean = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], ' ', $value);
        $clean = preg_replace('~[\x00-\x1F]+~u', ' ', $clean) ?? '';
        $clean = trim(preg_replace('~\s+~u', ' ', $clean) ?? '');

        return Str::limit($clean, 70, '');
    }
}
