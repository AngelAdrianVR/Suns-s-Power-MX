<?php
// Script temporal: extrae el texto de cada página del PDF para ver qué se va a la página 2.
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$f = array_fill_keys([
    'fecha', 'num_solicitud',
    'sol_nombre', 'sol_calle', 'sol_num_ext', 'sol_num_int', 'sol_cp', 'sol_colonia', 'sol_municipio', 'sol_estado', 'sol_telefono', 'sol_correo', 'sol_fax',
    'con_nombre', 'con_puesto', 'con_calle', 'con_num_ext', 'con_num_int', 'con_cp', 'con_colonia', 'con_municipio', 'con_estado', 'con_telefono', 'con_correo', 'con_fax',
    'rpu', 'nivel_tension', 'fecha_operacion', 'capacidad_bruta', 'capacidad_incrementar', 'generacion_promedio',
    'tecnologia_otro', 'num_unidades', 'combustible_principal', 'combustible_secundario', 'titular_nombre',
    'firma_nombre', 'firma_cargo', 'firma_fecha',
    'utm_x1', 'utm_y1', 'utm_x2', 'utm_y2', 'utm_x3', 'utm_y3', 'utm_x4', 'utm_y4', 'utm_x5', 'utm_y5', 'utm_x6', 'utm_y6',
], '');
$f['sol_nombre'] = 'CLIENTE DEMO SA DE CV';
$f['num_unidades'] = '8 UNIDADES DE 645 W';
$f['modalidad'] = 'baja';
$f['utilizacion'] = 'centros';
$f['manifiesto'] = 'Si';
$f['tecnologia'] = 'solar';
$f['titular_nombre'] = 'CLIENTE DEMO';

$html = view('pdf.anexo2', ['fields' => $f, 'order' => (object) ['id' => 1]])->render();

$pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('letter');
$pdf->render();
$raw = $pdf->output();
file_put_contents(__DIR__.'/storage/app/anexo2-preview.pdf', $raw);

// Extraer los streams de contenido y decompresionarlos
preg_match_all('/stream\r?\n(.*?)endstream/s', $raw, $m);
$i = 0;
$log = '';
foreach ($m[1] as $chunk) {
    $data = @gzuncompress(trim($chunk));
    if ($data === false) {
        continue;
    }
    if (strpos($data, 'BT') === false) {
        continue;
    }
    $i++;
    preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/s', $data, $texts);
    $txt = array_map(fn ($t) => substr($t, 1, -1), $texts[0]);
    $txt = array_values(array_filter($txt, fn ($t) => trim($t) !== '' && trim($t) !== ' '));
    $log .= "=== Página {$i} (".count($txt)." textos) ===\n";
    $log .= implode(' | ', array_slice($txt, 0, 80))."\n\n";
}

file_put_contents(__DIR__.'/storage/app/pages.txt', $log);
echo "escrito storage/app/pages.txt\n";
