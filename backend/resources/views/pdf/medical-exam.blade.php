@php
    use App\Support\ExamForm;

    $vitals = $parameters['vitals'] ?? [];
    $anthro = $parameters['anthropometry'] ?? [];
    $vision = $parameters['vision'] ?? [];
    $systemValues = $parameters['systems'] ?? [];
    $assessments = $parameters['assessments'] ?? [];

    $box = fn (bool $checked) => $checked ? '&#9746;' : '&#9744;';
    $blank = '_______________________';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Evaluación médica ocupacional {{ $exam->order_code }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 62px 26px 46px 26px; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 8.2px; color: #1f2937; margin: 0; }
        h1, h2, h3 { margin: 0; }
        table { width: 100%; border-collapse: collapse; }

        /* Encabezado y pie repetidos en cada pagina. */
        .running-head { position: fixed; top: -48px; left: 0; right: 0; text-align: center;
                        font-size: 7px; font-weight: bold; color: {{ $palette[800] }};
                        letter-spacing: .4px; text-transform: uppercase; }
        .running-foot { position: fixed; bottom: -34px; left: 0; right: 0; text-align: center;
                        font-size: 6.6px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 4px; }

        /* Banda superior con logo, titulo y consecutivo. */
        .masthead { border: 1px solid {{ $palette[800] }}; margin-bottom: 3px; }
        .masthead td { vertical-align: middle; padding: 6px 8px; }
        .masthead .logo-cell { width: 19%; text-align: center; border-right: 1px solid {{ $palette[800] }}; }
        .masthead .logo-cell img { max-height: 58px; max-width: 100%; }
        .masthead .title-cell { background: {{ $palette[800] }}; color: #ffffff; text-align: center; }
        .masthead .title-cell .center { font-size: 12px; font-weight: bold; letter-spacing: .3px; }
        .masthead .title-cell .doc { font-size: 10px; font-weight: bold; margin-top: 2px; }
        .masthead .title-cell .scope { font-size: 8px; margin-top: 1px; }
        .masthead .order-cell { width: 19%; text-align: center; border-left: 1px solid {{ $palette[800] }}; }
        .masthead .order-cell .label { font-size: 6.4px; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; }
        .masthead .order-cell .value { font-size: 11px; font-weight: bold; color: {{ $palette[800] }}; }

        .ips-strip { font-size: 7px; color: {{ $palette[700] }}; padding: 2px 2px 5px; }
        .ips-strip b { color: #374151; }

        /* Bandas de seccion. */
        .band { background: {{ $palette[800] }}; color: #ffffff; font-size: 7.6px; font-weight: bold;
                text-transform: uppercase; letter-spacing: .5px; padding: 3.5px 7px; margin-top: 5px; }

        .grid { border: 1px solid #d1d5db; border-top: none; }
        .grid td { padding: 4px 7px; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;
                   width: 50%; vertical-align: top; }
        .grid td:last-child { border-right: none; }
        .grid tr:last-child td { border-bottom: none; }
        .grid .k { display: block; font-size: 7px; color: #6b7280; }
        .grid .v { display: block; font-weight: bold; color: #111827; font-size: 8.4px;
                   border-bottom: 1px solid #9ca3af; padding-bottom: 1px; }

        /* Listas de casillas de verificacion en tres columnas. */
        .checks { border: 1px solid #d1d5db; border-top: none; }
        .checks td { padding: 3px 7px; width: 33.33%; font-size: 7.6px; vertical-align: top; }

        /* Tablas de datos con encabezado. */
        .data { border: 1px solid #d1d5db; border-top: none; }
        .data th { background: {{ $palette[50] }}; color: {{ $palette[900] }}; font-size: 6.8px;
                   text-transform: uppercase; letter-spacing: .4px; text-align: left;
                   padding: 3px 7px; border-bottom: 1px solid #d1d5db; }
        .data td { padding: 3px 7px; border-bottom: 1px solid #f3f4f6; font-size: 7.8px; }
        .data tr:last-child td { border-bottom: none; }
        .data .num { font-weight: bold; color: #111827; }

        .findings { border: 1px solid #d1d5db; border-top: none; padding: 4px 7px; font-size: 7.6px; }
        .findings .k { color: #6b7280; }

        /* Concepto de aptitud. */
        .aptitude { border: 1px solid #d1d5db; border-top: none; }
        .aptitude td { padding: 4px 7px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
        .aptitude tr:last-child td { border-bottom: none; }
        .aptitude .task { width: 34%; font-weight: bold; color: {{ $palette[800] }}; font-size: 7.8px;
                          text-transform: uppercase; letter-spacing: .3px; }
        .aptitude .opts { font-size: 7.6px; }
        .aptitude .na { color: #9ca3af; font-style: italic; }

        .chips span { display: inline-block; background: {{ $palette[50] }}; border: 1px solid {{ $palette[200] }};
                      color: {{ $palette[900] }}; border-radius: 7px; padding: 1px 6px;
                      margin: 1px 2px 1px 0; font-size: 7.2px; }

        .note { border: 1px solid #d1d5db; border-top: none; padding: 5px 7px; font-size: 7.4px;
                line-height: 1.5; color: #374151; }
        .note .line { border-bottom: 1px solid #9ca3af; min-height: 11px; padding-bottom: 1px; margin-top: 2px; }

        /* Firmas. */
        .sign { border: 1px solid #d1d5db; border-top: none; }
        .sign th { background: {{ $palette[50] }}; color: {{ $palette[900] }}; font-size: 7px;
                   text-transform: uppercase; letter-spacing: .4px; padding: 3px 7px;
                   border-bottom: 1px solid #d1d5db; border-right: 1px solid #e5e7eb; }
        .sign td { padding: 5px 7px; border-right: 1px solid #e5e7eb; vertical-align: top; font-size: 7.4px;
                   line-height: 1.9; }
        .sign th:last-child, .sign td:last-child { border-right: none; }
        .sign .fingerprint { height: 62px; }

        .verify { margin-top: 7px; }
        .verify td { vertical-align: middle; }
        .verify .qr { width: 96px; text-align: center; }
        .verify .qr img { width: 88px; height: 88px; border: 1px solid #d1d5db; padding: 3px; }
        .verify .legend { font-size: 7.2px; color: #4b5563; line-height: 1.5; padding-left: 10px; }
        .verify .legend strong { color: {{ $palette[800] }}; }
        .verify .code { font-family: DejaVu Sans Mono, monospace; font-size: 7.6px;
                        color: {{ $palette[800] }}; word-break: break-all; }

        /* Sello de anulacion: el documento sigue existiendo pero sin validez. */
        .annulled-stamp { position: fixed; top: 300px; left: 0; right: 0; text-align: center;
                          font-size: 74px; font-weight: bold; color: #dc2626; opacity: .18;
                          letter-spacing: 12px; }
    </style>
</head>
<body>

@if ($exam->isAnnulled())
    <div class="annulled-stamp">ANULADO</div>
@endif

<div class="running-head">
    Evaluación médica ocupacional &middot; {{ $exam->order_code }}
</div>

<div class="running-foot">
    Información clínica reservada &middot; Uso ocupacional &middot; {{ $center['name'] }}
</div>

{{-- ------------------------------------------------------------- encabezado --}}
<table class="masthead">
    <tr>
        <td class="logo-cell">
            @if ($logo)
                <img src="{{ $logo }}" alt="">
            @else
                <span style="font-size:7px;color:#9ca3af;">{{ $center['name'] }}</span>
            @endif
        </td>
        <td class="title-cell">
            <div class="center">{{ mb_strtoupper($center['name']) }}</div>
            <div class="doc">EVALUACIÓN MÉDICA OCUPACIONAL</div>
            <div class="scope">TRABAJO EN ALTURAS Y ESPACIOS CONFINADOS</div>
        </td>
        <td class="order-cell">
            <div class="label">Orden No.</div>
            <div class="value">{{ $exam->order_code }}</div>
            <div class="label" style="margin-top:2px;">Consecutivo {{ $exam->order_number }}</div>
        </td>
    </tr>
</table>

<div class="ips-strip">
    <b>Razón social IPS:</b> {{ $center['name'] }} &nbsp;
    <b>NIT:</b> {{ $center['nit'] }} &nbsp;
    <b>Dirección:</b> {{ $center['address'] }} &nbsp;
    <b>Correo:</b> {{ $center['email'] }} &nbsp;
    <b>Teléfono:</b> {{ $center['phone'] }}
</div>

{{-- ------------------------------------------------- datos de la evaluación --}}
<div class="band">Datos de la evaluación</div>
<table class="grid">
    <tr>
        <td><span class="k">Fecha de evaluación</span><span class="v">{{ $exam->exam_date->format('d/m/Y') }}</span></td>
        <td><span class="k">N.º de orden / historia</span><span class="v">{{ $exam->order_code }}</span></td>
    </tr>
    <tr>
        <td><span class="k">Ciudad / municipio</span><span class="v">{{ $exam->city?->name ?? '—' }}</span></td>
        <td><span class="k">Departamento</span><span class="v">{{ $exam->city?->department ?? '—' }}</span></td>
    </tr>
</table>

<table class="checks">
    @foreach (collect(\App\Enums\ExamType::cases())->chunk(3) as $row)
        <tr>
            @foreach ($row as $type)
                <td>{!! $box($exam->exam_type === $type) !!} {{ $type->label() }}</td>
            @endforeach
            @for ($i = count($row); $i < 3; $i++)
                <td></td>
            @endfor
        </tr>
    @endforeach
</table>

{{-- ---------------------------------------------- identificación trabajador --}}
<div class="band">Identificación del trabajador</div>
<table class="grid">
    <tr>
        <td><span class="k">Nombres y apellidos</span><span class="v">{{ $exam->full_name }}</span></td>
        <td>
            <span class="k">Tipo y N.º de documento</span>
            <span class="v">{{ $exam->document_type?->value ?? 'CC' }} {{ $exam->document_number }}</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="k">Fecha de nacimiento / edad</span>
            <span class="v">{{ $exam->birth_date->format('d/m/Y') }} ({{ $exam->age }} años)</span>
        </td>
        <td><span class="k">Sexo</span><span class="v">{{ $exam->sex?->label() ?? '—' }}</span></td>
    </tr>
    <tr>
        <td><span class="k">EPS / entidad de salud</span><span class="v">{{ $exam->eps?->name ?? '—' }}</span></td>
        <td><span class="k">ARL</span><span class="v">{{ $exam->arl?->name ?? '—' }}</span></td>
    </tr>
    <tr>
        <td><span class="k">AFP</span><span class="v">{{ $exam->afp?->name ?? '—' }}</span></td>
        <td>
            <span class="k">Estatura / peso</span>
            <span class="v">{{ $exam->height_cm }} cm &middot; {{ number_format((float) $exam->weight_kg, 1, ',', '.') }} kg</span>
        </td>
    </tr>
</table>

{{-- ------------------------------------------------- empleador y riesgos --}}
<div class="band">Empleador y riesgos del puesto</div>
<table class="grid">
    <tr>
        <td><span class="k">Razón social / empleador</span><span class="v">{{ $exam->company_name }}</span></td>
        <td>
            <span class="k">NIT</span>
            <span class="v">
                @if ($exam->is_independent)
                    Trabajador independiente
                @else
                    {{ $exam->company_nit ?: '—' }}
                @endif
            </span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="k">Empresa usuaria (si aplica)</span>
            <span class="v">{{ $exam->client_company ?: '—' }}</span>
        </td>
        <td>
            <span class="k">Actividad económica</span>
            <span class="v">{{ $exam->economic_activity ?: '—' }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="2"><span class="k">Cargo / ocupación</span><span class="v">{{ $exam->position }}</span></td>
    </tr>
</table>

{{-- ------------------------------------------------------------ examen físico --}}
<div class="band">Examen físico</div>
<table class="data">
    <thead>
        <tr>
            <th style="width:25%;">Parámetro</th><th style="width:25%;">Resultado</th>
            <th style="width:25%;">Parámetro</th><th style="width:25%;">Resultado</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Peso</td><td class="num">{{ number_format((float) $exam->weight_kg, 1, ',', '.') }} kg</td>
            <td>Estatura</td><td class="num">{{ number_format($exam->height_cm / 100, 2, ',', '.') }} m</td>
        </tr>
        <tr>
            <td>IMC</td>
            <td class="num">{{ $anthro['bmi'] ?? '—' }} kg/m² ({{ $anthro['bmi_classification'] ?? '—' }})</td>
            <td>Presión arterial</td>
            <td class="num">{{ $vitals['systolic'] ?? '—' }} / {{ $vitals['diastolic'] ?? '—' }} mmHg</td>
        </tr>
        <tr>
            <td>Frecuencia cardíaca</td><td class="num">{{ $vitals['heart_rate'] ?? '—' }} lpm</td>
            <td>SpO&#8322;</td><td class="num">{{ $vitals['spo2'] ?? '—' }} %</td>
        </tr>
        <tr>
            <td>Frecuencia respiratoria</td><td class="num">{{ $vitals['respiratory_rate'] ?? '—' }} rpm</td>
            <td>Temperatura</td>
            <td class="num">{{ isset($vitals['temperature']) ? number_format((float) $vitals['temperature'], 1, ',', '.') : '—' }} °C</td>
        </tr>
        <tr>
            <td>Agudeza visual</td>
            <td class="num">OD {{ $vision['right_eye'] ?? '—' }} / OI {{ $vision['left_eye'] ?? '—' }}</td>
            <td>Corrección óptica</td>
            <td>{!! $box(! empty($vision['optical_correction'])) !!} Sí &nbsp; {!! $box(empty($vision['optical_correction'])) !!} No</td>
        </tr>

        @foreach (collect($systems)->chunk(2) as $pair)
            <tr>
                @foreach ($pair as $key => $system)
                    @php $status = $systemValues[$key] ?? ExamForm::NORMAL; @endphp
                    <td>{{ $system['label'] }}</td>
                    <td>
                        {!! $box($status !== ExamForm::FINDINGS) !!} {{ $system['normal'] }} &nbsp;
                        {!! $box($status === ExamForm::FINDINGS) !!} {{ $system['findings'] }}
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

<div class="findings">
    <span class="k">Hallazgos clínicos relevantes:</span>
    <div style="border-bottom:1px solid #9ca3af;min-height:11px;padding-top:2px;">
        {{ $exam->clinical_findings ?: 'Sin hallazgos clínicos relevantes.' }}
    </div>
</div>

{{-- ------------------------------------------------------------ paraclínicos --}}
<div class="band">Paraclínicos</div>
<table class="data">
    <thead>
        <tr>
            <th style="width:30%;">Paraclínico</th>
            <th style="width:28%;">Realizado</th>
            <th style="width:42%;">Resultado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($paraclinicalLabels as $key => $label)
            @php $entry = $paraclinicals[$key] ?? null; @endphp
            <tr>
                <td>{{ $label }}</td>
                <td>
                    @if ($entry && ($entry['performed'] ?? false))
                        {!! $box(($entry['status'] ?? ExamForm::NORMAL) === ExamForm::NORMAL) !!} Normal &nbsp;
                        {!! $box(($entry['status'] ?? null) === ExamForm::ALTERED) !!} Alterada
                    @else
                        <span style="color:#9ca3af;">No realizado</span>
                    @endif
                </td>
                <td class="num">{{ $entry['result'] ?? '' ?: '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- ------------------------------------------------------ concepto de aptitud --}}
<div class="band">Concepto de aptitud</div>
<table class="aptitude">
    @foreach ($aptitudeLabels as $key => $label)
        @php $value = $exam->{$key}; @endphp
        <tr>
            <td class="task">{{ $label }}</td>
            <td class="opts">
                @if ($value)
                    {!! $box($value === \App\Enums\ExamResult::Apto) !!} APTO &nbsp;
                    {!! $box($value === \App\Enums\ExamResult::AptoConRestricciones) !!} APTO CON RECOMENDACIONES / RESTRICCIONES &nbsp;
                    {!! $box($value === \App\Enums\ExamResult::Aplazado) !!} APLAZADO
                @else
                    <span class="na">No aplica para este puesto</span>
                @endif
            </td>
        </tr>
    @endforeach
</table>

<div class="band">Recomendaciones / restricciones y temporalidad</div>
<div class="note">
    <div class="line">{{ $exam->restrictions ?: 'Sin restricciones. Se emiten las recomendaciones generales de vigilancia ocupacional.' }}</div>
    <div style="margin-top:4px;"><b>Temporalidad:</b> {{ $exam->restrictions_validity ?: 'No aplica' }}</div>
</div>

<table class="grid" style="border-top:1px solid #d1d5db;">
    @foreach (collect($assessmentLabels)->chunk(2) as $pair)
        <tr>
            @foreach ($pair as $key => $label)
                <td><span class="k">{{ $label }}</span><span class="v">{{ $assessments[$key] ?? '—' }}</span></td>
            @endforeach
        </tr>
    @endforeach
</table>

{{-- ---------------------------------------------------------- consentimiento --}}
<div class="band">Consentimiento informado del trabajador</div>
<div class="note">
    Declaro que recibí información clara sobre el propósito de la evaluación médica ocupacional y, cuando
    corresponda, sobre las pruebas o valoraciones complementarias indicadas, su finalidad, procedimiento,
    posibles molestias o riesgos, beneficios y manejo confidencial de la información. Otorgo mi consentimiento
    de manera libre, previa y por escrito para la realización de las pruebas o valoraciones complementarias
    requeridas dentro de esta evaluación. Entiendo que puedo manifestar mi negativa y que esta será registrada
    junto con la información y consecuencias que correspondan.

    <div style="margin-top:4px;font-weight:bold;">
        {!! $box((bool) $exam->consent_accepted) !!} ACEPTO / CONSIENTO
        &nbsp;&nbsp;&nbsp;
        {!! $box(! $exam->consent_accepted) !!} NO ACEPTO / ME NIEGO
    </div>
</div>

{{-- ------------------------------------------------------------------ firmas --}}
<table class="sign">
    <thead>
        <tr>
            <th style="width:38%;">Médico ocupacional</th>
            <th style="width:42%;">Trabajador</th>
            <th style="width:20%;">Huella dactilar</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                Nombre: {{ $center['physician']['name'] }}<br>
                Registro profesional: {{ $center['physician']['license'] }}<br>
                Firma: {{ $blank }}
            </td>
            <td>
                Nombre: {{ $exam->full_name }}<br>
                Documento: {{ $exam->document_type?->value ?? 'CC' }} {{ $exam->document_number }}<br>
                Firma: {{ $blank }}
            </td>
            <td class="fingerprint"></td>
        </tr>
    </tbody>
</table>

{{-- ------------------------------------------------------------ verificación --}}
<table class="verify">
    <tr>
        <td class="qr"><img src="{{ $qr }}" alt="Código QR de verificación"></td>
        <td class="legend">
            <strong>VERIFIQUE LA AUTENTICIDAD DE ESTE DOCUMENTO</strong><br>
            Escanee el código QR o ingrese a la dirección indicada para consultar y validar la información
            registrada. Esta evaluación fue emitida por <strong>{{ $center['name'] }}</strong>
            (NIT {{ $center['nit'] }}) el {{ $exam->issued_at?->format('d/m/Y') }}.<br>
            <span class="code">{{ $verificationUrl }}</span><br>
            Código de verificación: <span class="code">{{ $exam->verification_code }}</span><br>
            El concepto fue comunicado al trabajador y se entrega copia del mismo. La información clínica
            detallada permanece bajo reserva de la historia clínica ocupacional.
        </td>
    </tr>
</table>

</body>
</html>
