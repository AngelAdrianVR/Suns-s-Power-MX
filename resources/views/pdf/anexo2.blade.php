<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Anexo 2 · Orden {{ $order->id }}</title>
    <style>
        /* Calibri no está disponible en todos los servidores: Carlito es su clon métrico libre */
        @font-face {
            font-family: 'Carlito';
            font-style: normal;
            font-weight: normal;
            src: url("{{ str_replace('\\', '/', resource_path('fonts/Carlito-Regular.ttf')) }}") format('truetype');
        }
        @font-face {
            font-family: 'Carlito';
            font-style: normal;
            font-weight: bold;
            src: url("{{ str_replace('\\', '/', resource_path('fonts/Carlito-Bold.ttf')) }}") format('truetype');
        }
        @font-face {
            font-family: 'Carlito';
            font-style: italic;
            font-weight: normal;
            src: url("{{ str_replace('\\', '/', resource_path('fonts/Carlito-Italic.ttf')) }}") format('truetype');
        }

        @page {
            size: A4;
            margin: 0;
        }

        * {
            font-family: 'Carlito', 'DejaVu Sans', sans-serif;
            color: #000;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background: #fff;
        }

        .content {
            position: absolute;
            left: 50.4pt;
            top: 0;
            width: 487.92pt;
            height: 720.36pt;
        }

        /* Encabezado */
        .hd-label,
        .hd-line {
            position: absolute;
        }

        .hd-label {
            font-weight: bold;
            font-size: 6pt;
            line-height: 7pt;
            white-space: nowrap;
        }

        .hd-line {
            height: 1.08pt;
            background: #000;
        }

        /* Bandas grises de sección */
        .band {
            position: absolute;
            left: 0.96pt;
            width: 486.48pt;
            height: 9.96pt;
            background: #d9d9d9;
            font-size: 7.6pt;
            font-weight: bold;
            line-height: 9.96pt;
            white-space: nowrap;
        }

        .band-num,
        .band-title {
            position: absolute;
            top: -2.96pt;
            line-height: 9.96pt;
            white-space: nowrap;
        }

        .band-num {
            left: 11.04pt;
        }

        .band-title {
            left: 28.3pt;
        }

        /* Filas de campos */
        .fr {
            position: absolute;
            left: 0;
            width: 487.92pt;
        }

        .lbl {
            position: absolute;
            top: -0.7pt;
            margin-top: -1.1pt;
            font-size: 6pt;
            line-height: 7pt;
            white-space: nowrap;
        }

        .txt {
            position: absolute;
            margin-top: -1.1pt;
            font-size: 6pt;
            line-height: 7pt;
            white-space: nowrap;
        }

        .ln,
        .hln {
            position: absolute;
            background: #000;
        }

        .tk {
            position: absolute;
            width: 1.08pt;
            background: #000;
        }

        .fi {
            position: absolute;
            margin-top: -1.5pt;
            font-size: 6.5pt;
            white-space: nowrap;
        }

        /* Casillas */
        .cb {
            position: absolute;
            background: #fff;
            border: 0.84pt solid #000;
            text-align: center;
            white-space: nowrap;
        }

        .stack {
            position: absolute;
            background: #fff;
            border-style: solid;
            border-color: #000;
        }

        .xtext {
            position: absolute;
            text-align: center;
            white-space: nowrap;
        }

        /* Manifestación */
        .mtext1,
        .mtext2 {
            position: absolute;
            margin-top: -1.1pt;
            white-space: nowrap;
            line-height: 7pt;
        }

        .mtext1 {
            font-size: 5.4pt;
        }

        .mtext2 {
            font-size: 6pt;
        }

        /* Tabla UTM */
        .utm-num {
            left: 0.9pt;
            width: 124.8pt;
            text-align: center;
        }

        /* Textos legales */
        .legal-top,
        .legal-line {
            position: absolute;
            margin-top: -1.1pt;
            font-size: 6pt;
            line-height: 7pt;
            white-space: nowrap;
        }

        /* Firmas */
        .sign-box {
            position: absolute;
            border: 1.08pt solid #000;
        }

        .sign-title,
        .sign-foot,
        .cfe-text,
        .si-text {
            position: absolute;
            margin-top: -1.1pt;
            text-align: center;
            font-size: 6pt;
            line-height: 7pt;
            white-space: nowrap;
        }

    </style>
