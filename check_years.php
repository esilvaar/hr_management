<?php
require_once('wp-load.php');
global $wpdb;

$table = $wpdb->prefix . 'rrhh_documentos';

// Verificar documentos con diferentes años
$docs = $wpdb->get_results("SELECT YEAR(fecha_carga) as year, COUNT(*) as count FROM $table GROUP BY YEAR(fecha_carga) ORDER BY year DESC");
echo "=== AÑOS DE DOCUMENTOS ===\n";
foreach ($docs as $doc) {
    echo "Year: $doc->year | Count: $doc->count\n";
}

// Ver todos los documentos
echo "\n=== TODOS LOS DOCUMENTOS ===\n";
$all_docs = $wpdb->get_results("SELECT id, rut, tipo_documento, fecha_carga FROM $table ORDER BY fecha_carga DESC");
foreach ($all_docs as $doc) {
    $year = date('Y', strtotime($doc->fecha_carga));
    $formatted = date('d/m/Y H:i', strtotime($doc->fecha_carga));
    echo "ID: $doc->id | Tipo: $doc->tipo_documento | Fecha: $formatted (Year: $year)\n";
}
