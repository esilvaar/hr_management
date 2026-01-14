<?php
/**
 * =====================================================
 * SEGURIDAD BÁSICA
 * =====================================================
 * Evita la ejecución directa del archivo fuera
 * del contexto de WordPress.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =====================================================
 * EVENTO CRON: SINCRONIZACIÓN DIARIA DE PERSONAL VIGENTE
 * =====================================================
 * Ejecuta automáticamente cada día la función
 * hrm_actualizar_personal_vigente_por_vacaciones()
 * para mantener actualizado el personal disponible.
 */

/**
 * Registrar el evento cron diario si no existe.
 * Se ejecuta al activar el plugin.
 */
function hrm_schedule_daily_personal_vigente_sync() {
    if ( ! wp_next_scheduled( 'hrm_daily_personal_vigente_sync' ) ) {
        wp_schedule_event( time(), 'daily', 'hrm_daily_personal_vigente_sync' );
        error_log( 'HRM: Evento cron diario registrado - hrm_daily_personal_vigente_sync' );
    }
}

/**
 * Hook que ejecuta la sincronización de personal vigente.
 * Se dispara según la programación del evento cron.
 */
add_action( 'hrm_daily_personal_vigente_sync', function() {
    // Cargar el archivo de vacaciones si no está cargado
    if ( ! function_exists( 'hrm_actualizar_personal_vigente_por_vacaciones' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'vacaciones.php';
    }
    
    // Ejecutar la función de sincronización
    $resultado = hrm_actualizar_personal_vigente_por_vacaciones();
    
    // Log del resultado
    if ( $resultado['exitoso'] ) {
        error_log( 'HRM CRON: Sincronización de personal vigente completada exitosamente. Departamentos actualizados: ' . $resultado['departamentos_actualizados'] );
    } else {
        error_log( 'HRM CRON ERROR: Sincronización de personal vigente falló. Errores: ' . implode( ', ', $resultado['errores'] ) );
    }
} );

/* =====================================================
 * TIPOS DE AUSENCIA DEFINIDOS (FIJOS)
 * =====================================================
 * Retorna un arreglo asociativo con los tipos de ausencia
 * permitidos en el sistema.
 *
 * - La clave corresponde al ID del tipo (id_tipo en BD)
 * - El valor corresponde al nombre visible
 *
 * Esto evita que el usuario envíe tipos no autorizados.
 */
function hrm_get_tipos_ausencia_definidos() {
    return [
        3 => 'Vacaciones'
    ];
}

/**
 * =====================================================
 * OBTENER DATOS DE EMPLEADO (RUT, PUESTO, etc)
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

/* =====================================================
 * OBTENER GERENTE A CARGO DEL DEPARTAMENTO
 * =====================================================
 * Obtiene el nombre, email y datos del gerente que
 * tiene a cargo el departamento del empleado.
 * 
 * Verifica que el departamento del empleado coincida
 * con el departamento que tiene a cargo el gerente en
 * la tabla gerencia_deptos.
 * 
 * NOTA ESPECIAL: Si el empleado es del departamento "Gerencia"
 * (es decir, es un gerente), se envía la solicitud al Gerente de Operaciones.
 * El Gerente de Operaciones se identifica por tener area_gerencia = 'Operaciones'
 * en la tabla Bu6K9_rrhh_empleados.
 *
 * @param int $id_empleado ID del empleado
 * @return array|null Datos del gerente (nombre, correo_gerente, area_gerencial) o null si no existe
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
    
    // CASO ESPECIAL: Si el empleado es gerente (departamento = "Gerencia")
    // Enviar solicitud al Gerente de Operaciones (area_gerencia = 'Operaciones')
    if ( $departamento_empleado === 'Gerencia' ) {
        $gerente_operaciones = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id_empleado, CONCAT(nombre, ' ', apellido) as nombre_gerente, correo as correo_gerente
                 FROM {$table_empleados}
                 WHERE departamento = %s
                 AND area_gerencia = %s
                 AND estado = 1
                 LIMIT 1",
                'Gerencia',
                'Operaciones'
            ),
            ARRAY_A
        );
        
        if ( $gerente_operaciones && ! empty( $gerente_operaciones['correo_gerente'] ) ) {
            error_log( "HRM: Solicitud de gerente redirigida a Gerente de Operaciones: {$gerente_operaciones['nombre_gerente']} ({$gerente_operaciones['correo_gerente']})" );
            return $gerente_operaciones;
        } else {
            error_log( "HRM: No se encontró Gerente de Operaciones (area_gerencia='Operaciones') para enviar solicitud de gerente" );
            return null;
        }
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

/* =====================================================
 * DÍAS FERIADOS (DESDE URL O LOCAL)
 * =====================================================
 * Obtiene los feriados de una fuente externa (URL) con fallback local.
 * 
 * La URL debe retornar JSON con formato:
 * { "YYYY-MM-DD": "Nombre del feriado" }
 *
 * Se almacena en caché por 30 días para optimizar rendimiento.
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

    // URLs alternativas de APIs de feriados (verificadas como disponibles)
    $urls_alternativas = [
        'https://www.feriados-chile.cl/api/feriados/' . $ano, // API principal - FUNCIONA
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
                continue; // Intentar siguiente URL
            }

            $body = wp_remote_retrieve_body( $response );
            $code = wp_remote_retrieve_response_code( $response );

            // Verificar código HTTP (200 o 301/302 redirect)
            if ( ! in_array( $code, [ 200, 301, 302 ] ) ) {
                error_log( "HRM: Error HTTP $code en URL $url_feriados" );
                continue; // Intentar siguiente URL
            }

            // Parsear JSON
            $data = json_decode( $body, true );
            
            if ( ! is_array( $data ) || empty( $data ) ) {
                error_log( 'HRM: Respuesta JSON inválida o vacía de ' . $url_feriados );
                continue; // Intentar siguiente URL
            }

            // Guardar en caché durante 30 días
            wp_cache_set( $cache_key, $data, '', 30 * DAY_IN_SECONDS );
            
            error_log( "HRM: Feriados obtenidos exitosamente desde $url_feriados para año $ano" );
            return $data;

        } catch ( Exception $e ) {
            error_log( 'HRM: Excepción con URL ' . $url_feriados . ': ' . $e->getMessage() );
            continue; // Intentar siguiente URL
        }
    }

    // Si todas las URLs fallan, registrar en log
    error_log( "HRM: No se pudieron obtener feriados desde URLs remotas para año $ano. Usando fallback local." );
    return [];
}

/**
 * Feriados locales (fallback si URL no funciona)
 * Se usan como respaldo cuando no hay conexión
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

    // Feriados fijos (se ajustan si caen en domingo)
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

    // Feriados que NO se ajustan por día de semana (son irrevocables)
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

    // Calcular Viernes Santo (Pascua - 2 días) - FERIADO MOVIBLE
    $easter = easter_date( $ano );
    $viernes_santo = date( 'Y-m-d', $easter - 86400 * 2 );
    $feriados[ $viernes_santo ] = 'Viernes Santo';

    return $feriados;
}

/* =====================================================
 * OBTENER TODAS LAS SOLICITUDES (ADMIN)
 * =====================================================
 * Devuelve todas las solicitudes de ausencia del sistema.
 *
 * Uso típico:
 * - Panel administrativo
 * - Vista RRHH
 *
 * Permite búsqueda por nombre o apellido.
 * 
 * IMPORTANTE: Si el usuario actual es un gerente de departamento,
 * solo se mostrarán las solicitudes de empleados del departamento
 * que tiene a cargo. Los administradores verán todas las solicitudes.
 */function hrm_get_all_vacaciones( $search = '', $estado = '' ) {
    global $wpdb;

    // Generar cache key incluyendo estado y usuario actual
    $current_user_id = get_current_user_id();
    $cache_key = 'hrm_all_vacaciones_' . md5( $search . $estado . $current_user_id );
    
    $cached = wp_cache_get( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }

    // Tablas con prefijo dinámico
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_tipos = $wpdb->prefix . 'rrhh_tipo_ausencia';
    $table_gerencia = $wpdb->prefix . 'rrhh_gerencia_deptos';

    // CONSTRUIR WHERE DINÁMICAMENTE
    $where_conditions = array();
    $params = array();

    // VERIFICAR SI EL USUARIO ACTUAL ES UN ADMINISTRADOR O EDITOR DE VACACIONES
    $is_admin = current_user_can( 'manage_options' );
    $is_editor_vacaciones = current_user_can( 'manage_hrm_vacaciones' ) && ! current_user_can( 'edit_hrm_employees' );
    
    // Si es administrador o editor de vacaciones, mostrar todas las solicitudes (sin filtro)
    if ( ! $is_admin && ! $is_editor_vacaciones ) {
        // VERIFICAR SI EL USUARIO ACTUAL ES UN GERENTE DE DEPARTAMENTO
        $current_user = get_userdata( $current_user_id );
        $current_user_email = $current_user ? $current_user->user_email : '';
        
        $depto_gerente = null;
        $departamentos_a_gestionar = array();
        
        if ( $current_user_email ) {
            // Buscar en gerencia_deptos todos los departamentos que gestiona
            $deptos_result = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT depto_a_cargo FROM {$table_gerencia} 
                     WHERE correo_gerente = %s
                     AND estado = 1",
                    $current_user_email
                )
            );
            if ( ! empty( $deptos_result ) ) {
                $departamentos_a_gestionar = $deptos_result;
                $depto_gerente = $deptos_result[0]; // Para el log
            }
        }

        // Obtener su id_empleado
        $id_empleado_user = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id_empleado FROM {$table_empleados} WHERE user_id = %d LIMIT 1",
                $current_user_id
            )
        );

        // Si es gerente de departamento(s) O si es empleado, mostrar:
        // 1. Solicitudes de sus departamentos a gestionar
        // 2. Sus propias solicitudes
        if ( ! empty( $departamentos_a_gestionar ) || $id_empleado_user ) {
            // Construir WHERE con OR: (depto_a_cargo) OR (propias solicitudes)
            $where_or = array();
            
            // Opción 1: Solicitudes de sus departamentos
            if ( ! empty( $departamentos_a_gestionar ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $departamentos_a_gestionar ), '%s' ) );
                $where_or[] = "(e.departamento IN ({$placeholders}))";
                foreach ( $departamentos_a_gestionar as $depto ) {
                    $params[] = $depto;
                }
                error_log( "HRM: Usuario {$current_user_id} ({$current_user_email}) es gerente de departamentos: " . implode( ', ', $departamentos_a_gestionar ) );
            }
            
            // Opción 2: Sus propias solicitudes
            if ( $id_empleado_user ) {
                $where_or[] = "(s.id_empleado = %d)";
                $params[] = $id_empleado_user;
            }
            
            // Combinar con OR
            if ( ! empty( $where_or ) ) {
                $where_conditions[] = "( " . implode( ' OR ', $where_or ) . " )";
                error_log( "HRM: Usuario {$current_user_id} ve solicitudes de sus departamentos O sus propias solicitudes" );
            }
        } else {
            error_log( "HRM: Usuario {$current_user_id} no es gerente ni empleado. No mostrando solicitudes." );
            // No es gerente ni empleado, no mostrar nada
            $where_conditions[] = "1=0";
        }
    } else {
        error_log( "HRM: Usuario {$current_user_id} es administrador o editor de vacaciones. Mostrando todas las solicitudes." );
    }

    // FILTRO POR ESTADO
    if ( ! empty( $estado ) && in_array( $estado, array( 'PENDIENTE', 'APROBADA', 'RECHAZADA' ) ) ) {
        $where_conditions[] = "s.estado = %s";
        $params[] = $estado;
    } else {
        // Si no se especifica estado, mostrar todos (no filtrar por estado)
        // $where_conditions[] = "s.estado IN ('PENDIENTE', 'APROBADA', 'RECHAZADA')";
    }

    // FILTRO DE BÚSQUEDA POR NOMBRE
    if ( ! empty( $search ) ) {
        $where_conditions[] = "(e.nombre LIKE %s OR e.apellido LIKE %s)";
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $params[] = $like;
        $params[] = $like;
    }

    // CONSTRUIR CLAÚSULA WHERE
    $where = '';
    if ( ! empty( $where_conditions ) ) {
        $where = 'WHERE ' . implode( ' AND ', $where_conditions );
    }

    // CONSULTA PRINCIPAL
    $sql_base = "
        SELECT 
            s.id_solicitud,
            e.nombre,
            e.apellido,
            e.correo,
            t.nombre AS tipo,
            s.fecha_inicio,
            s.fecha_fin,
            s.total_dias,
            s.estado,
            s.comentario_empleado,
            e.departamento
        FROM {$table_solicitudes} s
        JOIN {$table_empleados} e ON s.id_empleado = e.id_empleado
        JOIN {$table_tipos} t ON s.id_tipo = t.id_tipo
        {$where}
        ORDER BY s.fecha_inicio DESC
    ";

    // PREPARAR CONSULTA
    if ( ! empty( $params ) ) {
        $sql = $wpdb->prepare( $sql_base, $params );
    } else {
        $sql = $sql_base;
    }

    $results = $wpdb->get_results( $sql, ARRAY_A );
    
    // Guardar en caché
    wp_cache_set( $cache_key, $results, '', 3600 );

    return $results;
}
/* =====================================================
 * OBTENER SOLICITUDES DEL EMPLEADO
 * =====================================================
 * Retorna únicamente las solicitudes asociadas
 * al usuario autenticado.
 *
 * Uso típico:
 * - Portal del empleado
 */
function hrm_get_vacaciones_empleado( $user_id ) {

    global $wpdb;

    // Generar cache key
    $cache_key = 'hrm_vacaciones_empleado_' . intval( $user_id );
    
    // Intentar obtener del caché
    $cached = wp_cache_get( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }

    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_tipos = $wpdb->prefix . 'rrhh_tipo_ausencia';

    $results = $wpdb->get_results(
        $wpdb->prepare("
            SELECT 
                s.id_solicitud,
                t.nombre AS tipo,
                s.fecha_inicio,
                s.fecha_fin,
                s.total_dias,
                s.estado,
                s.comentario_empleado
            FROM {$table_solicitudes} s
            JOIN {$table_empleados} e ON s.id_empleado = e.id_empleado
            JOIN {$table_tipos} t ON s.id_tipo = t.id_tipo
            WHERE e.user_id = %d
            ORDER BY s.fecha_inicio DESC
        ", $user_id),
        ARRAY_A
    );
    
    // Guardar en caché
    wp_cache_set( $cache_key, $results, '', HRM_CACHE_TIMEOUT );

    return $results;
}

/* =====================================================
 * CREAR SOLICITUD DE VACACIONES (HANDLER)
 * =====================================================
 * Procesa el formulario enviado por el empleado.
 * Incluye:
 * - Inserción de solicitud
 * - Subida de archivo (opcional)
 * - Registro documental
 */
function hrm_enviar_vacaciones_handler() {

    // Log de entrada
    error_log('=== HRM: Inicio de hrm_enviar_vacaciones_handler ===');
    error_log('POST data: ' . print_r($_POST, true));

    // Verificar que el usuario esté logueado
    if ( ! is_user_logged_in() ) {
        error_log('HRM: Usuario no logueado');
        wp_die( 'Debes iniciar sesión para enviar una solicitud.' );
    }

    // Verificar nonce
    if ( ! isset( $_POST['hrm_nonce'] ) || ! wp_verify_nonce( $_POST['hrm_nonce'], 'hrm_solicitud_vacaciones' ) ) {
        error_log('HRM: Fallo de verificación nonce');
        wp_die( 'Error de seguridad. Por favor, intenta de nuevo.' );
    }

    error_log('HRM: Verificaciones iniciales pasadas');

    global $wpdb;

    // Tablas con prefijo dinámico
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';

    // Usuario actual
    $user_id = get_current_user_id();
    error_log('HRM: User ID: ' . $user_id);

    // Obtener id_empleado asociado
    $id_empleado = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id_empleado FROM {$table_empleados} WHERE user_id = %d",
            $user_id
        )
    );

    error_log('HRM: ID Empleado: ' . ($id_empleado ? $id_empleado : 'NULL'));

    if ( ! $id_empleado ) {
        wp_die( 'No se encontró empleado vinculado al usuario.' );
    }

    // Datos del formulario
    $id_tipo      = isset( $_POST['id_tipo'] ) ? intval( $_POST['id_tipo'] ) : 0;
    $fecha_inicio = isset( $_POST['fecha_inicio'] ) ? sanitize_text_field( $_POST['fecha_inicio'] ) : '';
    $fecha_fin    = isset( $_POST['fecha_fin'] ) ? sanitize_text_field( $_POST['fecha_fin'] ) : '';
    $descripcion  = isset( $_POST['descripcion'] ) ? sanitize_textarea_field( $_POST['descripcion'] ) : '';




// ==================================
// VALIDACIÓN DE FECHAS (BACKEND)
// ==================================

$hoy = current_time( 'Y-m-d' );

// Fechas obligatorias
if ( empty( $fecha_inicio ) || empty( $fecha_fin ) ) {
    wp_die( 'Debes seleccionar fecha de inicio y término.' );
}

// Formato válido
$inicio = DateTime::createFromFormat( 'Y-m-d', $fecha_inicio );
$fin    = DateTime::createFromFormat( 'Y-m-d', $fecha_fin );

if ( ! $inicio || ! $fin ) {
    wp_die( 'Formato de fecha inválido.' );
}

// Calcular la fecha mínima: un mes después de hoy
$hoy_datetime = DateTime::createFromFormat( 'Y-m-d', $hoy );
$fecha_minima = clone $hoy_datetime;
$fecha_minima->modify( '+1 month' );
$fecha_minima_str = $fecha_minima->format( 'Y-m-d' );

// No permitir fechas antes de un mes
if ( $fecha_inicio < $fecha_minima_str ) {
    wp_die( 'La fecha de inicio debe ser al menos un mes después de hoy (' . $fecha_minima_str . ').' );
}

// Fin no puede ser menor al inicio
if ( $fecha_fin < $fecha_inicio ) {
    wp_die( 'La fecha de término no puede ser anterior a la fecha de inicio.' );
}

// Validar que las fechas no sean fin de semana (sábado=6, domingo=7)
$dia_inicio = $inicio->format( 'N' );
$dia_fin = $fin->format( 'N' );

if ( $dia_inicio >= 6 ) {
    wp_die( 'La fecha de inicio no puede ser un fin de semana.' );
}

if ( $dia_fin >= 6 ) {
    wp_die( 'La fecha de término no puede ser un fin de semana.' );
}

// Validar que las fechas no sean feriados
$feriados = hrm_get_feriados( (int) $inicio->format('Y') );
if ( isset( $feriados[ $fecha_inicio ] ) ) {
    wp_die( 'La fecha de inicio no puede ser un día feriado.' );
}

if ( isset( $feriados[ $fecha_fin ] ) ) {
    wp_die( 'La fecha de término no puede ser un día feriado.' );
}



// ================================
// CALCULAR TOTAL DE DÍAS SOLICITADOS
// ================================

if ( empty( $fecha_inicio ) || empty( $fecha_fin ) ) {
    wp_die( 'Fechas inválidas.' );
}

$total_dias = hrm_calcular_dias_habiles(
    $fecha_inicio,
    $fecha_fin
);

