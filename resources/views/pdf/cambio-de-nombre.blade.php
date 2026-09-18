<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cambio de Nombre · Orden {{ $order->id }}</title>
    <style>
        @page { size: letter; margin: 8mm 9mm; }

        * { font-family: 'DejaVu Sans', sans-serif; font-size: 11.5px; color: #000; }
        body { margin: 0; padding: 0; line-height: 1.3; }

        .head-row { margin-bottom: 8px; }
        .head-left { float: left; }
        .head-right { float: right; text-align: right; }
        .clear { clear: both; }

        .section { margin-bottom: 5px; }

        p { margin: 0; }
        .justify { text-align: justify; }

        strong { font-weight: normal; border-bottom: 1px solid #000; padding: 0 2px; }
        strong.wide { display: inline-block; min-width: 180px; }

        .chk { display: inline-block; width: 12px; text-align: center; font-weight: bold; }
        .opt { white-space: nowrap; }

        .check-list p { margin-bottom: 2px; }

        .closing { margin-bottom: 8px; text-align: justify; }

        .signature { text-align: center; }
        .atentamente { margin-bottom: 8px; }
        .sig-name { display: inline-block; min-width: 300px; }
        .sig-label { margin-top: 1px; font-size: 11px; }

        .foot-row { margin-top: 10px; font-size: 10.5px; }
        .foot-left { float: left; }
        .foot-right { float: right; }
    </style>
</head>
<body>

    {{-- ENCABEZADO --}}
    <div class="head-row">
        <div class="head-left">
            <p>Comision Federal de Electricidad</p>
            <p>A quien corresponda:</p>
        </div>
        <div class="head-right">
            <p>Fecha: <strong>{{ $fields['fecha'] ?: '__________' }}</strong></p>
            <p>Asunto: Solicitud de contrato por cambio de titular</p>
        </div>
        <div class="clear"></div>
    </div>

    {{-- IDENTIFICACIÓN DEL SOLICITANTE --}}
    <div class="section justify">
        <p>
            Yo, <strong>{{ $fields['solicitante_nombre'] ?: '______________________' }}</strong>
            en mi calidad de <strong>{{ $fields['solicitante_calidad'] ?: '______________' }}</strong>
        </p>
        <p>identificándome con:</p>
        <p>
            <span class="opt">( <span class="chk">{{ $fields['id_tipo'] === 'ife' ? 'X' : ' ' }}</span> ) IFE/INE &nbsp;</span>
            <span class="opt">( <span class="chk">{{ $fields['id_tipo'] === 'pasaporte' ? 'X' : ' ' }}</span> ) Pasaporte &nbsp;</span>
            <span class="opt">( <span class="chk">{{ $fields['id_tipo'] === 'cedula' ? 'X' : ' ' }}</span> ) Cédula Profesional &nbsp;</span>
            <span class="opt">( <span class="chk">{{ $fields['id_tipo'] === 'otro' ? 'X' : ' ' }}</span> ) Otro: <strong>{{ $fields['id_otro'] ?: '________' }}</strong></span>
            Número: <strong>{{ $fields['id_numero'] ?: '______________' }}</strong>,
        </p>
        <p>
            y acreditando mi representación con:
            <span class="opt">( <span class="chk">{{ $fields['rep_tipo'] === 'na' ? 'X' : ' ' }}</span> ) N/A &nbsp;</span>
            <span class="opt">( <span class="chk">{{ $fields['rep_tipo'] === 'acta' ? 'X' : ' ' }}</span> ) Acta Constitutiva No.: <strong>{{ $fields['rep_acta_no'] ?: '______' }}</strong> &nbsp;</span>
            <span class="opt">( <span class="chk">{{ $fields['rep_tipo'] === 'poder_notarial' ? 'X' : ' ' }}</span> ) Poder Notarial</span>
        </p>
        <p>
            No.: <strong>{{ $fields['rep_no'] ?: '______' }}</strong>
            <span class="opt">( <span class="chk">{{ $fields['rep_tipo'] === 'carta_poder' ? 'X' : ' ' }}</span> ) Carta Poder Simple &nbsp;&nbsp;</span>
            <span class="opt">( <span class="chk">{{ $fields['rep_tipo'] === 'otro' ? 'X' : ' ' }}</span> ) Otro: <strong>{{ $fields['rep_otro'] ?: '____________' }}</strong></span>
        </p>
    </div>

    {{-- SOLICITUD --}}
    <div class="section justify">
        <p>Solicito atentamente se sirva a efectuar los trámites administrativos necesarios para la contratación por cambio</p>
        <p>
            de titular para el servicio de energía eléctrica con número de servicio
            <strong>{{ $fields['servicio'] ?: '________________' }}</strong>
        </p>
        <p>
            actualmente a nombre de: <strong>{{ $fields['titular_actual'] ?: '______________________' }}</strong>
            correspondiente al inmueble
        </p>
        <p>ubicado en: <strong>{{ $fields['domicilio'] ?: '________________________________________________________________' }}</strong>,</p>
        <p>con motivo de: <strong>{{ $fields['motivo'] ?: '________________________________________________________________' }}</strong>,</p>
        <p>siendo el nombre del nuevo titular: <strong>{{ $fields['nuevo_titular'] ?: '______________________' }}</strong>.</p>
    </div>

    {{-- DOCUMENTACIÓN ADJUNTA --}}
    <div class="section">
        <p class="justify" style="margin-bottom: 8px;">
            Acredito la propiedad y/o legítima posesión del inmueble y adjunto a la presente, copia simple de la siguiente
            documentación, presentando original para cotejo:
        </p>
        <div class="check-list">
            <p>( <span class="chk">{{ $fields['doc_escritura'] ? 'X' : ' ' }}</span> ) Escritura Pública de la propiedad notariada</p>
            <p>( <span class="chk">{{ $fields['doc_compraventa'] ? 'X' : ' ' }}</span> ) Contrato de compraventa notariado</p>
            <p>( <span class="chk">{{ $fields['doc_gravamen'] ? 'X' : ' ' }}</span> ) Certificado de libertad de gravamen</p>
            <p>( <span class="chk">{{ $fields['doc_predial'] ? 'X' : ' ' }}</span> ) Recibo del Predial</p>
            <p>( <span class="chk">{{ $fields['doc_ine'] ? 'X' : ' ' }}</span> ) Credencial emitida por el INE con domicilio actual</p>
            <p>( <span class="chk">{{ $fields['doc_arrendamiento_certificado'] ? 'X' : ' ' }}</span> ) Contrato de arrendamiento certificado</p>
            <p>( <span class="chk">{{ $fields['doc_arrendamiento_simple'] ? 'X' : ' ' }}</span> ) Contrato de arrendamiento simple con firma de: A) fiador-aval o B) dos testigos. (incluyendo las identificaciones</p>
            <p style="padding-left: 34px;">oficiales de los suscribientes.)</p>
            <p>( <span class="chk">{{ $fields['doc_constancia'] ? 'X' : ' ' }}</span> ) Carta constancia de domicilio expedida por autoridad de la localidad</p>
        </div>
    </div>

    {{-- DATOS PERSONALES --}}
    <div class="section">
        <p style="margin-bottom: 8px;">Anexo datos personales: (Marcar con una "X")</p>
        <div class="check-list">
            <p>( <span class="chk">{{ $fields['tiene_rfc'] ? 'X' : ' ' }}</span> ) R.F.C.: <strong>{{ $fields['rfc'] ?: '________________' }}</strong></p>
            <p>( <span class="chk">{{ $fields['tiene_telefono'] ? 'X' : ' ' }}</span> ) Teléfono: <strong>{{ $fields['telefono'] ?: '______________' }}</strong></p>
            <p>( <span class="chk">{{ $fields['tiene_celular'] ? 'X' : ' ' }}</span> ) Celular: <strong>{{ $fields['celular'] ?: '______________' }}</strong></p>
            <p>( <span class="chk">{{ $fields['tiene_correo'] ? 'X' : ' ' }}</span> ) Correo electrónico: <strong>{{ $fields['correo'] ?: '____________________' }}</strong></p>
        </div>
    </div>

    {{-- TIMBRADO --}}
    <div class="section">
        <p>
            Requiero timbrado de factura:
            <span class="opt">( <span class="chk">{{ $fields['timbrado'] === 'Si' ? 'X' : ' ' }}</span> ) Si &nbsp;</span>
            <span class="opt">( <span class="chk">{{ $fields['timbrado'] === 'No' ? 'X' : ' ' }}</span> ) No</span>
        </p>
    </div>

    {{-- CONSTANCIA DE SITUACIÓN FISCAL --}}
    <div class="section">
        <p style="margin-bottom: 8px;">Datos de Constancia de Situación Fiscal*:</p>
        <p>Nombre, Denominación o Razón Social: <strong>{{ $fields['csf_nombre'] ?: '____________________________________' }}</strong></p>
        <p>Código Postal: <strong>{{ $fields['csf_cp'] ?: '________' }}</strong></p>
        <p>Régimen fiscal: <strong>{{ $fields['csf_regimen'] ?: '____________________________________________' }}</strong></p>
        <p>Uso de CFDI: <strong>{{ $fields['csf_uso'] ?: '____________________________________________' }}</strong></p>
        <p>Residencia Fiscal (cuando sea extranjero): <strong>{{ $fields['csf_residencia'] ?: '____________________________' }}</strong></p>
        <p>Número de registro de identidad fiscal (cuando sea extranjero): <strong>{{ $fields['csf_registro'] ?: '____________________' }}</strong></p>
    </div>

    {{-- DESPEDIDA --}}
    <div class="closing">
        <p>Sin otro particular por el momento, agradezco de antemano la atención que se brinde al presente.</p>
    </div>

    {{-- FIRMA --}}
    <div class="signature">
        <p class="atentamente">Atentamente</p>
        <p><strong class="sig-name">@if($fields['firma_nombre']){{ $fields['firma_nombre'] }}@else<span style="color: #9ca3af;">(opcional)</span>@endif</strong></p>
        <p class="sig-label">Nombre y firma (opcional)</p>
        <p class="sig-label">Propietario, Representante Legal, Arrendatario</p>
    </div>

    {{-- PIE --}}
    <div class="foot-row">
        <div class="foot-left">*En caso de requerir factura timbrada.</div>
        <div class="foot-right">I-4001-063-R-01</div>
        <div class="clear"></div>
    </div>

</body>
</html>
