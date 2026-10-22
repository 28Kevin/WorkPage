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

        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #111827; }

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

        h1.doc { font-size: 21px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;
                 text-align: center; color: {{ $palette[800] }}; margin: 0 0 10px;
                 padding-bottom: 5px; border-bottom: 2px solid {{ $palette[200] }}; }

        /* Datos del paciente, repetidos en cada pagina del paquete. */
        .patient { border: 1px solid #d1d5db; margin-bottom: 9px; }
        .patient td { padding: 5px 9px; border-right: 1px solid #e5e7eb;
                      border-bottom: 1px solid #e5e7eb; font-size: 12px; }
        .patient td:last-child { border-right: none; }
        .patient tr:last-child td { border-bottom: none; }
        .patient .k { color: #6b7280; text-transform: uppercase; font-size: 9.6px;
                      letter-spacing: .3px; display: block; margin-bottom: 1px; }
        .patient .v { font-weight: bold; }

        /* Paneles de laboratorio. */
        .panel { margin-bottom: 8px; border: 1px solid #d1d5db; }
        .panel .title { background: {{ $palette[50] }}; color: {{ $palette[900] }};
                        font-size: 12px; font-weight: bold; text-transform: uppercase;
                        letter-spacing: .5px; padding: 4px 9px; border-bottom: 1px solid #d1d5db; }
        .panel .group { background: #f9fafb; font-size: 10.5px; font-weight: bold; color: #374151;
                        padding: 2px 9px; border-bottom: 1px solid #e5e7eb; }
        /* Celda contenedora de las dos columnas: sin relleno ni separadores. */
        .panel td.cols { padding: 0; border-bottom: none; }
        .panel table.grid { width: 100%; }
        .panel table.grid > tr > td.col { width: 50%; padding: 0; border-bottom: none;
                                          vertical-align: top; }
        .panel table.grid > tr > td.col:first-child { border-right: 1px solid #e5e7eb; }

        .panel td { padding: 1.5px 9px; border-bottom: 1px solid #f3f4f6; font-size: 11.5px; }
        .panel td.f { width: 46%; color: #4b5563; }
        .panel td.v { font-weight: bold; }

        .prose { font-size: 14px; line-height: 2.05; text-align: justify; margin-bottom: 26px; }
        .prose strong { color: {{ $palette[900] }}; }

        /* Firma al pie de cada pagina. */
        /*
         * Los sellos son escaneos pequenios (180-234 px de ancho): al
         * agrandarlos pierden definicion, pero asi se leen el nombre y el
         * registro, que es lo que hace util el sello.
         */
        .sign { margin-top: 12px; }
        .sign img { width: 236px; height: auto; display: block; margin-bottom: 3px; }
        .sign .rule { border-bottom: 1px solid #6b7280; width: 300px; }
        .sign .who { font-size: 12.5px; font-weight: bold; margin-top: 3px; }
        .sign .role { font-size: 11px; color: #6b7280; }

        /* ------------------------------------------------------- carnet ---- */
        /*
         * Carne de manipulador: reproduce el formato impreso. Todo va en
         * cursiva, con el titulo y el curso dentro de recuadros, y a la
         * derecha la foto sobre el numero. 'margin: auto' lo centra.
         */
        .card { width: 470px; margin: 0 auto; border: 2px solid {{ $palette[800] }};
                border-radius: 16px; padding: 4px; }
        .card .inner { border: 1px solid {{ $palette[700] }}; border-radius: 12px;
                       padding: 9px 12px; font-style: italic; color: #111827; }

        .card .box { border: 1px solid #374151; padding: 3px 7px; text-align: center;
                     font-weight: bold; }
        .card .box.title { font-size: 11.5px; letter-spacing: .2px; display: inline-block; }
        .card .box.course { font-size: 12.5px; margin: 3px 0 7px; }

        .card .grid { width: 100%; }
        .card .grid > tr > td { vertical-align: top; padding: 0; }
        .card .side { width: 108px; text-align: center; }

        /* Linea de puntos donde en el impreso se escribe a mano. */
        .card .field { font-size: 11.5px; font-weight: bold; margin-top: 5px;
                       border-bottom: 1px solid #6b7280; padding-bottom: 1px; }
        .card .field .lbl { font-weight: bold; }

        .card .attended { text-align: center; font-size: 10.5px; font-weight: bold;
                          margin-top: 6px; }
        .card .meta { font-size: 11px; font-weight: bold; margin-top: 4px; }

        .card .photo { width: 96px; height: 120px; border: 2px solid {{ $palette[700] }};
                       border-radius: 10px; margin: 0 auto; }
        .card .photo img { width: 92px; height: 116px; }

        .card .numberbox { border: 1px solid #374151; margin: 10px auto 0; padding: 4px 6px;
                           font-size: 15px; font-weight: bold; letter-spacing: .5px; }

        .card .cardqr { margin-top: 8px; }
        .card .cardqr img { width: 62px; height: 62px; }

        /* El sello ya trae el nombre, el cargo y el NIT de quien firma. */
        .card .cardsign { height: 52px; margin-top: 2px; }
        .card .cardsign img { width: 186px; height: auto; }

        .card .auth { font-size: 9.5px; font-weight: bold; margin-top: 2px; }

        /* Tarjeta de la asesora: el escaneo va tal cual, debajo del carne. */
        .advisor { text-align: center; margin-top: 16px; }
        .advisor img { width: 390px; height: auto; }

        /* Verificacion publica. */
        .verify { margin-top: 26px; }
        .verify td { vertical-align: middle; }
        .verify .qr { width: 96px; text-align: center; }
        .verify .qr img { width: 88px; height: 88px; border: 1px solid #d1d5db; padding: 3px; }
        .verify .legend { font-size: 10.5px; color: #4b5563; line-height: 1.65; padding-left: 12px; }
        .verify .code { font-family: DejaVu Sans Mono, monospace; font-size: 10.5px;
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
        <div class="inner">
            <table class="grid">
                <tr>
                    <td>
                        <div class="box title">CARNET MANIPULADOR DE ALIMENTOS</div>

                        <div class="field">
                            <span class="lbl">Señor(a)</span> {{ $record->full_name }}
                        </div>
                        <div class="field">
                            <span class="lbl">Con c.c. No</span> {{ $record->document_number }}
                        </div>

                        <p class="attended">Asistió al curso de:</p>

                        {{-- Fijo de la plantilla: en el carné impreso es una
                             etiqueta, no el nombre largo del curso. --}}
                        <div class="box course">MANIPULACIÓN DE ALIMENTOS</div>

                        <p class="meta">Con una intensidad de {{ $texts['course_hours'] }}</p>
                        <p class="meta">
                            Fecha de expedición&nbsp;&nbsp;{{ $record->issue_date->format('d - m - Y') }}
                        </p>

                        <div class="cardsign">
                            @if ($images['food_engineer'])
                                <img src="{{ $images['food_engineer'] }}" alt="">
                            @endif
                        </div>

                        <p class="auth">{{ $texts['card_authorization'] }}</p>
                    </td>

                    <td class="side">
                        {{-- Sin fotografia el recuadro queda vacio, como en el impreso. --}}
                        <div class="photo">
                            @if ($record->photo)
                                <img src="{{ $record->photo }}" alt="">
                            @endif
                        </div>

                        <div class="numberbox">{{ $record->order_code }}</div>

                        <div class="cardqr"><img src="{{ $qr }}" alt="Código QR de verificación"></div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    @if ($images['advisor_card'])
        <div class="advisor">
            <img src="{{ $images['advisor_card'] }}" alt="Tarjeta de la asesora">
        </div>
    @endif

    {{-- Sin bloque de firma suelto: la ingeniera ya firma en el sello del
         carne y en su tarjeta, y repetirla una tercera vez no cabia. --}}
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