if ( $total_dias <= 0 ) {
    wp_die( 'El rango de fechas no contiene días hábiles.' );
}

    // Inserción de la solicitud
    error_log('HRM: Intentando insertar solicitud');
    error_log('HRM: Datos - ID empleado: ' . $id_empleado . ', ID tipo: ' . $id_tipo . ', Fechas: ' . $fecha_inicio . ' - ' . $fecha_fin . ', Días: ' . $total_dias);
    
    $inserted = $wpdb->insert(
        $table_solicitudes,
        [
            'id_empleado'         => $id_empleado,
            'id_tipo'             => $id_tipo,
            'fecha_inicio'        => $fecha_inicio,
            'fecha_fin'           => $fecha_fin,
            'total_dias' => $total_dias,
            'estado'              => 'PENDIENTE',
            'comentario_empleado' => $descripcion
        ],
        [ '%d', '%d', '%s', '%s', '%d', '%s', '%s' ]
    );

    error_log('HRM: Resultado de inserción: ' . ($inserted ? 'SUCCESS' : 'FAILED'));
    if (!$inserted) {
        error_log('HRM: Error de wpdb: ' . $wpdb->last_error);
    }

    if ( ! $inserted ) {
        wp_die( 'Error al guardar la solicitud. Intenta de nuevo.' );
    }

    /* =====================================================
     * ENVÍO DE NOTIFICACIÓN AL GERENTE (ESPECÍFICO DEL DEPARTAMENTO)
     * Y AL EDITOR DE VACACIONES
     * ===================================================== */
    
    // Obtener datos del empleado para el email
    $empleado = hrm_obtener_datos_empleado( $id_empleado );
    
    if ( $empleado ) {
        // Obtener el gerente a cargo del departamento del empleado
        // NOTA: Si el empleado es gerente, se obtiene al Gerente de Operaciones
        $gerente = hrm_obtener_gerente_departamento( $id_empleado );
        
        // Obtener TODOS los editores de vacaciones (usuarios con rol editor_vacaciones)
        $editores_vacaciones_emails = array();
        
        // Buscar todos los usuarios con rol editor_vacaciones directamente en la BD
        $editores_result = $wpdb->get_col(
            "SELECT DISTINCT user_email FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
             WHERE um.meta_key = '{$wpdb->prefix}capabilities'
             AND um.meta_value LIKE '%editor_vacaciones%'
             ORDER BY u.ID"
        );
        
        if ( ! empty( $editores_result ) ) {
            $editores_vacaciones_emails = $editores_result;
        } else {
            // Fallback: buscar si no se encuentra por meta_key
            $users_editor = get_users( array(
                'role' => 'editor_vacaciones'
            ) );
            
            if ( ! empty( $users_editor ) ) {
                foreach ( $users_editor as $user ) {
                    $editores_vacaciones_emails[] = $user->user_email;
                }
            }
        }
        
        error_log( "HRM: Buscando editores de vacaciones. Encontrados: " . count( $editores_vacaciones_emails ) . " - " . implode( ', ', $editores_vacaciones_emails ) );
        
        // Obtener tipo de ausencia
        $tipos_ausencia = hrm_get_tipos_ausencia_definidos();
        $tipo_nombre = isset( $tipos_ausencia[ $id_tipo ] ) ? $tipos_ausencia[ $id_tipo ] : 'Ausencia';
        
        // Construir mensaje de email (reutilizable)
        $asunto = "Nueva solicitud de {$tipo_nombre} - {$empleado->nombre} {$empleado->apellido}";
        
        $mensaje = "
            <h2>Nueva Solicitud de {$tipo_nombre}</h2>
            <p>Un empleado de tu departamento ha enviado una nueva solicitud.</p>
            
            <h3>Datos del Empleado:</h3>
            <ul>
                <li><strong>Nombre:</strong> {$empleado->nombre} {$empleado->apellido}</li>
                <li><strong>RUT:</strong> {$empleado->rut}</li>
                <li><strong>Departamento:</strong> {$empleado->departamento}</li>
                <li><strong>Correo:</strong> {$empleado->correo}</li>
            </ul>
            
            <h3>Solicitud:</h3>
            <ul>
                <li><strong>Tipo:</strong> {$tipo_nombre}</li>
                <li><strong>Fecha Inicio:</strong> {$fecha_inicio}</li>
                <li><strong>Fecha Fin:</strong> {$fecha_fin}</li>
                <li><strong>Total Días:</strong> {$total_dias}</li>
                <li><strong>Estado:</strong> PENDIENTE DE APROBACIÓN</li>
            </ul>
            
            " . ( ! empty( $descripcion ) ? "<h3>Descripción:</h3><p>{$descripcion}</p>" : '' ) . "
            
            <p><a href='" . admin_url( 'admin.php?page=hr-management-vacaciones' ) . "'>Revisar solicitud en el sistema</a></p>
        ";
        
        // Configurar headers para HTML
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        
        // Construir lista de destinatarios
        $destinatarios = array();
        
        error_log( "HRM: Gerente encontrado: " . ( $gerente ? json_encode( $gerente ) : "NO" ) );
        error_log( "HRM: Editores de vacaciones encontrados: " . count( $editores_vacaciones_emails ) );
        
        // Agregar gerente si existe
        if ( $gerente && ! empty( $gerente['correo_gerente'] ) ) {
            $destinatarios[] = array(
                'email' => $gerente['correo_gerente'],
                'nombre' => $gerente['nombre_gerente'] ?? 'Gerente'
            );
        }
        
        // Agregar TODOS los editores de vacaciones si existen y son diferentes al gerente
        if ( ! empty( $editores_vacaciones_emails ) ) {
            foreach ( $editores_vacaciones_emails as $editor_email ) {
                // Evitar duplicados con el gerente
                if ( empty( $gerente ) || $editor_email !== $gerente['correo_gerente'] ) {
                    $destinatarios[] = array(
                        'email' => $editor_email,
                        'nombre' => 'Editor Vacaciones'
                    );
                }
            }
        }
        
        // Enviar email a todos los destinatarios
        error_log( "HRM: Total de destinatarios: " . count( $destinatarios ) );
        
        if ( ! empty( $destinatarios ) ) {
            foreach ( $destinatarios as $dest ) {
                error_log( "HRM: Enviando correo a {$dest['nombre']} ({$dest['email']})" );
                $enviado = wp_mail( $dest['email'], $asunto, $mensaje, $headers );
                
                if ( $enviado ) {
                    error_log( "HRM: Email de solicitud enviado a {$dest['nombre']} ({$dest['email']})" );
                } else {
                    error_log( "HRM Error: Fallo al enviar email de solicitud a {$dest['email']}" );
                }
            }
        } else {
            error_log( "HRM: No se encontró gerente ni editor de vacaciones para enviar la solicitud" );
        }
    }

    /* =====================================================
     * PROCESAMIENTO DE ARCHIVO ADJUNTO (OPCIONAL)
     * ===================================================== */
    if ( ! empty( $_FILES['archivo_vacaciones'] ) && ! empty( $_FILES['archivo_vacaciones']['name'] ) ) {

        // Cargar utilidades de WordPress
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $file = $_FILES['archivo_vacaciones'];

        // Tipos de archivo permitidos
        $mimes = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
        ];

        $upload = wp_handle_upload(
            $file,
            [ 'test_form' => false, 'mimes' => $mimes ]
        );

        // Error en la subida
        if ( isset( $upload['error'] ) ) {
            $redirect = wp_get_referer() ?: home_url();
            wp_safe_redirect( add_query_arg( 'hrm_msg', 'upload_error', $redirect ) );
            exit;
        }
        
        // Registro documental
        $rut = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT rut FROM {$table_empleados} WHERE id_empleado = %d",
                $id_empleado
            )
        );

        if ( $rut ) {
            if ( ! class_exists( 'HRM_DB_Documentos' ) ) {
                require_once plugin_dir_path( __FILE__ ) . 'includes/db/class-hrm-db-documentos.php';
            }

            $db_docs = new HRM_DB_Documentos();

            $saved = $db_docs->create([
                'rut'    => $rut,
                'tipo'   => 'Solicitud Vacaciones',
                'nombre' => sanitize_file_name( $file['name'] ),
                'url'    => esc_url_raw( $upload['url'] ),
            ]);

            // Si falla el guardado, eliminar archivo físico
            if ( ! $saved && file_exists( $upload['file'] ) ) {
                @unlink( $upload['file'] );
            }
        }
    }

    // Redirección final con mensaje de éxito
    $redirect = wp_get_referer() ?: home_url();
    wp_safe_redirect( add_query_arg( 'hrm_msg', 'created', $redirect ) );
    exit;
}
add_action( 'admin_post_hrm_enviar_vacaciones', 'hrm_enviar_vacaciones_handler' );

/* =====================================================
 * PROCESO DE PRUEBA POST (DEBUG)
 * ===================================================== */
function hrm_procesar_solicitud_vacaciones() {

    if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
        return;
    }

    wp_die( '🔥 POST DETECTADO' );
}
add_action( 'wp', 'hrm_procesar_solicitud_vacaciones' );


function hrm_handle_aprobar_rechazar_solicitud() {

    if ( ! is_admin() || ! ( current_user_can( 'manage_options' ) || current_user_can( 'manage_hrm_vacaciones' ) ) ) {
        return;
    }

    if ( empty( $_POST['accion'] ) || empty( $_POST['solicitud_id'] ) ) {
        return;
    }

    $id_solicitud = intval( $_POST['solicitud_id'] );
    $accion       = sanitize_key( $_POST['accion'] );

    if ( ! in_array( $accion, [ 'aprobar', 'rechazar' ], true ) ) {
        return;
    }

    $nonce = $accion === 'aprobar'
        ? 'hrm_aprobar_solicitud'
        : 'hrm_rechazar_solicitud';

    if ( empty( $_POST['hrm_nonce'] ) || ! wp_verify_nonce( $_POST['hrm_nonce'], $nonce ) ) {
        return;
    }

    global $wpdb;

    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';

    // 🔥 CORRECCIÓN AQUÍ: Mapear al valor correcto del ENUM
    $estado = $accion === 'aprobar' ? 'APROBADA' : 'RECHAZADA';
    
    hrm_debug_log( "Cambiando estado solicitud {$id_solicitud} a {$estado}" );

    // VALIDACIÓN CRÍTICA: Verificar que el usuario NO sea el mismo empleado que solicita
    // EXCEPCIÓN: El Gerente de Operaciones PUEDE auto-aprobarse
    $solicitud = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id_empleado, total_dias, estado FROM $table_solicitudes WHERE id_solicitud = %d",
            $id_solicitud
        )
    );

    if ( ! $solicitud ) {
        wp_die( 'Solicitud no encontrada.' );
    }

    // ★ NUEVA VALIDACIÓN: No permitir cambiar estado si ya está aprobado o rechazado
    if ( $solicitud->estado !== 'PENDIENTE' ) {
        wp_die( '❌ No se puede cambiar el estado de una solicitud que ya ha sido ' . strtolower( $solicitud->estado ) . '. Una solicitud bloqueada solo puede ser visualizada.' );
    }

    // Obtener ID de empleado del usuario actual
    $current_user_id = get_current_user_id();
    $current_user_empleado_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id_empleado FROM $table_empleados WHERE user_id = %d",
            $current_user_id
        )
    );

    // Validar que no sea el mismo empleado (CON EXCEPCIÓN para Gerente de Operaciones)
    if ( $current_user_empleado_id && (int) $current_user_empleado_id === (int) $solicitud->id_empleado ) {
        // Verificar si es el Gerente de Operaciones
        $table_gerencia = $wpdb->prefix . 'rrhh_gerencia_deptos';
        $current_user = get_userdata( $current_user_id );
        $current_user_email = $current_user ? $current_user->user_email : '';
        
        // Obtener área gerencial del usuario actual
        $area_gerencial_actual = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT area_gerencial FROM {$table_gerencia} 
                 WHERE correo_gerente = %s AND estado = 1
                 LIMIT 1",
                $current_user_email
            )
        );
        
        // Permitir auto-aprobación solo si es Gerente de Operaciones
        $es_gerente_operaciones = ( $area_gerencial_actual && strtolower( $area_gerencial_actual ) === 'operaciones' );
        
        if ( ! $es_gerente_operaciones ) {
            wp_die( '❌ CONFLICTO DE INTERÉS: No puedes aprobar/rechazar tu propia solicitud de vacaciones. Por favor, contacta a un superior o al área de Recursos Humanos.' );
        }
    }

    // SI ES APROBACIÓN: Validaciones completas ANTES de actualizar
    if ( $accion === 'aprobar' ) {

        // VALIDACIÓN 1: Verificar disponibilidad de días de vacaciones
        $saldo = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT dias_vacaciones_disponibles FROM $table_empleados WHERE id_empleado = %d",
                $solicitud->id_empleado
            )
        );

        if ( ! $saldo || $saldo->dias_vacaciones_disponibles < $solicitud->total_dias ) {
            wp_die( 'El empleado no tiene días disponibles suficientes para aprobar esta solicitud.' );
        }

        // VALIDACIÓN 2: Registrar solapamientos (informativo, no bloquea)
        $empleados_vacaciones = hrm_get_empleados_departamento_con_vacaciones_aprobadas( $id_solicitud );
        hrm_verificar_conflicto_fechas_vacaciones( $id_solicitud, $empleados_vacaciones );

        // VALIDACIÓN 3: Verificar personal mínimo del departamento
        // Esta es la validación principal que considera las fechas y ausencias simultáneas
        $personal_ok = hrm_validar_minimo_personal_departamento( $id_solicitud );

        if ( ! $personal_ok ) {
            wp_die( '❌ No se puede aprobar esta solicitud.<br><br>' .
                   '<strong>Motivo:</strong> La aprobación haría que el departamento caiga por debajo del personal mínimo requerido durante las fechas solicitadas.<br><br>' .
                   '<strong>Nota:</strong> El sistema detectó que ya hay otros empleados del mismo departamento con vacaciones aprobadas en fechas que se solapan con esta solicitud, ' .
                   'y aprobar esta solicitud dejaría al departamento sin la cobertura mínima necesaria.' );
        }
    }

    // Preparar datos de actualización
    $update_data = [ 'estado' => $estado ];
    $update_format = [ '%s' ];

    // Si es rechazo, agregar motivo si se proporciona
    if ( $accion === 'rechazar' ) {
        $motivo_rechazo = isset( $_POST['motivo_rechazo'] ) ? sanitize_textarea_field( $_POST['motivo_rechazo'] ) : '';
        if ( ! empty( $motivo_rechazo ) ) {
            $update_data['motivo_rechazo'] = $motivo_rechazo;
            $update_format[] = '%s';
        }
    }

    $updated = $wpdb->update(
        $table_solicitudes,
        $update_data,
        [ 'id_solicitud' => $id_solicitud ],
        $update_format,
        [ '%d' ]
    );

    if ( $updated === false ) {
        error_log( 'HRM ERROR SQL: ' . $wpdb->last_error );
    }

    if ( $accion === 'aprobar' ) {
        hrm_descontar_dias_vacaciones_empleado( $id_solicitud );
    }

    hrm_enviar_notificacion_vacaciones( $id_solicitud, $estado ); // ← Pasar el estado correcto

    wp_safe_redirect(
        admin_url( 'admin.php?page=hrm-vacaciones&updated=1' )
    );
    exit;
}

// CORREGIR TAMBIÉN EL HOOK (esto es otro error)
// add_action( 'admin_init', 'hrm_handle_aprobar_rechazar_solicitud' ); // ❌ MAL
add_action( 'admin_post_hrm_aprobar_rechazar_solicitud', 'hrm_handle_aprobar_rechazar_solicitud' ); // ✅ CORRECTO



/* =====================================================
 * CANCELAR SOLICITUD DE VACACIONES (EMPLEADO)
 * ===================================================== */
function hrm_cancelar_solicitud_vacaciones() {
    
    if ( ! is_user_logged_in() ) {
        wp_die( 'Debes estar autenticado para cancelar solicitudes.' );
    }

    if ( empty( $_POST['id_solicitud'] ) ) {
        wp_die( 'ID de solicitud no especificado.' );
    }

    if ( empty( $_POST['hrm_nonce'] ) || ! wp_verify_nonce( $_POST['hrm_nonce'], 'hrm_cancelar_solicitud' ) ) {
        wp_die( 'Verificación de seguridad fallida.' );
    }

    global $wpdb;

    $id_solicitud = intval( $_POST['id_solicitud'] );
    $user_id = get_current_user_id();
    
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';

    // Verificar que la solicitud pertenece al usuario actual y está en estado PENDIENTE
    $solicitud = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT s.*, e.rut, e.nombre, e.apellido, e.puesto, e.departamento, e.correo, ta.nombre as tipo_ausencia_nombre
             FROM $table_solicitudes s
             JOIN $table_empleados e ON s.id_empleado = e.id_empleado
             LEFT JOIN {$wpdb->prefix}rrhh_tipo_ausencia ta ON s.id_tipo = ta.id_tipo
             WHERE s.id_solicitud = %d
             AND e.user_id = %d
             AND s.estado = 'PENDIENTE'",
            $id_solicitud,
            $user_id
        )
    );

    if ( ! $solicitud ) {
        wp_die( 'Solicitud no encontrada, no te pertenece, o no está en estado pendiente.' );
    }

    // Eliminar la solicitud
    $deleted = $wpdb->delete(
        $table_solicitudes,
        [ 'id_solicitud' => $id_solicitud ],
        [ '%d' ]
    );

    if ( $deleted === false ) {
        error_log( 'HRM ERROR SQL al cancelar solicitud: ' . $wpdb->last_error );
        wp_die( 'Error al cancelar la solicitud. Intenta de nuevo.' );
    }

    error_log( "HRM: Solicitud {$id_solicitud} cancelada por empleado {$user_id}" );

    // ★ NUEVA FUNCIONALIDAD: Enviar notificación de cancelación al gerente y editor de vacaciones
    hrm_enviar_notificacion_cancelacion_vacaciones( $solicitud );

    // Redireccionar con mensaje de éxito
    $redirect = wp_get_referer() ?: home_url();
    wp_safe_redirect( add_query_arg( 'hrm_msg', 'cancelled', $redirect ) );
    exit;
}
add_action( 'admin_post_hrm_cancelar_solicitud_vacaciones', 'hrm_cancelar_solicitud_vacaciones' );



/* =====================================================
 * ENVÍO DE NOTIFICACIÓN POR CORREO
 * ===================================================== */
