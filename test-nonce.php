<?php
require_once 'wp-load.php';

// Simular el contexto de la página de documentos
$user_id = get_current_user_id();
if ( ! $user_id ) {
    die( 'No hay usuario logueado' );
}

// Crear un nonce de prueba
$nonce = wp_create_nonce( 'hrm_delete_file' );
$action = 'hrm_delete_file';

echo "=== TEST DE NONCE PARA ELIMINAR DOCUMENTOS ===\n\n";
echo "User ID: $user_id\n";
echo "Nonce generado: $nonce\n";
echo "Acción: $action\n\n";

// Verificar el nonce inmediatamente
$verify = wp_verify_nonce( $nonce, $action );
echo "Verificación inmediata: " . ( $verify ? 'OK' : 'FALLÓ' ) . "\n";

// Verificar con una acción diferente
$verify2 = wp_verify_nonce( $nonce, 'otra_accion' );
echo "Verificación con acción diferente: " . ( $verify2 ? 'OK' : 'FALLÓ' ) . "\n";

// Ver el formato del nonce
echo "\nDetalles del nonce:\n";
echo "- Tipo: " . gettype( $nonce ) . "\n";
echo "- Longitud: " . strlen( $nonce ) . "\n";
echo "- Primeros 20 caracteres: " . substr( $nonce, 0, 20 ) . "...\n";

// Ver últimos errores
echo "\nÚltimos errores en debug.log:\n";
$logs = shell_exec( 'tail -20 /home/rrhhanacondaweb/public_html/wp-content/debug.log 2>/dev/null' );
if ( $logs ) {
    echo $logs;
} else {
    echo "No hay debug.log\n";
}
?>
