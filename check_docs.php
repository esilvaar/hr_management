<?php
require_once('wp-load.php');
global $wpdb;

$table = $wpdb->prefix . 'rrhh_documentos';
$columns = $wpdb->get_results("DESCRIBE $table");

echo "=== ESTRUCTURA ===\n";
foreach ($columns as $col) {
    echo $col->Field . " | " . $col->Type . " | Default: " . $col->Default . "\n";
}

$docs = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 5");
echo "\n=== DOCUMENTOS ===\n";
if (empty($docs)) {
    echo "No hay documentos\n";
} else {
    foreach ($docs as $doc) {
        echo "ID: $doc->id | RUT: $doc->rut | Tipo: $doc->tipo_documento | Fecha: " . ($doc->fecha_carga ?? 'NULL') . "\n";
    }
}