function hrm_enviar_notificacion_vacaciones( $id_solicitud, $estado ) {

    global $wpdb;

    $sol = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $emp = $wpdb->prefix . 'rrhh_empleados';
    $tipos = $wpdb->prefix . 'rrhh_tipo_ausencia';

    $d = $wpdb->get_row(
        $wpdb->prepare("
            SELECT s.fecha_inicio, s.fecha_fin, s.total_dias, s.motivo_rechazo, s.id_tipo,
                   e.nombre, e.apellido, e.correo,
                   t.nombre AS tipo_ausencia
            FROM $sol s
            JOIN $emp e ON s.id_empleado = e.id_empleado
            JOIN $tipos t ON s.id_tipo = t.id_tipo
            WHERE s.id_solicitud = %d
        ", $id_solicitud)
    );

    if ( ! $d || empty( $d->correo ) ) {
        return;
    }

    // Obtener nombre del sitio y URL
    $nombre_sitio = get_bloginfo( 'name' );
    $url_sitio = home_url();

    // Convertir fechas a formato más legible
    $fecha_inicio = new DateTime( $d->fecha_inicio );
    $fecha_fin = new DateTime( $d->fecha_fin );
    $fecha_inicio_formateada = $fecha_inicio->format( 'd/m/Y' );
    $fecha_fin_formateada = $fecha_fin->format( 'd/m/Y' );

    // Determinar el tipo de solicitud para personalizar el mensaje
    $tipo_ausencia = ! empty( $d->tipo_ausencia ) ? $d->tipo_ausencia : 'Ausencia';
    
    // Usar strtolower para comparaciones más robustas
    $tipo_ausencia_lower = strtolower( $tipo_ausencia );
    $es_vacaciones = strpos( $tipo_ausencia_lower, 'vacaciones' ) !== false;
    $es_permiso = strpos( $tipo_ausencia_lower, 'permiso' ) !== false;
    $es_licencia_medica = strpos( $tipo_ausencia_lower, 'licencia' ) !== false || strpos( $tipo_ausencia_lower, 'médica' ) !== false;

    // Determinar el asunto y contenido según el estado
    if ( $estado === 'APROBADA' ) {
        
        if ( $es_vacaciones ) {
            $asunto = "¡Buenas noticias! Tu solicitud de vacaciones ha sido aprobada ";
            $titulo = "SOLICITUD DE VACACIONES APROBADA";
            $icono_titulo = "";
            $mensaje = "Estimado/a {$d->nombre} {$d->apellido},\n\n";
            $mensaje .= "¡Tenemos buenas noticias para ti! Tu solicitud de vacaciones ha sido aprobada.\n\n";
            $mensaje .= "Disfruta de tus merecidas vacaciones con los siguientes detalles:\n\n";
            
            $mensaje .= "$icono_titulo $titulo\n";
            
            $mensaje .= "Fecha de inicio:     $fecha_inicio_formateada\n";
            $mensaje .= "Fecha de término:    $fecha_fin_formateada\n";
            $mensaje .= "Total de días:       {$d->total_dias} días hábiles\n";
            
            $mensaje .= "Te recordamos que es importante:\n";
            $mensaje .= " Entregar todos tus trabajos pendientes antes de partir\n";
            $mensaje .= " Asegurar que tus tareas sean cubiertas durante tu ausencia\n";
            $mensaje .= " Mantener contacto en caso de emergencias relacionadas con el trabajo\n\n";
            $mensaje .= "Si tienes alguna pregunta, no dudes en contactar al equipo de Recursos Humanos.\n\n";
            $mensaje .= "¡Que disfrutes tus vacaciones!\n\n";
            
        } elseif ( $es_permiso ) {
            $asunto = "Tu solicitud de permiso ha sido aprobada ";
            $titulo = "SOLICITUD DE PERMISO APROBADA";
            $icono_titulo = " ";
            $mensaje = "Estimado/a {$d->nombre} {$d->apellido},\n\n";
            $mensaje .= "Nos complace informarte que tu solicitud de permiso ha sido aprobada.\n\n";
            
            $mensaje .= "$icono_titulo $titulo\n";
            
            $mensaje .= "Fecha de inicio:     $fecha_inicio_formateada\n";
            $mensaje .= "Fecha de término:    $fecha_fin_formateada\n";
            $mensaje .= "Total de días:       {$d->total_dias} días\n";
            
            $mensaje .= "Por favor, recuerda que:\n";
            $mensaje .= " Debes informar a tu supervisor directo sobre tu ausencia\n";
            $mensaje .= " Procura dejar tus tareas en orden antes de partir\n";
            $mensaje .= " En caso de cambios, notifica al equipo de Recursos Humanos\n\n";
            $mensaje .= "Cualquier duda, estamos disponibles para ayudarte.\n\n";
            
        } elseif ( $es_licencia_medica ) {
            $asunto = "Tu solicitud de licencia médica ha sido aprobada ";
            $titulo = "SOLICITUD DE LICENCIA MÉDICA APROBADA";
            $icono_titulo = "  ";
            $mensaje = "Estimado/a {$d->nombre} {$d->apellido},\n\n";
            $mensaje .= "Tu solicitud de licencia médica ha sido aprobada. Esperamos tu pronta recuperación.\n\n";
            
            $mensaje .= "$icono_titulo $titulo\n";
            
            $mensaje .= "Fecha de inicio:     $fecha_inicio_formateada\n";
            $mensaje .= "Fecha de término:    $fecha_fin_formateada\n";
            $mensaje .= "Total de días:       {$d->total_dias} días\n";
            
            $mensaje .= "Notas importantes:\n";
            $mensaje .= " Asegúrate de proporcionarte el cuidado médico necesario\n";
            $mensaje .= " Si tus fechas cambian, comunícate inmediatamente con RRHH\n";
            $mensaje .= " Podría ser necesaria documentación médica adicional\n\n";
            $mensaje .= "Que te recuperes pronto. Estamos aquí si necesitas algo.\n\n";
        } else {
            $asunto = "Tu solicitud de ausencia ha sido aprobada ";
            $titulo = "SOLICITUD APROBADA";
            $icono_titulo = " ";
            $mensaje = "Estimado/a {$d->nombre} {$d->apellido},\n\n";
            $mensaje .= "Tu solicitud de {$tipo_ausencia} ha sido aprobada.\n\n";
            
            $mensaje .= "$icono_titulo $titulo\n";
            
            $mensaje .= "Tipo:                {$tipo_ausencia}\n";
            $mensaje .= "Fecha de inicio:     $fecha_inicio_formateada\n";
            $mensaje .= "Fecha de término:    $fecha_fin_formateada\n";
            $mensaje .= "Total de días:       {$d->total_dias} días\n";
            
            $mensaje .= "Si tienes preguntas, contacta a Recursos Humanos.\n\n";
        }
        
    } else {
        // RECHAZO/REVISIÓN
        
        if ( $es_vacaciones ) {
            $asunto = "Actualización sobre tu solicitud de vacaciones";
            $titulo = "SOLICITUD DE VACACIONES EN REVISIÓN";
            $icono_titulo = "";
            
            $mensaje = "Estimado/a {$d->nombre} {$d->apellido},\n\n";
            $mensaje .= "Agradecemos tu solicitud de vacaciones y hemos revisado cuidadosamente tu solicitud. En este momento no podemos aprobarla.\n\n";
            
        } elseif ( $es_permiso ) {
            $asunto = "Actualización sobre tu solicitud de permiso";
            $titulo = "SOLICITUD DE PERMISO EN REVISIÓN";
            $icono_titulo = " ";
            
            $mensaje = "Estimado/a {$d->nombre} {$d->apellido},\n\n";
            $mensaje .= "Hemos revisado tu solicitud de permiso. Lamentablemente, en este momento no podemos aprobarla.\n\n";
            
        } elseif ( $es_licencia_medica ) {
            $asunto = "Actualización sobre tu solicitud de licencia médica";
            $titulo = "SOLICITUD DE LICENCIA MÉDICA EN REVISIÓN";
            $icono_titulo = "  ";
            
            $mensaje = "Estimado/a {$d->nombre} {$d->apellido},\n\n";
            $mensaje .= "Hemos revisado tu solicitud de licencia médica y necesitamos información adicional o ajustes.\n\n";
            
        } else {
            $asunto = "Actualización sobre tu solicitud de ausencia";
            $titulo = "SOLICITUD EN REVISIÓN";
            $icono_titulo = " ";
            
            $mensaje = "Estimado/a {$d->nombre} {$d->apellido},\n\n";
            $mensaje .= "Hemos revisado tu solicitud de {$tipo_ausencia}. En este momento requiere revisión adicional.\n\n";
        }
        
        
        $mensaje .= "$icono_titulo $titulo\n";
        
        $mensaje .= "Tipo:                {$tipo_ausencia}\n";
        $mensaje .= "Fecha solicitada:    $fecha_inicio_formateada a $fecha_fin_formateada\n";
        $mensaje .= "Días solicitados:    {$d->total_dias} días\n";
        $mensaje .= "Estado:              Requiere revisión\n";
        
        
        if ( ! empty( $d->motivo_rechazo ) ) {
            $mensaje .= " MOTIVO DE LA REVISIÓN:\n";
            $mensaje .= $d->motivo_rechazo . "\n\n";
        }
        
        $mensaje .= "Te invitamos a:\n";
        $mensaje .= "• Contactar al equipo de Recursos Humanos para discutir alternativas\n";
        $mensaje .= "• Considerar fechas diferentes que se adapten mejor a nuestras necesidades operativas\n";
        $mensaje .= "• Reenviar una nueva solicitud cuando sea apropiado\n\n";
        $mensaje .= "Entendemos la importancia de tu solicitud y estamos aquí para ayudarte a encontrar la mejor solución.\n\n";
    }
    
    $mensaje .= "Saludos cordiales,\n";
    $mensaje .= "Equipo de Recursos Humanos\n";
    $mensaje .= "$nombre_sitio\n";
    $mensaje .= "$url_sitio\n";

    wp_mail(
        $d->correo,
        $asunto,
        $mensaje
    );
}

/**
 * =====================================================
 * ENVIAR NOTIFICACIÓN DE CANCELACIÓN DE SOLICITUD
 * =====================================================
 * Envía correos de notificación al gerente a cargo
 * y al editor de vacaciones cuando un empleado cancela
 * una solicitud pendiente de vacaciones
 * 
 * @param object $solicitud Datos de la solicitud cancelada
 */
function hrm_enviar_notificacion_cancelacion_vacaciones( $solicitud ) {
    global $wpdb;

    // Validar que la solicitud tenga datos necesarios
    if ( ! $solicitud || empty( $solicitud->nombre ) || empty( $solicitud->apellido ) ) {
        error_log( 'HRM: Datos insuficientes para enviar notificación de cancelación' );
        return;
    }

    // Obtener tipo de ausencia
    $tipos_ausencia = hrm_get_tipos_ausencia_definidos();
    $tipo_nombre = isset( $tipos_ausencia[ $solicitud->id_tipo ] ) ? $tipos_ausencia[ $solicitud->id_tipo ] : 'Ausencia';

    // Obtener el gerente a cargo del departamento del empleado
    $gerente = hrm_obtener_gerente_departamento( $solicitud->id_empleado );

    // Obtener TODOS los editores de vacaciones
    $editores_vacaciones_emails = array();
    $editores_result = $wpdb->get_col(
        "SELECT DISTINCT user_email FROM {$wpdb->users} u
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
         WHERE um.meta_key = '{$wpdb->prefix}capabilities'
         AND um.meta_value LIKE '%editor_vacaciones%'
         ORDER BY u.ID"
    );

    if ( ! empty( $editores_result ) ) {
        $editores_vacaciones_emails = $editores_result;
    } else {
        // Fallback si no se encuentra por meta_key
        $users_editor = get_users( array( 'role' => 'editor_vacaciones' ) );
        if ( ! empty( $users_editor ) ) {
            foreach ( $users_editor as $user ) {
                $editores_vacaciones_emails[] = $user->user_email;
            }
        }
    }

    // Información del sitio
    $nombre_sitio = get_bloginfo( 'name' );
    $url_sitio = home_url();

    // Formatear fechas
    $fecha_inicio_formateada = date_i18n( 'd/m/Y', strtotime( $solicitud->fecha_inicio ) );
    $fecha_fin_formateada = date_i18n( 'd/m/Y', strtotime( $solicitud->fecha_fin ) );

    // Construir mensaje para gerente y editor de vacaciones
    $asunto = "Solicitud de {$tipo_nombre} CANCELADA - {$solicitud->nombre} {$solicitud->apellido}";

    $mensaje = "
        <h2>Notificación de Cancelación de Solicitud</h2>
        
        <p>Un empleado ha cancelado su solicitud de {$tipo_nombre} que se encontraba pendiente de revisión.</p>
        
        <h3>Datos del Empleado:</h3>
        <ul>
            <li><strong>Nombre:</strong> {$solicitud->nombre} {$solicitud->apellido}</li>
            <li><strong>RUT:</strong> {$solicitud->rut}</li>
            <li><strong>Departamento:</strong> {$solicitud->departamento}</li>
            <li><strong>Puesto:</strong> {$solicitud->puesto}</li>
            <li><strong>Correo:</strong> {$solicitud->correo}</li>
        </ul>
        
        <h3>Solicitud Cancelada:</h3>
        <ul>
            <li><strong>Tipo:</strong> {$tipo_nombre}</li>
            <li><strong>Fecha Inicio:</strong> {$fecha_inicio_formateada}</li>
            <li><strong>Fecha Fin:</strong> {$fecha_fin_formateada}</li>
            <li><strong>Total Días:</strong> {$solicitud->total_dias} días hábiles</li>
            <li><strong>Estado:</strong> CANCELADA</li>
        </ul>
        
        <p style=\"color: #666; font-style: italic; margin-top: 20px;\">
            Esta es una notificación automática. No es necesario tomar acción alguna.
        </p>
        
        <p><em>$nombre_sitio</em></p>
    ";

    // Configurar headers para HTML
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );

    // Construir lista de destinatarios
    $destinatarios = array();

    // Agregar gerente si existe
    if ( $gerente && ! empty( $gerente['correo_gerente'] ) ) {
        $destinatarios[] = array(
            'email' => $gerente['correo_gerente'],
            'nombre' => $gerente['nombre_gerente'] ?? 'Gerente'
        );
    }

    // Agregar TODOS los editores de vacaciones (evitando duplicados con gerente)
    if ( ! empty( $editores_vacaciones_emails ) ) {
        foreach ( $editores_vacaciones_emails as $editor_email ) {
            if ( empty( $gerente ) || $editor_email !== $gerente['correo_gerente'] ) {
                $destinatarios[] = array(
                    'email' => $editor_email,
                    'nombre' => 'Editor de Vacaciones'
                );
            }
        }
    }

    // Enviar email a todos los destinatarios
    if ( ! empty( $destinatarios ) ) {
        foreach ( $destinatarios as $dest ) {
            error_log( "HRM: Enviando notificación de cancelación a {$dest['nombre']} ({$dest['email']})" );
            $enviado = wp_mail( $dest['email'], $asunto, $mensaje, $headers );

            if ( $enviado ) {
                error_log( "HRM: Notificación de cancelación enviada a {$dest['nombre']} ({$dest['email']})" );
            } else {
                error_log( "HRM Error: Fallo al enviar notificación de cancelación a {$dest['email']}" );
            }
        }
    } else {
        error_log( "HRM: No se encontró gerente ni editor de vacaciones para enviar notificación de cancelación" );
    }
}


function hrm_get_documentos_por_solicitud( $id_solicitud ) {
    global $wpdb;

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT nombre, url
             FROM {$wpdb->prefix}rrhh_documentos
             WHERE id_solicitud = %d",
            $id_solicitud
        )
    );
}

//FUNCIÓN: CALCULAR DÍAS DE UNA SOLICITUD
function hrm_get_dias_solicitud( $id_solicitud ) {
    global $wpdb;

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT fecha_inicio, fecha_fin
             FROM {$wpdb->prefix}rrhh_solicitudes_ausencia
             WHERE id_solicitud = %d",
            $id_solicitud
        )
    );

    if ( ! $row ) {
        return 0;
    }

    return hrm_calcular_dias_habiles(
        $row->fecha_inicio,
        $row->fecha_fin
    );
}


//FUNCIÓN: DESCONTAR DÍAS AL APROBAR
function hrm_descontar_dias_vacaciones_empleado( $id_solicitud ) {
    global $wpdb;
    
    // DEFINIR TABLAS
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_vacaciones_anual = $wpdb->prefix . 'rrhh_vacaciones_anual';
    
    // 1. Obtener datos de la solicitud APROBADA
    $sol = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id_empleado, fecha_inicio, fecha_fin
             FROM $table_solicitudes
             WHERE id_solicitud = %d
             AND estado = 'APROBADA'",
            $id_solicitud
        )
    );

    if ( ! $sol ) {
        error_log( "HRM: Solicitud no encontrada o no está aprobada: $id_solicitud" );
        return false;
    }

    // 2. Calcular días hábiles
    $dias = hrm_calcular_dias_habiles( $sol->fecha_inicio, $sol->fecha_fin );
    
    if ( $dias <= 0 ) {
        error_log( "HRM: Días calculados <= 0 para solicitud: $id_solicitud" );
        return false;
    }

    // 3. Verificar si el empleado tiene días suficientes
    $saldo = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT dias_vacaciones_disponibles 
             FROM $table_empleados 
             WHERE id_empleado = %d",
            $sol->id_empleado
        )
    );
    
    if ( ! $saldo ) {
        error_log( "HRM: Empleado no encontrado: " . $sol->id_empleado );
        return false;
    }
    
    if ( $saldo->dias_vacaciones_disponibles < $dias ) {
        error_log( "HRM: Empleado no tiene días suficientes. Disponibles: " . 
                   $saldo->dias_vacaciones_disponibles . ", Solicitados: $dias" );
        return false;
    }

    // 4. Actualizar saldo de vacaciones en Bu6K9_rrhh_empleados
    $resultado_empleados = $wpdb->query(
        $wpdb->prepare(
            "UPDATE $table_empleados
             SET
                dias_vacaciones_usados = dias_vacaciones_usados + %d,
                dias_vacaciones_disponibles = dias_vacaciones_disponibles - %d
             WHERE id_empleado = %d",
            $dias,
            $dias,
            $sol->id_empleado
        )
    );
    
    if ( $resultado_empleados === false ) {
        error_log( "HRM Error SQL al descontar días en empleados: " . $wpdb->last_error );
        return false;
    }

    // 5. Actualizar saldo en Bu6K9_rrhh_vacaciones_anual (si existe la tabla)
    $ano_actual = (int) gmdate( 'Y' );
    
    // Verificar si existe el registro para este año
    $vacacion_anual = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id FROM $table_vacaciones_anual 
             WHERE id_empleado = %d AND ano = %d",
            $sol->id_empleado,
            $ano_actual
        )
    );

    if ( $vacacion_anual ) {
        // Actualizar registro existente
        $resultado_anual = $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table_vacaciones_anual
                 SET
                    dias_usados = dias_usados + %d,
                    dias_disponibles = dias_disponibles - %d
                 WHERE id_empleado = %d AND ano = %d",
                $dias,
                $dias,
                $sol->id_empleado,
                $ano_actual
            )
        );

        if ( $resultado_anual === false ) {
            error_log( "HRM Error SQL al actualizar vacaciones anuales: " . $wpdb->last_error );
            return false;
        }
    } else {
        // Si no existe, crear registro nuevo
        $wpdb->insert(
            $table_vacaciones_anual,
            [
                'id_empleado' => $sol->id_empleado,
                'ano' => $ano_actual,
                'dias_asignados' => 15,
                'dias_usados' => $dias,
                'dias_disponibles' => 15 - $dias,
                'dias_carryover_anterior' => 0
            ],
            [ '%d', '%d', '%d', '%d', '%d', '%d' ]
        );
    }
    
    error_log( "HRM: Descontados $dias días al empleado ID: " . $sol->id_empleado );
    return true;
}

//FUNCIÓN PARA MOSTRAR SALDO
function hrm_get_saldo_vacaciones( $id_empleado ) {
    global $wpdb;

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                dias_vacaciones_anuales,
                dias_vacaciones_usados,
                dias_vacaciones_disponibles
             FROM {$wpdb->prefix}rrhh_empleados
             WHERE id_empleado = %d",
            $id_empleado
        )
    );
}


