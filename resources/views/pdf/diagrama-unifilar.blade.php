<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Diagrama Unifilar · Orden {{ $order->id }}</title>
    <style>
        @page { size: letter landscape; margin: 10mm; }

        * { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111827; }
        body { margin: 0; padding: 0; }
        table { border-collapse: collapse; }

        /* --- Encabezado técnico (simbología) --- */
        .legend-table { width: 100%; margin-bottom: 6px; }
        .legend-table td { border: none; padding: 2px 8px 2px 0; vertical-align: middle; }
        .legend-label { font-size: 9px; font-weight: bold; }
        .circle-sym { display: inline-block; width: 20px; height: 20px; border: 1px solid #000; border-radius: 50%;
                      text-align: center; font-size: 10px; font-weight: bold; line-height: 20px; margin-right: 4px; vertical-align: middle; }
        .sym-text { font-size: 16px; font-weight: bold; margin-right: 4px; vertical-align: middle; }
        .green-box { display: inline-block; width: 15px; height: 15px; background: #16a34a; margin-right: 4px; vertical-align: middle; }
        .mi-symbol { position: relative; display: inline-block; width: 34px; height: 32px; border: 1px solid #000; margin-right: 4px; vertical-align: middle; }
        .mi-symbol .ac { position: absolute; top: 0; left: 2px; font-size: 9px; }
        .mi-symbol .cc { position: absolute; bottom: 0; right: 2px; font-size: 9px; }
        .mi-symbol .diag { position: absolute; left: 4px; top: 15px; width: 24px; border-top: 1px solid #000; transform: rotate(45deg); }

        .main-title { font-size: 12px; font-weight: bold; letter-spacing: 3px; margin: 8px 0 6px; }

        /* --- Generador fotovoltaico --- */
        .gf-table { width: 100%; margin-bottom: 8px; }
        .gf-table > tbody > tr > td { vertical-align: top; border: 1px solid #000; padding: 5px 6px; }
        .box-title { font-size: 9px; text-transform: uppercase; margin-bottom: 4px; }
        .panels-grid td { border: none; padding: 2px; text-align: center; }
        .strings-table td { border: none; padding: 2px 10px 2px 0; text-align: center; vertical-align: top; }
        .string-panels td { border: none; padding: 1px; }
        .string-drop { width: 1px; border-left: 1px solid #000; height: 8px; margin: 2px auto 0; }
        .string-label { display: inline-block; font-size: 8.5px; font-weight: bold; border: 1px solid #000; background: #eef2ff; padding: 0 5px; }
        .mi-group-title { font-size: 8.5px; font-weight: bold; color: #374151; border-bottom: 1px solid #d1d5db; margin: 5px 0 2px; padding-bottom: 1px; }
        .serials-table td { border: none; padding: 2px 6px 2px 0; font-family: 'DejaVu Sans Mono', monospace; font-size: 9px; }
        .hint { font-size: 8px; color: #6b7280; border-top: 1px solid #e5e7eb; margin-top: 4px; padding-top: 3px; }

        /* --- Línea unifilar --- */
        .bus-table { width: 100%; }
        .bus-table td { border: none; text-align: center; vertical-align: bottom; padding: 0 5px; }
        .cc-sym { font-size: 14px; font-weight: bold; }
        .mi-box { border: 1px solid #000; padding: 3px; width: 92px; text-align: center; margin: 2px auto 0; background: #fff; }
        .mi-model { font-size: 7.5px; line-height: 1.25; }
        .mi-pins { margin: 3px auto 0; }
        .mi-pins td { border: 1px solid #000; width: 6px; height: 12px; padding: 0; }
        .mi-sn { font-size: 8px; margin-top: 2px; font-family: 'DejaVu Sans Mono', monospace; }
        .mi-cap { font-size: 6.5px; width: 88px; margin: 1px auto 0; color: #111827; }
        .eq-box { border: 1px solid #000; padding: 3px 2px; width: 68px; text-align: center; margin: 0 auto; background: #fff; }
        .eq-label { font-size: 7px; }
        .eq-sub { font-size: 7px; font-weight: bold; }
        .box-g { width: 26px; height: 26px; border: 1px solid #000; border-radius: 50%; margin: 0 auto; font-weight: bold; font-size: 10px; line-height: 26px; background: #fff; }
        .box-g .sub { font-size: 6px; display: block; line-height: 0; margin-top: -6px; }
        .green-sq { width: 14px; height: 14px; background: #16a34a; margin: 0 auto; }
        .drop { width: 1px; border-left: 1px solid #000; height: 16px; margin: 2px auto 0; }
        .bus-line td { border-top: 1px solid #000; height: 2px; padding: 0; }
        .ground-row td { padding: 2px 5px 0; }

        /* Símbolo de puesta a tierra (CSS puro, sin SVG) */
        .ground { text-align: center; }
        .ground .g-line { width: 1px; height: 4px; background: #000; margin: 0 auto; }
        .ground .g-bar { height: 1px; background: #000; margin: 1px auto 0; }

        /* Símbolo de panel solar (CSS puro, sin SVG) */
        .panel-sym { position: relative; width: 26px; height: 26px; border: 1px solid #000; margin: 0 auto; }
        .panel-sym .p-diag { position: absolute; left: 0; top: 12px; width: 26px; height: 0; border-top: 1px solid #000; }
        .panel-sym .p-diag.d1 { transform: rotate(45deg); }
        .panel-sym .p-diag.d2 { transform: rotate(-45deg); }
        .panel-num { font-size: 7px; text-align: center; margin-top: 1px; }

        .footer { margin-top: 8px; border-top: 1px solid #d1d5db; padding-top: 4px; font-size: 8px; color: #6b7280; }
        .footer td { border: none; padding: 0; }
    </style>
</head>
<body>

    {{-- ENCABEZADO TÉCNICO (SIMBOLOGÍA) --}}
    <table class="legend-table">
        <tr>
            <td width="26%">
                <span class="ground" style="display: inline-block; vertical-align: middle; margin-right: 4px;">
                    <div class="g-line"></div>
                    <div class="g-bar" style="width: 14px;"></div>
                    <div class="g-bar" style="width: 10px;"></div>
                    <div class="g-bar" style="width: 6px;"></div>
                </span>
                <span class="legend-label">DE PUESTA A TIERRA</span>
            </td>
            <td width="26%">
                <span class="circle-sym">G</span>
                <span class="legend-label">GENERADOR</span>
            </td>
            <td width="26%">
                <span class="sym-text">~</span>
                <span class="legend-label">SIMBOLO CORRIENTE ALTERNA (C.A.)</span>
            </td>
            <td>
                <span class="sym-text">=</span>
                <span class="legend-label">SIMBOLO CORRIENTE CONTINUA (C.C.)</span>
            </td>
        </tr>
    </table>

    <table class="legend-table">
        <tr>
            <td width="26%">
                <span class="mi-symbol">
                    <span class="ac">~</span>
                    <span class="diag"></span>
                    <span class="cc">=</span>
                </span>
                <span class="legend-label">MICRO INVERSOR DE<br>SOLAX POWER X1 - MICRO</span>
            </td>
            <td>
                <span class="green-box"></span>
                <span class="legend-label">CAJA DE CONEXION</span>
            </td>
        </tr>
    </table>

    {{-- TÍTULO PRINCIPAL --}}
    <div class="main-title">GENERADOR FOTOVOLTAICO</div>

    {{-- GENERADOR FOTOVOLTAICO: MÓDULOS + SERIES --}}
    <table class="gf-table">
        <tr>
            <td width="48%">
                <div class="box-title">{{ count($panels) }} MÓDULOS SOLARES · {{ count($groups) }} RAMA(S) DE 4</div>
                @if (empty($panels))
                    <div style="font-size: 9px; color: #6b7280; font-style: italic; padding: 6px 0;">
                        Registra la cantidad de unidades en "Detalles Operativos" para capturar las series.
                    </div>
                @else
                    <table class="strings-table">
                        <tr>
                            @foreach ($groups as $idx => $group)
                                <td>
                                    <table class="string-panels">
                                        @foreach (array_chunk($group, 2) as $row)
                                            <tr>
                                                @foreach ($row as $panel)
                                                    <td>
                                                        <div class="panel-sym">
                                                            <div class="p-diag d1"></div>
                                                            <div class="p-diag d2"></div>
                                                        </div>
                                                        <div class="panel-num">{{ $panel['number'] }}</div>
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </table>
                                    <div class="string-drop"></div>
                                    <div class="string-label">MI-{{ $idx + 1 }}</div>
                                </td>
                            @endforeach
                        </tr>
                    </table>
                @endif
                <div style="font-size: 9px; margin-top: 4px;">Modelo: {{ $order->system_type ?: 'ESTÁNDAR' }}</div>
            </td>
            <td>
                <div class="box-title" style="text-align: center;">S N P A N E L E S &nbsp; S O L A R E S</div>
                @if (empty($panels))
                    <div style="font-size: 9px; color: #6b7280; font-style: italic; text-align: center; padding: 6px 0;">
                        Sin paneles registrados.
                    </div>
                @else
                    @foreach ($groups as $idx => $group)
                        <div class="mi-group-title">MI-{{ $idx + 1 }} · PV {{ $group[0]['number'] }} – {{ $group[count($group) - 1]['number'] }}</div>
                        <table class="serials-table" style="width: 100%;">
                            @foreach (array_chunk($group, 2) as $row)
                                <tr>
                                    @foreach ($row as $panel)
                                        <td style="width: 14px;">{{ $panel['number'] }}.</td>
                                        <td style="width: 40%;">{{ $panel['serial'] !== '' ? $panel['serial'] : '—' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </table>
                    @endforeach
                @endif
                {{-- <div class="hint">Números de serie registrados desde el diagrama unifilar.</div> --}}
            </td>
        </tr>
    </table>

    {{-- LÍNEA UNIFILAR --}}
    <table class="bus-table">
        <tr>
            {{-- Microinversores --}}
            @foreach ($microinverters as $idx => $micro)
                <td>
                    <div class="cc-sym">=</div>
                    <div class="mi-box">
                        <div class="mi-model">{{ $micro['model'] }}</div>
                        <table class="mi-pins">
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </table>
                    </div>
                    <div class="mi-sn">SN: {{ $micro['serial'] }}</div>
                    <div class="mi-cap">UNIDAD DE CONVERTIDOR DE ENERGIA</div>
                    <div class="drop"></div>
                </td>
            @endforeach

            {{-- Caja de conexión y puesta a tierra --}}
            <td>
                <div style="width:1px; border-left:1px solid #000; height:30px; margin:0 auto;"></div>
                <div class="green-sq"></div>
                <div style="height:14px;"></div>
                <div class="drop"></div>
            </td>

            {{-- Generador --}}
            <td>
                <div class="box-g">G<span class="sub">27</span></div>
                <div class="eq-sub">kVA</div>
                <div class="drop"></div>
            </td>

            {{-- Interruptor termomagnético --}}
            <td>
                <div class="eq-box">
                    <div class="eq-label">INTERRUPTOR<br>TERMOMAGNETICO</div>
                    <div style="width:12px; height:12px; border:1px solid #000; margin:2px auto 0; position: relative;">
                        <div style="position:absolute; left:5px; top:-1px; width:1px; height:12px; border-left:1px solid #000; transform: rotate(45deg);"></div>
                    </div>
                    <div class="eq-sub">32A</div>
                </div>
                <div class="drop"></div>
            </td>

            {{-- Supresor de picos --}}
            <td>
                <div class="eq-box">
                    <div class="eq-label">SUPRESOR DE<br>PICOS</div>
                    <div style="width:12px; height:12px; border:1px solid #000; margin:2px auto 0;">
                        <div style="border-top:1px solid #000; margin-top:5px;"></div>
                    </div>
                    <div class="eq-sub">275V</div>
                </div>
                <div class="drop"></div>
            </td>

            {{-- Centro de carga --}}
            <td>
                <div class="eq-box">
                    <div class="eq-label">CENTRO DE<br>CARGA</div>
                    <div style="width:14px; height:12px; border:1px solid #000; margin:2px auto 0;">
                        <div style="width:6px; height:10px; border-right:1px solid #000; float:left;"></div>
                    </div>
                </div>
                <div class="drop"></div>
            </td>

            {{-- CFE (medidor) --}}
            <td>
                <div class="eq-box" style="width:60px;">
                    <div style="width:22px; height:22px; border:1px solid #000; border-radius:50%; margin:0 auto;">
                        <div style="width:10px; height:6px; border:1px solid #000; margin:7px auto 0;"></div>
                    </div>
                    <div class="eq-label">CFE</div>
                </div>
                <div class="drop"></div>
            </td>
        </tr>

        {{-- Línea de bus horizontal --}}
        <tr class="bus-line">
            <td colspan="{{ max(1, count($microinverters) + 6) }}"></td>
        </tr>

        {{-- Puestas a tierra bajo la línea --}}
        <tr class="ground-row">
            @foreach ($microinverters as $idx => $micro)
                <td></td>
            @endforeach
            <td>
                <div class="ground">
                    <div class="g-line"></div>
                    <div class="g-bar" style="width: 12px;"></div>
                    <div class="g-bar" style="width: 8px;"></div>
                    <div class="g-bar" style="width: 5px;"></div>
                </div>
            </td>
            <td></td>
            <td></td>
            <td>
                <div class="ground">
                    <div class="g-line"></div>
                    <div class="g-bar" style="width: 12px;"></div>
                    <div class="g-bar" style="width: 8px;"></div>
                    <div class="g-bar" style="width: 5px;"></div>
                </div>
            </td>
            <td></td>
            <td>
                <div class="ground">
                    <div class="g-line"></div>
                    <div class="g-bar" style="width: 12px;"></div>
                    <div class="g-bar" style="width: 8px;"></div>
                    <div class="g-bar" style="width: 5px;"></div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Pie de página --}}
    {{-- <table class="footer" style="width:100%;">
        <tr>
            <td>CLIENTE: {{ $order->client?->name ?: 'N/A' }} | DIRECCIÓN: {{ $order->full_installation_address ?: 'N/A' }}</td>
            <td style="text-align:right;">ORDEN #{{ $order->id }} | FECHA: {{ $generatedAt->format('d/m/Y H:i') }}</td>
        </tr>
    </table> --}}

</body>
</html>
