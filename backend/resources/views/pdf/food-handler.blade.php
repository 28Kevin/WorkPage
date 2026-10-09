@php
    $blank = '_______________________';

    /* Nombre que acompania cada firma. Vacio => linea en blanco para firmar. */
    $signatureName = [
        'bacteriologist' => $texts['bacteriologist_name'],
        'physician' => $center['physician_name'],
        'food_engineer' => $texts['food_engineer_name'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    {{-- Sin charset las tildes salen rotas en el PDF. --}}
    <meta charset="utf-8">
    <title>Manipulación de alimentos {{ $record->order_code }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 104px 42px 86px; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 10.4px; color: #111827; }

        table { width: 100%; border-collapse: collapse; }

        /* Encabezado y pie: dompdf repite los elementos fixed en cada pagina. */
        .letterhead { position: fixed; top: -92px; left: 0; right: 0; text-align: center; }
        .letterhead img { max-height: 76px; max-width: 100%; }
        .letterhead .fallback { border-bottom: 2px solid {{ $palette[700] }}; padding-bottom: 5px; }
        .letterhead .name { font-size: 13.6px; font-weight: bold; color: {{ $palette[800] }}; }
        .letterhead .meta { font-size: 8.8px; color: #4b5563; }

        .pagefoot { position: fixed; bottom: -74px; left: 0; right: 0; text-align: center; }
        .pagefoot img { max-height: 62px; max-width: 100%; }
        .pagefoot .fallback { border-top: 1px solid #d1d5db; padding-top: 4px;
                              font-size: 8.4px; color: #6b7280; }

        /* Marca de agua: z-index negativo o dompdf la pinta encima del texto. */
        .watermark { position: fixed; top: 300px; left: 0; right: 0; text-align: center; z-index: -1; }
        .watermark img { width: 300px; }

        .page { page-break-after: always; }
        .page.last { page-break-after: auto; }

        h1.doc { font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: .6px;
                 text-align: center; color: {{ $palette[800] }}; margin: 0 0 9px; }

        /* Datos del paciente, repetidos en cada pagina del paquete. */
        .patient { border: 1px solid #d1d5db; margin-bottom: 9px; }
        .patient td { padding: 4px 7px; border-right: 1px solid #e5e7eb;
                      border-bottom: 1px solid #e5e7eb; font-size: 9.6px; }
        .patient td:last-child { border-right: none; }
        .patient tr:last-child td { border-bottom: none; }
        .patient .k { color: #6b7280; text-transform: uppercase; font-size: 7.8px;
                      letter-spacing: .3px; display: block; }
        .patient .v { font-weight: bold; }

        /* Paneles de laboratorio. */
        .panel { margin-bottom: 9px; border: 1px solid #d1d5db; }
        .panel .title { background: {{ $palette[50] }}; color: {{ $palette[900] }};
                        font-size: 9.2px; font-weight: bold; text-transform: uppercase;
                        letter-spacing: .4px; padding: 4px 7px; border-bottom: 1px solid #d1d5db; }
        .panel .group { background: #f9fafb; font-size: 8.6px; font-weight: bold; color: #374151;
                        padding: 3px 7px; border-bottom: 1px solid #e5e7eb; }
        .panel td { padding: 3px 7px; border-bottom: 1px solid #f3f4f6; font-size: 9.6px; }
        .panel td.f { width: 42%; color: #4b5563; }
        .panel td.v { font-weight: bold; }
        .panel tr:last-child td { border-bottom: none; }

        .prose { font-size: 10.4px; line-height: 1.75; text-align: justify; }
        .prose strong { color: {{ $palette[900] }}; }

        /* Firma al pie de cada pagina. */
        .sign { margin-top: 20px; }
        .sign img { max-height: 54px; max-width: 200px; display: block; margin-bottom: 2px; }
        .sign .rule { border-bottom: 1px solid #6b7280; width: 220px; }
        .sign .who { font-size: 9.2px; font-weight: bold; margin-top: 2px; }
        .sign .role { font-size: 8.6px; color: #6b7280; }

        /* ------------------------------------------------------- carnet ---- */
        .card { width: 340px; border: 3px solid {{ $palette[700] }}; border-radius: 12px;
                padding: 9px 11px; }
        .card .brand { font-size: 9.6px; font-weight: bold; color: {{ $palette[800] }};
                       text-transform: uppercase; letter-spacing: .4px; text-align: center;
                       border-bottom: 1px solid {{ $palette[200] }}; padding-bottom: 4px; }
        .card .body td { vertical-align: top; padding-top: 5px; font-size: 8.8px; }
        .card .photo { width: 74px; }
        .card .photo .frame { width: 68px; height: 86px; border: 1px solid {{ $palette[300] }};
                              border-radius: 5px; text-align: center; }
        .card .photo .frame img { width: 68px; height: 86px; }
        .card .photo .empty { font-size: 7px; color: #9ca3af; padding-top: 36px; }
        .card .k { color: #6b7280; text-transform: uppercase; font-size: 6.8px;
                   letter-spacing: .3px; display: block; }
        .card .v { font-weight: bold; font-size: 9.2px; }
        .card .serial { font-family: DejaVu Sans Mono, monospace; font-weight: bold;
                        color: {{ $palette[800] }}; font-size: 10px; }
        .card .qr { width: 66px; text-align: right; }
        .card .qr img { width: 62px; height: 62px; }
        .card .cardsign { height: 30px; }
        .card .cardsign img { max-height: 28px; max-width: 120px; }
        .card .foot { border-top: 1px solid {{ $palette[200] }}; margin-top: 6px; padding-top: 4px;
                      font-size: 7px; color: #4b5563; line-height: 1.5; }
        .card .foot strong { color: {{ $palette[800] }}; }

        .advisor { width: 340px; border: 1px solid #d1d5db; border-radius: 10px; padding: 9px 11px;
                   margin-top: 16px; }
        .advisor img { max-height: 76px; max-width: 100%; }
        .advisor .k { color: #6b7280; text-transform: uppercase; font-size: 6.8px; display: block; }
        .advisor .v { font-weight: bold; font-size: 9.6px; }

        /* Verificacion publica. */
        .verify { margin-top: 14px; }
        .verify td { vertical-align: middle; }
        .verify .qr { width: 86px; text-align: center; }
        .verify .qr img { width: 78px; height: 78px; border: 1px solid #d1d5db; padding: 3px; }
        .verify .legend { font-size: 8.4px; color: #4b5563; line-height: 1.5; padding-left: 10px; }
        .verify .code { font-family: DejaVu Sans Mono, monospace; font-size: 8.8px;
                        color: {{ $palette[800] }}; word-break: break-all; }

        .annulled-stamp { position: fixed; top: 300px; left: 0; right: 0; text-align: center;
                          font-size: 88.8px; font-weight: bold; color: #dc2626; opacity: .18;
                          letter-spacing: 12px; }
    </style>
</head>
<body>

@if ($watermark)
    <div class="watermark"><img src="{{ $watermark }}" alt=""></div>
@endif

@if ($record->isAnnulled())
    <div class="annulled-stamp">ANULADO</div>
@endif

{{-- Encabezado y pie de la plantilla: se repiten en las cinco paginas. --}}
<div class="letterhead">
    @if ($images['header'])
        <img src="{{ $images['header'] }}" alt="">
    @else
        <div class="fallback">
            <div class="name">{{ $center['name'] }}</div>
            <div class="meta">NIT {{ $center['nit'] }} · {{ $center['address'] }}</div>
        </div>
    @endif
</div>

<div class="pagefoot">
    @if ($images['footer'])
        <img src="{{ $images['footer'] }}" alt="">
    @else
        <div class="fallback">
            {{ $center['name'] }} · NIT {{ $center['nit'] }} · {{ $center['phone'] }} ·
            Documento No. {{ $record->order_code }}
        </div>
    @endif
</div>

{{-- ====================================================== 1. Laboratorio I --}}
<div class="page">
    <h1 class="doc">Resultados de laboratorio clínico</h1>

    @include('pdf.food-handler.patient')

    @include('pdf.food-handler.panel', ['panelKey' => 'frotis_garganta'])
    @include('pdf.food-handler.panel', ['panelKey' => 'koh'])
    @include('pdf.food-handler.panel', ['panelKey' => 'coprologico'])

    @include('pdf.food-handler.signature', ['role' => 'bacteriologist'])
</div>

{{-- ================================================= 2. Certificado medico --}}
<div class="page">
    <h1 class="doc">Certificado médico para manipulación de alimentos</h1>

    @include('pdf.food-handler.patient')

    <p class="prose">
        {{ $center['name'] }}, con NIT {{ $center['nit'] }}, hace constar que el(la) señor(a)
        <strong>{{ $record->full_name }}</strong>, identificado(a) con cédula de ciudadanía No.
        {{ $record->document_number }}, fue valorado(a) mediante examen médico y los exámenes de
        laboratorio que forman parte de este documento, practicados el
        {{ $record->issue_date->translatedFormat('d \d\e F \d\e Y') }}.
    </p>

    <p class="prose">
        De acuerdo con los hallazgos clínicos y paraclínicos, se concluye que se encuentra
        <strong>APTO(A) PARA MANIPULAR ALIMENTOS</strong>, en cumplimiento de lo dispuesto en la
        {{ $texts['training_resolution'] }} del Ministerio de Salud y Protección Social.
    </p>

    <p class="prose">
        El presente certificado se expide con destino a quien corresponda y su vigencia se sujeta a
        la normatividad aplicable y a las condiciones de salud del(de la) titular.
    </p>

    @include('pdf.food-handler.signature', ['role' => 'physician'])
</div>

{{-- ===================================================== 3. Laboratorio II --}}
<div class="page">
    <h1 class="doc">Resultados de laboratorio clínico</h1>

    @include('pdf.food-handler.patient')

    @include('pdf.food-handler.panel', ['panelKey' => 'vdrl'])
    @include('pdf.food-handler.panel', ['panelKey' => 'uroanalisis'])

    @include('pdf.food-handler.signature', ['role' => 'bacteriologist'])
</div>

{{-- =========================================== 4. Certificado de capacitacion --}}
<div class="page">
    <h1 class="doc">Certificación de capacitación</h1>

    @include('pdf.food-handler.patient')

    <p class="prose">
        {{ $center['name'] }} certifica que el(la) señor(a) <strong>{{ $record->full_name }}</strong>,
        identificado(a) con cédula de ciudadanía No. {{ $record->document_number }}, asistió y aprobó
        el <strong>{{ $texts['course_name'] }}</strong>, con una intensidad horaria de
        <strong>{{ $texts['course_hours'] }}</strong>.
    </p>

    <p class="prose">
        La capacitación se desarrolló conforme a los requisitos sanitarios establecidos en la
        <strong>{{ $texts['training_resolution'] }}</strong>, y abarcó los temas de manipulación
        higiénica de alimentos, buenas prácticas de manufactura, higiene personal, limpieza y
        desinfección, y prevención de enfermedades transmitidas por alimentos.
    </p>

    <p class="prose">
        Se expide en {{ $center['address'] }} el
        {{ $record->issue_date->translatedFormat('d \d\e F \d\e Y') }}.
    </p>

    @include('pdf.food-handler.signature', ['role' => 'food_engineer'])
</div>

{{-- ================================================================ 5. Carné --}}
<div class="page last">
    <h1 class="doc">Carné de manipulador de alimentos</h1>

    <div class="card">
        <div class="brand">{{ $center['name'] }}</div>

        <table class="body">
            <tr>
                <td class="photo">
                    <div class="frame">
                        @if ($record->photo)
                            <img src="{{ $record->photo }}" alt="">
                        @else
                            <div class="empty">Sin foto</div>
                        @endif
                    </div>
                </td>
                <td>
                    <span class="k">Señor(a)</span>
                    <span class="v">{{ $record->full_name }}</span>

                    <span class="k" style="margin-top:4px;">C.C. No.</span>
                    <span class="v">{{ $record->document_number }}</span>

                    <span class="k" style="margin-top:4px;">Fecha de expedición</span>
                    <span class="v">{{ $record->issue_date->format('d/m/Y') }}</span>

                    <span class="k" style="margin-top:4px;">Número</span>
                    <span class="serial">{{ $record->order_code }}</span>
                </td>
                <td class="qr"><img src="{{ $qr }}" alt="Código QR de verificación"></td>
            </tr>
        </table>

        <div class="cardsign">
            @if ($images['food_engineer'])
                <img src="{{ $images['food_engineer'] }}" alt="">
            @endif
        </div>

        <div class="foot">
            {{ $texts['course_name'] }} · Intensidad: {{ $texts['course_hours'] }}<br>
            <strong>{{ $texts['card_authorization'] }}</strong><br>
            NIT {{ $center['nit'] }}
        </div>
    </div>

    {{-- Tarjeta de la asesora: imagen de la plantilla o sus datos en texto. --}}
    <div class="advisor">
        @if ($images['advisor_card'])
            <img src="{{ $images['advisor_card'] }}" alt="">
        @else
            <span class="k">Asesora</span>
            <span class="v">{{ $texts['advisor_name'] ?: $blank }}</span>
            <span class="k" style="margin-top:4px;">Contacto</span>
            <span class="v">{{ $texts['advisor_phone'] ?: $center['phone'] }}</span>
        @endif
    </div>

    @include('pdf.food-handler.signature', ['role' => 'food_engineer'])

    <table class="verify">
        <tr>
            <td class="qr"><img src="{{ $qr }}" alt="Código QR de verificación"></td>
            <td class="legend">
                <strong>VERIFIQUE LA AUTENTICIDAD DE ESTE DOCUMENTO</strong><br>
                Escanee el código QR o ingrese a la dirección indicada para validar la información
                registrada. Este paquete fue emitido por <strong>{{ $center['name'] }}</strong>
                (NIT {{ $center['nit'] }}) el {{ $record->issued_at?->format('d/m/Y') }}.<br>
                <span class="code">{{ $verificationUrl }}</span>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