//FUNCIÓN: DESCONTAR PERSONAL VIGENTE AL APROBAR
function hrm_descontar_personal_vigente_departamento( $id_solicitud ) {
    global $wpdb;
    
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_departamentos = $wpdb->prefix . 'rrhh_departamentos';

    // 1. Obtener el departamento del empleado
    $solicitud = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT e.departamento
             FROM {$table_solicitudes} s
             JOIN {$table_empleados} e ON s.id_empleado = e.id_empleado
             WHERE s.id_solicitud = %d",
            $id_solicitud
        )
    );

    if ( ! $solicitud ) {
        error_log( "HRM: Solicitud no encontrada para descontar personal: $id_solicitud" );
        return false;
    }

    $departamento = $solicitud->departamento;

    if ( empty( $departamento ) ) {
        error_log( "HRM: Empleado sin departamento asignado para descontar personal" );
        return false;
    }

    // 2. Descontar 1 de personal_vigente
    $resultado = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table_departamentos}
             SET personal_vigente = personal_vigente - 1
             WHERE nombre_departamento = %s
             AND personal_vigente > 0",
            $departamento
        )
    );

    if ( $resultado === false ) {
        error_log( "HRM Error SQL al descontar personal vigente: " . $wpdb->last_error );
        return false;
    }

    if ( $resultado === 0 ) {
        error_log( "HRM Warning: No se pudo descontar personal vigente para departamento: $departamento" );
        return false;
    }

    error_log( "HRM: Descontado 1 personal vigente del departamento: $departamento" );
    return true;
}


/* =====================================================
 * VALIDACIÓN COMPLETA: Verificar si solicitud puede ser aprobada
 * =====================================================
 * Valida las 3 condiciones sin ejecutar wp_die()
 * Retorna información detallada sobre qué falló
 *
 * @param int $id_solicitud ID de la solicitud a validar
 * @return array Array con 'puede_aprobar' (bool) y 'razon' (string)
 */
function hrm_validar_aprobacion_solicitud( $id_solicitud ) {
    global $wpdb;

    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';

    // Obtener solicitud
    $solicitud = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id_empleado, total_dias FROM $table_solicitudes WHERE id_solicitud = %d",
            $id_solicitud
        )
    );

    if ( ! $solicitud ) {
        return [
            'puede_aprobar' => false,
            'razon' => 'Solicitud no encontrada'
        ];
    }

    // VALIDACIÓN 1: Días de vacaciones disponibles
    $saldo = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT dias_vacaciones_disponibles FROM $table_empleados WHERE id_empleado = %d",
            $solicitud->id_empleado
        )
    );

    if ( ! $saldo || $saldo->dias_vacaciones_disponibles < $solicitud->total_dias ) {
        return [
            'puede_aprobar' => false,
            'razon' => 'El empleado no tiene días de vacaciones suficientes (' . 
                      ($saldo->dias_vacaciones_disponibles ?? 0) . ' disponibles, ' . 
                      $solicitud->total_dias . ' solicitados)'
        ];
    }

    // VALIDACIÓN 2: Registrar solapamientos (informativo)
    $empleados_vacaciones = hrm_get_empleados_departamento_con_vacaciones_aprobadas( $id_solicitud );
    hrm_verificar_conflicto_fechas_vacaciones( $id_solicitud, $empleados_vacaciones );

    // VALIDACIÓN 3: Personal mínimo del departamento (considera fechas y ausencias simultáneas)
    $personal_ok = hrm_validar_minimo_personal_departamento( $id_solicitud );

    if ( ! $personal_ok ) {
        return [
            'puede_aprobar' => false,
            'razon' => 'La aprobación haría que el departamento caiga por debajo del personal mínimo requerido durante las fechas solicitadas (hay otras solicitudes aprobadas con fechas solapadas)'
        ];
    }

    // Todas las validaciones pasaron
    return [
        'puede_aprobar' => true,
        'razon' => 'Solicitud válida para aprobación'
    ];
}


/* =====================================================
 * FIN DE AÑO: PROCESAR CARRYOVER DE VACACIONES
 * ===================================================== */
/**
 * Procesa el carryover de días no usados al nuevo año
 * Se ejecuta típicamente el 31 de diciembre o al inicio del año nuevo
 * 
 * @param int $ano_anterior Año a procesar (ej: 2025)
 * @return array Resultado del procesamiento
 */
function hrm_procesar_carryover_anual( $ano_anterior = null ) {
    global $wpdb;

    if ( ! $ano_anterior ) {
        $ano_anterior = (int) gmdate( 'Y' ) - 1;
    }

    $ano_nuevo = $ano_anterior + 1;
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_vacaciones_anual = $wpdb->prefix . 'rrhh_vacaciones_anual';

    $resultado = [
        'procesados' => 0,
        'errores' => 0,
        'detalles' => []
    ];

    // Obtener todos los empleados con días disponibles en el año anterior
    $empleados_con_carryover = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id_empleado, dias_disponibles 
             FROM $table_vacaciones_anual 
             WHERE ano = %d AND dias_disponibles > 0",
            $ano_anterior
        )
    );

    if ( empty( $empleados_con_carryover ) ) {
        error_log( "HRM: No hay empleados con carryover para el año $ano_anterior" );
        return $resultado;
    }

    foreach ( $empleados_con_carryover as $empleado ) {
        // Calcular días según antigüedad (ACTIVADO: Ley Chilena)
        $dias_nuevos_periodo = hrm_calcular_dias_segun_antiguedad( $empleado->id_empleado );
        
        // Crear registro nuevo año con carryover
        $insert = $wpdb->insert(
            $table_vacaciones_anual,
            [
                'id_empleado' => $empleado->id_empleado,
                'ano' => $ano_nuevo,
                'dias_asignados' => $dias_nuevos_periodo,
                'dias_usados' => 0,
                'dias_disponibles' => $dias_nuevos_periodo + $empleado->dias_disponibles,
                'dias_carryover_anterior' => $empleado->dias_disponibles
            ],
            [ '%d', '%d', '%d', '%d', '%d', '%d' ]
        );

        if ( $insert ) {
            $resultado['procesados']++;
            $resultado['detalles'][] = "Empleado {$empleado->id_empleado}: {$empleado->dias_disponibles} días carryover + {$dias_nuevos_periodo} días nuevos al año $ano_nuevo";
            
            // Actualizar tabla empleados con los nuevos días disponibles
            $wpdb->update(
                $table_empleados,
                [
                    'dias_vacaciones_disponibles' => $dias_nuevos_periodo + $empleado->dias_disponibles,
                    'dias_vacaciones_usados' => 0
                ],
                [ 'id_empleado' => $empleado->id_empleado ],
                [ '%d', '%d' ],
                [ '%d' ]
            );

            error_log( "HRM: Carryover procesado para empleado {$empleado->id_empleado} - {$empleado->dias_disponibles} días + {$dias_nuevos_periodo} nuevos" );
        } else {
            $resultado['errores']++;
            error_log( "HRM Error: No se pudo crear registro carryover para empleado {$empleado->id_empleado}" );
        }
    }

    return $resultado;
}


/* =====================================================
 * OBTENER HISTORIAL DE VACACIONES POR EMPLEADO
 * ===================================================== */
/**
 * Obtiene el historial de vacaciones de un empleado por año
 * 
 * @param int $id_empleado
 * @return array Registros de vacaciones anuales
 */
function hrm_get_historial_vacaciones_empleado( $id_empleado ) {
    global $wpdb;

    $table = $wpdb->prefix . 'rrhh_vacaciones_anual';

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table 
             WHERE id_empleado = %d 
             ORDER BY ano DESC",
            $id_empleado
        ),
        ARRAY_A
    );
}


/* =====================================================
 * VERIFICAR SI EMPLEADO USÓ TODOS LOS DÍAS EN UN AÑO
 * ===================================================== */
/**
 * Verifica si un empleado tomó todos sus días disponibles
 * 
 * @param int $id_empleado
 * @param int $ano
 * @return bool True si usó todos los días, false si le quedan
 */
function hrm_empleado_uso_todos_dias( $id_empleado, $ano = null ) {
    global $wpdb;

    if ( ! $ano ) {
        $ano = (int) gmdate( 'Y' );
    }

    $table = $wpdb->prefix . 'rrhh_vacaciones_anual';

    $registro = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT dias_disponibles FROM $table 
             WHERE id_empleado = %d AND ano = %d",
            $id_empleado,
            $ano
        )
    );

    if ( ! $registro ) {
        return false; // No hay registro para este año
    }

    return (int) $registro->dias_disponibles === 0;
}


/* =====================================================
 * VALIDACIÓN: EMPLEADOS DEL DEPARTAMENTO CON VACACIONES APROBADAS
 * =====================================================
 * Devuelve una lista de empleados del mismo departamento
 * que tengan solicitudes de vacaciones aprobadas.
 *
 * Usado para:
 * - Validar si hay personal disponible durante el período
 * - Verificar cobertura del departamento
 * - Análisis de sobrecarga de trabajo
 *
 * @param int $id_solicitud ID de la solicitud a validar
 * @return array Lista de empleados con vacaciones aprobadas
 */
function hrm_get_empleados_departamento_con_vacaciones_aprobadas( $id_solicitud ) {
    global $wpdb;

    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';

    // 1. Obtener el departamento del empleado que hace la solicitud
    $solicitud = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT e.id_empleado, e.departamento, e.nombre, e.apellido
             FROM {$table_solicitudes} s
             JOIN {$table_empleados} e ON s.id_empleado = e.id_empleado
             WHERE s.id_solicitud = %d",
            $id_solicitud
        )
    );

    if ( ! $solicitud ) {
        error_log( "HRM: Solicitud no encontrada: $id_solicitud" );
        return array();
    }

    $id_empleado_solicitante = $solicitud->id_empleado;
    $departamento = $solicitud->departamento;

    if ( empty( $departamento ) ) {
        error_log( "HRM: Empleado sin departamento asignado: $id_empleado_solicitante" );
        return array();
    }

    // 2. Obtener todos los empleados del mismo departamento con vacaciones aprobadas
    $empleados_con_vacaciones = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT
                e.id_empleado,
                e.nombre,
                e.apellido,
                e.correo,
                s.fecha_inicio,
                s.fecha_fin,
                s.total_dias,
                s.id_solicitud
             FROM {$table_empleados} e
             JOIN {$table_solicitudes} s ON e.id_empleado = s.id_empleado
             WHERE e.departamento = %s
             AND e.id_empleado != %d
             AND s.estado = 'APROBADA'
             ORDER BY s.fecha_inicio ASC",
            $departamento,
            $id_empleado_solicitante
        ),
        ARRAY_A
    );

    error_log( "HRM: Encontrados " . count( $empleados_con_vacaciones ) . " empleados del departamento '$departamento' con vacaciones aprobadas" );

    return $empleados_con_vacaciones;
}


/* =====================================================
 * VALIDACIÓN: CONFLICTO DE FECHAS EN VACACIONES
 * =====================================================
 * Compara rangos de fechas de una solicitud en proceso
 * con las vacaciones aprobadas del departamento.
 *
 * NOTA: Esta función ahora solo es INFORMATIVA y registra
 * los solapamientos en el log. La validación real de si
 * se puede aprobar la solicitud la hace 
 * hrm_validar_minimo_personal_departamento() que considera
 * el personal mínimo requerido vs las ausencias simultáneas.
 *
 * Usado para:
 * - Registrar información de solapamientos en el log
 * - Análisis de riesgo de cobertura
 *
 * @param int   $id_solicitud ID de la solicitud a validar
 * @param array $empleados_con_vacaciones Array de empleados con vacaciones aprobadas
 * @return bool Siempre TRUE (la validación real está en hrm_validar_minimo_personal_departamento)
 */
function hrm_verificar_conflicto_fechas_vacaciones( $id_solicitud, $empleados_con_vacaciones ) {
    global $wpdb;

    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';

    // 1. Obtener fechas de la solicitud en proceso
    $solicitud_actual = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT fecha_inicio, fecha_fin, total_dias
             FROM {$table_solicitudes}
             WHERE id_solicitud = %d",
            $id_solicitud
        )
    );

    if ( ! $solicitud_actual ) {
        error_log( "HRM: Solicitud no encontrada: $id_solicitud" );
        return true; // No bloquear, dejar que la otra validación maneje esto
    }

    $fecha_inicio = $solicitud_actual->fecha_inicio;
    $fecha_fin = $solicitud_actual->fecha_fin;
    $solapamientos_encontrados = 0;

    // 2. Registrar solapamientos (solo informativo)
    foreach ( $empleados_con_vacaciones as $empleado ) {
        $emp_inicio = $empleado['fecha_inicio'];
        $emp_fin = $empleado['fecha_fin'];

        // Verificar solapamiento: 
        // Hay solapamiento si: inicio_solicitud <= fin_empleado AND fin_solicitud >= inicio_empleado
        if ( $fecha_inicio <= $emp_fin && $fecha_fin >= $emp_inicio ) {
            
            $dias_solapamiento = hrm_calcular_dias_habiles( 
                max( $fecha_inicio, $emp_inicio ), 
                min( $fecha_fin, $emp_fin ) 
            );

            $solapamientos_encontrados++;

            error_log( "HRM INFO: Solapamiento detectado con " . $empleado['nombre'] . " " . $empleado['apellido'] . 
                      " | Días: " . $dias_solapamiento . 
                      " | Período empleado: " . $emp_inicio . " a " . $emp_fin );
        }
    }

    if ( $solapamientos_encontrados > 0 ) {
        error_log( "HRM INFO: Total de solapamientos para solicitud #$id_solicitud: $solapamientos_encontrados" );
    }

    // Siempre retorna TRUE - la validación real está en hrm_validar_minimo_personal_departamento
    return true;
}


/* =====================================================
 * VALIDACIÓN: PERSONAL MÍNIMO DEL DEPARTAMENTO
 * =====================================================
 * Verifica si la aprobación de una solicitud mantendría
 * el personal mínimo requerido en el departamento.
 *
 * MEJORADO: Ahora considera todas las solicitudes aprobadas
 * que se solapan con las fechas de la nueva solicitud para
 * calcular el personal real disponible día a día.
 *
 * Valida que NO se caiga por debajo del mínimo de
 * empleados activos durante el período de vacaciones.
 *
 * Usado para:
 * - Validar si es seguro aprobar la solicitud
 * - Garantizar cobertura mínima del departamento
 * - Cumplir políticas de personal
 *
 * @param int $id_solicitud ID de la solicitud a validar
 * @return bool TRUE si se puede aprobar (hay personal mínimo), 
 *              FALSE si no hay personal mínimo
 */
function hrm_validar_minimo_personal_departamento( $id_solicitud ) {
    global $wpdb;

    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_departamentos = $wpdb->prefix . 'rrhh_departamentos';

    // 1. Obtener datos de la solicitud y departamento del empleado
    $solicitud = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT s.id_empleado, s.fecha_inicio, s.fecha_fin, e.departamento
             FROM {$table_solicitudes} s
             JOIN {$table_empleados} e ON s.id_empleado = e.id_empleado
             WHERE s.id_solicitud = %d",
            $id_solicitud
        )
    );

    if ( ! $solicitud ) {
        error_log( "HRM: Solicitud no encontrada: $id_solicitud" );
        return false;
    }

    $departamento = $solicitud->departamento;
    $id_empleado_solicitante = $solicitud->id_empleado;
    $fecha_inicio_solicitud = $solicitud->fecha_inicio;
    $fecha_fin_solicitud = $solicitud->fecha_fin;

    if ( empty( $departamento ) ) {
        error_log( "HRM: Empleado sin departamento asignado: $id_empleado_solicitante" );
        return false;
    }

    // 2. Obtener personal_vigente y minimo_empleados del departamento
    $depto_info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT personal_vigente, minimo_empleados 
             FROM {$table_departamentos} 
             WHERE nombre_departamento = %s",
            $departamento
        )
    );

    if ( ! $depto_info ) {
        error_log( "HRM: Departamento no encontrado en tabla de departamentos: $departamento" );
        return false;
    }

    $personal_vigente = (int) $depto_info->personal_vigente;
    $minimo_requerido = (int) $depto_info->minimo_empleados;

    // 3. NUEVO: Obtener todas las solicitudes APROBADAS del mismo departamento
    //    que se solapen con las fechas de la solicitud actual
    $solicitudes_solapadas = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT
                s.id_solicitud,
                s.id_empleado,
                s.fecha_inicio,
                s.fecha_fin,
                e.nombre,
                e.apellido
             FROM {$table_solicitudes} s
             JOIN {$table_empleados} e ON s.id_empleado = e.id_empleado
             WHERE e.departamento = %s
             AND s.id_empleado != %d
             AND s.estado = 'APROBADA'
             AND s.fecha_inicio <= %s
             AND s.fecha_fin >= %s",
            $departamento,
            $id_empleado_solicitante,
            $fecha_fin_solicitud,
            $fecha_inicio_solicitud
        ),
        ARRAY_A
    );

    // 4. NUEVO: Calcular el máximo de ausencias simultáneas día a día
    //    durante el período de la solicitud
    $max_ausencias_simultaneas = hrm_calcular_max_ausencias_simultaneas(
        $fecha_inicio_solicitud,
        $fecha_fin_solicitud,
        $solicitudes_solapadas
    );

    // 5. Calcular personal disponible considerando:
    //    - La nueva solicitud (1 persona)
    //    - Las ausencias ya aprobadas que se solapan (max simultáneas)
    $total_ausentes = 1 + $max_ausencias_simultaneas; // +1 por la solicitud actual
    $personal_disponible = $personal_vigente - $total_ausentes;

    error_log( "HRM VALIDACIÓN PERSONAL MÍNIMO (MEJORADA):" );
    error_log( "  Departamento: $departamento" );
    error_log( "  Personal vigente actual: $personal_vigente" );
    error_log( "  Mínimo requerido: $minimo_requerido" );
    error_log( "  Período solicitud: $fecha_inicio_solicitud a $fecha_fin_solicitud" );
    error_log( "  Solicitudes aprobadas solapadas: " . count( $solicitudes_solapadas ) );
    error_log( "  Máximo ausencias simultáneas (ya aprobadas): $max_ausencias_simultaneas" );
    error_log( "  Total ausentes si se aprueba: $total_ausentes" );
    error_log( "  Personal disponible si se aprueba: $personal_disponible" );

    // 6. Validar si cumple el mínimo
    if ( $personal_disponible < $minimo_requerido ) {
        error_log( "HRM: RECHAZO - Personal disponible ($personal_disponible) menor al mínimo requerido ($minimo_requerido)" );
        return false;
    }

    error_log( "HRM: APROBACIÓN - Personal disponible ($personal_disponible) cumple el mínimo requerido ($minimo_requerido)" );
    return true;
}


/* =====================================================
 * HELPER: CALCULAR MÁXIMO DE AUSENCIAS SIMULTÁNEAS
 * =====================================================
 * Calcula el número máximo de empleados ausentes en un
 * mismo día dentro de un rango de fechas dado.
 *
 * @param string $fecha_inicio Fecha inicio del período a analizar
 * @param string $fecha_fin Fecha fin del período a analizar
 * @param array $solicitudes Array de solicitudes aprobadas
 * @return int Número máximo de ausencias simultáneas
 */
function hrm_calcular_max_ausencias_simultaneas( $fecha_inicio, $fecha_fin, $solicitudes ) {
    if ( empty( $solicitudes ) ) {
        return 0;
    }

    $inicio = new DateTime( $fecha_inicio );
    $fin = new DateTime( $fecha_fin );
    $fin->modify( '+1 day' ); // Incluir el último día

    $max_ausencias = 0;

    // Recorrer cada día del período
    $periodo = new DatePeriod( $inicio, new DateInterval( 'P1D' ), $fin );

    foreach ( $periodo as $fecha ) {
        $fecha_str = $fecha->format( 'Y-m-d' );
        $ausencias_dia = 0;

        // Contar cuántas solicitudes incluyen este día
        foreach ( $solicitudes as $sol ) {
            if ( $fecha_str >= $sol['fecha_inicio'] && $fecha_str <= $sol['fecha_fin'] ) {
                $ausencias_dia++;
            }
        }

        // Actualizar máximo si este día tiene más ausencias
        if ( $ausencias_dia > $max_ausencias ) {
            $max_ausencias = $ausencias_dia;
            error_log( "HRM: Día $fecha_str tiene $ausencias_dia ausencias (nuevo máximo)" );
        }
    }

    return $max_ausencias;
}


