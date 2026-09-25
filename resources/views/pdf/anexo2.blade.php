<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Anexo 2 · Orden {{ $order->id }}</title>
    <style>
        @page { size: letter; margin: 15mm 22mm; }

        * { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #000; }
        body { margin: 0; padding: 0; line-height: 1.15; }

        .head-table { width: 100%; border-collapse: collapse; margin: 0 0 2px; }
        .head-table td { vertical-align: bottom; padding: 0 4px; }
        .head-table td.head-label-cell { font-weight: bold; font-size: 10.5px; padding: 0 8px 1px 4px; white-space: nowrap; }
        .head-table td.head-value-cell { border-bottom: 2px solid #000; font-size: 9px; padding: 0 4px 1px; min-height: 11px; text-align: right; }

        .section-header { background-color: #d9d9d9; font-weight: bold; font-size: 9.5px; padding: 1px 5px; margin: 2px 0 1px; }

        /* Rejillas de campos */
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 1px; }
        table.grid td { padding: 0 3px; vertical-align: top; }

        /* Secciones III a VI: un poco más de aire arriba y abajo */
        table.grid.section-body { margin-top: 7px; margin-bottom: 7px; }

        /* Campos: línea inferior corrida; las verticales sólo recorren la zona de
           captura, es decir quedan por debajo de los títulos de cada campo */
        table.grid.fields td {
            border-bottom: 2px solid #000;
            padding: 0;
            vertical-align: bottom;
        }

        .field-value { font-size: 9px; line-height: 1; min-height: 7px; padding: 0 4px; }
        table.grid.fields .field-value { border-right: 2px solid #000; }
        table.grid.fields td:first-child .field-value { border-left: 2px solid #000; }
        .field-label { font-size: 6.5px; line-height: 1; padding: 0 4px 1px; }
        .field-label.center { text-align: center; }

        .sub-label { font-size: 9px; font-weight: bold; margin: 0; }

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
        table.utm-table { width: 100%; border-collapse: collapse; border: 2px solid #000; text-align: center; margin: 2px 0 2px; }
        table.utm-table td { border: 2px solid #000; height: 7px; padding: 1px 3px; font-size: 8px; line-height: 1; }
        .utm-title { width: 25%; font-weight: bold; text-align: left; }
        .utm-head { font-weight: bold; }

        /* Textos legales */
        .legal { font-size: 7px; text-align: justify; line-height: 1.15; margin-bottom: 2px; }
        .legal p { margin: 0 0 1px; }

        /* Renglón del titular: la línea cubre el espacio libre a su izquierda */
        table.legal-row { width: 100%; border-collapse: collapse; margin: 0 0 1px; }
        table.legal-row td { vertical-align: bottom; font-size: 7px; padding: 0; }
        table.legal-row td.legal-name-cell { width: 58%; border-bottom: 2px solid #000; padding: 0 3px; font-weight: bold; }
        table.legal-row td.legal-name-text { width: 42%; padding-left: 3px; text-align: right; }

        /* Firmas */
        table.sign-table { width: 100%; border-collapse: collapse; margin-top: 0; }
        table.sign-table td { vertical-align: top; padding: 0 10px 0 0; }
        table.firma-box { width: 80%; height: 52px; border: 2px solid #000; border-collapse: collapse; margin-bottom: 3px; }
        table.firma-box td { text-align: center; vertical-align: top; font-size: 8px; padding: 1px 4px 0; }
        table.firma-box td.firma-foot { vertical-align: bottom; padding: 0 4px 2px; }
        .sign-line { margin-bottom: 2px; font-size: 8px; padding-left: 24px; }
        .sign-line .lbl { display: inline-block; width: 40px; }
        .sign-line .val { display: inline-block; border-bottom: 2px solid #000; min-width: 150px; padding: 0 3px; font-size: 8px; }
        table.cfe-wrap { width: 100%; border-collapse: collapse; }
        table.cfe-wrap td.cfe-gap { padding: 0; }
        table.cfe-wrap td.cfe-cell { padding: 0; }
        table.cfe-box { width: 100%; height: 84px; border: 2px solid #000; border-collapse: collapse; text-align: center; }
        table.cfe-box td { vertical-align: bottom; font-size: 8px; line-height: 1.2; padding: 0 4px 5px; }

        /* Línea horizontal de cierre, al final de la hoja */
        .sheet-end-line { margin-top: 10px; border-bottom: 2px solid #000; }
    </style>
</head>
<body>

    @php
        $f = function ($key, $label, $center = false) use ($fields) {
            $v = e((string) ($fields[$key] ?? ''));
            $cls = $center ? ' center' : '';
            return '<div class="field-label'.$cls.'">'.e($label).'</div>'
                .'<div class="field-value">'.($v !== '' ? $v : '&nbsp;').'</div>';
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
            <td class="head-label-cell" style="width: 12%;">Fecha</td>
            <td class="head-value-cell" style="width: 22.4%;">{{ $fields['fecha'] ?: ' ' }}</td>
            <td class="head-label-cell" style="width: 22%;">Número de Solicitud</td>
            <td class="head-value-cell" style="width: 30.4%;">{{ $fields['num_solicitud'] ?: ' ' }}</td>
            <td style="width: 13.2%;">&nbsp;</td>
        </tr>
    </table>

    {{-- I. DATOS DEL SOLICITANTE --}}
    <div class="section-header">I. &nbsp;&nbsp;&nbsp; Datos del Solicitante</div>

    <table class="grid fields">
        <tr>
            <td style="width: 100%;">{!! $f('sol_nombre', 'Nombre, Denominación o Razón Social') !!}</td>
        </tr>
    </table>

    <div class="sub-label">Domicilio</div>
    <table class="grid fields">
        <tr>
            <td style="width: 40%;">{!! $f('sol_calle', 'Calle') !!}</td>
            <td style="width: 20%;">{!! $f('sol_num_ext', 'Número exterior') !!}</td>
            <td style="width: 20%;">{!! $f('sol_num_int', 'Número Interior') !!}</td>
            <td style="width: 20%;">{!! $f('sol_cp', 'Código Postal') !!}</td>
        </tr>
    </table>
    <table class="grid fields">
        <tr>
            <td style="width: 35%;">{!! $f('sol_colonia', 'Colonia/Población') !!}</td>
            <td style="width: 35%;">{!! $f('sol_municipio', 'Delegación/Municipio') !!}</td>
            <td style="width: 30%;">{!! $f('sol_estado', 'Estado') !!}</td>
        </tr>
    </table>
    <table class="grid fields">
        <tr>
            <td style="width: 33%;">{!! $f('sol_telefono', 'Teléfono') !!}</td>
            <td style="width: 33%;">{!! $f('sol_correo', 'Correo Electrónico') !!}</td>
            <td style="width: 33%;">{!! $f('sol_fax', 'Fax') !!}</td>
        </tr>
    </table>

    {{-- II. DATOS DE CONTACTO --}}
    <div class="section-header">II. &nbsp;&nbsp; Datos de Contacto</div>

    <table class="grid fields">
        <tr>
            <td style="width: 50%;">{!! $f('con_nombre', 'Nombre') !!}</td>
            <td style="width: 50%;">{!! $f('con_puesto', 'Puesto') !!}</td>
        </tr>
    </table>

    <div class="sub-label">Domicilio</div>
    <table class="grid fields">
        <tr>
            <td style="width: 40%;">{!! $f('con_calle', 'Calle') !!}</td>
            <td style="width: 20%;">{!! $f('con_num_ext', 'Número exterior') !!}</td>
            <td style="width: 20%;">{!! $f('con_num_int', 'Número Interior') !!}</td>
            <td style="width: 20%;">{!! $f('con_cp', 'Código Postal') !!}</td>
        </tr>
    </table>
    <table class="grid fields">
        <tr>
            <td style="width: 35%;">{!! $f('con_colonia', 'Colonia/Población') !!}</td>
            <td style="width: 35%;">{!! $f('con_municipio', 'Delegación/Municipio') !!}</td>
            <td style="width: 30%;">{!! $f('con_estado', 'Estado') !!}</td>
        </tr>
    </table>
    <table class="grid fields">
        <tr>
            <td style="width: 33%;">{!! $f('con_telefono', 'Teléfono') !!}</td>
            <td style="width: 33%;">{!! $f('con_correo', 'Correo Electrónico') !!}</td>
            <td style="width: 33%;">{!! $f('con_fax', 'Fax') !!}</td>
        </tr>
    </table>

    {{-- III. DATOS DE LA SOLICITUD --}}
    <div class="section-header">III. &nbsp; Datos de la Solicitud</div>

    <table class="grid section-body">
        <tr>
            <td style="width: 32%; font-size: 9.5px;">Modalidad de la Solicitud</td>
            <td style="width: 34%;"><span class="opt">Baja Tensión</span> {!! $box($fields['modalidad'] === 'baja') !!}</td>
            <td style="width: 34%;"><span class="opt">Media Tensión</span> {!! $box($fields['modalidad'] === 'media') !!}</td>
        </tr>
    </table>

    {{-- IV. UTILIZACIÓN DE LA ENERGÍA --}}
    <div class="section-header">IV. &nbsp; Utilización de la Energía Eléctrica Producida</div>

    <table class="grid section-body">
        <tr>
            <td style="width: 33%;"><span class="opt">Consumo de Centros de Carga</span> {!! $box($fields['utilizacion'] === 'centros') !!}</td>
            <td style="width: 40%;"><span class="opt">Consumo de Centros de Carga y Venta de Excedentes</span> {!! $box($fields['utilizacion'] === 'excedentes') !!}</td>
            <td style="width: 27%;"><span class="opt">Venta Total</span> {!! $box($fields['utilizacion'] === 'venta') !!}</td>
        </tr>
    </table>

    {{-- V. DATOS DEL SERVICIO --}}
    <div class="section-header">V. &nbsp;&nbsp; Datos del Servicio Suministro Actual</div>

    <table class="grid fields section-body">
        <tr>
            <td style="width: 50%;">{!! $f('rpu', 'Registro Público de Usuario (RPU)') !!}</td>
            <td style="width: 50%;">{!! $f('nivel_tension', 'Nivel de Tensión de Suministro') !!}</td>
        </tr>
    </table>

    {{-- VI. CENTRAL ELÉCTRICA --}}
    <div class="section-header">VI. &nbsp; Central Eléctrica</div>

    <table class="grid fields section-body">
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
                    <tr><td>Especificar</td><td style="border-bottom: 2px solid #000;">{{ $fields['tecnologia_otro'] ?: ' ' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="grid fields" style="margin-top: 4px;">
        <tr>
            <td style="width: 33%;">{!! $f('num_unidades', 'No de unidades de generación') !!}</td>
            <td style="width: 33%;">{!! $f('combustible_principal', 'Combustible principal') !!}</td>
            <td style="width: 33%;">{!! $f('combustible_secundario', 'Combustible secundario') !!}</td>
        </tr>
    </table>

    {{-- TABLA COORDENADAS UTM --}}
    <table class="utm-table">
        <tr>
            {{-- <td class="utm-title" rowspan="7">Coordenadas UTM</td> --}}
            <td class="utm-head !border-b-transparent !text-left" style="width: 37.5%;">Coordenadas UTM</td>
            <td class="utm-head !border-b-transparent !text-left" style="width: 37.5%;">X</td>
            <td class="utm-head !border-b-transparent !text-left" style="width: 37.5%;">Y</td>
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
        <table class="legal-row">
            <tr>
                <td class="legal-name-cell">{{ $fields['titular_nombre'] ?: ' ' }}</td>
                <td class="legal-name-text">(Representante Legal o El Solicitante) / (El Solicitante) certifica que la</td>
            </tr>
        </table>
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
                <table class="firma-box">
                    <tr>
                        <td class="firma-title">Firma de Conformidad</td>
                    </tr>
                    <tr>
                        <td class="firma-foot">Solicitante</td>
                    </tr>
                </table>

                <div class="sign-line"><span class="lbl">Nombre</span> <span class="val">{{ $fields['firma_nombre'] ?: ' ' }}</span></div>
                <div class="sign-line"><span class="lbl">Cargo</span> <span class="val">{{ $fields['firma_cargo'] ?: ' ' }}</span></div>
                <div class="sign-line"><span class="lbl">Fecha</span> <span class="val">{{ $fields['firma_fecha'] ?: ' ' }}</span></div>
            </td>
            <td style="width: 42%; padding-right: 0;">
                <table class="cfe-wrap">
                    <tr>
                        <td class="cfe-gap" style="width: 20%;">&nbsp;</td>
                        <td class="cfe-cell" style="width: 80%;">
                            <table class="cfe-box">
                                <tr>
                                    <td>sello y firma<br>Centro de Atención</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- LÍNEA DE CIERRE AL FINAL DE LA HOJA --}}
    <div class="sheet-end-line"></div>

</body>
</html>
