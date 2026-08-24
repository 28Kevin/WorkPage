<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Examen Médico Ocupacional {{ $exam->order_code }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 20px 30px 48px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; margin: 0; }
        h1, h2, h3 { margin: 0; }
        .header { border-bottom: 2px solid #0f766e; padding-bottom: 6px; margin-bottom: 8px; }
        .header td { vertical-align: top; }
        .center-name { font-size: 14px; font-weight: bold; color: #0f766e; }
        .center-meta { font-size: 8px; color: #6b7280; line-height: 1.5; }
        .order-box { border: 1.5px solid #0f766e; border-radius: 4px; padding: 6px 8px; text-align: center; }
        .order-box .label { font-size: 7px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; }
        .order-box .value { font-size: 12px; font-weight: bold; color: #0f766e; }
        .doc-title { text-align: center; font-size: 12px; font-weight: bold; text-transform: uppercase;
                     letter-spacing: .6px; background: #f0fdfa; border: 1px solid #99f6e4;
                     padding: 5px; border-radius: 4px; margin-bottom: 7px; }
        .section { margin-bottom: 5px; }
        .section-title { background: #0f766e; color: #fff; font-size: 8.5px; font-weight: bold;
                         text-transform: uppercase; letter-spacing: .5px; padding: 4px 7px; border-radius: 3px 3px 0 0; }
        table { width: 100%; border-collapse: collapse; }
        .data-table { border: 1px solid #d1d5db; border-top: none; }
        .data-table td { padding: 2.6px 7px; border-bottom: 1px solid #f3f4f6; width: 25%; }
        .data-table td.k { color: #6b7280; font-size: 8px; text-transform: uppercase; letter-spacing: .3px; width: 15%; }
        .data-table td.v { font-weight: bold; color: #111827; }
        .chips span { display: inline-block; background: #ecfdf5; border: 1px solid #a7f3d0;
                      color: #065f46; border-radius: 8px; padding: 1.5px 7px; margin: 1px 2px 1px 0; font-size: 8px; }
        .result-band { border: 2px solid #059669; background: #ecfdf5; border-radius: 4px;
                       padding: 5px; text-align: center; margin: 5px 0; }
        .result-band .r { font-size: 16px; font-weight: bold; color: #047857; letter-spacing: 1px; }
        .result-band .d { font-size: 8px; color: #065f46; }
        ul { margin: 4px 0 0 14px; padding: 0; }
        li { margin-bottom: 0.5px; }
        .qr-wrap { border: 1px solid #d1d5db; border-radius: 4px; padding: 5px; }
        .qr-wrap img { width: 80px; height: 80px; }
        .legend { font-size: 7.5px; color: #4b5563; line-height: 1.45; }
        .legend strong { color: #0f766e; }
        .code { font-family: DejaVu Sans Mono, monospace; font-size: 8px; color: #0f766e; word-break: break-all; }
        .sign { margin-top: 2px; }
        .sign td { width: 50%; padding-top: 14px; }
        .sign .line { border-top: 1px solid #374151; padding-top: 3px; font-size: 8px; }
        .footer { position: fixed; bottom: -38px; left: 0; right: 0; font-size: 7px;
                  color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; }
    </style>
</head>
<body>

<table class="header">
    <tr>
        <td style="width: 72%;">
            <div class="center-name">{{ $center['name'] }}</div>
            <div class="center-meta">
                NIT {{ $center['nit'] }} &middot; {{ $center['license'] }}<br>
                {{ $center['address'] }}<br>
                Tel. {{ $center['phone'] }} &middot; {{ $center['email'] }}
            </div>
        </td>
        <td style="width: 28%;">
            <div class="order-box">
                <div class="label">Orden No.</div>
                <div class="value">{{ $exam->order_code }}</div>
                <div class="label" style="margin-top:2px;">Consecutivo {{ $exam->order_number }}</div>
            </div>
        </td>
    </tr>
</table>

<div class="doc-title">Certificado de Aptitud Médico Ocupacional &mdash; {{ $exam->exam_type->label() }}</div>

<div class="section">
    <div class="section-title">1. Datos del trabajador</div>
    <table class="data-table">
        <tr>
            <td class="k">Nombre completo</td><td class="v" colspan="3">{{ $exam->full_name }}</td>
        </tr>
        <tr>
            <td class="k">Cédula</td><td class="v">{{ $exam->document_number }}</td>
            <td class="k">Fecha de nacimiento</td><td class="v">{{ $exam->birth_date->format('d/m/Y') }} ({{ $exam->age }} años)</td>
        </tr>
        <tr>
            <td class="k">Correo electrónico</td><td class="v">{{ $exam->email }}</td>
            <td class="k">Celular</td><td class="v">{{ $exam->phone }}</td>
        </tr>
        <tr>
            <td class="k">Estatura</td><td class="v">{{ $exam->height_cm }} cm</td>
            <td class="k">Peso adecuado</td>
            <td class="v">{{ number_format((float) $exam->ideal_weight_kg, 1, ',', '.') }} kg
                (registrado {{ number_format((float) $exam->weight_kg, 1, ',', '.') }} kg)</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">2. Datos ocupacionales y de afiliación</div>
    <table class="data-table">
        <tr>
            <td class="k">Empresa</td><td class="v">{{ $exam->company_name }}</td>
            <td class="k">NIT</td><td class="v">{{ $exam->company_nit }}</td>
        </tr>
        <tr>
            <td class="k">EPS</td><td class="v">{{ $exam->eps->name }}</td>
            <td class="k">ARL</td><td class="v">{{ $exam->arl->name }}</td>
        </tr>
        <tr>
            <td class="k">Ciudad</td><td class="v">{{ $exam->city->name }}{{ $exam->city->department ? ', '.$exam->city->department : '' }}</td>
            <td class="k">Cargo</td><td class="v">{{ $exam->position }}</td>
        </tr>
        <tr>
            <td class="k">Riesgos / especialidades</td>
            <td class="v chips" colspan="3">
                @foreach ($exam->risks as $risk)<span>{{ $risk->name }}</span>@endforeach
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">3. Signos vitales y antropometría</div>
    <table class="data-table">
        <tr>
            <td class="k">Presión arterial</td><td class="v">{{ $parameters['signos_vitales']['presion_arterial'] }}</td>
            <td class="k">Frec. cardíaca</td><td class="v">{{ $parameters['signos_vitales']['frecuencia_cardiaca'] }}</td>
        </tr>
        <tr>
            <td class="k">Frec. respiratoria</td><td class="v">{{ $parameters['signos_vitales']['frecuencia_respiratoria'] }}</td>
            <td class="k">Temperatura</td><td class="v">{{ $parameters['signos_vitales']['temperatura'] }}</td>
        </tr>
        <tr>
            <td class="k">Saturación O2</td><td class="v">{{ $parameters['signos_vitales']['saturacion_oxigeno'] }}</td>
            <td class="k">IMC</td><td class="v">{{ $parameters['antropometria']['imc'] }} ({{ $parameters['antropometria']['clasificacion_imc'] }})</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">4. Exámenes complementarios</div>
    <table class="data-table">
        <tr>
            <td class="k">Agudeza visual</td>
            <td class="v" colspan="3">OD {{ $parameters['agudeza_visual']['ojo_derecho'] }} &middot;
                OI {{ $parameters['agudeza_visual']['ojo_izquierdo'] }} &middot;
                Cromática: {{ $parameters['agudeza_visual']['vision_cromatica'] }}</td>
        </tr>
        <tr>
            <td class="k">Audiometría</td><td class="v" colspan="3">{{ $parameters['audiometria']['concepto'] }}</td>
        </tr>
        <tr>
            <td class="k">Espirometría</td>
            <td class="v" colspan="3">{{ $parameters['espirometria']['concepto'] }}
                (CVF {{ $parameters['espirometria']['cvf'] }}, VEF1 {{ $parameters['espirometria']['vef1'] }})</td>
        </tr>
        <tr>
            <td class="k">Laboratorio</td>
            <td class="v" colspan="3">Glicemia {{ $parameters['laboratorio']['glicemia'] }} &middot;
                Colesterol {{ $parameters['laboratorio']['colesterol_total'] }} &middot;
                Triglicéridos {{ $parameters['laboratorio']['trigliceridos'] }} &middot;
                Hemoglobina {{ $parameters['laboratorio']['hemoglobina'] }}</td>
        </tr>
        @foreach ($parameters['examen_fisico'] as $key => $value)
            @if ($loop->index % 2 === 0)<tr>@endif
            <td class="k">{{ ucfirst(str_replace('_', ' / ', $key)) }}</td><td class="v">{{ $value }}</td>
            @if ($loop->index % 2 === 1 || $loop->last)</tr>@endif
        @endforeach
    </table>
</div>

<div class="result-band">
    <div class="d">Concepto de aptitud para el cargo de {{ $exam->position }}</div>
    <div class="r">{{ $exam->result->label() }}</div>
    <div class="d">{{ $parameters['concepto_medico']['diagnostico'] }} &middot;
        Restricciones: {{ $parameters['concepto_medico']['restricciones'] }}</div>
</div>

<div class="section">
    <div class="section-title">5. Recomendaciones</div>
    <table class="data-table"><tr><td colspan="4" style="padding: 5px 7px;">
        <ul>
            @foreach (array_filter(explode("\n", (string) $exam->recommendations)) as $recommendation)
                <li>{{ $recommendation }}</li>
            @endforeach
        </ul>
    </td></tr></table>
</div>

<table style="margin-top: 4px;">
    <tr>
        <td style="width: 102px; vertical-align: top;">
            <div class="qr-wrap"><img src="{{ $qr }}" alt="Código QR de verificación"></div>
        </td>
        <td style="vertical-align: top; padding-left: 10px;">
            <div class="legend">
                <strong>VERIFICACIÓN DE AUTENTICIDAD.</strong> Escanee el código QR para validar en línea que este
                examen médico ocupacional fue emitido por <strong>{{ $center['name'] }}</strong> (NIT {{ $center['nit'] }}).
                También puede consultarlo por número de cédula en el módulo de consulta pública de la plataforma.<br><br>
                <strong>Código de verificación:</strong> <span class="code">{{ $exam->verification_code }}</span><br>
                <strong>URL:</strong> <span class="code">{{ $verificationUrl }}</span><br>
                <strong>Fecha de examen:</strong> {{ $exam->exam_date->format('d/m/Y') }} &middot;
                <strong>Expedición:</strong> {{ $exam->issued_at->format('d/m/Y H:i') }}
            </div>
        </td>
    </tr>
</table>

<table class="sign">
    <tr>
        <td><div class="line">{{ $center['physician']['name'] }}<br>Médico especialista en SST &middot; {{ $center['physician']['license'] }}</div></td>
        <td style="padding-left: 20px;"><div class="line">{{ $exam->full_name }}<br>C.C. {{ $exam->document_number }} &middot; Trabajador</div></td>
    </tr>
</table>

<div class="footer">
    {{ $center['name'] }} &middot; Orden {{ $exam->order_code }} &middot;
    Documento generado electrónicamente el {{ $exam->issued_at->format('d/m/Y H:i') }}.
    Su autenticidad se valida en {{ $verificationUrl }}
</div>

</body>
</html>