function hrm_calcular_dias_habiles( $fecha_inicio, $fecha_fin ) {

    $inicio = new DateTime( $fecha_inicio );
    $fin    = new DateTime( $fecha_fin );

    // Incluir el último día
    $fin->modify('+1 day');

    $periodo = new DatePeriod(
        $inicio,
        new DateInterval('P1D'),
        $fin
    );

    // Obtener feriados para el año (soportar múltiples años si el período los abarca)
    $anos = [];
    foreach ( $periodo as $fecha ) {
        $ano = $fecha->format('Y');
        if ( ! in_array( $ano, $anos ) ) {
            $anos[] = $ano;
        }
    }

    // Consolidar todos los feriados
    $todos_feriados = [];
    foreach ( $anos as $ano ) {
        $feriados_ano = hrm_get_feriados( $ano );
        $todos_feriados = array_merge( $todos_feriados, array_keys( $feriados_ano ) );
    }

    $dias = 0;

    foreach ( $periodo as $fecha ) {
        $fecha_str = $fecha->format('Y-m-d');
        
        // 1 = lunes ... 5 = viernes (excluir sábado y domingo)
        // También excluir feriados
        if ( $fecha->format('N') < 6 && ! in_array( $fecha_str, $todos_feriados ) ) {
            $dias++;
        }
    }

    return $dias;
}


function hrm_analizar_personas_en_vacaciones_hoy() {
    global $wpdb;

    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_tipos = $wpdb->prefix . 'rrhh_tipo_ausencia';

    // Fecha actual
    $hoy = current_time( 'Y-m-d' );

    // Obtener todas las solicitudes aprobadas
    $solicitudes_aprobadas = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                s.id_solicitud,
                e.nombre,
                e.apellido,
                e.departamento,
                s.fecha_inicio,
                s.fecha_fin,
                s.total_dias,
                t.nombre AS tipo_ausencia
             FROM {$table_solicitudes} s
             JOIN {$table_empleados} e ON s.id_empleado = e.id_empleado
             JOIN {$table_tipos} t ON s.id_tipo = t.id_tipo
             WHERE s.estado = 'APROBADA'
             ORDER BY e.departamento ASC, s.fecha_inicio ASC",
            array()
        ),
        ARRAY_A
    );

    // Analizar cuáles están activas hoy
    $resultado = [
        'total_en_vacaciones' => 0,
        'por_departamento' => [],
        'fecha_analisis' => $hoy
    ];

    if ( empty( $solicitudes_aprobadas ) ) {
        error_log( "HRM: No hay solicitudes aprobadas para analizar" );
        return $resultado;
    }

    foreach ( $solicitudes_aprobadas as $solicitud ) {
        $fecha_inicio = $solicitud['fecha_inicio'];
        $fecha_fin = $solicitud['fecha_fin'];
        $departamento = $solicitud['departamento'] ?: 'Sin Departamento';

        // Verificar si hoy está dentro del rango de vacaciones
        if ( $hoy >= $fecha_inicio && $hoy <= $fecha_fin ) {
            
            // Inicializar departamento si no existe
            if ( ! isset( $resultado['por_departamento'][$departamento] ) ) {
                $resultado['por_departamento'][$departamento] = [
                    'cantidad' => 0,
                    'empleados' => []
                ];
            }

            // Agregar empleado
            $resultado['por_departamento'][$departamento]['empleados'][] = [
                'nombre' => $solicitud['nombre'],
                'apellido' => $solicitud['apellido'],
                'fecha_inicio' => $solicitud['fecha_inicio'],
                'fecha_fin' => $solicitud['fecha_fin'],
                'total_dias' => $solicitud['total_dias'],
                'tipo_ausencia' => $solicitud['tipo_ausencia']
            ];

            $resultado['por_departamento'][$departamento]['cantidad']++;
            $resultado['total_en_vacaciones']++;

            error_log( "HRM: {$solicitud['nombre']} {$solicitud['apellido']} ({$departamento}) en vacaciones hoy" );
        }
    }

    return $resultado;
}


/* =====================================================
 * ACTUALIZAR: Personal Vigente según Vacaciones
 * =====================================================
 * Sincroniza la columna personal_vigente de cada departamento
 * basándose en el total real de empleados menos los que están
 * en vacaciones hoy.
 *
 * IMPORTANTE:
 * - Obtiene el total_empleados dinámicamente contando empleados
 *   activos en la tabla empleados (no desde tabla departamentos)
 * - Verifica que coincida con el registro en tabla departamentos
 * - Si hay discrepancia, la reporta y usa el conteo real
 * - Calcula vigentes: total_real - personas_en_vacaciones_hoy
 *
 * VALIDACIÓN:
 * - Verifica que personal_vigente + personas_en_vacaciones = total_empleados_real
 * - Asegura que personal_vigente nunca sea negativo
 * - Detecta discrepancias entre tabla departamentos y tabla empleados
 *
 * Fórmula:
 * personal_vigente = total_empleados_real - personas_en_vacaciones_hoy
 *
 * Donde:
 * - total_empleados_real: conteo dinámico de empleados por departamento
 * - personas_en_vacaciones_hoy: obtenido de solicitudes APROBADAS activas hoy
 *
 * @return array Resultado de la actualización con detalles por departamento
 */
function hrm_actualizar_personal_vigente_por_vacaciones() {
    global $wpdb;

    $table_departamentos = $wpdb->prefix . 'rrhh_departamentos';
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';

    $hoy = current_time( 'Y-m-d' );

    $resultado = [
        'exitoso' => true,
        'departamentos_actualizados' => 0,
        'detalles' => [],
        'errores' => [],
        'advertencias' => []
    ];

    // 1. Obtener todos los departamentos
    $departamentos = $wpdb->get_results(
        "SELECT id_departamento, nombre_departamento, total_empleados 
         FROM {$table_departamentos}
         ORDER BY nombre_departamento ASC",
        ARRAY_A
    );

    if ( empty( $departamentos ) ) {
        error_log( "HRM: No hay departamentos para actualizar" );
        $resultado['exitoso'] = false;
        return $resultado;
    }

    // 2. Procesar cada departamento
    foreach ( $departamentos as $depto ) {
        $id_depto = (int) $depto['id_departamento'];
        $nombre = sanitize_text_field( $depto['nombre_departamento'] );
        $total_registrado = (int) $depto['total_empleados'];

        // OBTENER TOTAL REAL: Contar solo empleados ACTIVOS en tabla empleados
        // estado = 1 significa empleado activo, estado = 0 significa inactivo
        $total_real = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(id_empleado)
                 FROM {$table_empleados}
                 WHERE departamento = %s
                 AND estado = 1",
                $nombre
            )
        );

        $total_real = (int) $total_real;

        // VERIFICAR DISCREPANCIA
        if ( $total_real !== $total_registrado ) {
            $advertencia = "Departamento '$nombre': Total registrado ({$total_registrado}) ≠ Total real ({$total_real}). Usando total real.";
            error_log( "HRM Warning: {$advertencia}" );
            $resultado['advertencias'][] = $advertencia;
            
            // Actualizar tabla departamentos con el total correcto
            $wpdb->update(
                $table_departamentos,
                [ 'total_empleados' => $total_real ],
                [ 'id_departamento' => $id_depto ],
                [ '%d' ],
                [ '%d' ]
            );
        }

        // CONTAR PERSONAS EN VACACIONES HOY
        $personas_vacaciones = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT s.id_empleado)
                 FROM {$table_solicitudes} s
                 JOIN {$table_empleados} e ON s.id_empleado = e.id_empleado
                 WHERE e.departamento = %s
                 AND s.estado = 'APROBADA'
                 AND %s BETWEEN s.fecha_inicio AND s.fecha_fin",
                $nombre,
                $hoy
            )
        );

        $personas_vacaciones = (int) $personas_vacaciones;

        // VALIDACIÓN: no puede haber más personas en vacaciones que el total real
        if ( $personas_vacaciones > $total_real ) {
            $error_msg = "Departamento '$nombre': {$personas_vacaciones} en vacaciones excede total real de {$total_real}";
            error_log( "HRM Error: {$error_msg}" );
            $resultado['errores'][] = $error_msg;
            $resultado['exitoso'] = false;
            continue;
        }

        // CALCULAR PERSONAL VIGENTE
        $personal_vigente = $total_real - $personas_vacaciones;
        
        // VALIDACIÓN: debe ser >= 0
        if ( $personal_vigente < 0 ) {
            $personal_vigente = 0;
        }

        // ACTUALIZAR EN BASE DE DATOS
        $actualizado = $wpdb->update(
            $table_departamentos,
            [ 'personal_vigente' => $personal_vigente ],
            [ 'id_departamento' => $id_depto ],
            [ '%d' ],
            [ '%d' ]
        );

        if ( false !== $actualizado ) {
            $resultado['departamentos_actualizados']++;
            $resultado['detalles'][] = [
                'id_departamento' => $id_depto,
                'nombre' => $nombre,
                'total_empleados_real' => $total_real,
                'total_registrado' => $total_registrado,
                'discrepancia' => ( $total_real !== $total_registrado ),
                'personas_en_vacaciones' => $personas_vacaciones,
                'personal_vigente' => $personal_vigente,
                'verificacion' => ( $personal_vigente + $personas_vacaciones === $total_real ) ? 'OK' : 'ERROR'
            ];

            error_log( "HRM: Actualizado '$nombre' → Total real: $total_real, Vacaciones: $personas_vacaciones, Vigente: $personal_vigente (verificado)" );
        } else {
            $error_msg = "No se pudo actualizar departamento '$nombre'";
            error_log( "HRM Error: {$error_msg}" );
            $resultado['errores'][] = $error_msg;
            $resultado['exitoso'] = false;
        }
    }

    error_log( "HRM: Sincronización completada - {$resultado['departamentos_actualizados']} departamentos actualizados" );
    if ( ! empty( $resultado['advertencias'] ) ) {
        error_log( "HRM: Advertencias detectadas - " . count( $resultado['advertencias'] ) . " discrepancias encontradas" );
    }

    // Limpiar caché de departamentos para que se obtengan datos frescos
    hrm_clear_departamentos_cache();

    return $resultado;
}


/**
 * =====================================================
 * OBTENER TODOS LOS DEPARTAMENTOS CON SUS DATOS
 * =====================================================
 * Retorna una lista completa de todos los departamentos
 * con sus datos: nombre, total de empleados, personal vigente
 * y personal mínimo requerido.
 *
 * @return array Array de departamentos con estructura:
 *         [
 *           [
 *             'id_departamento' => int,
 *             'nombre_departamento' => string,
 *             'total_empleados' => int,
 *             'personal_vigente' => int,
 *             'minimo_empleados' => int
 *           ],
 *           ...
 *         ]
 */
function hrm_get_all_departamentos() {
    global $wpdb;

    $table_departamentos = $wpdb->prefix . 'rrhh_departamentos';

    $departamentos = $wpdb->get_results(
        "SELECT 
            id_departamento,
            nombre_departamento,
            total_empleados,
            personal_vigente,
            minimo_empleados
         FROM {$table_departamentos}
         ORDER BY nombre_departamento ASC",
        ARRAY_A
    );

    return $departamentos;
}

/**
 * =====================================================
 * LIMPIAR CACHÉ DE DEPARTAMENTOS
 * =====================================================
 * Elimina el caché de departamentos para forzar
 * que se obtengan datos frescos en la próxima consulta.
 * Se debe llamar cuando se actualiza información de departamentos.
 */
function hrm_clear_departamentos_cache() {
    wp_cache_delete( 'hrm_all_departamentos' );
}

/**
 * =====================================================
 * OBTENER TOTAL DE EMPLEADOS DE UN DEPARTAMENTO
 * =====================================================
 * Retorna el total de empleados registrados en un departamento específico.
 * 
 * Para el departamento "Gerencia": Obtiene desde Bu6K9_rrhh_empleados 
 *   donde departamento = 'Gerencia' y estado = 1
 * 
 * Para otros departamentos: Obtiene desde Bu6K9_rrhh_departamentos
 * 
 * @param string $nombre_departamento Nombre del departamento
 * @return int Total de empleados del departamento
 */
function hrm_get_total_empleados_departamento( $nombre_departamento ) {
    global $wpdb;
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_departamentos = $wpdb->prefix . 'rrhh_departamentos';
    
    // Si es Gerencia, obtener directamente desde empleados con departamento = 'Gerencia'
    if ( strtolower( $nombre_departamento ) === 'gerencia' ) {
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_empleados} 
                 WHERE departamento = %s AND estado = %d",
                'Gerencia',
                1
            )
        );
    } else {
        // Para otros departamentos, obtener de la tabla departamentos
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT total_empleados FROM {$table_departamentos} WHERE nombre_departamento = %s",
                $nombre_departamento
            )
        );
    }
    
    return $total;
}

/**
 * =====================================================
 * OBTENER GERENTES EN VACACIONES HOY
 * =====================================================
 * Retorna el número de gerentes (departamento='Gerencia')
 * que tienen solicitudes de ausencia aprobadas hoy.
 * 
 * @return int Número de gerentes en vacaciones hoy
 */
function hrm_get_gerentes_vacaciones_hoy() {
    global $wpdb;
    
    $hoy = current_time( 'Y-m-d' );
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    
    $gerentes_vacaciones = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT e.id_empleado) 
             FROM {$table_empleados} e
             INNER JOIN {$table_solicitudes} s ON e.id_empleado = s.id_empleado
             WHERE e.departamento = %s 
             AND e.estado = %d
             AND s.estado = %s
             AND %s BETWEEN s.fecha_inicio AND s.fecha_fin",
            'Gerencia',
            1,
            'APROBADA',
            $hoy
        )
    );
    
    return $gerentes_vacaciones;
}

/**
 * =====================================================
 * OBTENER GERENTES ACTIVOS HOY
 * =====================================================
 * Retorna el número de gerentes (departamento='Gerencia')
 * que están trabajando hoy (total - en vacaciones).
 * 
 * @return int Número de gerentes activos hoy
 */
function hrm_get_gerentes_activos_hoy() {
    $total_gerentes = hrm_get_total_empleados_departamento( 'Gerencia' );
    $gerentes_vacaciones = hrm_get_gerentes_vacaciones_hoy();
    
    $activos = $total_gerentes - $gerentes_vacaciones;
    
    return max( 0, $activos );
}

/**
 * =====================================================
 * SINCRONIZAR PERSONAL VIGENTE (MANUAL - ADMIN)
 * =====================================================
 * Handler que permite al administrador ejecutar manualmente
 * la sincronización de personal vigente por vacaciones.
 *
 * Se ejecuta vía admin_post y retorna JSON con el resultado.
 */
function hrm_manual_sincronizar_personal_vigente() {
    // Verificar permisos
    if ( ! is_admin() || ! ( current_user_can( 'manage_options' ) || current_user_can( 'manage_hrm_vacaciones' ) ) ) {
        wp_send_json_error( [
            'mensaje' => 'No tienes permisos para ejecutar esta acción.',
            'code' => 'permission_denied'
        ], 403 );
    }

    // Verificar nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'hrm_sincronizar_personal' ) ) {
        wp_send_json_error( [
            'mensaje' => 'Verificación de seguridad fallida.',
            'code' => 'invalid_nonce'
        ], 403 );
    }

    // Ejecutar la sincronización
    $resultado = hrm_actualizar_personal_vigente_por_vacaciones();

    if ( $resultado['exitoso'] ) {
        wp_send_json_success( [
            'mensaje' => 'Personal vigente sincronizado correctamente.',
            'departamentos_actualizados' => $resultado['departamentos_actualizados'],
            'detalles' => $resultado['detalles']
        ] );
    } else {
        wp_send_json_error( [
            'mensaje' => 'Error durante la sincronización.',
            'errores' => $resultado['errores']
        ] );
    }
}

add_action( 'wp_ajax_hrm_sincronizar_personal_vigente', 'hrm_manual_sincronizar_personal_vigente' );

/**
 * =====================================================
 * OBTENER FERIADOS VÍA AJAX
 * =====================================================
 * Endpoint AJAX que retorna los feriados de un año específico.
 * Utilizado por el calendario para cargar dinámicamente los feriados.
 */
function hrm_get_feriados_ajax() {
    // Soportar tanto GET como POST
    $ano = isset( $_REQUEST['ano'] ) ? intval( $_REQUEST['ano'] ) : (int) date( 'Y' );
    
    $feriados = hrm_get_feriados( $ano );
    
    wp_send_json_success( $feriados );
}

add_action( 'wp_ajax_hrm_get_feriados', 'hrm_get_feriados_ajax' );

/**
 * =====================================================
 * OBTENER VACACIONES POR DEPARTAMENTO VÍA AJAX
 * =====================================================
 * Endpoint AJAX que retorna las solicitudes aprobadas
 * filtradas por departamento, para mostrar en el calendario.
 *
 * @return JSON Array de vacaciones con estructura:
 *         [
 *           {
 *             'fecha_inicio': 'YYYY-MM-DD',
 *             'fecha_fin': 'YYYY-MM-DD',
 *             'empleado': 'Nombre Apellido'
 *           },
 *           ...
 *         ]
 */
function hrm_get_vacaciones_calendario_ajax() {
    $departamento = isset( $_POST['departamento'] ) ? sanitize_text_field( $_POST['departamento'] ) : '';
    
    global $wpdb;
    
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_tipos = $wpdb->prefix . 'rrhh_tipo_ausencia';

    // Construir la consulta
    if ( empty( $departamento ) ) {
        // Si no hay departamento, obtener todas las vacaciones aprobadas
        $solicitudes = $wpdb->get_results(
            "SELECT 
                e.nombre,
                e.apellido,
                s.fecha_inicio,
                s.fecha_fin,
                e.departamento
             FROM {$table_solicitudes} s
             JOIN {$table_empleados} e ON s.id_empleado = e.id_empleado
             WHERE s.estado = 'APROBADA'
             ORDER BY s.fecha_inicio ASC",
            ARRAY_A
        );
    } else {
        // Si hay departamento, filtrar por ese departamento
        $solicitudes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    e.nombre,
                    e.apellido,
                    s.fecha_inicio,
                    s.fecha_fin,
                    e.departamento
                 FROM {$table_solicitudes} s
                 JOIN {$table_empleados} e ON s.id_empleado = e.id_empleado
                 WHERE s.estado = 'APROBADA'
                 AND e.departamento = %s
                 ORDER BY s.fecha_inicio ASC",
                $departamento
            ),
            ARRAY_A
        );
    }

    // Transformar los datos al formato esperado por el calendario
    $vacaciones = [];
    foreach ( $solicitudes as $s ) {
        $vacaciones[] = [
            'fecha_inicio' => $s['fecha_inicio'],
            'fecha_fin' => $s['fecha_fin'],
            'empleado' => $s['nombre'] . ' ' . $s['apellido']
        ];
    }

    wp_send_json_success( $vacaciones );
}

add_action( 'wp_ajax_hrm_get_vacaciones_calendario', 'hrm_get_vacaciones_calendario_ajax' );

