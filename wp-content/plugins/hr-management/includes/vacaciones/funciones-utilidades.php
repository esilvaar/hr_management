<?php
/**
 * Funciones Utilidades para Vacaciones
 * 
 * Contiene funciones auxiliares, helpers y utilidades
 * compartidas entre empleados, gerentes y administradores.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * =====================================================
 * TIPOS DE AUSENCIA DEFINIDOS
 * =====================================================
 * Retorna un arreglo asociativo con los tipos de ausencia
 * permitidos en el sistema.
 */
function hrm_get_tipos_ausencia_definidos() {
    return [
        3 => 'Vacaciones',
        4 => 'Permiso',
        5 => 'Licencia Médica'
    ];
}

/**
 * =====================================================
 * OBTENER DATOS DE EMPLEADO
 * =====================================================
 * Obtiene información del empleado desde la tabla de empleados
 */
function hrm_obtener_datos_empleado( $id_empleado = null ) {
    global $wpdb;
    
    // Si no se proporciona ID, obtener del usuario actual
    if ( ! $id_empleado ) {
        $current_user_id = get_current_user_id();
        $table_empleados = $wpdb->prefix . 'rrhh_empleados';
        
        $id_empleado = $wpdb->get_var( $wpdb->prepare(
            "SELECT id_empleado FROM {$table_empleados} WHERE user_id = %d",
            $current_user_id
        ) );
    }
    
    if ( ! $id_empleado ) {
        return null;
    }
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT id_empleado, nombre, apellido, rut, puesto, departamento, correo, telefono, estado
         FROM {$table_empleados}
         WHERE id_empleado = %d",
        $id_empleado
    ) );
}

/**
 * =====================================================
 * OBTENER GERENTE A CARGO DEL DEPARTAMENTO
 * =====================================================
 * Obtiene el nombre, email y datos del gerente que
 * tiene a cargo el departamento del empleado.
 */
function hrm_obtener_gerente_departamento( $id_empleado ) {
    global $wpdb;
    
    if ( ! $id_empleado ) {
        return null;
    }
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_gerencia = $wpdb->prefix . 'rrhh_gerencia_deptos';
    
    // 1. Obtener departamento del empleado
    $departamento_empleado = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT departamento FROM {$table_empleados} WHERE id_empleado = %d",
            $id_empleado
        )
    );
    
    if ( ! $departamento_empleado ) {
        error_log( "HRM: El empleado {$id_empleado} no tiene departamento asignado" );
        return null;
    }
    
    // 2. Obtener gerente que tiene a cargo ese departamento
    $gerente = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT nombre_gerente, correo_gerente, area_gerencial 
             FROM {$table_gerencia}
             WHERE depto_a_cargo = %s
             AND estado = 1
             LIMIT 1",
            $departamento_empleado
        ),
        ARRAY_A
    );
    
    if ( ! $gerente ) {
        error_log( "HRM: No se encontró gerente para el departamento: {$departamento_empleado}" );
        return null;
    }
    
    // 3. Validar que el gerente tiene correo
    if ( empty( $gerente['correo_gerente'] ) ) {
        error_log( "HRM: El gerente del departamento {$departamento_empleado} no tiene correo registrado" );
        return null;
    }
    
    error_log( "HRM: Gerente encontrado para departamento {$departamento_empleado}: {$gerente['nombre_gerente']} ({$gerente['correo_gerente']})" );
    
    return $gerente;
}

/**
 * =====================================================
 * DÍAS FERIADOS
 * =====================================================
 * Obtiene los feriados de una fuente externa (URL) con fallback local.
 */
function hrm_get_feriados( $ano = null ) {
    if ( ! $ano ) {
        $ano = (int) date( 'Y' );
    }

    // Intentar obtener desde fuente remota
    $feriados = hrm_obtener_feriados_desde_url( $ano );
    
    // Si no hay conexión o falla, usar feriados locales
    if ( empty( $feriados ) ) {
        $feriados = hrm_get_feriados_locales( $ano );
    }

    // Permitir agregar feriados personalizados mediante filtro
    $feriados = apply_filters( 'hrm_feriados_custom', $feriados, $ano );

    // Ordenar por fecha
    ksort( $feriados );

    return $feriados;
}

/**
 * Obtiene feriados desde una URL remota con caché de 30 días
 */
