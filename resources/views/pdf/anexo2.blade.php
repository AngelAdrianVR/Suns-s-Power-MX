<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Anexo 2 · Orden {{ $order->id }}</title>
    <style>
        @page { size: letter; margin: 6mm 7mm; }

        * { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #000; }
        body { margin: 0; padding: 0; line-height: 1.15; }

        .head-table { width: 100%; border-collapse: collapse; margin: 0 0 4px; }
        .head-table td { padding: 0 14px; vertical-align: bottom; }
        .head-label { font-weight: bold; font-size: 10.5px; }
        .head-value { border-bottom: 1.2px solid #000; font-size: 9px; padding: 0 3px 1px; min-height: 11px; }

        .section-header { background-color: #e5e7eb; font-weight: bold; font-size: 9.5px; padding: 1px 5px; margin: 4px 0 2px; }

        /* Rejillas de campos */
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        table.grid td { padding: 0 3px; vertical-align: top; }

        .field-bracket { border: 1.2px solid #000; border-top: none; min-height: 10px; padding: 0 2px; }
        .bv { font-size: 9px; }
        .field-label { font-size: 6.5px; line-height: 1.05; padding-left: 1px; }
        .field-label.center { text-align: center; }

        .sub-label { font-size: 9px; font-weight: bold; margin: 1px 0; }

        /* Casillas */
        .check-box { display: inline-block; border: 1.2px solid #000; width: 36px; height: 11px; text-align: center; font-weight: bold; font-size: 8px; line-height: 11px; }
        .check-box.w24 { width: 24px; }
        .check-box.w60 { width: 60px; }
        .opt { font-size: 9px; }
        .si-box { border: 1.2px solid #000; padding: 0 5px; font-weight: bold; font-size: 9px; }

        table.plain { width: 100%; border-collapse: collapse; }
        table.plain td { padding: 0; font-size: 9px; }
        table.plain td.box-cell { text-align: right; width: 44px; }

        .manifest-text { font-size: 8.5px; text-align: justify; }

        /* Tabla UTM */
        table.utm-table { width: 100%; border-collapse: collapse; border: 1.2px solid #000; text-align: center; margin: 4px 0 2px; }
        table.utm-table td { border: 0.8px solid #000; height: 10px; padding: 0 3px; font-size: 9px; }
        .utm-title { width: 25%; font-weight: bold; text-align: left; }
        .utm-head { font-weight: bold; }

        /* Textos legales */
        .legal { font-size: 7px; text-align: justify; line-height: 1.15; margin-bottom: 4px; }
        .legal p { margin: 0 0 1px; }
        .legal-right { text-align: right; }

        /* Firmas */
        table.sign-table { width: 100%; border-collapse: collapse; margin-top: 2px; }
        table.sign-table td { vertical-align: top; padding: 0 10px 0 0; }
        .firma-box { border: 1.2px solid #000; height: 52px; text-align: center; padding-top: 2px; margin-bottom: 3px; }
        .firma-title { font-size: 8px; }
        .firma-line { width: 78%; border-bottom: 1.2px solid #000; margin: 22px auto 2px; }
        .firma-foot { font-size: 8px; }
        .sign-line { margin-bottom: 2px; font-size: 9px; }
        .sign-line .lbl { display: inline-block; width: 40px; }
        .sign-line .val { display: inline-block; border-bottom: 1.2px solid #000; min-width: 150px; padding: 0 3px; font-size: 9px; }
        .cfe-box { border: 1.2px solid #000; height: 70px; text-align: center; }
        .cfe-box .cfe-text { margin-top: 50px; font-size: 8px; line-height: 1.2; }
    </style>
</head>
<body>

    @php
        $f = function ($key, $label, $center = false) use ($fields) {
            $v = e((string) ($fields[$key] ?? ''));
            $cls = $center ? ' center' : '';
            return '<div class="field-bracket"><span class="bv">'.$v.'</span></div>'
                .'<div class="field-label'.$cls.'">'.e($label).'</div>';
        };

        $chk = function ($key, $value) use ($fields) {
            return '<span class="check-box">'.(($fields[$key] ?? '') === $value ? 'X' : '&nbsp;').'</span>';
        };

        $box = function ($checked) {
            return '<span class="check-box">'.($checked ? 'X' : '&nbsp;').'</span>';
        };
    @endphp

    {{-- ENCABEZADO --}}
    <table class="head-table">
        <tr>
            <td style="width: 40%;">
                <span class="head-label">Fecha</span>
                <div class="head-value">{{ $fields['fecha'] ?: ' ' }}</div>
            </td>
            <td style="width: 45%;">
                <span class="head-label">Número de Solicitud</span>
                <div class="head-value">{{ $fields['num_solicitud'] ?: ' ' }}</div>
            </td>
        </tr>
    </table>

    {{-- I. DATOS DEL SOLICITANTE --}}
    <div class="section-header">I. &nbsp;&nbsp;&nbsp; Datos del Solicitante</div>

    <table class="grid">
        <tr>
            <td style="width: 100%;">{!! $f('sol_nombre', 'Nombre, Denominación o Razón Social') !!}</td>
        </tr>
    </table>

    <div class="sub-label">Domicilio</div>
    <table class="grid">
        <tr>
            <td style="width: 40%;">{!! $f('sol_calle', 'Calle') !!}</td>
            <td style="width: 20%;">{!! $f('sol_num_ext', 'Número exterior') !!}</td>
            <td style="width: 20%;">{!! $f('sol_num_int', 'Número Interior') !!}</td>
            <td style="width: 20%;">{!! $f('sol_cp', 'Código Postal') !!}</td>
        </tr>
    </table>
    <table class="grid">
        <tr>
            <td style="width: 35%;">{!! $f('sol_colonia', 'Colonia/Población') !!}</td>
            <td style="width: 35%;">{!! $f('sol_municipio', 'Delegación/Municipio') !!}</td>
            <td style="width: 30%;">{!! $f('sol_estado', 'Estado') !!}</td>
        </tr>
    </table>
    <table class="grid">
        <tr>
            <td style="width: 33%;">{!! $f('sol_telefono', 'Teléfono') !!}</td>
            <td style="width: 33%;">{!! $f('sol_correo', 'Correo Electrónico') !!}</td>
            <td style="width: 33%;">{!! $f('sol_fax', 'Fax') !!}</td>
        </tr>
    </table>

    {{-- II. DATOS DE CONTACTO --}}
    <div class="section-header">II. &nbsp;&nbsp; Datos de Contacto</div>

    <table class="grid">
        <tr>
            <td style="width: 50%;">{!! $f('con_nombre', 'Nombre') !!}</td>
            <td style="width: 50%;">{!! $f('con_puesto', 'Puesto') !!}</td>
        </tr>
    </table>

    <div class="sub-label">Domicilio</div>
    <table class="grid">
        <tr>
            <td style="width: 40%;">{!! $f('con_calle', 'Calle') !!}</td>
            <td style="width: 20%;">{!! $f('con_num_ext', 'Número exterior') !!}</td>
            <td style="width: 20%;">{!! $f('con_num_int', 'Número Interior') !!}</td>
            <td style="width: 20%;">{!! $f('con_cp', 'Código Postal') !!}</td>
        </tr>
    </table>
    <table class="grid">
        <tr>
            <td style="width: 35%;">{!! $f('con_colonia', 'Colonia/Población') !!}</td>
            <td style="width: 35%;">{!! $f('con_municipio', 'Delegación/Municipio') !!}</td>
            <td style="width: 30%;">{!! $f('con_estado', 'Estado') !!}</td>
        </tr>
    </table>
    <table class="grid">
        <tr>
            <td style="width: 33%;">{!! $f('con_telefono', 'Teléfono') !!}</td>
            <td style="width: 33%;">{!! $f('con_correo', 'Correo Electrónico') !!}</td>
            <td style="width: 33%;">{!! $f('con_fax', 'Fax') !!}</td>
        </tr>
    </table>

    {{-- III. DATOS DE LA SOLICITUD --}}
    <div class="section-header">III. &nbsp; Datos de la Solicitud</div>

    <table class="grid">
        <tr>
            <td style="width: 32%; font-size: 9.5px;">Modalidad de la Solicitud</td>
            <td style="width: 34%;"><span class="opt">Baja Tensión</span> {!! $box($fields['modalidad'] === 'baja') !!}</td>
            <td style="width: 34%;"><span class="opt">Media Tensión</span> {!! $box($fields['modalidad'] === 'media') !!}</td>
        </tr>
    </table>

    {{-- IV. UTILIZACIÓN DE LA ENERGÍA --}}
    <div class="section-header">IV. &nbsp; Utilización de la Energía Eléctrica Producida</div>

    <table class="grid">
        <tr>
            <td style="width: 33%;"><span class="opt">Consumo de Centros de Carga</span> {!! $box($fields['utilizacion'] === 'centros') !!}</td>
            <td style="width: 40%;"><span class="opt">Consumo de Centros de Carga y Venta de Excedentes</span> {!! $box($fields['utilizacion'] === 'excedentes') !!}</td>
            <td style="width: 27%;"><span class="opt">Venta Total</span> {!! $box($fields['utilizacion'] === 'venta') !!}</td>
        </tr>
    </table>

    {{-- V. DATOS DEL SERVICIO --}}
    <div class="section-header">V. &nbsp;&nbsp; Datos del Servicio Suministro Actual</div>

    <table class="grid">
        <tr>
            <td style="width: 50%;">{!! $f('rpu', 'Registro Público de Usuario (RPU)') !!}</td>
            <td style="width: 50%;">{!! $f('nivel_tension', 'Nivel de Tensión de Suministro') !!}</td>
        </tr>
    </table>

    {{-- VI. CENTRAL ELÉCTRICA --}}
    <div class="section-header">VI. &nbsp; Central Eléctrica</div>

    <table class="grid">
        <tr>
            <td style="width: 25%;">{!! $f('fecha_operacion', 'Fecha estimada de Operación Normal (DD/MM/AAAA)', true) !!}</td>
            <td style="width: 25%;">{!! $f('capacidad_bruta', 'Capacidad Bruta Instalada (Kw)', true) !!}</td>
            <td style="width: 25%;">{!! $f('capacidad_incrementar', 'Capacidad a Incrementar (kw) (Opcional)', true) !!}</td>
            <td style="width: 25%;">{!! $f('generacion_promedio', 'Generación Promedio Mensual Estimada (kwh/Mes)', true) !!}</td>
        </tr>
    </table>

    {{-- VII. MANIFESTACIÓN --}}
    <div class="section-header">VII. Manifestación de Cumplimiento de las Especificaciones Técnicas Generales</div>

    <table class="grid">
        <tr>
            <td style="width: 88%;">
                <div class="manifest-text">
                    Manifiesto bajo protesta de decir la verdad que la Central Eléctrica cumple con las
                    Especificaciones técnicas requeridas de acuerdo las disposiciones aplicables.
                </div>
            </td>
            <td style="width: 12%; text-align: right;"><span class="si-box">{{ $fields['manifiesto'] ?: 'Si' }}</span></td>
        </tr>
    </table>

    <div style="font-size: 9.5px; margin: 3px 0 2px;">Tecnología para generación de energía eléctrica</div>

    <table class="grid">
        <tr>
            <td style="width: 22%;">
                <table class="plain">
                    <tr><td>Solar</td><td class="box-cell">{!! $box($fields['tecnologia'] === 'solar') !!}</td></tr>
                    <tr><td>Eólico</td><td class="box-cell">{!! $box($fields['tecnologia'] === 'eolico') !!}</td></tr>
                </table>
            </td>
            <td style="width: 26%;">
                <table class="plain">
                    <tr><td>Biomasa</td><td class="box-cell">{!! $box($fields['tecnologia'] === 'biomasa') !!}</td></tr>
                    <tr><td>Cogeneración</td><td class="box-cell">{!! $box($fields['tecnologia'] === 'cogeneracion') !!}</td></tr>
                </table>
            </td>
            <td style="width: 52%;">
                <table class="plain">
                    <tr><td style="width: 70px;">Otro</td><td>{!! $box($fields['tecnologia'] === 'otro') !!}</td></tr>
                    <tr><td>Especificar</td><td style="border-bottom: 1.2px solid #000;">{{ $fields['tecnologia_otro'] ?: ' ' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="grid" style="margin-top: 4px;">
        <tr>
            <td style="width: 33%;">{!! $f('num_unidades', 'No de unidades de generación') !!}</td>
            <td style="width: 33%;">{!! $f('combustible_principal', 'Combustible principal') !!}</td>
            <td style="width: 33%;">{!! $f('combustible_secundario', 'Combustible secundario') !!}</td>
        </tr>
    </table>

    {{-- TABLA COORDENADAS UTM --}}
    <table class="utm-table">
        <tr>
            <td class="utm-title" rowspan="7">Coordenadas UTM</td>
            <td class="utm-head" style="width: 37.5%;"></td>
            <td class="utm-head" style="width: 37.5%;">X</td>
            <td class="utm-head" style="width: 37.5%;">Y</td>
        </tr>
        @for ($i = 1; $i <= 6; $i++)
            <tr>
                <td>{{ $i }}</td>
                <td>{{ $fields["utm_x{$i}"] ?: ' ' }}</td>
                <td>{{ $fields["utm_y{$i}"] ?: ' ' }}</td>
            </tr>
        @endfor
    </table>

    {{-- TEXTOS LEGALES --}}
    <div class="legal">
        <p class="legal-right">(Representante Legal o El Solicitante) / (El Solicitante) certifica que la</p>
        <p>
            información proporcionada en la presente solicitud es apropiada, precisa y verídica. El solicitante acepta
            que los datos proporcionados sean utilizados para llevar a cabo los Estudios de Interconexión para
            garantizar la confiabilidad del Sistema Eléctrico Nacional con la Interconexion de la Central Eléctrica del
            solicitante al amparo de la <em>Ley de la Industria Eléctrica y su Reglamento</em>, en caso de ser
            requeridos. El solicitante entiende que los datos proporcionados, se añadirán a las bases de datos del
            suministrador cuando se firme un contrato de interconexión respectivo.
        </p>
        <p>
            El solicitante deberá anexa a la presente solicitud, la información técnica requerida en el documento
            "Informacion Técnica Requerida para Centrales Eléctricas"
        </p>
    </div>

    {{-- FIRMAS --}}
    <table class="sign-table">
        <tr>
            <td style="width: 58%;">
                <div class="firma-box">
                    <div class="firma-title">Firma de Conformidad</div>
                    <div class="firma-line">&nbsp;</div>
                    <div class="firma-foot">Solicitante</div>
                </div>

                <div class="sign-line"><span class="lbl">Nombre</span> <span class="val">{{ $fields['firma_nombre'] ?: ' ' }}</span></div>
                <div class="sign-line"><span class="lbl">Cargo</span> <span class="val">{{ $fields['firma_cargo'] ?: ' ' }}</span></div>
                <div class="sign-line"><span class="lbl">Fecha</span> <span class="val">{{ $fields['firma_fecha'] ?: ' ' }}</span></div>
            </td>
            <td style="width: 42%; padding-right: 0;">
                <div class="cfe-box">
                    <div class="cfe-text">sello y firma<br>Centro de Atención</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