/**
 * =====================================================
 * GUARDAR RESPUESTA DE RRHH/JEFATURA (Admin)
 * =====================================================
 * Procesa el formulario de respuesta de RRHH cuando es editado por un admin
 */
function hrm_guardar_respuesta_rrhh_handler() {
    // Verificar permisos
    if ( ! ( current_user_can( 'manage_options' ) || current_user_can( 'manage_hrm_vacaciones' ) || current_user_can( 'edit_hrm_employees' ) ) ) {
        wp_die( 'No tienes permisos para realizar esta acción.' );
    }

    // Verificar nonce
    if ( ! isset( $_POST['hrm_nonce'] ) || ! wp_verify_nonce( $_POST['hrm_nonce'], 'hrm_respuesta_rrhh' ) ) {
        wp_die( 'Error de seguridad. Por favor, intenta de nuevo.' );
    }

    global $wpdb;
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';

    // Obtener ID de solicitud
    $id_solicitud = absint( $_POST['solicitud_id'] ?? 0 );
    if ( ! $id_solicitud ) {
        wp_die( 'ID de solicitud inválido.' );
    }

    // Verificar que la solicitud exista
    $solicitud = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table_solicitudes} WHERE id_solicitud = %d",
        $id_solicitud
    ) );

    if ( ! $solicitud ) {
        wp_die( 'Solicitud no encontrada.' );
    }

    // ★ VALIDACIÓN CRÍTICA: Prevenir edición de solicitudes aprobadas o rechazadas
    if ( $solicitud->estado !== 'PENDIENTE' ) {
        wp_die( 'No se puede editar una solicitud que ya ha sido aprobada o rechazada. Las solicitudes bloqueadas solo pueden ser visualizadas.' );
    }

    // Obtener datos del formulario
    $respuesta_rrhh = sanitize_key( $_POST['respuesta_rrhh'] ?? '' );
    $nombre_jefe = sanitize_text_field( $_POST['nombre_jefe'] ?? '' );
    $fecha_respuesta = sanitize_text_field( $_POST['fecha_respuesta'] ?? current_time( 'Y-m-d' ) );
    $observaciones = sanitize_textarea_field( $_POST['observaciones_rrhh'] ?? '' );

    // Mapear respuesta a estado
    $estado_map = [
        'aceptado' => 'APROBADA',
        'rechazado' => 'RECHAZADA',
        'pendiente' => 'PENDIENTE',
    ];

    $nuevo_estado = $estado_map[ $respuesta_rrhh ] ?? $solicitud->estado;

    // Actualizar la solicitud
    $actualizado = $wpdb->update(
        $table_solicitudes,
        [
            'estado' => $nuevo_estado,
            'nombre_jefe' => $nombre_jefe,
            'fecha_respuesta' => $fecha_respuesta,
            'motivo_rechazo' => $observaciones,
        ],
        [ 'id_solicitud' => $id_solicitud ],
        [ '%s', '%s', '%s', '%s' ],
        [ '%d' ]
    );

    if ( $actualizado === false ) {
        wp_die( 'Error al guardar los cambios.' );
    }

    // Enviar email de notificación al empleado si cambió de estado
    if ( $nuevo_estado !== $solicitud->estado ) {
        hrm_enviar_notificacion_vacaciones( $id_solicitud, $nuevo_estado );
    }

    // Redireccionar de vuelta a la lista de solicitudes
    $redirect = wp_get_referer() ?: admin_url( 'admin.php?page=hrm-vacaciones' );
    wp_safe_redirect( add_query_arg( 'mensaje', 'actualizado', $redirect ) );
    exit;
}
add_action( 'admin_post_hrm_guardar_respuesta_rrhh', 'hrm_guardar_respuesta_rrhh_handler' );
/**
 * =====================================================
 * OBTENER DEPARTAMENTOS DE UN ÁREA GERENCIAL
 * =====================================================
 * Endpoint AJAX para obtener los departamentos a cargo
 * de un área gerencial específica
 */
function hrm_get_deptos_area_ajax() {
    $area_gerencial = isset( $_POST['area_gerencial'] ) ? sanitize_text_field( $_POST['area_gerencial'] ) : '';
    
    if ( empty( $area_gerencial ) ) {
        wp_send_json_error( [ 'mensaje' => 'Área gerencial no especificada' ] );
    }
    
    require_once plugin_dir_path( __FILE__ ) . 'db/class-hrm-db-gerencia-deptos.php';
    $db_gerencia = new HRM_DB_Gerencia_Deptos();
    $deptos = $db_gerencia->get_deptos_by_area( $area_gerencial );
    
    wp_send_json_success( $deptos );
}

add_action( 'wp_ajax_hrm_get_deptos_area', 'hrm_get_deptos_area_ajax' );

/**
 * =====================================================
 * OBTENER PERSONAL ACTIVO HOY POR DEPARTAMENTO
 * =====================================================
 * Retorna el número de empleados que están trabajando hoy
 * (excluye los que están de vacaciones aprobadas)
 * Solo cuenta empleados ACTIVOS (estado = 1)
 * 
 * Personal Activo = Total empleados activos - los que tengan solicitudes en rango que incluya hoy
 */
function hrm_get_personal_activo_hoy( $departamento ) {
    global $wpdb;
    
    $hoy = current_time( 'Y-m-d' );
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    
    // Obtener total de empleados ACTIVOS del departamento
    $total_empleados = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_empleados 
         WHERE departamento = %s AND estado = %d",
        $departamento,
        1
    ) );
    
    // Obtener empleados ACTIVOS que tienen solicitudes aprobadas HOY (donde hoy esté en el rango de fechas)
    $empleados_de_vacaciones = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT e.id_empleado) 
         FROM $table_empleados e
         INNER JOIN $table_solicitudes s ON e.id_empleado = s.id_empleado
         WHERE e.departamento = %s 
         AND e.estado = %d
         AND s.estado = %s
         AND s.fecha_inicio <= %s
         AND s.fecha_fin >= %s",
        $departamento,
        1,
        'APROBADA',
        $hoy,
        $hoy
    ) );
    
    // Personal activo = total - los que están de vacaciones hoy
    return max( 0, $total_empleados - $empleados_de_vacaciones );
}

/**
 * =====================================================
 * OBTENER PERSONAL EN VACACIONES HOY POR DEPARTAMENTO
 * =====================================================
 * Retorna el número de empleados que están de vacaciones hoy
 * (solicitudes aprobadas donde hoy está entre fecha_inicio y fecha_fin)
 * Solo cuenta empleados ACTIVOS (estado = 1)
 */
function hrm_get_personal_vacaciones_hoy( $departamento ) {
    global $wpdb;
    
    $hoy = current_time( 'Y-m-d' );
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    
    $empleados_vacaciones = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT e.id_empleado) 
         FROM $table_empleados e
         INNER JOIN $table_solicitudes s ON e.id_empleado = s.id_empleado
         WHERE e.departamento = %s 
         AND e.estado = %d
         AND s.estado = %s
         AND %s BETWEEN s.fecha_inicio AND s.fecha_fin",
        $departamento,
        1,
        'APROBADA',
        $hoy
    ) );
    
    return (int) $empleados_vacaciones;
}

/**
 * =====================================================
 * OBTENER DETALLE DE EMPLEADOS EN VACACIONES HOY
 * =====================================================
 * Retorna lista de empleados con nombre, apellido y período de vacaciones
 * para un departamento específico en el día actual
 * Solo incluye empleados ACTIVOS (estado = 1)
 */
function hrm_get_empleados_vacaciones_hoy_detalle( $departamento ) {
    global $wpdb;
    
    $hoy = current_time( 'Y-m-d' );
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    
    $empleados = $wpdb->get_results( $wpdb->prepare(
        "SELECT DISTINCT
            e.id_empleado,
            e.nombre,
            e.apellido,
            DATE(s.fecha_inicio) as fecha_inicio,
            DATE(s.fecha_fin) as fecha_fin,
            s.id_solicitud
         FROM $table_empleados e
         INNER JOIN $table_solicitudes s ON e.id_empleado = s.id_empleado
         WHERE e.departamento = %s 
         AND e.estado = %d
         AND s.estado = %s
         AND DATE(s.fecha_inicio) <= %s
         AND DATE(s.fecha_fin) >= %s
         ORDER BY e.apellido, e.nombre",
        $departamento,
        1,
        'APROBADA',
        $hoy,
        $hoy
    ) );
    
    return $empleados;
}

/**
 * =====================================================
 * ENDPOINT AJAX: OBTENER EMPLEADOS EN VACACIONES HOY
 * =====================================================
 */
function hrm_get_empleados_vacaciones_hoy_ajax() {
    $departamento = isset( $_POST['departamento'] ) ? sanitize_text_field( $_POST['departamento'] ) : '';
    
    if ( empty( $departamento ) ) {
        wp_send_json_error( [ 'mensaje' => 'Departamento no especificado' ] );
    }
    
    $empleados = hrm_get_empleados_vacaciones_hoy_detalle( $departamento );
    
    // Formatear respuesta
    $datos = [];
    foreach ( $empleados as $emp ) {
        $datos[] = [
            'nombre' => $emp->nombre . ' ' . $emp->apellido,
            'fecha_inicio' => $emp->fecha_inicio,
            'fecha_fin' => $emp->fecha_fin,
        ];
    }
    
    wp_send_json_success( $datos );
}

add_action( 'wp_ajax_hrm_get_empleados_vacaciones_hoy', 'hrm_get_empleados_vacaciones_hoy_ajax' );

/**
 * =====================================================
 * OBTENER DATOS PARA TOOLTIP DE VACACIONES
 * =====================================================
 * Retorna nombres con días restantes para que vuelvan
 * Ejemplo: "Claudio vuelve en: 2 días"
 * Esto es DINÁMICO: se actualiza cada día
 */
function hrm_get_tooltip_vacaciones_hoy( $departamento ) {
    global $wpdb;
    
    $hoy = current_time( 'Y-m-d' );
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    
    $empleados = $wpdb->get_results( $wpdb->prepare(
        "SELECT DISTINCT
            e.nombre,
            e.apellido,
            DATE(s.fecha_inicio) as fecha_inicio,
            DATE(s.fecha_fin) as fecha_fin
         FROM $table_empleados e
         INNER JOIN $table_solicitudes s ON e.id_empleado = s.id_empleado
         WHERE e.departamento = %s 
         AND e.estado = %d
         AND s.estado = %s
         AND DATE(s.fecha_inicio) <= %s
         AND DATE(s.fecha_fin) >= %s
         ORDER BY e.apellido, e.nombre",
        $departamento,
        1,
        'APROBADA',
        $hoy,
        $hoy
    ) );
    
    $tooltip_lines = [];
    foreach ( $empleados as $emp ) {
        // Calcular días restantes hasta que termine la vacación
        $fecha_fin_obj = DateTime::createFromFormat( 'Y-m-d', $emp->fecha_fin );
        $hoy_obj = DateTime::createFromFormat( 'Y-m-d', $hoy );
        
        if ( $fecha_fin_obj && $hoy_obj ) {
            $dias_restantes = $fecha_fin_obj->diff( $hoy_obj )->days;
            
            // Si es hoy el último día de vacaciones, vuelve MAÑANA
            if ( $dias_restantes === 0 ) {
                $mensaje = $emp->nombre . ' ' . $emp->apellido . ' vuelve: MAÑANA';
            } else {
                $palabra_dias = $dias_restantes === 1 ? 'día' : 'días';
                $mensaje = $emp->nombre . ' ' . $emp->apellido . ' vuelve en: ' . $dias_restantes . ' ' . $palabra_dias;
            }
            
            $tooltip_lines[] = $mensaje;
        }
    }
    
    return implode( '\n', $tooltip_lines );
}
/**
 * Calcula los días de vacaciones según antigüedad (Ley Chilena)
 * 
 * Base: 15 días
 * Progresivos: Si (años_acreditados_anteriores + años_en_empresa) > 10
 *              Entonces: +1 día por cada 3 años completos en la empresa actual
 * 
 * Ejemplos:
 * - Empleado nuevo: 15 días
 * - 5 años en empresa: 15 días (no cumple > 10 años totales)
 * - 8 años acreditados + 3 años en empresa (11 años total): 15 + 1 = 16 días
 * - 8 años acreditados + 9 años en empresa (17 años total): 15 + 3 = 18 días
 * 
 * @param int $id_empleado ID del empleado
 * @return int Número de días de vacaciones anuales
 */
function hrm_calcular_dias_segun_antiguedad( $id_empleado ) {
    global $wpdb;
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    
    $empleado = $wpdb->get_row( $wpdb->prepare(
        "SELECT 
            fecha_ingreso,
            COALESCE(anos_acreditados_anteriores, 0) as anos_acreditados_anteriores
         FROM $table_empleados
         WHERE id_empleado = %d",
        $id_empleado
    ) );
    
    if ( ! $empleado || empty( $empleado->fecha_ingreso ) ) {
        // Si no hay datos válidos, retornar 15 días (estándar)
        return 15;
    }
    
    try {
        $fecha_inicio = new DateTime( $empleado->fecha_ingreso );
        $hoy = new DateTime();
        $diferencia = $fecha_inicio->diff( $hoy );
    } catch ( Exception $e ) {
        // Si hay error en fecha, retornar estándar
        return 15;
    }
    
    $anos_en_empresa = $diferencia->y;
    $anos_acreditados = (int) $empleado->anos_acreditados_anteriores;
    $anos_totales = $anos_en_empresa + $anos_acreditados;
    
    // Base: 15 días
    $dias_base = 15;
    
    // Progresivos: +1 día por cada 3 años en la empresa actual
    // Solo si años totales > 10 Y años en empresa >= 3
    $dias_progresivos = 0;
    if ( $anos_totales > 10 && $anos_en_empresa >= 3 ) {
        $dias_progresivos = floor( $anos_en_empresa / 3 );
    }
    
    return $dias_base + $dias_progresivos;
}

/**
 * =====================================================
 * ACTUALIZAR DÍAS DE VACACIONES POR ANIVERSARIO
 * =====================================================
 * Verifica si se cumplió el aniversario de ingreso y resetea
 * automáticamente los días de vacaciones (período anual)
 * 
 * AHORA ACTIVADO: Usa cálculo dinámico según antigüedad (Ley Chilena)
 * Se renuevan los días anualmente desde fecha_ingreso
 * Ej: Si ingresó 15/03/2021 con 8 años previos:
 *     - 15/03/2022: Se renuevan calculados (actualmente < 10 años = 15)
 *     - 15/03/2025: Se renuevan calculados (11+ años = 16+ días)
 * 
 * @param int $id_empleado ID del empleado
 * @return bool True si se actualizó, false si no
 */
function hrm_actualizar_dias_vacaciones_por_aniversario( $id_empleado ) {
    global $wpdb;
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    
    // Obtener empleado con fecha_ingreso y última fecha de actualización
    $empleado = $wpdb->get_row( $wpdb->prepare(
        "SELECT id_empleado, fecha_ingreso, dias_vacaciones_anuales, ultima_actualizacion_vacaciones
         FROM $table_empleados
         WHERE id_empleado = %d",
        $id_empleado
    ) );
    
    if ( ! $empleado || empty( $empleado->fecha_ingreso ) ) {
        return false;
    }
    
    // Calcular fechas importantes
    $fecha_ingreso = new DateTime( $empleado->fecha_ingreso );
    $hoy = new DateTime();
    
    // Calcular próximo aniversario (fecha_ingreso + 1 año desde la última actualización)
    $ultima_actualizacion = $empleado->ultima_actualizacion_vacaciones ? 
        new DateTime( $empleado->ultima_actualizacion_vacaciones ) : 
        $fecha_ingreso;
    
    $proximo_aniversario = clone $ultima_actualizacion;
    $proximo_aniversario->add( new DateInterval( 'P1Y' ) );
    
    // Si HOY >= próximo aniversario, debe resetear los días
    if ( $hoy >= $proximo_aniversario ) {
        // ACTIVACIÓN: Usar cálculo según Ley Chilena basado en antigüedad
        $dias_segun_ley = hrm_calcular_dias_segun_antiguedad( $id_empleado );
        $dias_nuevos_periodo = $dias_segun_ley;  // Días del nuevo período según antigüedad
        
        // Sumar días nuevos a los que ya tenía (los no usados del período anterior)
        $nuevos_dias = $empleado->dias_vacaciones_anuales + $dias_nuevos_periodo;
        
        $actualizado = $wpdb->update(
            $table_empleados,
            [
                'dias_vacaciones_anuales' => $nuevos_dias,  // 15 nuevos + los no usados
                'ultima_actualizacion_vacaciones' => current_time( 'mysql' ),
            ],
            [ 'id_empleado' => $id_empleado ],
            [ '%d', '%s' ],
            [ '%d' ]
        );
        
        if ( $actualizado !== false ) {
            error_log( "HRM: Días de vacaciones renovados para empleado $id_empleado. Nuevos días: $nuevos_dias (15 nuevos + " . $empleado->dias_vacaciones_anuales . " del período anterior)" );
            return true;
        }
    }
    
    return false;
}


/**
 * OBTENER DÍAS ACUMULADOS DE LOS ÚLTIMOS 2 AÑOS
 * =====================================================
 * Obtiene los días vacaciones disponibles del empleado directamente de la BD
 * Esta columna ya contiene el total acumulado (máximo 2 años de acumulación)
 * 
 * @param int $id_empleado ID del empleado
 * @return int Días acumulados
 */
function hrm_get_dias_acumulados_dos_anos( $id_empleado ) {
    global $wpdb;
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    
    // Obtener los días acumulados directamente de la BD
    $empleado = $wpdb->get_row( $wpdb->prepare(
        "SELECT dias_vacaciones_disponibles
         FROM $table_empleados
         WHERE id_empleado = %d",
        $id_empleado
    ) );
    
    if ( ! $empleado ) {
        return 0;
    }
    
    return (int) $empleado->dias_vacaciones_disponibles;
}


/**
 * OBTENER INFORMACIÓN DEL PRÓXIMO ANIVERSARIO
 * =====================================================
 * Calcula la fecha del próximo aniversario (renovación de vacaciones)
 * y retorna cuántos meses faltan para que ocurra
 * 
 * @param int $id_empleado ID del empleado
 * @return array|false Array con ['fecha_aniversario', 'meses_restantes', 'es_proximo_aniversario'] o false
 */
function hrm_get_proximo_aniversario_info( $id_empleado ) {
    global $wpdb;
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    
    $empleado = $wpdb->get_row( $wpdb->prepare(
        "SELECT fecha_ingreso, ultima_actualizacion_vacaciones
         FROM $table_empleados
         WHERE id_empleado = %d",
        $id_empleado
    ) );
    
    if ( ! $empleado || empty( $empleado->fecha_ingreso ) ) {
        return false;
    }
    
    // La base es ultima_actualizacion_vacaciones, o fecha_ingreso si es la primera vez
    $ultima_actualizacion = $empleado->ultima_actualizacion_vacaciones ? 
        new DateTime( $empleado->ultima_actualizacion_vacaciones ) : 
        new DateTime( $empleado->fecha_ingreso );
    
    // El próximo aniversario es 1 año desde la última actualización
    $proximo_aniversario = clone $ultima_actualizacion;
    $proximo_aniversario->add( new DateInterval( 'P1Y' ) );
    
    // Calcular meses restantes
    $hoy = new DateTime();
    $interval = $hoy->diff( $proximo_aniversario );
    
    // Convertir a meses: (años * 12) + meses
    $meses_restantes = ( $interval->y * 12 ) + $interval->m;
    
    // Ajuste: si han pasado días, contar el mes actual si quedan días
    if ( $interval->d > 0 && $hoy < $proximo_aniversario ) {
        // Los días restantes cuentan como parte del mes actual
    }
    
    return [
        'fecha_aniversario' => $proximo_aniversario->format( 'Y-m-d' ),
        'meses_restantes' => $meses_restantes,
        'es_proximo_aniversario' => $hoy >= $proximo_aniversario, // Ya llegó o pasó
    ];
}