function hrm_obtener_feriados_desde_url( $ano ) {
    $cache_key = 'hrm_feriados_remote_' . intval( $ano );
    
    // Intentar obtener del caché
    $cached = wp_cache_get( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }

    // URLs alternativas de APIs de feriados
    $urls_alternativas = [
        'https://www.feriados-chile.cl/api/feriados/' . $ano,
    ];

    // Permitir personalizar las URLs mediante filtro
    $urls_alternativas = apply_filters( 'hrm_feriados_urls_alternativas', $urls_alternativas, $ano );

    foreach ( $urls_alternativas as $url_feriados ) {
        try {
            // Realizar petición remota con timeout extendido
            $response = wp_remote_get( $url_feriados, [
                'timeout'   => 15,
                'sslverify' => true,
                'user-agent' => 'WordPress HRM Plugin'
            ] );

            // Verificar errores de conexión
            if ( is_wp_error( $response ) ) {
                error_log( 'HRM: Error con URL ' . $url_feriados . ': ' . $response->get_error_message() );
                continue;
            }

            $body = wp_remote_retrieve_body( $response );
            $code = wp_remote_retrieve_response_code( $response );

            // Verificar código HTTP
            if ( ! in_array( $code, [ 200, 301, 302 ] ) ) {
                error_log( "HRM: Error HTTP $code en URL $url_feriados" );
                continue;
            }

            // Parsear JSON
            $data = json_decode( $body, true );
            
            if ( ! is_array( $data ) || empty( $data ) ) {
                error_log( 'HRM: Respuesta JSON inválida o vacía de ' . $url_feriados );
                continue;
            }

            // Guardar en caché durante 30 días
            wp_cache_set( $cache_key, $data, '', 30 * DAY_IN_SECONDS );
            
            error_log( "HRM: Feriados obtenidos exitosamente desde $url_feriados para año $ano" );
            return $data;

        } catch ( Exception $e ) {
            error_log( 'HRM: Excepción con URL ' . $url_feriados . ': ' . $e->getMessage() );
            continue;
        }
    }

    error_log( "HRM: No se pudieron obtener feriados desde URLs remotas para año $ano. Usando fallback local." );
    return [];
}

/**
 * Feriados locales (fallback si URL no funciona)
 */
function hrm_get_feriados_locales( $ano ) {
    // Función auxiliar para ajustar feriados que caen en domingo
    $ajustar_feriado = function( $fecha_str, $nombre ) {
        $fecha = new DateTime( $fecha_str );
        // Si cae en domingo (7), trasladar al lunes siguiente
        if ( $fecha->format( 'N' ) == 7 ) {
            $fecha->modify( '+1 day' );
        }
        return [ $fecha->format( 'Y-m-d' ) => $nombre ];
    };

    $feriados = [];

    // Feriados fijos
    $feriados_fijos = [
        $ano . '-01-01' => 'Año Nuevo',
        $ano . '-05-01' => 'Día del Trabajo',
        $ano . '-07-16' => 'Virgen del Carmen',
        $ano . '-08-15' => 'Asunción de María',
        $ano . '-10-12' => 'Descubrimiento de América',
        $ano . '-12-25' => 'Navidad',
    ];

    foreach ( $feriados_fijos as $fecha => $nombre ) {
        $feriados = array_merge( $feriados, $ajustar_feriado( $fecha, $nombre ) );
    }

    // Feriados irrevocables
    $feriados_irrevocables = [
        $ano . '-04-04' => 'Día de Protestas',
        $ano . '-05-21' => 'Día de la Armada',
        $ano . '-06-21' => 'Solsticio de Invierno',
        $ano . '-06-29' => 'San Pedro y San Pablo',
        $ano . '-09-18' => 'Independencia Nacional',
        $ano . '-09-19' => 'Glorias del Ejército',
        $ano . '-10-31' => 'Conmemoración Halloween',
        $ano . '-11-01' => 'Todos los Santos',
        $ano . '-12-08' => 'Inmaculada Concepción',
    ];

    $feriados = array_merge( $feriados, $feriados_irrevocables );

    // Viernes Santo (Pascua - 2 días)
    $easter = easter_date( $ano );
    $viernes_santo = date( 'Y-m-d', $easter - 86400 * 2 );
    $feriados[ $viernes_santo ] = 'Viernes Santo';

    return $feriados;
}

/**
 * =====================================================
 * OBTENER TOTAL DE EMPLEADOS DE UN DEPARTAMENTO
 * =====================================================
 */
function hrm_get_total_empleados_departamento( $nombre_departamento ) {
    global $wpdb;
    
    $cache_key = 'hrm_total_empleados_' . sanitize_key( $nombre_departamento );
    
    $cached = wp_cache_get( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }
    
    $table_departamentos = $wpdb->prefix . 'rrhh_departamentos';
    
    $total = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT total_empleados FROM {$table_departamentos} WHERE nombre_departamento = %s",
            $nombre_departamento
        )
    );
    
    wp_cache_set( $cache_key, $total, '', 3600 );
    
    return $total;
}

/**
 * =====================================================
 * OBTENER SALDO DE VACACIONES DE EMPLEADO
 * =====================================================
 */
function hrm_get_saldo_vacaciones( $id_empleado ) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'rrhh_empleados';
    
    $saldo = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT dias_vacaciones_disponibles FROM {$table} WHERE id_empleado = %d",
            $id_empleado
        )
    );
    
    return $saldo ? (int) $saldo : 0;
}

/**
 * =====================================================
 * OBTENER DOCUMENTOS DE UNA SOLICITUD
 * =====================================================
 */
function hrm_get_documentos_por_solicitud( $id_solicitud ) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'rrhh_documentos_ausencia';
    
    $documentos = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id_solicitud = %d ORDER BY fecha_creacion DESC",
            $id_solicitud
        ),
        ARRAY_A
    );
    
    return $documentos ?: [];
}

/**
 * =====================================================
 * OBTENER DÍAS DE UNA SOLICITUD
 * =====================================================
 */
function hrm_get_dias_solicitud( $id_solicitud ) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    
    $solicitud = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT total_dias, fecha_inicio, fecha_fin FROM {$table} WHERE id_solicitud = %d",
            $id_solicitud
        )
    );
    
    return $solicitud ?: null;
}
