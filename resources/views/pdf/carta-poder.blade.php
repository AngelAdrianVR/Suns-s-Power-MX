<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Carta Poder · Orden {{ $order->id }}</title>
    <style>
        @page { size: letter; margin: 14mm 12mm; }

        * { font-family: 'DejaVu Serif', serif; font-size: 11.5px; color: #000; }
        body { margin: 0; padding: 0; line-height: 1.5; }

        .carta-page { page-break-after: always; }
        .ine-page { page-break-after: always; text-align: center; }
        .ine-page.last { page-break-after: auto; }

        .doc-title { text-align: center; font-size: 17px; font-weight: bold; letter-spacing: 4px; margin: 0 0 16px; text-transform: uppercase; }

        p.paragraph { margin-bottom: 11px; text-align: justify; }

        strong { font-weight: bold; text-decoration: underline; }

        .fecha { margin-bottom: 18px; text-align: justify; }

        /* Tabla de firmas */
        table.signature-grid { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.signature-grid td { border: 1px solid #000; padding: 0; text-align: center; vertical-align: top; width: 50%; }
        .sig-header { border-bottom: 1px solid #000; padding: 3px; font-weight: bold; font-size: 11px; }
        .sig-body { height: 100px; padding: 8px 20px 6px; }
        .sig-line { border-bottom: 1px solid #000; margin-top: 72px; width: 80%; display: inline-block; }
        .sig-name { margin-top: 3px; font-size: 11.5px; }

        .testigos-title { text-align: center; font-size: 20px; font-weight: bold; color: #3B5A9A; letter-spacing: 4px; margin: 0 0 4px; }
        .testigos-line { border: none; border-top: 2px solid #82A2D4; margin: 0 0 10px; }

        /* Páginas de INE */
        .ine-caption { font-size: 12px; color: #374151; margin-bottom: 8px; font-family: 'DejaVu Sans', sans-serif; }
        .ine-caption strong { text-decoration: none; font-family: 'DejaVu Sans', sans-serif; }
        .ine-image { margin: 0 auto; }
        .ine-fallback { border: 1px dashed #9ca3af; padding: 40px 20px; font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>

    {{-- PÁGINA 1: CARTA PODER --}}
    <div class="carta-page">
        <h1 class="doc-title">CARTA PODER</h1>

        <p class="paragraph">
            Yo <strong>{{ $fields['otorgante_nombre'] ?: '______' }}</strong> con domicilio en
            <strong>{{ $fields['otorgante_domicilio'] ?: '______' }}</strong> y con identificación oficial
            <strong>{{ $fields['otorgante_ine'] ?: 'INE No. ______' }}</strong>, por medio de la presente otorgo
            poder amplio, cumplido y bastante a favor de <strong>{{ $fields['apoderado_nombre'] ?: '______' }}</strong>
            quien se identifica con <strong>{{ $fields['apoderado_ine'] ?: 'INE No. ______' }}</strong>, para que en mi
            nombre y representación realice las gestiones necesarias, firme y suscriba los contratos de interconexión y
            contraprestación ante la Comisión Federal de Electricidad (CFE), así como cualquier documento relacionado con
            dichos contratos, dando por válidas y legales todas y cada una de las gestiones que realice.
        </p>

        <p class="paragraph">
            Manifiesto que la persona antes mencionada ha sido contratada únicamente para realizar los procesos como
            gestor de contratos de interconexión y contraprestación, no excediendo sus honorarios lo establecido en el
            artículo 2556 del CÓDIGO CIVIL FEDERAL.
        </p>

        <p class="paragraph">
            El presente poder se otorga con carácter de especial, limitándose exclusivamente a la facultad descrita en
            el párrafo anterior, el cual tendrá una <strong>{{ $fields['vigencia'] ?: 'vigencia de 6 meses' }}</strong>
            contados a partir de la firma del presente.
        </p>

        <p class="fecha">
            En la ciudad de <strong>{{ $fields['ciudad'] ?: '______' }}</strong>, a los
            <strong>{{ $fields['dia'] ?: '__' }}</strong> días del mes de <strong>{{ $fields['mes'] ?: '______' }}</strong>
            de <strong>{{ $fields['anio'] ?: '____' }}</strong>.
        </p>

        <table class="signature-grid">
            <tr>
                <td>
                    <div class="sig-header">OTORGANTE</div>
                    <div class="sig-body">
                        <span class="sig-line">&nbsp;</span>
                        <div class="sig-name">{{ $fields['otorgante_nombre'] ?: '' }}</div>
                    </div>
                </td>
                <td>
                    <div class="sig-header">APODERADO</div>
                    <div class="sig-body">
                        <span class="sig-line">&nbsp;</span>
                        <div class="sig-name">{{ $fields['apoderado_nombre'] ?: '' }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="testigos-title">TESTIGOS</div>
        <hr class="testigos-line">

        <table class="signature-grid">
            <tr>
                <td>
                    <div class="sig-header">Testigo 1</div>
                    <div class="sig-body">
                        <span class="sig-line">&nbsp;</span>
                        <div class="sig-name">{{ $fields['testigo1_nombre'] ?: '' }}</div>
                    </div>
                </td>
                <td>
                    <div class="sig-header">Testigo 2</div>
                    <div class="sig-body">
                        <span class="sig-line">&nbsp;</span>
                        <div class="sig-name">{{ $fields['testigo2_nombre'] ?: '' }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- PÁGINAS 2-4: INE DE APODERADO Y TESTIGOS --}}
    @foreach (['apoderado', 'testigo1', 'testigo2'] as $roleIndex => $role)
        @if (!empty($ine_pages[$role]))
            <div class="ine-page{{ $roleIndex === 2 ? ' last' : '' }}">
                {{-- <div class="ine-caption">
                    INE — {{ ['apoderado' => 'Apoderado', 'testigo1' => 'Testigo 1', 'testigo2' => 'Testigo 2'][$role] }}:
                    <strong>{{ $ine_pages[$role]['person'] }}</strong>
                </div> --}}
                @if ($ine_pages[$role]['is_image'])
                    <img class="ine-image" src="{{ $ine_pages[$role]['src'] }}"
                         style="width: {{ $ine_pages[$role]['w'] }}mm; {{ $ine_pages[$role]['h'] !== null ? 'height: '.$ine_pages[$role]['h'].'mm;' : '' }}" />
                @elseif (!empty($ine_pages[$role]['needs_gd']))
                    <div class="ine-fallback">
                        La INE ({{ $ine_pages[$role]['file_name'] }}) está en formato PNG/WEBP y el servidor no tiene habilitada
                        la extensión GD de PHP para incrustarla. Súbela en formato JPG o adjúntala por separado en Evidencias y Documentos.
                    </div>
                @else
                    <div class="ine-fallback">
                        La INE seleccionada ({{ $ine_pages[$role]['file_name'] }}) no pudo incluirse como imagen en este PDF.
                        Adjúntala por separado en Evidencias y Documentos.
                    </div>
                @endif
            </div>
        @endif
    @endforeach

</body>
</html>