/**
 * VERIFICAR SI DEBE MOSTRAR NOTIFICACIÓN DE EXCESO
 * =====================================================
 * Retorna true si:
 * - Días acumulados > 15 AND
 * - Meses restantes < 6 (o < 3, o < 2)
 * 
 * @param int $id_empleado ID del empleado
 * @return array|false Array con ['debe_notificar', 'nivel', 'dias_acumulados', 'meses_restantes'] o false
 */
function hrm_debe_mostrar_notificacion_exceso( $id_empleado ) {
    $dias_acumulados = hrm_get_dias_acumulados_dos_anos( $id_empleado );
    $info_aniversario = hrm_get_proximo_aniversario_info( $id_empleado );
    
    if ( ! $info_aniversario ) {
        return false;
    }
    
    // Si no tiene exceso de días, no notificar
    if ( $dias_acumulados <= 15 ) {
        return [
            'debe_notificar' => false,
            'nivel' => null,
            'dias_acumulados' => $dias_acumulados,
            'meses_restantes' => $info_aniversario['meses_restantes'],
        ];
    }
    
    $meses_restantes = $info_aniversario['meses_restantes'];
    $nivel = null;
    $debe_notificar = false;
    
    // Determinar nivel de urgencia
    if ( $meses_restantes < 2 ) {
        $nivel = 'critico'; // < 2 meses
        $debe_notificar = true;
    } elseif ( $meses_restantes < 3 ) {
        $nivel = 'alto'; // < 3 meses
        $debe_notificar = true;
    } elseif ( $meses_restantes < 6 ) {
        $nivel = 'medio'; // < 6 meses
        $debe_notificar = true;
    }
    
    return [
        'debe_notificar' => $debe_notificar,
        'nivel' => $nivel,
        'dias_acumulados' => $dias_acumulados,
        'meses_restantes' => $meses_restantes,
        'fecha_aniversario' => $info_aniversario['fecha_aniversario'],
    ];
}


/**
 * OBTENER MENSAJE DE NOTIFICACIÓN DE EXCESO
 * =====================================================
 * Retorna el mensaje HTML para mostrar al empleado
 * basado en el nivel de urgencia
 * 
 * @param int $id_empleado ID del empleado
 * @return string HTML del mensaje o string vacío
 */
function hrm_get_mensaje_notificacion_exceso( $id_empleado ) {
    $notificacion = hrm_debe_mostrar_notificacion_exceso( $id_empleado );
    
    if ( ! $notificacion || ! $notificacion['debe_notificar'] ) {
        return '';
    }
    
    $dias = $notificacion['dias_acumulados'];
    $meses = $notificacion['meses_restantes'];
    $fecha = date_create( $notificacion['fecha_aniversario'] )->format( 'd/m/Y' );
    $nivel = $notificacion['nivel'];
    
    // Determinar clase CSS y ícono según nivel
    if ( $nivel === 'critico' ) {
        $clase = 'hrm-notificacion-critica';
        $icono = '⚠️';
        $titulo = 'AVISO CRÍTICO: Exceso de Días de Vacaciones';
    } elseif ( $nivel === 'alto' ) {
        $clase = 'hrm-notificacion-alta';
        $icono = '⚡';
        $titulo = 'AVISO: Exceso de Días de Vacaciones';
    } else {
        $clase = 'hrm-notificacion-media';
        $icono = 'ℹ️';
        $titulo = 'Información: Exceso de Días de Vacaciones';
    }
    
    $mensaje = sprintf(
        '<div class="hrm-notificacion-exceso %s">
            <div class="hrm-notificacion-titulo">%s %s</div>
            <div class="hrm-notificacion-contenido">
                <p>Tienes <strong>%d días acumulados</strong> de vacaciones (máximo 2 años de acumulación).</p>
                <p>Tu próxima renovación de días es el <strong>%s</strong> (en %d mes%s).</p>
                <p>Se recomienda que disfrutes los días excedentes o planifiques tus vacaciones próximamente.</p>
            </div>
        </div>',
        esc_attr( $clase ),
        $icono,
        esc_html( $titulo ),
        $dias,
        esc_html( $fecha ),
        $meses,
        $meses !== 1 ? 'es' : ''
    );
    
    return $mensaje;
}


/* =====================================================
 * CÁLCULO DE VACACIONES LEGALES Y PROGRESIVAS - CHILE
 * =====================================================
 * Calcula el saldo de vacaciones considerando:
 * - Días base: 1.25 días por mes trabajado (15 días hábiles por año)
 * - Días progresivos: +1 día por cada 3 años en la empresa actual,
 *   siempre que (años_acreditados_anteriores + años_en_empresa) > 10
 * - Límite de acumulación: máximo 2 períodos anuales
 *
 * @param string $fecha_ingreso             Fecha de inicio en la empresa (Y-m-d)
 * @param int    $anos_acreditados_anteriores Años trabajados en otras empresas
 * @param float  $dias_vacaciones_usados    Total de días ya utilizados
 * @return array Información detallada del cálculo de vacaciones
 */
function hrm_calcular_saldo_vacaciones_chile( $fecha_ingreso, $anos_acreditados_anteriores = 0, $dias_vacaciones_usados = 0 ) {
    
    // =====================================================
    // VALIDACIONES DE ENTRADA
    // =====================================================
    
    // Validar fecha de ingreso
    if ( empty( $fecha_ingreso ) ) {
        return [
            'error' => true,
            'mensaje' => 'Fecha de ingreso no proporcionada',
            'codigo' => 'FECHA_VACIA',
        ];
    }
    
    try {
        $fecha_inicio = new DateTime( $fecha_ingreso );
        $hoy = new DateTime();
    } catch ( Exception $e ) {
        return [
            'error' => true,
            'mensaje' => 'Formato de fecha inválido: ' . $fecha_ingreso,
            'codigo' => 'FECHA_INVALIDA',
        ];
    }
    
    // Validar años acreditados (no puede ser negativo)
    $anos_acreditados_anteriores = max( 0, (int) $anos_acreditados_anteriores );
    
    // Validar días usados (no puede ser negativo)
    $dias_vacaciones_usados = max( 0, (float) $dias_vacaciones_usados );
    
    // =====================================================
    // CASO LÍMITE: Fecha futura
    // =====================================================
    if ( $fecha_inicio > $hoy ) {
        return [
            'error' => false,
            'mensaje' => 'El empleado aún no ha comenzado a trabajar',
            'codigo' => 'FECHA_FUTURA',
            'fecha_ingreso' => $fecha_ingreso,
            'anos_acreditados_anteriores' => $anos_acreditados_anteriores,
            'dias_usados' => 0,
            'anos_en_empresa' => 0,
            'meses_trabajados_total' => 0,
            'anos_totales' => $anos_acreditados_anteriores,
            'dias_base_por_mes' => 1.25,
            'dias_legales_generados' => 0,
            'tiene_derecho_progresivos' => false,
            'dias_progresivos' => 0,
            'total_dias_generados' => 0,
            'dias_disponibles' => 0,
            'limite_acumulacion' => 30,
            'dias_excedidos' => 0,
            'supera_limite' => false,
            'periodos_completos' => 0,
            'detalle_periodos' => [],
            'dias_periodo_actual' => 15,
            'dias_periodo_proximo' => 15,
            'proximo_aniversario' => $fecha_ingreso,
            'dias_para_aniversario' => $hoy->diff( $fecha_inicio )->days,
        ];
    }
    
    // =====================================================
    // CÁLCULO DE TIEMPO EN LA EMPRESA
    // =====================================================
    
    $diferencia = $fecha_inicio->diff( $hoy );
    $anos_en_empresa = $diferencia->y;
    $meses_en_ano_actual = $diferencia->m;
    $dias_en_mes_actual = $diferencia->d;
    
    // Meses totales trabajados (incluyendo fracción si tiene más de 15 días del mes)
    $meses_trabajados_total = ( $anos_en_empresa * 12 ) + $meses_en_ano_actual;
    
    // Si tiene más de 15 días trabajados en el mes actual, contar como mes completo
    if ( $dias_en_mes_actual >= 15 ) {
        $meses_trabajados_total += 1;
    }
    
    // Años totales (empresa actual + anteriores)
    $anos_totales = $anos_en_empresa + $anos_acreditados_anteriores;
    
    // =====================================================
    // CASO LÍMITE: Menos de 1 mes trabajado
    // =====================================================
    if ( $meses_trabajados_total < 1 ) {
        return [
            'error' => false,
            'mensaje' => 'Menos de un mes trabajado, aún no genera días de vacaciones',
            'codigo' => 'SIN_DIAS_GENERADOS',
            'fecha_ingreso' => $fecha_ingreso,
            'anos_acreditados_anteriores' => $anos_acreditados_anteriores,
            'dias_usados' => $dias_vacaciones_usados,
            'anos_en_empresa' => 0,
            'meses_trabajados_total' => 0,
            'dias_trabajados' => $dias_en_mes_actual,
            'anos_totales' => $anos_acreditados_anteriores,
            'dias_base_por_mes' => 1.25,
            'dias_legales_generados' => 0,
            'tiene_derecho_progresivos' => false,
            'dias_progresivos' => 0,
            'total_dias_generados' => 0,
            'dias_disponibles' => 0,
            'limite_acumulacion' => 30,
            'dias_excedidos' => 0,
            'supera_limite' => false,
            'periodos_completos' => 0,
            'detalle_periodos' => [],
            'dias_periodo_actual' => 15,
            'dias_periodo_proximo' => 15,
            'proximo_aniversario' => $fecha_inicio->modify( '+1 year' )->format( 'Y-m-d' ),
            'dias_para_aniversario' => $hoy->diff( $fecha_inicio )->days,
        ];
    }
    
    // =====================================================
    // CÁLCULO DE DÍAS BASE (LEGALES)
    // =====================================================
    // 1.25 días por mes trabajado = 15 días hábiles por año
    
    $dias_base_por_mes = 1.25;
    $dias_legales_generados = $meses_trabajados_total * $dias_base_por_mes;
    
    // =====================================================
    // CÁLCULO DE DÍAS PROGRESIVOS
    // =====================================================
    // Condición: (años_acreditados_anteriores + años_en_empresa) > 10
    // Si cumple: +1 día por cada 3 años EXCLUSIVAMENTE en la empresa actual
    
    $dias_progresivos = 0;
    $tiene_derecho_progresivos = $anos_totales > 10;
    $anos_faltantes_progresivos = max( 0, 11 - $anos_totales );
    
    if ( $tiene_derecho_progresivos && $anos_en_empresa >= 3 ) {
        // Cada 3 años completos en la empresa actual = 1 día adicional
        $dias_progresivos = floor( $anos_en_empresa / 3 );
    }
    
    // =====================================================
    // CÁLCULO DE PERÍODOS ANUALES (ANUALIDADES)
    // =====================================================
    // Un período = 15 días base + días progresivos del año correspondiente
    
    $periodos_completos = $anos_en_empresa;
    $detalle_periodos = [];
    $total_dias_generados = 0;
    
    // Calcular cada período anual completado
    for ( $i = 1; $i <= $periodos_completos; $i++ ) {
        $dias_base_periodo = 15;
        $progresivos_periodo = 0;
        
        // Verificar si este período tiene derecho a progresivos
        $anos_acumulados_periodo = $anos_acreditados_anteriores + $i;
        if ( $anos_acumulados_periodo > 10 && $i >= 3 ) {
            $progresivos_periodo = floor( $i / 3 );
        }
        
        $total_periodo = $dias_base_periodo + $progresivos_periodo;
        
        $detalle_periodos[] = [
            'periodo'            => $i,
            'dias_base'          => $dias_base_periodo,
            'progresivos'        => $progresivos_periodo,
            'total'              => $total_periodo,
            'anos_acumulados'    => $anos_acumulados_periodo,
            'tiene_progresivos'  => $progresivos_periodo > 0,
        ];
        
        $total_dias_generados += $total_periodo;
    }
    
    // =====================================================
    // FRACCIÓN DEL AÑO EN CURSO
    // =====================================================
    
    $dias_fraccion_ano = 0;
    if ( $meses_en_ano_actual > 0 || $dias_en_mes_actual >= 15 ) {
        $meses_fraccion = $meses_en_ano_actual + ( $dias_en_mes_actual >= 15 ? 1 : 0 );
        $dias_fraccion_ano = $meses_fraccion * $dias_base_por_mes;
        
        // Agregar progresivos proporcionales si corresponde
        if ( $tiene_derecho_progresivos && $anos_en_empresa >= 3 ) {
            $progresivos_anuales = floor( $anos_en_empresa / 3 );
            $dias_fraccion_ano += ( $progresivos_anuales / 12 ) * $meses_fraccion;
        }
    }
    
    $total_dias_generados += $dias_fraccion_ano;
    
    // =====================================================
    // LÍMITE DE ACUMULACIÓN (ÚLTIMOS 2 PERÍODOS)
    // =====================================================
    
    $limite_acumulacion = 0;
    $ultimo_periodo_dias = 15;
    $penultimo_periodo_dias = 15;
    
    if ( count( $detalle_periodos ) >= 2 ) {
        // Suma de los últimos 2 períodos completos
        $ultimo_periodo_dias = $detalle_periodos[ count( $detalle_periodos ) - 1 ]['total'];
        $penultimo_periodo_dias = $detalle_periodos[ count( $detalle_periodos ) - 2 ]['total'];
        $limite_acumulacion = $ultimo_periodo_dias + $penultimo_periodo_dias;
        
    } elseif ( count( $detalle_periodos ) === 1 ) {
        // Solo un período: período actual + proyección del siguiente
        $ultimo_periodo_dias = $detalle_periodos[0]['total'];
        
        // Proyección del siguiente período
        $anos_siguiente = $anos_en_empresa + 1;
        $progresivos_siguiente = 0;
        if ( ( $anos_acreditados_anteriores + $anos_siguiente ) > 10 && $anos_siguiente >= 3 ) {
            $progresivos_siguiente = floor( $anos_siguiente / 3 );
        }
        $penultimo_periodo_dias = 15 + $progresivos_siguiente;
        $limite_acumulacion = $ultimo_periodo_dias + $penultimo_periodo_dias;
        
    } else {
        // Sin períodos completos: usar proyección de los primeros 2 años
        // Año 1
        $progresivos_ano_1 = 0;
        if ( ( $anos_acreditados_anteriores + 1 ) > 10 ) {
            $progresivos_ano_1 = 0; // Aún no tiene 3 años en empresa
        }
        $ultimo_periodo_dias = 15 + $progresivos_ano_1;
        
        // Año 2
        $progresivos_ano_2 = 0;
        if ( ( $anos_acreditados_anteriores + 2 ) > 10 ) {
            $progresivos_ano_2 = 0; // Aún no tiene 3 años en empresa
        }
        $penultimo_periodo_dias = 15 + $progresivos_ano_2;
        
        $limite_acumulacion = $ultimo_periodo_dias + $penultimo_periodo_dias;
    }
    
    // =====================================================
    // CÁLCULO DE SALDO FINAL
    // =====================================================
    
    $dias_usados = (float) $dias_vacaciones_usados;
    $dias_disponibles_sin_limite = $total_dias_generados - $dias_usados;
    
    // Aplicar límite de acumulación
    $dias_disponibles = min( $dias_disponibles_sin_limite, $limite_acumulacion );
    $dias_disponibles = max( 0, $dias_disponibles ); // No puede ser negativo
    
    // Días que exceden el límite (se perderían)
    $dias_excedidos = max( 0, $dias_disponibles_sin_limite - $limite_acumulacion );
    
    // =====================================================
    // CASO LÍMITE: Días usados mayores a generados
    // =====================================================
    $dias_en_deficit = false;
    $deficit_dias = 0;
    if ( $dias_usados > $total_dias_generados ) {
        $dias_en_deficit = true;
        $deficit_dias = $dias_usados - $total_dias_generados;
        $dias_disponibles = 0;
    }
    
    // =====================================================
    // INFORMACIÓN DEL PERÍODO ACTUAL Y SIGUIENTE
    // =====================================================
    
    // Período actual
    $dias_periodo_actual = 15;
    if ( $tiene_derecho_progresivos && $anos_en_empresa >= 3 ) {
        $dias_periodo_actual += floor( $anos_en_empresa / 3 );
    }
    
    // Próximo período (proyección)
    $anos_proximo = $anos_en_empresa + 1;
    $dias_periodo_proximo = 15;
    if ( ( $anos_acreditados_anteriores + $anos_proximo ) > 10 && $anos_proximo >= 3 ) {
        $dias_periodo_proximo += floor( $anos_proximo / 3 );
    }
    
    // Fecha del próximo aniversario
    $proximo_aniversario = clone $fecha_inicio;
    $proximo_aniversario->add( new DateInterval( 'P' . ( $anos_en_empresa + 1 ) . 'Y' ) );
    
    // Días hasta próximo aniversario
    $dias_para_aniversario = $hoy->diff( $proximo_aniversario )->days;
    if ( $proximo_aniversario < $hoy ) {
        $dias_para_aniversario = 0; // Ya pasó, debería recalcular
    }
    
    // =====================================================
    // INFORMACIÓN ADICIONAL ÚTIL
    // =====================================================
    
    // Próximo hito de progresivos
    $proximo_hito_progresivos = null;
    $dias_para_proximo_hito = null;
    
    if ( ! $tiene_derecho_progresivos ) {
        // Cuándo cumplirá los 10 años totales
        $proximo_hito_progresivos = 'Cumplir más de 10 años totales';
        $dias_para_proximo_hito = $anos_faltantes_progresivos * 365;
    } elseif ( $anos_en_empresa < 3 ) {
        // Cuándo cumplirá 3 años en la empresa (requisito para progresivos)
        $anos_para_3 = 3 - $anos_en_empresa;
        $proximo_hito_progresivos = 'Cumplir 3 años en la empresa';
        $dias_para_proximo_hito = $anos_para_3 * 365;
    } else {
        // Próximo tramo de 3 años
        $proximo_tramo = ( floor( $anos_en_empresa / 3 ) + 1 ) * 3;
        $anos_para_tramo = $proximo_tramo - $anos_en_empresa;
        $proximo_hito_progresivos = "Cumplir {$proximo_tramo} años en la empresa (+1 día progresivo)";
        $dias_para_proximo_hito = $anos_para_tramo * 365;
    }
    
    // =====================================================
    // RETORNO DE RESULTADOS
    // =====================================================
    
    return [
        'error' => false,
        'codigo' => 'OK',
        
        // Datos de entrada
        'fecha_ingreso' => $fecha_ingreso,
        'anos_acreditados_anteriores' => $anos_acreditados_anteriores,
        'dias_usados' => $dias_usados,
        
        // Tiempo trabajado
        'anos_en_empresa' => $anos_en_empresa,
        'meses_en_ano_actual' => $meses_en_ano_actual,
        'dias_en_mes_actual' => $dias_en_mes_actual,
        'meses_trabajados_total' => $meses_trabajados_total,
        'anos_totales' => $anos_totales,
        
        // Días base (legales)
        'dias_base_por_mes' => $dias_base_por_mes,
        'dias_legales_generados' => round( $dias_legales_generados, 2 ),
        
        // Días progresivos
        'tiene_derecho_progresivos' => $tiene_derecho_progresivos,
        'dias_progresivos' => $dias_progresivos,
        'anos_faltantes_progresivos' => $anos_faltantes_progresivos,
        
        // Totales
        'total_dias_generados' => round( $total_dias_generados, 2 ),
        'dias_fraccion_ano' => round( $dias_fraccion_ano, 2 ),
        'dias_disponibles' => round( $dias_disponibles, 2 ),
        
        // Déficit (caso límite)
        'dias_en_deficit' => $dias_en_deficit,
        'deficit_dias' => round( $deficit_dias, 2 ),
        
        // Límites y excesos
        'limite_acumulacion' => $limite_acumulacion,
        'ultimo_periodo_dias' => $ultimo_periodo_dias,
        'penultimo_periodo_dias' => $penultimo_periodo_dias,
        'dias_excedidos' => round( $dias_excedidos, 2 ),
        'supera_limite' => $dias_excedidos > 0,
        
        // Períodos
        'periodos_completos' => $periodos_completos,
        'detalle_periodos' => $detalle_periodos,
        'dias_periodo_actual' => $dias_periodo_actual,
        'dias_periodo_proximo' => $dias_periodo_proximo,
        
        // Próximo aniversario
        'proximo_aniversario' => $proximo_aniversario->format( 'Y-m-d' ),
        'dias_para_aniversario' => $dias_para_aniversario,
        
        // Información de progresivos
        'proximo_hito_progresivos' => $proximo_hito_progresivos,
        'dias_para_proximo_hito' => $dias_para_proximo_hito,
    ];
}


