<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud Arco CFE · Orden {{ $order->id }}</title>
    <style>
        @page { size: letter; margin: 22mm 20mm; }

        * { font-family: 'DejaVu Sans', sans-serif; font-size: 14px; color: #111827; }
        body { margin: 0; padding: 0; line-height: 1.55; }

        .letter-head { text-align: right; margin-bottom: 10px; color: #374151; }
        .addressee { font-size: 15px; font-weight: bold; color: #111827; margin-bottom: 14px; }
        .paragraph { margin-bottom: 12px; text-align: justify; }
        .list { margin: 0 0 12px; padding-left: 0; list-style: none; }
        .list li { margin-bottom: 10px; text-align: justify; }
        strong { font-weight: bold; color: #000; }
        a { color: #1e3a8a; }
        .signature { margin-top: 70px; }
        .signature .line { width: 65%; border-top: 1px solid #000; margin: 0 auto; text-align: center; }
        .signature .label { margin-top: 5px; text-align: center; font-size: 13px; color: #374151; }
        .signature .signer-name { margin-top: 12px; text-align: center; font-size: 13px; font-weight: bold; color: #000; }
    </style>
</head>
<body>

    <div class="letter-head">{{ $fields['fecha'] ?: '&nbsp;' }}</div>

    <div class="addressee">Comisión Federal de Electricidad</div>

    <p class="paragraph">
        Por medio del presente solicito que el servicio no. <strong>{{ $fields['servicio'] ?: '______' }}</strong>
        a nombre de <strong>{{ $fields['cliente'] ?: '______' }}</strong> ubicado en
        <strong>{{ $fields['domicilio'] ?: '______' }}</strong>,
        @if (!empty($fields['maps_url']))
            <a href="{{ $fields['maps_url'] }}">VER</a>
        @else
            <strong>VER</strong>
        @endif
        sea rectificado y/o actualizado de la siguiente forma:
    </p>

    <ul class="list">
        <li>
            • Actualizados los datos fiscales con timbrado, RFC: <strong>{{ $fields['rfc'] ?: '______' }}</strong>.
            Régimen fiscal: <strong>{{ $fields['regimen'] ?: '______' }}</strong>,
            Uso de CFDI: <strong>{{ $fields['uso_cfdi'] ?: '______' }}</strong>.
        </li>
        <li>
            • Actualizado los medios de contacto: número de teléfono:
            <strong>{{ $fields['telefono'] ?: '______' }}</strong> y correo electrónico:
            <strong>{{ $fields['correo'] ?: '______' }}</strong>.
        </li>
        <li>
            • Actualizado <strong>{{ $fields['calle'] ?: '______' }}</strong> a entre calles
            <strong>{{ $fields['entre_calles'] ?: '______' }}</strong>.
        </li>
        <li>
            • Rectificado nombre del titular <strong>{{ $fields['titular'] ?: '______' }}</strong>.
        </li>
    </ul>

    {{-- <p>Atentamente</p> --}}

    <div class="signature">
        <div class="line">&nbsp;</div>
        {{-- <div class="label">Firma</div> --}}
        <div class="signer-name">{{ $fields['firma_nombre'] ?? '' }}</div>
    </div>

</body>
</html>
