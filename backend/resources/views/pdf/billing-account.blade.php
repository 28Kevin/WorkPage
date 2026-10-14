@php
    use App\Support\MoneyInWords;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    {{-- Sin charset las tildes salen rotas en el PDF. --}}
    <meta charset="utf-8">
    <title>Cuenta de cobro {{ $account->number_code }}</title>
    <style>
        /*
         * Margenes del documento de guia, en twips sobre 1440 por pulgada:
         * izq 800, der 1580, sup 1220, inf 280.
         */
        @page { margin: 61pt 79pt 14pt 40pt; }

        body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #000; }

        p { margin: 0; }

        .blank { height: 9pt; }

        .title { text-align: center; font-weight: bold; font-size: 10pt; }

        .head { font-weight: bold; }

        .center { text-align: center; }
        .center.b { font-weight: bold; }

        .items p { margin: 0 0 1pt; }

        /* Espacio para la firma entre "Cordial saludo," y la cedula. */
        .signature { height: 58pt; }
        .signature img { max-height: 58pt; max-width: 220pt; }

        .annulled { text-align: center; font-weight: bold; color: #b91c1c;
                    letter-spacing: 3pt; margin-bottom: 6pt; }
    </style>
</head>
<body>

@if ($account->isAnnulled())
    <p class="annulled">CUENTA DE COBRO ANULADA</p>
@endif

<div class="blank"></div>

<p class="title">Cuenta de cobro</p>

<div class="blank"></div>

<p class="head">Lugar de Expedición: {{ $account->issue_place }}</p>

<div class="blank"></div>

<p class="head">Fecha de expedición: {{ $account->issue_date->format('d/m/Y') }}</p>
<p class="head">Cuenta de Cobro No.: {{ $account->number_code }}</p>

<div class="blank"></div>

{{-- Empresa a la que se le cobra. --}}
<p class="center b">{{ $account->company_name }}</p>
<p class="center b">
    NIT: {{ $account->company_nit }}&nbsp;&nbsp;DIRECCIÓN: {{ $account->company_address }}
    {{ $account->company_city }}
</p>

<p class="center b">DEBE A:</p>

{{-- Emisor: se configura una vez en el panel. --}}
<p class="center">{{ $biller['name'] }}</p>
<p class="center">NIT: {{ $biller['nit'] }}</p>

<div class="blank"></div>

<p class="center b">LA SUMA DE:</p>
<p class="center b">
    Total: {{ MoneyInWords::spell($account->total) }} ({{ MoneyInWords::format($account->total) }})
</p>

<div class="blank"></div>

<p class="head">POR CONCEPTO DE:</p>

<div class="items">
    @foreach ($account->items as $item)
        <p>({{ $item['quantity'] }})&nbsp;&nbsp;&nbsp;{{ $item['concept'] }}</p>
    @endforeach
</div>

<div class="blank"></div>

<p>Gracias por su atención.</p>

<div class="blank"></div>
<div class="blank"></div>

<p>Cordial saludo,</p>

<div class="signature">
    @if ($biller['signature'])
        <img src="{{ $biller['signature'] }}" alt="Firma">
    @endif
</div>

<p>{{ $biller['name'] }}</p>
<p>CC. {{ $biller['document'] }}</p>
<p class="head">Teléfono Celular: {{ $biller['mobile'] }}</p>
<p class="head">E-mail: {{ $biller['email'] }}</p>

</body>
</html>