/**
 * OBTENER SALDO DE VACACIONES CHILE PARA UN EMPLEADO
 * =====================================================
 * Obtiene los datos del empleado de la BD y calcula información
 * adicional según la ley chilena (días progresivos, límites, etc.)
 * 
 * IMPORTANTE: Usa los días disponibles de la BD como fuente de verdad
 *
 * @param int $id_empleado ID del empleado
 * @return array Información del saldo de vacaciones
 */
function hrm_get_saldo_vacaciones_chile( $id_empleado ) {
    global $wpdb;
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    
    // Obtener todos los datos relevantes del empleado
    $empleado = $wpdb->get_row( $wpdb->prepare(
        "SELECT 
            fecha_ingreso,
            COALESCE(anos_acreditados_anteriores, 0) as anos_acreditados_anteriores,
            COALESCE(dias_vacaciones_anuales, 15) as dias_vacaciones_anuales,
            COALESCE(dias_vacaciones_usados, 0) as dias_vacaciones_usados,
            COALESCE(dias_vacaciones_disponibles, 0) as dias_vacaciones_disponibles
         FROM $table_empleados
         WHERE id_empleado = %d",
        $id_empleado
    ) );
    
    if ( ! $empleado ) {
        return [
            'error' => true,
            'mensaje' => 'Empleado no encontrado',
            'codigo' => 'EMPLEADO_NO_ENCONTRADO',
        ];
    }
    
    // Validar fecha de ingreso
    if ( empty( $empleado->fecha_ingreso ) ) {
        return [
            'error' => true,
            'mensaje' => 'Fecha de ingreso no registrada',
            'codigo' => 'FECHA_VACIA',
        ];
    }
    
    try {
        $fecha_inicio = new DateTime( $empleado->fecha_ingreso );
        $hoy = new DateTime();
    } catch ( Exception $e ) {
        return [
            'error' => true,
            'mensaje' => 'Formato de fecha inválido',
            'codigo' => 'FECHA_INVALIDA',
        ];
    }
    
    // =====================================================
    // DATOS DIRECTOS DE LA BD (fuente de verdad)
    // =====================================================
    
    $dias_disponibles_bd = (float) $empleado->dias_vacaciones_disponibles;
    $dias_usados_bd = (float) $empleado->dias_vacaciones_usados;
    $dias_anuales_bd = (float) $empleado->dias_vacaciones_anuales;
    $anos_acreditados = (int) $empleado->anos_acreditados_anteriores;
    
    // =====================================================
    // CÁLCULO DE TIEMPO EN LA EMPRESA
    // =====================================================
    
    $diferencia = $fecha_inicio->diff( $hoy );
    $anos_en_empresa = $diferencia->y;
    $meses_en_ano_actual = $diferencia->m;
    $dias_en_mes_actual = $diferencia->d;
    
    // Años totales (empresa actual + anteriores)
    $anos_totales = $anos_en_empresa + $anos_acreditados;
    
    // =====================================================
    // CÁLCULO DE DÍAS PROGRESIVOS
    // =====================================================
    // Condición: (años_acreditados + años_en_empresa) > 10
    // Si cumple: +1 día por cada 3 años EN LA EMPRESA ACTUAL
    // Ejemplo: 3 años = +1, 6 años = +2, 9 años = +3, etc.
    
    $tiene_derecho_progresivos = $anos_totales > 10;
    $dias_progresivos_anuales = 0;
    
    if ( $tiene_derecho_progresivos && $anos_en_empresa >= 3 ) {
        // +1 día por cada 3 años completos en la empresa actual
        $dias_progresivos_anuales = floor( $anos_en_empresa / 3 );
    }
    
    // Años faltantes para tener derecho a progresivos
    $anos_faltantes_progresivos = $tiene_derecho_progresivos ? 0 : max( 0, 11 - $anos_totales );
    
    // =====================================================
    // DÍAS DEL PERÍODO ACTUAL
    // =====================================================
    // Base: 15 días + progresivos según años en empresa
    
    $dias_periodo_actual = 15 + $dias_progresivos_anuales;
    
    // =====================================================
    // PROYECCIÓN PRÓXIMO PERÍODO
    // =====================================================
    
    $anos_proximo = $anos_en_empresa + 1;
    $dias_progresivos_proximo = 0;
    
    if ( ( $anos_acreditados + $anos_proximo ) > 10 && $anos_proximo >= 3 ) {
        $dias_progresivos_proximo = floor( $anos_proximo / 3 );
    }
    
    $dias_periodo_proximo = 15 + $dias_progresivos_proximo;
    
    // =====================================================
    // LÍMITE DE ACUMULACIÓN (2 PERÍODOS)
    // =====================================================
    
    // El límite es la suma de los últimos 2 períodos
    // Usamos el período actual y el anterior (o proyectado)
    
    if ( $anos_en_empresa >= 2 ) {
        // Calcular período anterior
        $anos_anterior = $anos_en_empresa - 1;
        $dias_progresivos_anterior = 0;
        if ( ( $anos_acreditados + $anos_anterior ) > 10 && $anos_anterior >= 3 ) {
            $dias_progresivos_anterior = floor( $anos_anterior / 3 );
        }
        $dias_periodo_anterior = 15 + $dias_progresivos_anterior;
        
        $limite_acumulacion = $dias_periodo_actual + $dias_periodo_anterior;
    } elseif ( $anos_en_empresa == 1 ) {
        // 1 año: período actual + siguiente proyectado
        $limite_acumulacion = $dias_periodo_actual + $dias_periodo_proximo;
    } else {
        // Menos de 1 año: límite estándar 30 días
        $limite_acumulacion = 30;
    }
    
    // =====================================================
    // VERIFICAR EXCESO Y DÉFICIT
    // =====================================================
    
    $supera_limite = $dias_disponibles_bd > $limite_acumulacion;
    $dias_excedidos = $supera_limite ? ( $dias_disponibles_bd - $limite_acumulacion ) : 0;
    
    $dias_en_deficit = $dias_disponibles_bd < 0;
    $deficit_dias = $dias_en_deficit ? abs( $dias_disponibles_bd ) : 0;
    
    // =====================================================
    // PRÓXIMO ANIVERSARIO
    // =====================================================
    
    $proximo_aniversario = clone $fecha_inicio;
    $proximo_aniversario->add( new DateInterval( 'P' . ( $anos_en_empresa + 1 ) . 'Y' ) );
    
    $dias_para_aniversario = $hoy->diff( $proximo_aniversario )->days;
    if ( $proximo_aniversario < $hoy ) {
        // Si ya pasó, recalcular
        $proximo_aniversario->add( new DateInterval( 'P1Y' ) );
        $dias_para_aniversario = $hoy->diff( $proximo_aniversario )->days;
    }
    
    // =====================================================
    // PRÓXIMO HITO DE PROGRESIVOS
    // =====================================================
    
    $proximo_hito_progresivos = null;
    $dias_para_proximo_hito = null;
    
    if ( ! $tiene_derecho_progresivos ) {
        $proximo_hito_progresivos = "Cumplir más de 10 años totales (faltan {$anos_faltantes_progresivos})";
        $dias_para_proximo_hito = $anos_faltantes_progresivos * 365;
    } elseif ( $anos_en_empresa < 3 ) {
        $anos_para_3 = 3 - $anos_en_empresa;
        $proximo_hito_progresivos = "Cumplir 3 años en la empresa (faltan {$anos_para_3})";
        $dias_para_proximo_hito = $anos_para_3 * 365;
    } else {
        $proximo_tramo = ( floor( $anos_en_empresa / 3 ) + 1 ) * 3;
        $anos_para_tramo = $proximo_tramo - $anos_en_empresa;
        $proximo_hito_progresivos = "Cumplir {$proximo_tramo} años en empresa (+1 día progresivo)";
        $dias_para_proximo_hito = $anos_para_tramo * 365;
    }
    
    // =====================================================
    // DETALLE DE PERÍODOS (historial)
    // =====================================================
    
    $detalle_periodos = [];
    for ( $i = 1; $i <= $anos_en_empresa; $i++ ) {
        $prog_periodo = 0;
        if ( ( $anos_acreditados + $i ) > 10 && $i >= 3 ) {
            $prog_periodo = floor( $i / 3 );
        }
        $detalle_periodos[] = [
            'periodo' => $i,
            'dias_base' => 15,
            'progresivos' => $prog_periodo,
            'total' => 15 + $prog_periodo,
            'tiene_progresivos' => $prog_periodo > 0,
        ];
    }
    
    // =====================================================
    // RETORNO DE RESULTADOS
    // =====================================================
    
    return [
        'error' => false,
        'codigo' => 'OK',
        
        // Datos de la BD (fuente de verdad)
        'dias_disponibles' => $dias_disponibles_bd,
        'dias_usados' => $dias_usados_bd,
        'dias_anuales_bd' => $dias_anuales_bd,
        
        // Datos de entrada
        'fecha_ingreso' => $empleado->fecha_ingreso,
        'anos_acreditados_anteriores' => $anos_acreditados,
        
        // Tiempo trabajado
        'anos_en_empresa' => $anos_en_empresa,
        'meses_en_ano_actual' => $meses_en_ano_actual,
        'dias_en_mes_actual' => $dias_en_mes_actual,
        'anos_totales' => $anos_totales,
        
        // Días progresivos
        'tiene_derecho_progresivos' => $tiene_derecho_progresivos,
        'dias_progresivos_anuales' => $dias_progresivos_anuales,
        'anos_faltantes_progresivos' => $anos_faltantes_progresivos,
        
        // Períodos
        'dias_periodo_actual' => $dias_periodo_actual,
        'dias_periodo_proximo' => $dias_periodo_proximo,
        'detalle_periodos' => $detalle_periodos,
        
        // Límites
        'limite_acumulacion' => $limite_acumulacion,
        'supera_limite' => $supera_limite,
        'dias_excedidos' => round( $dias_excedidos, 2 ),
        
        // Déficit
        'dias_en_deficit' => $dias_en_deficit,
        'deficit_dias' => round( $deficit_dias, 2 ),
        
        // Próximo aniversario
        'proximo_aniversario' => $proximo_aniversario->format( 'Y-m-d' ),
        'dias_para_aniversario' => $dias_para_aniversario,
        
        // Información de progresivos
        'proximo_hito_progresivos' => $proximo_hito_progresivos,
        'dias_para_proximo_hito' => $dias_para_proximo_hito,
    ];
}


/**
 * MOSTRAR RESUMEN DE VACACIONES FORMATEADO
 * =====================================================
 * Genera HTML con el resumen del saldo de vacaciones
 * Incluye manejo de casos límites
 *
 * @param array $saldo Array retornado por hrm_calcular_saldo_vacaciones_chile
 * @param bool  $mostrar_detalle Mostrar detalle expandido (default: true)
 * @return string HTML formateado
 */
function hrm_render_saldo_vacaciones_chile( $saldo, $mostrar_detalle = true ) {
    
    // Caso de error
    if ( isset( $saldo['error'] ) && $saldo['error'] === true ) {
        return '<div class="alert alert-danger" style="border-left: 4px solid #dc3545;">
            <strong>❌ Error:</strong> ' . esc_html( $saldo['mensaje'] ?? 'Error desconocido' ) . '
        </div>';
    }
    
    // =====================================================
    // CASOS LÍMITES ESPECIALES
    // =====================================================
    
    // Caso: Fecha futura
    if ( isset( $saldo['codigo'] ) && $saldo['codigo'] === 'FECHA_FUTURA' ) {
        $fecha_inicio = date_create( $saldo['fecha_ingreso'] )->format( 'd/m/Y' );
        return '<div class="alert alert-info" style="border-left: 4px solid #17a2b8;">
            <strong>📅 Próximo Ingreso:</strong><br>
            El empleado comenzará a trabajar el <strong>' . esc_html( $fecha_inicio ) . '</strong>.<br>
            Los días de vacaciones se calcularán a partir de esa fecha.
        </div>';
    }
    
    // Caso: Menos de 1 mes trabajado
    if ( isset( $saldo['codigo'] ) && $saldo['codigo'] === 'SIN_DIAS_GENERADOS' ) {
        $dias_trabajados = $saldo['dias_trabajados'] ?? 0;
        return '<div class="alert alert-warning" style="border-left: 4px solid #ffc107;">
            <strong>⏳ Período Inicial:</strong><br>
            El empleado lleva <strong>' . esc_html( $dias_trabajados ) . ' días</strong> trabajados.<br>
            Se requiere al menos 1 mes completo (o 15 días) para generar días de vacaciones.<br>
            <small class="text-muted">Faltan ' . esc_html( 15 - $dias_trabajados ) . ' días para el primer cálculo.</small>
        </div>';
    }
    
    // =====================================================
    // RENDERIZADO NORMAL
    // =====================================================
    
    $html = '<div class="hrm-saldo-vacaciones-chile">';
    
    // =====================================================
    // RESUMEN PRINCIPAL (CARDS)
    // =====================================================
    
    $html .= '<div class="row g-3 mb-4">';
    
    // Card: Días Disponibles (de la BD)
    $bg_disponibles = $saldo['dias_en_deficit'] ? '#dc3545' : '#27ae60';
    $html .= '<div class="col-md-4">';
    $html .= '<div class="card h-100 text-center" style="border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">';
    $html .= '<div class="card-body" style="background: ' . $bg_disponibles . '; border-radius: 12px; color: white; padding: 1.5rem;">';
    $html .= '<div style="font-size: 2.5rem; font-weight: 700; line-height: 1;">' . number_format( $saldo['dias_disponibles'], 1 ) . '</div>';
    $html .= '<div style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.9;">Días Disponibles</div>';
    if ( $saldo['dias_en_deficit'] ) {
        $html .= '<div style="font-size: 0.75rem; margin-top: 0.25rem; background: rgba(255,255,255,0.2); padding: 0.25rem 0.5rem; border-radius: 4px;">⚠️ Déficit: ' . number_format( $saldo['deficit_dias'], 1 ) . ' días</div>';
    }
    $html .= '</div></div></div>';
    
    // Card: Días Usados (de la BD)
    $html .= '<div class="col-md-4">';
    $html .= '<div class="card h-100 text-center" style="border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">';
    $html .= '<div class="card-body" style="background: #e74c3c; border-radius: 12px; color: white; padding: 1.5rem;">';
    $html .= '<div style="font-size: 2.5rem; font-weight: 700; line-height: 1;">' . number_format( $saldo['dias_usados'], 1 ) . '</div>';
    $html .= '<div style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.9;">Días Usados</div>';
    $html .= '</div></div></div>';
    
    // Card: Días del Período Actual (calculado)
    $html .= '<div class="col-md-4">';
    $html .= '<div class="card h-100 text-center" style="border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">';
    $html .= '<div class="card-body" style="background: #2c3e50; border-radius: 12px; color: white; padding: 1.5rem;">';
    $html .= '<div style="font-size: 2.5rem; font-weight: 700; line-height: 1;">' . number_format( $saldo['dias_periodo_actual'], 0 ) . '</div>';
    $html .= '<div style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.9;">Días por Año</div>';
    if ( $saldo['dias_progresivos_anuales'] > 0 ) {
        $html .= '<div style="font-size: 0.75rem; margin-top: 0.25rem; background: rgba(39,174,96,0.3); padding: 0.25rem 0.5rem; border-radius: 4px;">15 base + ' . $saldo['dias_progresivos_anuales'] . ' progresivos</div>';
    }
    $html .= '</div></div></div>';
    
    $html .= '</div>'; // row
    
    // =====================================================
    // ALERTAS DE CASOS LÍMITES
    // =====================================================
    
    // Alerta de déficit
    if ( $saldo['dias_en_deficit'] ) {
        $html .= '<div class="alert" style="background: #fff3cd; border-left: 4px solid #dc3545; border-radius: 8px; margin-bottom: 1rem;">';
        $html .= '<strong>⚠️ Déficit de Días:</strong> Se han usado <strong>' . number_format( $saldo['deficit_dias'], 1 ) . ' días</strong> más de los generados. ';
        $html .= 'Contacte a RRHH para regularizar la situación.';
        $html .= '</div>';
    }
    
    // Alerta de exceso de acumulación
    if ( $saldo['supera_limite'] ) {
        $html .= '<div class="alert" style="background: #fff3cd; border-left: 4px solid #f39c12; border-radius: 8px; margin-bottom: 1rem;">';
        $html .= '<strong>📋 Exceso de Acumulación:</strong> Tienes <strong>' . number_format( $saldo['dias_excedidos'], 1 ) . ' días</strong> ';
        $html .= 'que exceden el límite legal de ' . $saldo['limite_acumulacion'] . ' días. ';
        $html .= '<br><small class="text-muted">Según la ley chilena, el máximo acumulable es la suma de los últimos 2 períodos anuales.</small>';
        $html .= '</div>';
    }
    
    // =====================================================
    // INFORMACIÓN ADICIONAL
    // =====================================================
    
    $html .= '<div class="row g-3 mt-3">';
    
    // Próximo aniversario
    $dias_aniv = $saldo['dias_para_aniversario'];
    $color_aniv = $dias_aniv <= 30 ? '#f39c12' : '#27ae60';
    $html .= '<div class="col-md-6">';
    $html .= '<div class="alert mb-0" style="background: #f8f9fa; border-left: 4px solid ' . $color_aniv . '; border-radius: 8px;">';
    $html .= '<strong>📅 Próxima Recarga:</strong><br>';
    $html .= '<span style="font-size: 1.1rem; color: ' . $color_aniv . ';">' . date_create( $saldo['proximo_aniversario'] )->format( 'd/m/Y' ) . '</span>';
    $html .= '<br><small class="text-muted">Faltan ' . $dias_aniv . ' días</small>';
    $html .= '</div></div>';
    
    $html .= '</div>'; // row
    
    $html .= '</div>'; // hrm-saldo-vacaciones-chile
    
    return $html;
}