</head>
<body>

    <div class="content">

        {{-- ENCABEZADO --}}
        <div class="hd-label" style="left: 108.2pt; top: 60.2pt; font-size: 6.5pt; line-height: 7.9pt;">Fecha</div>
        <div class="hd-line" style="left: 125.28pt; top: 70.56pt; width: 115.56pt;"></div>
        <div class="fi" style="left: 125.28pt; top: 62pt; width: 115.56pt; height: 8.5pt; line-height: 8.5pt;">{{ $fields['fecha'] ?? '' }}</div>
        <div class="hd-label" style="left: 346.1pt; top: 61.1pt;">Número de Solicitud</div>
        <div class="hd-line" style="left: 410.16pt; top: 70.56pt; width: 77.28pt;"></div>
        <div class="fi" style="left: 410.16pt; top: 62pt; width: 77.28pt; height: 8.5pt; line-height: 8.5pt;">{{ $fields['num_solicitud'] ?? '' }}</div>

        {{-- I. DATOS DEL SOLICITANTE --}}
        <div class="band" style="top: 78.84pt;"><span class="band-num">I.</span><span class="band-title">Datos del Solicitante</span></div>

        <div class="fr" style="top: 89.2pt; height: 16.4pt;">
            <span class="lbl" style="left: 1.8pt;">Nombre, Denominación o Razon Social</span>
            <div class="fi" style="left: 3.6pt; top: 7.52pt; width: 480.72pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['sol_nombre'] ?? '' }}</div>
            <span class="ln" style="left: 1.08pt; top: 15.92pt; width: 485.76pt; height: 0.48pt;"></span>
            <span class="tk" style="left: 0; top: 6.8pt; height: 9.6pt;"></span>
            <span class="tk" style="left: 486.84pt; top: 6.8pt; height: 9.6pt;"></span>
        </div>

        <span class="txt" style="left: 1.8pt; top: 105.7pt;">Domicilio</span>

        <div class="fr" style="top: 113.7pt; height: 17.46pt;">
            <span class="lbl" style="left: 1.8pt;">Calle</span>
            <span class="lbl" style="left: 129.2pt;">Número exterior</span>
            <span class="lbl" style="left: 244.7pt;">Número Interior</span>
            <span class="lbl" style="left: 360.4pt;">Código Postal</span>
            <div class="fi" style="left: 3.6pt; top: 8pt; width: 117.6pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['sol_calle'] ?? '' }}</div>
            <div class="fi" style="left: 128.4pt; top: 8pt; width: 108pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['sol_num_ext'] ?? '' }}</div>
            <div class="fi" style="left: 243.84pt; top: 8pt; width: 108.24pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['sol_num_int'] ?? '' }}</div>
            <div class="fi" style="left: 359.52pt; top: 8pt; width: 123.6pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['sol_cp'] ?? '' }}</div>
            <span class="ln" style="left: 1.08pt; top: 16.38pt; width: 486.36pt; height: 1.08pt;"></span>
            <span class="tk" style="left: 0; top: 7.5pt; height: 9.96pt;"></span>
            <span class="tk" style="left: 124.8pt; top: 7.5pt; height: 9.96pt;"></span>
            <span class="tk" style="left: 240.24pt; top: 7.5pt; height: 9.96pt;"></span>
            <span class="tk" style="left: 355.92pt; top: 7.5pt; height: 9.96pt;"></span>
            <span class="tk" style="left: 486.84pt; top: 7.5pt; height: 9.96pt;"></span>
        </div>

        <div class="fr" style="top: 130.8pt; height: 17.52pt;">
            <span class="lbl" style="left: 1.8pt;">Colonia/Poblacion</span>
            <span class="lbl" style="left: 126.6pt;">Delegación/Municipio</span>
            <span class="lbl" style="left: 323.9pt;">Estado</span>
            <div class="fi" style="left: 3.6pt; top: 8.04pt; width: 150.68pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['sol_colonia'] ?? '' }}</div>
            <div class="fi" style="left: 161.88pt; top: 8.04pt; width: 166.08pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['sol_municipio'] ?? '' }}</div>
            <div class="fi" style="left: 335.16pt; top: 8.04pt; width: 149.16pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['sol_estado'] ?? '' }}</div>
            <span class="ln" style="left: 0.24pt; top: 16.44pt; width: 487.68pt; height: 1.08pt;"></span>
            <span class="tk" style="left: 0; top: 7.56pt; height: 9.96pt;"></span>
            <span class="tk" style="left: 158.28pt; top: 7.56pt; height: 9.96pt;"></span>
            <span class="tk" style="left: 331.56pt; top: 7.56pt; height: 9.96pt;"></span>
            <span class="tk" style="left: 486.84pt; top: 7.56pt; height: 9.96pt;"></span>
        </div>

        <div class="fr" style="top: 148pt; height: 17.48pt;">
            <span class="lbl" style="left: 1.8pt;">Teléfono</span>
            <span class="lbl" style="left: 162.2pt;">Correo Electrónico</span>
            <span class="lbl" style="left: 337.3pt;">Fax</span>
            <div class="fi" style="left: 3.6pt; top: 8pt; width: 150.68pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['sol_telefono'] ?? '' }}</div>
            <div class="fi" style="left: 161.88pt; top: 8pt; width: 166.08pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['sol_correo'] ?? '' }}</div>
            <div class="fi" style="left: 335.16pt; top: 8pt; width: 149.16pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['sol_fax'] ?? '' }}</div>
            <span class="ln" style="left: 1.08pt; top: 16.4pt; width: 486.84pt; height: 1.08pt;"></span>
            <span class="tk" style="left: 0; top: 7.52pt; height: 9.96pt;"></span>
            <span class="tk" style="left: 158.28pt; top: 7.52pt; height: 9.96pt;"></span>
            <span class="tk" style="left: 331.56pt; top: 7.52pt; height: 9.96pt;"></span>
            <span class="tk" style="left: 486.84pt; top: 7.52pt; height: 9.96pt;"></span>
        </div>

        {{-- II. DATOS DE CONTACTO --}}
        <div class="band" style="top: 172.68pt;"><span class="band-num">II.</span><span class="band-title">Datos de Contacto</span></div>

        <div class="fr" style="top: 183pt; height: 18pt;">
            <span class="lbl" style="left: 1.8pt;">Nombre</span>
            <span class="lbl" style="left: 242pt;">Puesto</span>
            <div class="fi" style="left: 3.6pt; top: 8.28pt; width: 232.44pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['con_nombre'] ?? '' }}</div>
            <div class="fi" style="left: 243.84pt; top: 8.28pt; width: 240.48pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['con_puesto'] ?? '' }}</div>
            <span class="ln" style="left: 1.08pt; top: 16.92pt; width: 486.84pt; height: 1.08pt;"></span>
            <span class="tk" style="left: 0; top: 7.32pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 240.24pt; top: 7.32pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 486.84pt; top: 7.32pt; height: 10.68pt;"></span>
        </div>

        <span class="txt" style="left: 1.8pt; top: 200pt;">Domicilio</span>

        <div class="fr" style="top: 208.5pt; height: 18.18pt;">
            <span class="lbl" style="left: 1.8pt;">Calle</span>
            <span class="lbl" style="left: 129.2pt;">Número exterior</span>
            <span class="lbl" style="left: 244.7pt;">Número Interior</span>
            <span class="lbl" style="left: 367.7pt;">Código Postal</span>
            <div class="fi" style="left: 3.6pt; top: 8.1pt; width: 117.6pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['con_calle'] ?? '' }}</div>
            <div class="fi" style="left: 128.4pt; top: 8.1pt; width: 108pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['con_num_ext'] ?? '' }}</div>
            <div class="fi" style="left: 243.84pt; top: 8.1pt; width: 108.24pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['con_num_int'] ?? '' }}</div>
            <div class="fi" style="left: 359.52pt; top: 8.1pt; width: 123.6pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['con_cp'] ?? '' }}</div>
            <span class="ln" style="left: 1.08pt; top: 17.1pt; width: 486.84pt; height: 1.08pt;"></span>
            <span class="tk" style="left: 0; top: 7.5pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 124.8pt; top: 7.5pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 240.24pt; top: 7.5pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 355.92pt; top: 7.5pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 486.84pt; top: 7.5pt; height: 10.68pt;"></span>
        </div>

        <div class="fr" style="top: 226.4pt; height: 18.88pt;">
            <span class="lbl" style="left: 1.8pt;">Colonia/Poblacion</span>
            <span class="lbl" style="left: 163.6pt;">Delegación/Municipio</span>
            <span class="lbl" style="left: 323.9pt;">Estado</span>
            <div class="fi" style="left: 3.6pt; top: 8pt; width: 150.68pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['con_colonia'] ?? '' }}</div>
            <div class="fi" style="left: 161.88pt; top: 8pt; width: 166.08pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['con_municipio'] ?? '' }}</div>
            <div class="fi" style="left: 335.16pt; top: 8pt; width: 149.16pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['con_estado'] ?? '' }}</div>
            <span class="ln" style="left: 1.08pt; top: 17.8pt; width: 486.84pt; height: 1.08pt;"></span>
            <span class="tk" style="left: 0; top: 7.48pt; height: 11.4pt;"></span>
            <span class="tk" style="left: 158.28pt; top: 7.48pt; height: 11.4pt;"></span>
            <span class="tk" style="left: 331.56pt; top: 7.48pt; height: 11.4pt;"></span>
            <span class="tk" style="left: 486.84pt; top: 7.48pt; height: 11.4pt;"></span>
        </div>

        <div class="fr" style="top: 245pt; height: 18.16pt;">
            <span class="lbl" style="left: 1.8pt;">Teléfono</span>
            <span class="lbl" style="left: 163.5pt;">Correo Electrónico</span>
            <span class="lbl" style="left: 337.3pt;">Fax</span>
            <div class="fi" style="left: 3.6pt; top: 8pt; width: 150.68pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['con_telefono'] ?? '' }}</div>
            <div class="fi" style="left: 161.88pt; top: 8pt; width: 166.08pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['con_correo'] ?? '' }}</div>
            <div class="fi" style="left: 335.16pt; top: 8pt; width: 149.16pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['con_fax'] ?? '' }}</div>
            <span class="ln" style="left: 1.08pt; top: 17.08pt; width: 486.84pt; height: 1.08pt;"></span>
            <span class="tk" style="left: 0; top: 7.48pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 158.28pt; top: 7.48pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 331.56pt; top: 7.48pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 486.84pt; top: 7.48pt; height: 10.68pt;"></span>
        </div>

        {{-- III. DATOS DE LA SOLICITUD --}}
        <div class="band" style="top: 270.36pt;"><span class="band-num">III.</span><span class="band-title">Datos de la Solicitud</span></div>

        <span class="txt" style="left: 1.8pt; top: 283pt;">Modalidad de la Solicitud</span>
        <span class="txt" style="left: 166.7pt; top: 283pt;">Baja Tensión</span>
        <span class="cb" style="left: 251.28pt; top: 279.72pt; width: 23.76pt; height: 9.84pt; font-size: 10.8pt; line-height: 7.84pt;">{{ ($fields['modalidad'] ?? '') === 'baja' ? 'X' : '' }}</span>
        <span class="txt" style="left: 292.2pt; top: 283pt;">Media Tensión</span>
        <span class="cb" style="left: 354.84pt; top: 280.2pt; width: 53.16pt; height: 8.52pt; font-size: 10.8pt; line-height: 6.82pt;">{{ ($fields['modalidad'] ?? '') === 'media' ? 'X' : '' }}</span>

        {{-- IV. UTILIZACIÓN DE LA ENERGÍA --}}
        <div class="band" style="top: 299.4pt;"><span class="band-num">IV.</span><span class="band-title">Utilización de la Energia Eléctrica Producida</span></div>

        <span class="txt" style="left: 1.8pt; top: 309.2pt;">Consumo de Centros de</span>
        <span class="txt" style="left: 1.8pt; top: 317.3pt;">Carga</span>
        <span class="cb" style="left: 74.28pt; top: 312.48pt; width: 24.72pt; height: 10.92pt; font-size: 10.8pt; line-height: 8.22pt;">{{ ($fields['utilizacion'] ?? '') === 'centros' ? 'X' : '' }}</span>
        <span class="txt" style="left: 126.6pt; top: 309.2pt;">Consumo de Centros de Carga y</span>
        <span class="txt" style="left: 145.1pt; top: 317.3pt;">Venta de Excedentes</span>
        <span class="cb" style="left: 251.76pt; top: 311.64pt; width: 23.64pt; height: 11.4pt; font-size: 10.8pt; line-height: 8.7pt;">{{ ($fields['utilizacion'] ?? '') === 'excedentes' ? 'X' : '' }}</span>
        <span class="txt" style="left: 298.2pt; top: 312.5pt;">Venta Total</span>
        <span class="cb" style="left: 355.68pt; top: 311.64pt; width: 53.28pt; height: 11.04pt; font-size: 10.8pt; line-height: 8.34pt;">{{ ($fields['utilizacion'] ?? '') === 'venta' ? 'X' : '' }}</span>

        {{-- V. DATOS DEL SERVICIO --}}
        <div class="band" style="top: 333.72pt;"><span class="band-num">V.</span><span class="band-title">Datos del Servicio Suministro Actual</span></div>

        <div class="fr" style="top: 344.2pt; height: 18.2pt;">
            <span class="lbl" style="left: 1.8pt;">Registro Público de Usuario (RPU)</span>
            <span class="lbl" style="left: 248.7pt;">Nivel de Tensión de Suministro</span>
            <div class="fi" style="left: 3.6pt; top: 8.28pt; width: 232.44pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['rpu'] ?? '' }}</div>
            <div class="fi" style="left: 243.84pt; top: 8.28pt; width: 240.48pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['nivel_tension'] ?? '' }}</div>
            <span class="ln" style="left: 1.08pt; top: 17.12pt; width: 486.36pt; height: 1.08pt;"></span>
            <span class="tk" style="left: 0; top: 7.52pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 240.24pt; top: 7.52pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 486.84pt; top: 7.52pt; height: 10.68pt;"></span>
        </div>

        {{-- VI. CENTRAL ELÉCTRICA --}}
        <div class="band" style="top: 369.96pt;"><span class="band-num">VI.</span><span class="band-title">Central Eléctrica</span></div>

        <div class="fr" style="top: 379.1pt; height: 24.46pt;">
            <span class="lbl" style="left: 1.8pt;">Fecha estimada de Operación Normal</span>
            <span class="lbl" style="left: 1.8pt; top: 5.9pt;">(DD/MM/AAAA)</span>
            <span class="lbl" style="left: 178.8pt; top: 0.1pt;">Capacidad Bruta</span>
            <span class="lbl" style="left: 164.9pt; top: 5.9pt;">Instalada (Kw)</span>
            <span class="lbl" style="left: 243.4pt; top: 0.1pt;">Capacidad a Incrementar (kw)</span>
            <span class="lbl" style="left: 274.3pt; top: 5.6pt;">(Opcional)</span>
            <span class="lbl" style="left: 360.7pt; top: 0.1pt;">Generación Promedio Mensual Estimada</span>
            <span class="lbl" style="left: 396.8pt; top: 5.6pt;">(kwh/Mes)</span>
            <div class="fi" style="left: 3.6pt; top: 14.86pt; width: 150.68pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['fecha_operacion'] ?? '' }}</div>
            <div class="fi" style="left: 161.88pt; top: 14.86pt; width: 74.76pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['capacidad_bruta'] ?? '' }}</div>
            <div class="fi" style="left: 243.84pt; top: 14.86pt; width: 84.12pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['capacidad_incrementar'] ?? '' }}</div>
            <div class="fi" style="left: 335.16pt; top: 14.86pt; width: 149.16pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['generacion_promedio'] ?? '' }}</div>
            <span class="ln" style="left: 1.08pt; top: 23.38pt; width: 486.84pt; height: 1.08pt;"></span>
            <span class="tk" style="left: 0; top: 13.78pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 158.28pt; top: 13.78pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 240.24pt; top: 13.78pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 331.56pt; top: 13.78pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 486.84pt; top: 13.78pt; height: 10.68pt;"></span>
        </div>

        {{-- VII. MANIFESTACIÓN --}}
        <div class="band" style="top: 411.12pt;"><span class="band-num">VII.</span><span class="band-title">Manifestación de Cumplimiento de las Especificicaciones Técnicas Generales</span></div>

        <span class="mtext1" style="left: 1.7pt; top: 420.9pt;">Manifiesto bajo protesta de decir la verdad que la Central Eléctrica cumple con las Especificaciones técnicas requeridas de acuerdo las disposiciones</span>
        <span class="mtext2" style="left: 1.8pt; top: 429.1pt;">aplicables.</span>
        <span class="cb" style="left: 462.12pt; top: 420.48pt; width: 23.16pt; height: 8.16pt;"></span>
        <span class="si-text" style="left: 462.12pt; top: 420.9pt; width: 24.84pt;">{{ ($fields['manifiesto'] ?? '') === 'Si' ? 'SI' : '' }}</span>

        <span class="txt" style="left: 1.8pt; top: 444.7pt;">Tecnología para generación de energía eléctrica</span>

        <span class="txt" style="left: 10.6pt; top: 455.1pt;">Solar</span>
        <span class="stack" style="left: 31.68pt; top: 452.64pt; width: 22.8pt; height: 25.68pt; border-width: 0.48pt;"></span>
        <span class="hln" style="left: 31.68pt; top: 465.72pt; width: 23.76pt; height: 0.48pt;"></span>
        <span class="xtext" style="left: 31.68pt; top: 449.2pt; width: 23.76pt; height: 12.12pt; font-size: 8.6pt; line-height: 12.12pt;">{{ ($fields['tecnologia'] ?? '') === 'solar' ? 'X' : '' }}</span>
        <span class="txt" style="left: 9.5pt; top: 468.2pt;">Eólico</span>

        <span class="txt" style="left: 143.8pt; top: 454.6pt;">Biomasa</span>
        <span class="stack" style="left: 193.08pt; top: 452.4pt; width: 46.44pt; height: 24.48pt; border-width: 0.84pt;"></span>
        <span class="hln" style="left: 193.08pt; top: 465.48pt; width: 48.12pt; height: 0.84pt;"></span>
        <span class="txt" style="left: 137.2pt; top: 468.2pt;">Cogeneración</span>

        <span class="txt" style="left: 308pt; top: 457.7pt;">Otro</span>
        <span class="cb" style="left: 354.84pt; top: 452.4pt; width: 53.16pt; height: 11.4pt; font-size: 8.6pt; line-height: 11.4pt;">{{ ($fields['tecnologia'] ?? '') === 'otro' ? 'X' : '' }}</span>
        <span class="txt" style="left: 302.8pt; top: 470.8pt;">Especificar</span>
        <div class="fi" style="left: 334.04pt; top: 471.74pt; width: 151.6pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['tecnologia_otro'] ?? '' }}</div>
        <span class="hln" style="left: 332.04pt; top: 480.24pt; width: 155.4pt; height: 1.08pt;"></span>

        <div class="fr" style="top: 485pt; height: 18.16pt;">
            <span class="lbl" style="left: 1.8pt;">Cantidad de paneles y capacidad de ellos</span>
            <span class="lbl" style="left: 130.6pt;">Combustible príncipal</span>
            <span class="lbl" style="left: 337.3pt;">Combustible secundarío</span>
            <div class="fi" style="left: 3.6pt; top: 8pt; width: 117.6pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['num_unidades'] ?? '' }}</div>
            <div class="fi" style="left: 128.4pt; top: 8pt; width: 199.56pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['combustible_principal'] ?? '' }}</div>
            <div class="fi" style="left: 335.16pt; top: 8pt; width: 149.16pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['combustible_secundario'] ?? '' }}</div>
            <span class="ln" style="left: 1.08pt; top: 17.08pt; width: 486.84pt; height: 1.08pt;"></span>
            <span class="tk" style="left: 0; top: 7.48pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 124.8pt; top: 7.48pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 331.56pt; top: 7.48pt; height: 10.68pt;"></span>
            <span class="tk" style="left: 486.84pt; top: 7.48pt; height: 10.68pt;"></span>
        </div>

        {{-- TABLA COORDENADAS UTM --}}
        <span class="txt" style="left: 1.8pt; top: 502pt;">Coordenadas UTM</span>
        <span class="txt" style="left: 126.6pt; top: 502.4pt;">X</span>
        <span class="txt" style="left: 333.4pt; top: 502.4pt;">Y</span>

        <span class="ln" style="left: 1.08pt; top: 516.24pt; width: 486.84pt; height: 1.08pt;"></span>
        <span class="ln" style="left: 1.08pt; top: 524.4pt; width: 486.84pt; height: 1.08pt;"></span>
        <span class="ln" style="left: 1.08pt; top: 532.56pt; width: 486.84pt; height: 1.08pt;"></span>
        <span class="ln" style="left: 1.08pt; top: 540.72pt; width: 486.84pt; height: 1.08pt;"></span>
        <span class="ln" style="left: 1.08pt; top: 548.88pt; width: 486.84pt; height: 1.08pt;"></span>
        <span class="ln" style="left: 1.08pt; top: 557.04pt; width: 486.84pt; height: 1.08pt;"></span>

        <span class="tk" style="left: 0; top: 510.24pt; height: 47.88pt;"></span>
        <span class="tk" style="left: 124.8pt; top: 492.48pt; height: 65.64pt;"></span>
        <span class="tk" style="left: 331.56pt; top: 492.48pt; height: 65.64pt;"></span>
        <span class="tk" style="left: 486.84pt; top: 492.48pt; height: 65.64pt;"></span>

        @for ($i = 1; $i <= 6; $i++)
            <span class="txt utm-num" style="top: {{ $i === 1 ? '507.9' : (516.1 + ($i - 2) * 8.16) }}pt;">{{ $i }}</span>
            <div class="fi" style="left: 128.4pt; top: {{ $i === 1 ? '503.7' : (517.7 + ($i - 2) * 8.16) }}pt; width: 200.64pt; height: {{ $i === 1 ? '11.5' : '7.1' }}pt; line-height: {{ $i === 1 ? '11.5' : '7.1' }}pt;">{{ $fields["utm_x{$i}"] ?? '' }}</div>
            <div class="fi" style="left: 335.16pt; top: {{ $i === 1 ? '503.7' : (517.7 + ($i - 2) * 8.16) }}pt; width: 149.16pt; height: {{ $i === 1 ? '11.5' : '7.1' }}pt; line-height: {{ $i === 1 ? '11.5' : '7.1' }}pt;">{{ $fields["utm_y{$i}"] ?? '' }}</div>
        @endfor

        {{-- TEXTOS LEGALES --}}
        <span class="hln" style="left: 2.8pt; top: 578.8pt; width: 292.5pt; height: 0.84pt;"></span>
        <div class="fi" style="left: 4.6pt; top: 570.8pt; width: 288pt; height: 8pt; line-height: 8pt;">{{ $fields['titular_nombre'] ?? '' }}</div>
        <span class="legal-top" style="left: 285.6pt; top: 572.1pt;">(Representante Legal o El Solicitante) ( El Solicitante) certifica que la</span>

        <span class="legal-line" style="left: 1.8pt; top: 579.9pt;">Información proporcionada en la presente solicitud es apropiada,&nbsp; precisa y verídica.&nbsp; El solicitante acepta que los datos proporcionados sean utilizados para llevr a cabo los Estudios de Interconexión</span>
        <span class="legal-line" style="left: 1.8pt; top: 587.7pt;">para garantizar la confiabilidad del Sistema Eléctrico Nacional con la Interconexion de la Central Eléctrica del solicitante al amparo de la <i>Ley de la Industria Eléctrica y su Reglamento</i>,&nbsp; en caso de ser</span>
        <span class="legal-line" style="left: 1.8pt; top: 595.5pt;">requeridos.</span>
        <span class="legal-line" style="left: 412.4pt; top: 595.5pt;">El solicitante entiende que los</span>
        <span class="legal-line" style="left: 1.8pt; top: 603.3pt;">datos proporcionados, se añadiran a las bases de datos del suministrador cuando se firme un contrato de interconexión respectivo.</span>
        <span class="legal-line" style="left: 1.8pt; top: 611.1pt;">El solicitante deberá anexa a la presente solicitud, la información técnica requerida en el documento “Informacion Técnica Requerida para Centrales Eléctricas”</span>

        {{-- FIRMAS --}}
        <span class="sign-box" style="left: 0; top: 634.56pt; width: 239.16pt; height: 38.64pt;"></span>
        <span class="sign-title" style="left: 0; top: 634.5pt; width: 241.32pt;">Firma de Conformidad</span>
        <span class="sign-foot" style="left: 0; top: 666.5pt; width: 241.32pt;">Solicitante</span>

        <span class="sign-box" style="left: 331.56pt; top: 642.72pt; width: 154.2pt; height: 66.12pt;"></span>
        <span class="cfe-text" style="left: 331.56pt; top: 693.7pt; width: 156.36pt;">sello y firma</span>
        <span class="cfe-text" style="left: 331.56pt; top: 701.6pt; width: 156.36pt;">Centro de Atención</span>

        <span class="txt" style="left: 33.2pt; top: 683.8pt;">Nombre</span>
        <span class="txt" style="left: 33.2pt; top: 693.7pt;">Cargo</span>
        <span class="txt" style="left: 33.2pt; top: 701.6pt;">Fecha</span>

        <span class="hln" style="left: 68.3pt; top: 691.9pt; width: 172.4pt; height: 0.84pt;"></span>
        <span class="hln" style="left: 68.3pt; top: 702.2pt; width: 172.4pt; height: 0.84pt;"></span>
        <span class="hln" style="left: 68.3pt; top: 710.8pt; width: 172.4pt; height: 0.84pt;"></span>

        <div class="fi" style="left: 70.1pt; top: 683.4pt; width: 168.8pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['firma_nombre'] ?? '' }}</div>
        <div class="fi" style="left: 70.1pt; top: 693.7pt; width: 168.8pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['firma_cargo'] ?? '' }}</div>
        <div class="fi" style="left: 70.1pt; top: 702.3pt; width: 168.8pt; height: 8.4pt; line-height: 8.4pt;">{{ $fields['firma_fecha'] ?? '' }}</div>

        <span class="txt" style="left: 411.5pt; top: 710.9pt;">-</span>
        <span class="hln" style="left: 0.48pt; top: 719.28pt; width: 486.96pt; height: 1.08pt;"></span>
    </div>

</body>
</html>
