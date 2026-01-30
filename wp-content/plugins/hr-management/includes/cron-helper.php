<?php
/**
 * =====================================================
 * HELPER DE CRON - UTILIDADES PARA SINCRONIZACIÓN
 * =====================================================
 * Proporciona funciones administrativas para manejar
 * el evento cron de sincronización de personal vigente.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ejecutar manualmente la sincronización de personal vigente.
 * Útil para pruebas o sincronización manual desde el admin.
 * 
 * Uso: En admin, añadir esto a la URL de cualquier página:
 * ?hrm_manual_sync=1
 */
add_action( 'admin_init', function() {
    // Permitir acceso a administradores, supervisores/gerentes y editores de vacaciones
    if ( ! current_user_can( 'manage_options' ) && 
         ! current_user_can( 'edit_hrm_employees' ) && 
         ! current_user_can( 'manage_hrm_vacaciones' ) ) {
        return;
    }
    
    // Verificar si se solicitó sincronización manual
    if ( ! empty( $_GET['hrm_manual_sync'] ) && $_GET['hrm_manual_sync'] == '1' ) {
        // Verificar nonce para seguridad
        $nonce = isset( $_GET['hrm_nonce'] ) ? sanitize_text_field( $_GET['hrm_nonce'] ) : '';
        
        if ( wp_verify_nonce( $nonce, 'hrm_manual_sync' ) ) {
            // Ejecutar la sincronización
            if ( ! function_exists( 'hrm_actualizar_personal_vigente_por_vacaciones' ) ) {
                require_once plugin_dir_path( __FILE__ ) . 'vacaciones.php';
            }
            
            $resultado = hrm_actualizar_personal_vigente_por_vacaciones();
            
            // Determinar a qué página redirigir
            // Primero, verificar si se pasó una página de retorno específica
            $return_page = isset( $_GET['hrm_return_page'] ) ? sanitize_text_field( $_GET['hrm_return_page'] ) : '';
            
            if ( ! empty( $return_page ) && in_array( $return_page, [ 'hrm-empleados', 'hrm-vacaciones', 'hrm-mi-perfil' ], true ) ) {
                // Usar la página de retorno especificada
                $redirect_page = $return_page;
            } else {
                // Determinar según el rol del usuario
                if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_hrm_employees' ) ) {
                    $redirect_page = 'hrm-empleados';
                }
                elseif ( current_user_can( 'manage_hrm_vacaciones' ) ) {
                    $redirect_page = 'hrm-vacaciones';
                }
                else {
                    $redirect_page = 'hrm-mi-perfil';
                }
            }
            
            // Redirigir con mensaje
            $redirect_url = admin_url( 'admin.php?page=' . $redirect_page . '&hrm_sync_msg=1' );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }
});

/**
 * Mostrar mensaje de confirmación después de sincronización manual.
 */
add_action( 'admin_notices', function() {
    // Permitir acceso a administradores, supervisores/gerentes y editores de vacaciones
    if ( ! current_user_can( 'manage_options' ) && 
         ! current_user_can( 'edit_hrm_employees' ) && 
         ! current_user_can( 'manage_hrm_vacaciones' ) ) {
        return;
    }
    
    if ( ! empty( $_GET['hrm_sync_msg'] ) && $_GET['hrm_sync_msg'] == '1' ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>✓ HR Management:</strong> Sincronización de personal vigente completada exitosamente.</p>';
        echo '</div>';
    }
});

/**
 * Agregar botón de sincronización manual en el dashboard de RRHH.
 * Se muestra en la página principal de Empleados.
 */
add_action( 'hrm_dashboard_actions', function() {
    // Permitir acceso a administradores, supervisores/gerentes y editores de vacaciones
    if ( ! current_user_can( 'manage_options' ) && 
         ! current_user_can( 'edit_hrm_employees' ) && 
         ! current_user_can( 'manage_hrm_vacaciones' ) ) {
        return;
    }
    
    // Generar URL de sincronización con nonce
    $sync_url = add_query_arg( [
        'hrm_manual_sync' => '1',
        'hrm_nonce' => wp_create_nonce( 'hrm_manual_sync' )
    ], admin_url( 'admin.php?page=hrm-empleados' ) );
    
    echo '<a href="' . esc_url( $sync_url ) . '" class="button button-secondary" title="Sincronizar personal vigente ahora">';
    echo '🔄 Sincronizar Personal Vigente';
    echo '</a>';
});

/**
 * Obtener información del próximo evento cron programado.
 * Útil para debugging.
 * 
 * @return array|false Array con información del evento o false si no existe
 */
function hrm_get_next_cron_sync() {
    $timestamp = wp_next_scheduled( 'hrm_daily_personal_vigente_sync' );
    
    if ( ! $timestamp ) {
        return false;
    }
    
    return [
        'timestamp' => $timestamp,
        'fecha_próxima' => date( 'Y-m-d H:i:s', $timestamp ),
        'diferencia_horas' => round( ( $timestamp - current_time( 'timestamp' ) ) / 3600, 1 )
    ];
}

/**
 * Obtener estadísticas de la última sincronización.
 * Busca en los logs de WordPress.
 * 
 * @return array Array con información de la última ejecución
 */
function hrm_get_last_sync_info() {
    // Buscar la última línea del log que contenga "HRM CRON:" leyendo solo las últimas KB para ahorrar memoria
    $debug_log = WP_CONTENT_DIR . '/debug.log';

    if ( ! file_exists( $debug_log ) || ! is_readable( $debug_log ) ) {
        return [
            'existente' => false,
            'mensaje' => 'No se encontró archivo de log'
        ];
    }

    // Leer sólo las últimas N bytes (por defecto 64KB) para evitar cargar todo el log en memoria
    $read_bytes = apply_filters( 'hrm_log_read_bytes', 65536 ); // 64KB

    $fp = @fopen( $debug_log, 'r' );
    if ( ! $fp ) {
        return [
            'existente' => false,
            'mensaje' => 'No se pudo abrir el log para lectura'
        ];
    }

    $stat = fstat( $fp );
    $filesize = isset( $stat['size'] ) ? $stat['size'] : 0;
    $start = $filesize > $read_bytes ? $filesize - $read_bytes : 0;
    fseek( $fp, $start );
    $data = stream_get_contents( $fp );
    fclose( $fp );

    if ( $data === '' ) {
        return [
            'existente' => false,
            'mensaje' => 'El archivo de log está vacío'
        ];
    }

    $lines = preg_split( "/\r\n|\n|\r/", $data );
    // Si empezamos en medio de una línea, descartar la primera línea parcial
    if ( $start !== 0 ) {
        array_shift( $lines );
    }

    // Buscar la última línea que contenga "HRM CRON:"
    $last_sync = null;
    for ( $i = count( $lines ) - 1; $i >= 0; $i-- ) {
        if ( strpos( $lines[ $i ], 'HRM CRON:' ) !== false ) {
            $last_sync = $lines[ $i ];
            break;
        }
    }

    // Fallback: si no se encontró en los últimos KB, intentar con file() (safe fallback, pero más costoso)
    if ( ! $last_sync ) {
        $full = @file( $debug_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        if ( ! empty( $full ) ) {
            foreach ( array_reverse( $full ) as $line ) {
                if ( strpos( $line, 'HRM CRON:' ) !== false ) {
                    $last_sync = $line;
                    break;
                }
            }
        }
    }

    if ( ! $last_sync ) {
        return [
            'existente' => false,
            'mensaje' => 'No hay registro de sincronizaciones aún'
        ];
    }

    return [
        'existente' => true,
        'ultima_ejecucion' => $last_sync
    ];
}
