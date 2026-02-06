<?php
/**
 * Script de Diagnostico para Generacion de PDF
 * Subelo a la raiz del plugin y visitalo: tudominio.com/wp-content/plugins/hr-management/test-pdf-debug.php
 */

// 1. Cargar WordPress (Ajusta la ruta si es necesario para llegar a wp-load.php)
require_once( explode( "wp-content", __FILE__ )[0] . "wp-load.php" );

echo "<h1>Diagnostico de PDF AnacondaWeb</h1>";

// 2. Verificar Rutas Clave
$plugin_dir = plugin_dir_path( __FILE__ );
$lib_path   = $plugin_dir . 'includes/lib/dompdf/autoload.inc.php';
$func_path  = $plugin_dir . 'includes/functions-pdf.php';

echo "<strong>1. Verificando archivos del plugin:</strong><br>";
echo "Ruta base: " . $plugin_dir . "<br>";

if ( file_exists( $lib_path ) ) {
    echo "✅ Libreria Dompdf encontrada en: $lib_path<br>";
    require_once $lib_path;
} else {
    echo "❌ ERROR FATAL: No encuentro Dompdf en: $lib_path<br>";
    die("Detenido.");
}

if ( file_exists( $func_path ) ) {
    echo "✅ Archivo functions-pdf.php encontrado.<br>";
    require_once $func_path;
} else {
    echo "❌ ERROR: No encuentro functions-pdf.php en: $func_path<br>";
}

// 3. Verificar Carpeta de Uploads
$upload_dir = wp_upload_dir();
$hrm_temp   = $upload_dir['basedir'] . '/hrm_temp';

echo "<br><strong>2. Verificando permisos de escritura:</strong><br>";
echo "Intentando escribir en: $hrm_temp<br>";

if ( ! file_exists( $hrm_temp ) ) {
    echo "⚠️ La carpeta no existe. Intentando crearla...<br>";
    if ( wp_mkdir_p( $hrm_temp ) ) {
        echo "✅ Carpeta creada exitosamente.<br>";
    } else {
        echo "❌ ERROR: No pude crear la carpeta. Verifica permisos (CHMOD 755 o 777) en uploads.<br>";
    }
}

if ( is_writable( $hrm_temp ) ) {
    echo "✅ La carpeta tiene permisos de escritura.<br>";
} else {
    echo "❌ ERROR: La carpeta existe pero NO puedo escribir en ella. (CHMOD actual insuficiente).<br>";
}

// 4. Prueba Real de Generacion
echo "<br><strong>3. Prueba de Generacion de PDF:</strong><br>";

if ( class_exists( 'Dompdf\\Dompdf' ) ) {
    echo "✅ Clase Dompdf cargada correctamente.<br>";

    // Crear objeto dummy para probar
    $dummy_empleado = (object) [
        'ID' => get_current_user_id(),
        'first_name' => 'Test',
        'last_name' => 'User',
        'correo' => 'test@anacondaweb.com'
    ];

    echo "Generando PDF de prueba...<br>";

    try {
        // Llamamos a tu funcion real
        $ruta_test = hrm_generar_pdf_solicitud( 9999, $dummy_empleado, date('Y-m-d'), date('Y-m-d'), 1 );

        if ( ! empty( $ruta_test ) && file_exists( $ruta_test ) ) {
            echo "🎉 <strong>¡EXITO!</strong> PDF generado en: $ruta_test<br>";
            echo "Tamano: " . filesize($ruta_test) . " bytes.<br>";
            echo "<a href='" . $upload_dir['baseurl'] . "/hrm_temp/" . basename($ruta_test) . "' target='_blank'>Ver PDF generado</a>";
        } else {
            echo "❌ ERROR: La funcion se ejecuto pero no devolvio un archivo valido.<br>";
        }
    } catch ( Exception $e ) {
        echo "❌ EXCEPCION AL GENERAR: " . $e->getMessage();
    }

} else {
    echo "❌ ERROR: La clase Dompdf\\Dompdf no esta disponible.";
}
