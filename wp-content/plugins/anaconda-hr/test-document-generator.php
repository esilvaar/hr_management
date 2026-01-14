<?php
/**
 * Script de Prueba: Sistema de Generación de Documentos
 * 
 * Propósito: Verificar que el sistema de documentos funcione correctamente
 * antes de implementarlo en hr-management.
 * 
 * Uso: Acceder desde el navegador a:
 * /wp-content/plugins/anaconda-hr/test-document-generator.php
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    wp_die('❌ No tienes permisos para ejecutar este script de prueba.');
}

// Cargar clase de base de datos
require_once plugin_dir_path(__FILE__) . 'includes/class-ahr-db.php';

$db = new AHR_DB();
global $wpdb;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 Prueba: Generador de Documentos</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d2327;
            border-bottom: 3px solid #2271b1;
            padding-bottom: 10px;
        }
        h2 {
            color: #2271b1;
            margin-top: 30px;
        }
        .status {
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .success {
            background: #d5f4e6;
            border-left: 4px solid #46b450;
        }
        .warning {
            background: #fff8e5;
            border-left: 4px solid #ffb900;
        }
        .error {
            background: #fbe7e8;
            border-left: 4px solid #dc3232;
        }
        .info {
            background: #e5f5fa;
            border-left: 4px solid #2271b1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f6f7f7;
            font-weight: 600;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #2271b1;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            margin: 5px;
        }
        .btn:hover {
            background: #135e96;
        }
        .btn-success {
            background: #46b450;
        }
        .btn-success:hover {
            background: #2ea832;
        }
        .btn-warning {
            background: #ffb900;
            color: #1d2327;
        }
        .btn-warning:hover {
            background: #e5a500;
        }
        code {
            background: #f6f7f7;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .step {
            background: #f6f7f7;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #2271b1;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Prueba del Sistema de Generación de Documentos</h1>
        <p>Este script verifica el funcionamiento del generador de documentos PDF antes de implementarlo en <strong>hr-management</strong>.</p>

        <?php
        // ====================================
        // PASO 1: Verificar tablas
        // ====================================
        ?>
        <h2>📊 Paso 1: Verificación de Base de Datos</h2>
        <?php
        $tabla_vacaciones = $wpdb->prefix . 'ahr_vacaciones';
        $tabla_empleados = $wpdb->prefix . 'ahr_empleados';
        
        $tabla_vac_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla_vacaciones'");
        $tabla_emp_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla_empleados'");
        
        if ($tabla_vac_existe && $tabla_emp_existe) {
            echo '<div class="status success">✅ <strong>Tablas encontradas:</strong> ';
            echo "<code>$tabla_vacaciones</code> y <code>$tabla_empleados</code></div>";
        } else {
            echo '<div class="status error">❌ <strong>Error:</strong> Faltan tablas. ';
            echo 'Desactiva y reactiva el plugin <strong>Anaconda RRHH v2</strong> desde Plugins.</div>';
        }

        // ====================================
        // PASO 2: Contar registros
        // ====================================
        ?>
        <h2>📈 Paso 2: Datos Existentes</h2>
        <?php
        $count_empleados = $wpdb->get_var("SELECT COUNT(*) FROM $tabla_empleados");
        $count_solicitudes = $wpdb->get_var("SELECT COUNT(*) FROM $tabla_vacaciones");
        
        echo "<div class='status info'>";
        echo "👥 <strong>Empleados:</strong> $count_empleados<br>";
        echo "📝 <strong>Solicitudes:</strong> $count_solicitudes";
        echo "</div>";

        if ($count_empleados == 0) {
            echo '<div class="status warning">⚠️ <strong>No hay empleados registrados.</strong> ';
            echo 'Ve a <a href="' . admin_url('admin.php?page=ahr-nuevo-empleado') . '">Añadir Empleado</a> para crear uno.</div>';
        }

        if ($count_solicitudes == 0) {
            echo '<div class="status warning">⚠️ <strong>No hay solicitudes.</strong> ';
            echo 'Ve a <a href="' . admin_url('admin.php?page=ahr-vacaciones') . '">Solicitar Vacaciones</a> para crear una.</div>';
        }

        // ====================================
        // PASO 3: Mostrar solicitudes con enlace al documento
        // ====================================
        ?>
        <h2>📄 Paso 3: Prueba de Generación de Documentos</h2>
        <?php
        if ($count_solicitudes > 0) {
            $solicitudes = $wpdb->get_results("
                SELECT v.*, e.rut, e.nombres, e.apellidos, e.cargo, e.departamento
                FROM $tabla_vacaciones v 
                LEFT JOIN $tabla_empleados e ON v.user_id = e.wp_user_id
                ORDER BY v.created_at DESC
                LIMIT 10
            ");

            if ($solicitudes) {
                echo '<div class="status success">✅ Se encontraron ' . count($solicitudes) . ' solicitudes. Haz clic en "Ver Documento" para probar:</div>';
                
                echo '<table>';
                echo '<thead>';
                echo '<tr>';
                echo '<th>ID</th>';
                echo '<th>Empleado</th>';
                echo '<th>RUT</th>';
                echo '<th>Tipo</th>';
                echo '<th>Fechas</th>';
                echo '<th>Estado</th>';
                echo '<th>Acciones</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                foreach ($solicitudes as $sol) {
                    $nombre_completo = $sol->nombres . ' ' . $sol->apellidos;
                    if (empty($nombre_completo) || trim($nombre_completo) == '') {
                        $user_data = get_userdata($sol->user_id);
                        $nombre_completo = $user_data ? $user_data->display_name : 'Usuario #' . $sol->user_id;
                    }
                    
                    $estado_color = '';
                    if ($sol->estado == 'APROBADO') $estado_color = 'color: #46b450; font-weight: bold;';
                    if ($sol->estado == 'RECHAZADO') $estado_color = 'color: #dc3232; font-weight: bold;';
                    if ($sol->estado == 'PENDIENTE') $estado_color = 'color: #ffb900; font-weight: bold;';
                    
                    echo '<tr>';
                    echo '<td>' . $sol->id . '</td>';
                    echo '<td>' . esc_html($nombre_completo) . '</td>';
                    echo '<td>' . esc_html($sol->rut ?? 'N/A') . '</td>';
                    echo '<td>' . esc_html($sol->tipo) . '</td>';
                    echo '<td>' . date('d/m/Y', strtotime($sol->fecha_inicio)) . ' → ' . date('d/m/Y', strtotime($sol->fecha_fin)) . '</td>';
                    echo '<td style="' . $estado_color . '">' . $sol->estado . '</td>';
                    echo '<td>';
                    
                    // ENLACE AL DOCUMENTO
                    $doc_url = admin_url('admin.php?page=ahr-view-pdf&id=' . $sol->id);
                    echo '<a href="' . $doc_url . '" target="_blank" class="btn btn-success">📄 Ver Documento</a>';
                    
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
            }
        } else {
            echo '<div class="status warning">';
            echo '⚠️ <strong>No hay solicitudes para probar.</strong><br><br>';
            echo '<div class="step">';
            echo '<strong>Para crear datos de prueba:</strong><br>';
            echo '1. Ve a <a href="' . admin_url('admin.php?page=ahr-nuevo-empleado') . '">Añadir Empleado</a><br>';
            echo '2. Crea un empleado de prueba<br>';
            echo '3. Ve a <a href="' . admin_url('admin.php?page=ahr-vacaciones') . '">Solicitar Vacaciones</a><br>';
            echo '4. Crea una solicitud de prueba<br>';
            echo '5. Vuelve aquí y recarga la página';
            echo '</div>';
            echo '</div>';
        }
        ?>

        <h2>🔍 Paso 4: Verificar Componentes del Sistema</h2>
        <?php
        $componentes = [
            'Plantilla HTML' => AHR_PATH . 'views/document-template.php',
            'Controlador PDF' => AHR_PATH . 'includes/admin-menu.php',
            'Clase DB' => AHR_PATH . 'includes/class-ahr-db.php',
            'Vista Dashboard' => AHR_PATH . 'views/dashboard-requests.php'
        ];

        echo '<table>';
        echo '<thead><tr><th>Componente</th><th>Estado</th><th>Ruta</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($componentes as $nombre => $ruta) {
            $existe = file_exists($ruta);
            $estado = $existe 
                ? '<span style="color: #46b450;">✅ Existe</span>' 
                : '<span style="color: #dc3232;">❌ No encontrado</span>';
            
            echo '<tr>';
            echo '<td><strong>' . $nombre . '</strong></td>';
            echo '<td>' . $estado . '</td>';
            echo '<td><code>' . str_replace(ABSPATH, '/', $ruta) . '</code></td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        ?>

        <h2>📋 Paso 5: Instrucciones de Prueba</h2>
        <div class="step">
            <strong>Para probar el sistema completo:</strong>
            <ol>
                <li>Haz clic en cualquier botón <strong>"📄 Ver Documento"</strong> de la tabla arriba</li>
                <li>Se abrirá una nueva pestaña con el documento generado</li>
                <li>Verifica que todos los datos se muestren correctamente:
                    <ul>
                        <li>Logo de la empresa</li>
                        <li>Nombre del empleado y RUT</li>
                        <li>Fechas de inicio y fin</li>
                        <li>Tipo de solicitud (Vacaciones/Licencia/Permiso)</li>
                        <li>Estado (APROBADO/RECHAZADO/PENDIENTE)</li>
                    </ul>
                </li>
                <li>Haz clic en el botón <strong>"🖨️ Descargar PDF"</strong></li>
                <li>Selecciona "Guardar como PDF" en el diálogo de impresión</li>
                <li>Verifica que el PDF se descargue correctamente</li>
            </ol>
        </div>

        <h2>🚀 Próximos Pasos</h2>
        <div class="status info">
            <strong>Una vez que verifiques que todo funciona:</strong><br><br>
            ✅ Podrás implementar el mismo sistema en <code>hr-management</code><br>
            ✅ Reutilizarás la plantilla HTML adaptándola a tus necesidades<br>
            ✅ Copiarás la estructura de controlador y SQL con JOIN<br>
            ✅ Mantendrás el mismo flujo de generación de documentos
        </div>

        <div style="margin-top: 30px; padding: 20px; background: #f6f7f7; border-radius: 5px;">
            <strong>Enlaces Útiles:</strong><br>
            <a href="<?php echo admin_url('admin.php?page=ahr-dashboard'); ?>" class="btn">📊 Dashboard Anaconda RRHH</a>
            <a href="<?php echo admin_url('admin.php?page=ahr-empleados'); ?>" class="btn">👥 Lista de Empleados</a>
            <a href="<?php echo admin_url('admin.php?page=ahr-vacaciones'); ?>" class="btn">📝 Solicitar Vacaciones</a>
            <a href="<?php echo admin_url('plugins.php'); ?>" class="btn btn-warning">🔌 Gestión de Plugins</a>
        </div>
    </div>
</body>
</html>
