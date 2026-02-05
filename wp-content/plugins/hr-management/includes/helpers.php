<?php
/**
 * Funciones de utilidad y helpers globales
 * Funciones que se reutilizan en todo el plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Obtiene los roles permitidos para asignar a empleados
 * 
 * Personalización: Modifica el array $allowed_roles para controlar qué roles
 * pueden ser asignados al crear nuevos empleados.
 * 
 * IMPORTANTE: Usa las CLAVES de los roles (slugs), no los nombres visibles:
 * - 'administrator' (no "Administrador")
 * - 'administrador_anaconda' (Rol específico del plugin)
 * - 'editor' (no "Editor")
 * - 'supervisor' (no "Supervisor") - si existe en tu sistema
 * - 'employee' (no "Empleado") - si existe en tu sistema
 *
 * @return array Array asociativo de roles permitidos (role_key => role_name)
 */
function hrm_get_allowed_employee_roles() {
    // Define aquí los roles que deseas permitir (CLAVES, no nombres visibles)
    $allowed_roles = array(
        'administrador_anaconda',  // Administrador Anaconda (rol específico)
        'editor_vacaciones',         // Editor
        'supervisor',     // Supervisor (si existe en tu sistema)
        'empleado',       // Empleado (si existe en tu sistema)
    );

    // Obtener todos los roles disponibles en WordPress
    $all_roles = wp_roles()->get_names();

    // Filtrar solo los roles permitidos que existan en el sistema
    $filtered_roles = array_intersect_key(
        $all_roles,
        array_flip( $allowed_roles )
    );

    /**
     * Filtro para personalizar roles permitidos desde temas o plugins
     *
     * @param array $filtered_roles Roles permitidos
     * @param array $allowed_roles  Roles configurados
     * @param array $all_roles      Todos los roles del sistema
     */
    return apply_filters( 'hrm_allowed_employee_roles', $filtered_roles, $allowed_roles, $all_roles );
}

/**
 * Verificar si el usuario actual puede ver las vistas de administrador.
 * Esto incluye a administradores de WordPress y usuarios con la capacidad especial
 * 'view_hrm_admin_views' (como administrador_anaconda).
 *
 * @return bool true si el usuario puede ver vistas de admin, false en caso contrario
 */
function hrm_can_user_view_admin_views() {
    return current_user_can( 'manage_options' ) || current_user_can( 'view_hrm_admin_views' );
}

/**
 * Enviar email con credenciales (username + password) al crear usuario.
 * Se puede desactivar con el filtro `hrm_send_plain_credentials`.
 *
 * @param int    $user_id   ID del usuario
 * @param string $username  Nombre de usuario
 * @param string $password  Contraseña en texto plano
 * @param string $email     Email del usuario
 *
 * @return bool Resultado del envío de email
 */
function hrm_send_user_credentials_email( $user_id, $username, $password, $email ) {
    /**
     * Filtro para controlar si enviar credenciales por defecto.
     *
     * @param bool $send    Si enviar credenciales (por defecto: true)
     * @param int  $user_id ID del usuario creado
     */
    $send = apply_filters( 'hrm_send_plain_credentials', true, $user_id );
    if ( ! $send ) {
        return false;
    }

    $blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
    $subject = sprintf( '[%s] Acceso a %s', $blogname, 'Mi cuenta' );

    $message = "Hola,\n\n";
    $message .= "Se ha creado una cuenta para ti en $blogname.\n\n";
    $message .= "Usuario: $username\n";
    $message .= "Contraseña: $password\n\n";
    $message .= "Puedes iniciar sesión en: " . wp_login_url() . "\n\n";
    $message .= "Por seguridad, cambia tu contraseña desde tu perfil después de iniciar sesión.\n\n";
    $message .= "Saludos,\n$blogname";

    $headers = array( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );

    return wp_mail( $email, $subject, $message, $headers );
}

/**
 * Cargar una plantilla del plugin permitiendo override desde el tema.
 * Busca en este orden:
 * 1) Tema: 'hrm/{slug}-{name}.php' o 'hrm/{slug}.php'
 * 2) Plugin: 'views/{slug}-{name}.php' o 'views/{slug}.php'
 * 3) Plugin: 'views/Administrador/{slug}-{name}.php' o 'views/Administrador/{slug}.php'
 * 4) Plugin: 'views/Empleado/{slug}-{name}.php' o 'views/Empleado/{slug}.php'
 * 5) Plugin: 'views/partials/{slug}-{name}.php' o 'views/partials/{slug}.php'
 *
 * @param string $slug  Slug de la plantilla (ej: 'employee-edit-form')
 * @param string $name  Nombre adicional (ej: 'view', resultado: 'employee-edit-form-view.php')
 * @param array  $vars  Variables para extraer en la vista
 *
 * @return void
 */
function hrm_get_template_part( $slug, $name = '', $vars = [] ) {

    // ✅ Exponer variables a la vista
    if ( is_array( $vars ) && ! empty( $vars ) ) {
        extract( $vars, EXTR_SKIP );
    }

    $templates = array();

    // Construcción de nombres de plantillas
    if ( $name ) {
        $templates[] = "hrm/{$slug}-{$name}.php";
    }
    $templates[] = "hrm/{$slug}.php";

    if ( $name ) {
        $templates[] = "{$slug}-{$name}.php";
    }
    $templates[] = "{$slug}.php";

    // Intentar localizar en el tema activo primero
    $located = locate_template( $templates, false, false );
    if ( $located && file_exists( $located ) ) {
        include $located;
        return;
    }

    // Fallback a rutas dentro del plugin
    $plugin_paths = array();
    
    // Rutas en views/
    if ( $name ) {
        $plugin_paths[] = HRM_PLUGIN_DIR . "views/{$slug}-{$name}.php";
    }
    $plugin_paths[] = HRM_PLUGIN_DIR . "views/{$slug}.php";
    
    // Rutas en views/Administrador/
    if ( $name ) {
        $plugin_paths[] = HRM_PLUGIN_DIR . "views/Administrador/{$slug}-{$name}.php";
    }
    $plugin_paths[] = HRM_PLUGIN_DIR . "views/Administrador/{$slug}.php";
    
    // Rutas en views/Empleado/
    if ( $name ) {
        $plugin_paths[] = HRM_PLUGIN_DIR . "views/Empleado/{$slug}-{$name}.php";
    }
    $plugin_paths[] = HRM_PLUGIN_DIR . "views/Empleado/{$slug}.php";
    
    // Rutas en views/partials/ (soporta tanto 'employee-selector' como 'partials/employee-selector')
    if ( $name ) {
        $plugin_paths[] = HRM_PLUGIN_DIR . "views/partials/{$slug}-{$name}.php";
    }
    $plugin_paths[] = HRM_PLUGIN_DIR . "views/partials/{$slug}.php";
    
    // Si el slug no contiene '/', también buscar directamente en partials
    if ( strpos( $slug, '/' ) === false ) {
        if ( $name ) {
            $plugin_paths[] = HRM_PLUGIN_DIR . "views/partials/{$slug}-{$name}.php";
        }
    }

    foreach ( $plugin_paths as $path ) {
        if ( file_exists( $path ) ) {
            include $path;
            return;
        }
    }

    // Nada encontrado: no hacer nada (silencioso)
}

/**
 * Ensure a safe index.html exists in a directory to prevent execution or listing.
 *
 * @param string $dir Absolute path to directory
 * @param string $contents Optional contents for index file
 * @return bool True on success or already exists, false on failure
 */
function hrm_ensure_placeholder_index( $dir, $contents = null ) {
    if ( empty( $dir ) ) return false;
    $dir = wp_normalize_path( $dir );
    if ( ! is_dir( $dir ) ) return false;

    $index_file = rtrim( $dir, '/' ) . '/index.html';
    if ( file_exists( $index_file ) ) return true;

    if ( $contents === null ) {
        $contents = '<!doctype html><meta charset="utf-8"><title>Directorio</title><meta name="robots" content="noindex,nofollow"><!-- HRM placeholder -->';
    }

    $result = @file_put_contents( $index_file, $contents );
    if ( $result === false ) {
        error_log( "HRM Helper - failed to create index file at {$index_file}" );
        return false;
    }
    @chmod( $index_file, 0644 );
    return true;
}

/**
 * Normalizar nombre de archivo subido por el usuario.
 *
 * Por defecto elimina sufijos tipo "-<timestamp>" cuando coinciden con
 * el patrón de 7+ dígitos al final del nombre (antes de la extensión).
 * Puedes desactivar este comportamiento con el filtro `hrm_strip_uploaded_timestamp`.
 *
 * @param string $filename Nombre original del archivo (puede incluir path)
 * @return string Nombre normalizado (sin path)
 */
function hrm_normalize_uploaded_filename( $filename ) {
    $strip = apply_filters( 'hrm_strip_uploaded_timestamp', true );

    $ext = pathinfo( $filename, PATHINFO_EXTENSION );
    $base = pathinfo( $filename, PATHINFO_FILENAME );

    if ( $strip ) {
        // eliminar sufijo tipo -12345678 o -1600000000 (7+ dígitos)
        $new_base = preg_replace( '/-\d{7,}$/', '', $base );
        if ( $new_base !== $base ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[HRM-UPLOAD] normalized filename: ' . $base . ' -> ' . $new_base );
            }
            $base = $new_base;
        }
    }

    return $base . ( $ext ? '.' . $ext : '' );
}

/**
 * Remove a directory and its contents recursively.
 * Returns true if removed or didn't exist; false on failure.
 *
 * @param string $dir Absolute path
 * @return bool
 */
function hrm_recursive_rmdir( $dir ) {
    $dir = wp_normalize_path( $dir );
    if ( ! file_exists( $dir ) ) return true;
    if ( ! is_dir( $dir ) ) return @unlink( $dir );

    $items = scandir( $dir );
    if ( $items === false ) return false;

    foreach ( $items as $item ) {
        if ( $item === '.' || $item === '..' ) continue;
        $path = $dir . '/' . $item;
        if ( is_dir( $path ) ) {
            if ( ! hrm_recursive_rmdir( $path ) ) {
                return false;
            }
        } else {
            @chmod( $path, 0644 );
            if ( ! @unlink( $path ) ) {
                error_log( "HRM Helper - failed to unlink file: {$path}" );
                // continue trying to remove others
            }
        }
    }

    @chmod( $dir, 0755 );
    $result = @rmdir( $dir );
    if ( $result === false ) {
        error_log( "HRM Helper - failed to rmdir: {$dir}" );
    }
    return $result;
}

/**
 * Attempt to remove per-type view stub file (views/mis-documentos-tipo-{id}.php)
 *
 * @param int $type_id
 * @return bool True if removed or not found
 */
function hrm_remove_type_view_stub( $type_id ) {
    hrm_ensure_db_classes();
    $db = new HRM_DB_Documentos();

    // Try to resolve type name/slug
    $types = $db->get_all_types();
    $slug = isset( $types[ $type_id ] ) ? sanitize_title( $types[ $type_id ] ) : '';

    // Candidate paths (slug preferred, then slug-id, then legacy id)
    $candidates = array();
    if ( $slug ) {
        $candidates[] = HRM_PLUGIN_DIR . "views/mis-documentos-tipo-" . $slug . ".php";
        $candidates[] = HRM_PLUGIN_DIR . "views/mis-documentos-tipo-" . $slug . "-" . intval( $type_id ) . ".php";
    }
    $candidates[] = HRM_PLUGIN_DIR . "views/mis-documentos-tipo-" . intval( $type_id ) . ".php";
    $found_any = false;
    $deleted_any = false;
    $failed_any = false;

    foreach ( $candidates as $stub_path ) {
        if ( file_exists( $stub_path ) ) {
            $found_any = true;
            @chmod( $stub_path, 0644 );
            if ( @unlink( $stub_path ) ) {
                $deleted_any = true;
                error_log( "HRM Helper - removed type view stub: {$stub_path}" );
            } else {
                $failed_any = true;
                error_log( "HRM Helper - failed to remove type view stub: {$stub_path}" );
            }
        }
    }

    // Si no se encontraron archivos, retornar true (no es error). Si alguno falló al borrar, retornar false.
    if ( ! $found_any ) return true;
    if ( $failed_any ) return false;
    return true;
}

// Helper: write plugin-local debug logs in a file writable by the web user.
if ( ! function_exists( 'hrm_local_debug_log' ) ) {
    function hrm_local_debug_log( $msg ) {
        // Only write local debug logs when WP_DEBUG is enabled.
        if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
            return false;
        }
        // Prefer writing to uploads dir (writable by web server) and fallback to plugin dir.
        $ts = date( 'c' );
        $current = wp_get_current_user();
        $uid = isset( $current->ID ) ? intval( $current->ID ) : 0;
        $line = "[{$ts}] user_id={$uid} msg={$msg}\n";

        $upload_dir = wp_get_upload_dir();
        $file_candidates = array();
        if ( ! empty( $upload_dir['basedir'] ) ) {
            $file_candidates[] = rtrim( $upload_dir['basedir'], '/' ) . '/hrm-debug.log';
        }
        $file_candidates[] = rtrim( HRM_PLUGIN_DIR, '/' ) . '/hrm-debug.log';

        foreach ( $file_candidates as $file ) {
            $res = @file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
            if ( $res !== false ) {
                return true;
            }
        }

        // Last resort: write to PHP error log
        error_log( '[HRM-DEBUG] Failed to write hrm_local_debug_log to disk. Message: ' . $msg );
        return false;
    }
}

/**
 * Valida un RUT chileno usando el algoritmo del Módulo 11
 * 
 * Formato aceptado: 12345678-9 (SIN puntos, obligatorio guión)
 * 
 * El dígito verificador puede ser:
 * - Un número (0-9)
 * - La letra 'K' (equivalente a 10)
 * 
 * @param string $rut El RUT a validar
 * @return bool true si el RUT es válido, false en caso contrario
 */
function hrm_validar_rut( $rut ) {
    if ( empty( $rut ) ) {
        return false;
    }
    
    // Convertir a mayúsculas
    $rut = strtoupper( trim( $rut ) );
    
    // Validar formato: número(s)-dígito (SIN puntos)
    if ( ! preg_match( '/^(\d{1,8})-([0-9K])$/', $rut ) ) {
        return false;
    }
    
    $numeros = preg_match( '/^(\d+)-/', $rut, $matches ) ? $matches[1] : '';
    $digito_verificador = substr( $rut, -1 );
    
    // Calcular el dígito verificador esperado
    $digito_esperado = hrm_calcular_digito_verificador_rut( $numeros );
    
    // Comparar
    return $digito_verificador === $digito_esperado;
}

/**
 * Calcula el dígito verificador de un RUT chileno usando el algoritmo Módulo 11
 * 
 * @param string|int $numeros Los números del RUT sin dígito verificador
 * @return string El dígito verificador (0-9 o 'K')
 */
function hrm_calcular_digito_verificador_rut( $numeros ) {
    $numeros = strval( $numeros );
    
    // Convertir string a array de dígitos
    $digitos = str_split( $numeros );
    
    // Invertir el orden (de derecha a izquierda)
    $digitos = array_reverse( $digitos );
    
    // Multiplicadores: 2, 3, 4, 5, 6, 7, 2, 3, ...
    $multiplicadores = array( 2, 3, 4, 5, 6, 7 );
    
    $suma = 0;
    
    // Multiplicar cada dígito por su multiplicador
    foreach ( $digitos as $index => $digito ) {
        $multiplicador = $multiplicadores[ $index % 6 ];
        $suma += intval( $digito ) * $multiplicador;
    }
    
    // Obtener el resto de la división por 11
    $resto = $suma % 11;
    
    // Calcular el dígito verificador
    $digito_verificador = 11 - $resto;
    
    // Si el resultado es 11, el dígito es 0
    if ( $digito_verificador === 11 ) {
        return '0';
    }
    
    // Si el resultado es 10, el dígito es K
    if ( $digito_verificador === 10 ) {
        return 'K';
    }
    
    return strval( $digito_verificador );
}

/**
 * Formatea un RUT chileno al formato estándar: 12345678-9 (SIN puntos)
 * 
 * @param string $rut El RUT a formatear
 * @return string El RUT formateado (12345678-9) o string vacío si es inválido
 */
function hrm_formatear_rut( $rut ) {
    if ( empty( $rut ) ) {
        return '';
    }
    
    // Eliminar espacios y puntos
    $rut = str_replace( array( ' ', '.' ), '', trim( $rut ) );
    $rut = strtoupper( $rut );
    
    // Validar formato básico: números-dígito
    if ( ! preg_match( '/^(\d{7,8})-?([0-9K])$/i', $rut ) ) {
        return '';
    }
    
    // Separar números del dígito verificador
    if ( strpos( $rut, '-' ) !== false ) {
        list( $numeros, $digito ) = explode( '-', $rut );
    } else {
        $numeros = substr( $rut, 0, -1 );
        $digito = substr( $rut, -1 );
    }
    
    // Devolver en formato SIN puntos: 12345678-9
    return $numeros . '-' . strtoupper( $digito );
}

/**
 * =====================================================
 * FUNCIONES DE ANTIGÜEDAD LABORAL
 * =====================================================
 */

/**
 * Calcula los años en la empresa basado en la fecha de ingreso
 * 
 * Es un cálculo "inteligente" que:
 * - Calcula correctamente incluso si la fecha es muy antigua (ej: hace 6, 10, 20 años)
 * - Valida que la fecha no sea futura
 * - Retorna SOLO AÑOS COMPLETOS (se actualiza en aniversarios)
 * - Maneja excepciones y fechas inválidas
 * 
 * @param string $fecha_ingreso Fecha de ingreso en formato YYYY-MM-DD
 * @return int Número de años completos en la empresa (sin decimales)
 */
function hrm_calcular_anos_en_empresa( $fecha_ingreso ) {
    if ( empty( $fecha_ingreso ) || $fecha_ingreso === '0000-00-00' ) {
        return 0;
    }
    
    try {
        $fecha_ingreso_obj = new DateTime( $fecha_ingreso );
        $hoy = new DateTime();
        
        // Validar que la fecha no sea futura
        if ( $fecha_ingreso_obj > $hoy ) {
            error_log( "HRM Warning: Fecha de ingreso futura: $fecha_ingreso" );
            return 0;
        }
        
        // Calcular solo años COMPLETOS (sin decimales)
        $intervalo = $hoy->diff( $fecha_ingreso_obj );
        $anos_completos = $intervalo->y;
        
        return max( 0, intval( $anos_completos ) );
    } catch ( Exception $e ) {
        error_log( "HRM Error calculando años en empresa para fecha '$fecha_ingreso': " . $e->getMessage() );
        return 0;
    }
}

/**
 * Calcula el total de años trabajados
 * Solo suma años COMPLETOS (se actualiza en aniversarios)
 * 
 * @param int $anos_acreditados_anteriores Años acreditados en otras empresas
 * @param int $anos_en_la_empresa Años en la empresa actual
 * @return int Total de años trabajados
 */
function hrm_calcular_total_anos_trabajados( $anos_acreditados_anteriores, $anos_en_la_empresa ) {
    $anos_acreditados_anteriores = intval( $anos_acreditados_anteriores ) ?: 0;
    $anos_en_la_empresa = intval( $anos_en_la_empresa ) ?: 0;
    
    return max( 0, $anos_acreditados_anteriores + $anos_en_la_empresa );
}

/**
 * Actualiza los años en la empresa, total de años trabajados y días de vacaciones anuales
 * Se llama automáticamente cuando se actualiza un empleado
 * 
 * ACTIVACIÓN: También actualiza dias_vacaciones_anuales según la antigüedad
 * (Ley Chilena: 15 días base + progresivos según años totales)
 * 
 * @param int $id_empleado ID del empleado
 * @return bool True si se actualizó correctamente
 */
function hrm_actualizar_anos_empleado( $id_empleado ) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'rrhh_empleados';
    
    // Obtener los datos del empleado
    $empleado = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT fecha_ingreso, anos_acreditados_anteriores FROM {$table} WHERE id_empleado = %d",
            $id_empleado
        )
    );
    
    if ( ! $empleado || empty( $empleado->fecha_ingreso ) || $empleado->fecha_ingreso === '0000-00-00' ) {
        return false;
    }
    
    // Calcular años en la empresa
    $anos_en_empresa = hrm_calcular_anos_en_empresa( $empleado->fecha_ingreso );
    
    // Calcular total de años trabajados
    $anos_acreditados = (float) $empleado->anos_acreditados_anteriores ?: 0;
    $total_anos = hrm_calcular_total_anos_trabajados( $anos_acreditados, $anos_en_empresa );
    
    // ACTIVACIÓN: Calcular días de vacaciones según antigüedad (Ley Chilena)
    $dias_vacaciones = hrm_calcular_dias_segun_antiguedad( $id_empleado );
    
    // Actualizar en la base de datos
    $result = $wpdb->update(
        $table,
        array(
            'anos_en_la_empresa' => $anos_en_empresa,
            'anos_totales_trabajados' => $total_anos,
            'dias_vacaciones_anuales' => $dias_vacaciones  // Guardar días calculados según antigüedad
        ),
        array( 'id_empleado' => $id_empleado ),
        array( '%f', '%f', '%d' ),
        array( '%d' )
    );
    
    if ( $result !== false ) {
        error_log( "HRM: Empleado {$id_empleado} actualizado - Años: {$anos_en_empresa} empresa / {$total_anos} totales - Días vacaciones: {$dias_vacaciones}" );
        return true;
    }
    
    return false;
}

/**
 * Inicializa los días disponibles cuando se crea un empleado nuevo
 * 
 * Al crear: dias_disponibles = dias_anuales (no ha usado nada aún)
 * 
 * @param int $id_empleado ID del empleado
 * @return bool True si se inicializó correctamente
 */
function hrm_inicializar_dias_disponibles_empleado( $id_empleado ) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'rrhh_empleados';
    
    // Obtener los días anuales recién calculados
    $empleado = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT dias_vacaciones_anuales FROM {$table} WHERE id_empleado = %d",
            $id_empleado
        )
    );
    
    if ( ! $empleado ) {
        return false;
    }
    
    $dias_anuales = intval( $empleado->dias_vacaciones_anuales );
    
    // Actualizar dias_disponibles = dias_anuales (empleado nuevo, no ha usado nada)
    $result = $wpdb->update(
        $table,
        array(
            'dias_vacaciones_disponibles' => $dias_anuales,
            'dias_vacaciones_usados' => 0  // Empleado nuevo, no ha usado días
        ),
        array( 'id_empleado' => $id_empleado ),
        array( '%d', '%d' ),
        array( '%d' )
    );
    
    if ( $result !== false ) {
        error_log( "HRM: Días disponibles inicializados para empleado nuevo {$id_empleado}: {$dias_anuales}" );
        return true;
    }
    
    return false;
}

/**
 * Actualiza los días disponibles cuando se modifica un empleado
 * 
 * Calcula:
 * - Días anuales (según antigüedad)
 * - Menos días ya usados en el período actual
 * - Más carryover de períodos anteriores (últimos 2 períodos)
 * 
 * @param int $id_empleado ID del empleado
 * @return bool True si se actualizó correctamente
 */
function hrm_actualizar_dias_disponibles_empleado( $id_empleado ) {
    global $wpdb;
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_vacaciones_anual = $wpdb->prefix . 'rrhh_vacaciones_anual';
    
    // Obtener datos del empleado
    $empleado = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT dias_vacaciones_anuales, dias_vacaciones_usados 
             FROM {$table_empleados}
             WHERE id_empleado = %d",
            $id_empleado
        )
    );
    
    if ( ! $empleado ) {
        return false;
    }
    
    $ano_actual = date( 'Y' );
    $dias_anuales = intval( $empleado->dias_vacaciones_anuales );
    $dias_usados_actual = intval( $empleado->dias_vacaciones_usados ?: 0 );
    
    // Obtener carryover de períodos anteriores
    // Buscar en los registros anuales anteriores
    $carryover_total = 0;
    
    // Buscar últimos 2 años
    for ( $i = 1; $i <= 2; $i++ ) {
        $ano_anterior = $ano_actual - $i;
        
        $registro_anterior = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT dias_carryover_anterior, dias_disponibles 
                 FROM {$table_vacaciones_anual}
                 WHERE id_empleado = %d AND ano = %d",
                $id_empleado,
                $ano_anterior
            )
        );
        
        if ( $registro_anterior ) {
            // Si hay carryover en ese año, sumarlo
            $carryover_total += intval( $registro_anterior->dias_carryover_anterior ?: 0 );
        }
    }
    
    // Calcular días disponibles
    // = Días anuales - días usados + carryover
    $dias_disponibles = $dias_anuales - $dias_usados_actual + $carryover_total;
    $dias_disponibles = max( 0, $dias_disponibles ); // No permitir negativos
    
    // Actualizar en la BD
    $result = $wpdb->update(
        $table_empleados,
        array(
            'dias_vacaciones_disponibles' => $dias_disponibles
        ),
        array( 'id_empleado' => $id_empleado ),
        array( '%d' ),
        array( '%d' )
    );
    
    if ( $result !== false ) {
        error_log( "HRM: Días disponibles actualizados para empleado {$id_empleado}: {$dias_disponibles} (Anuales: {$dias_anuales} - Usados: {$dias_usados_actual} + Carryover: {$carryover_total})" );
        return true;
    }
    
    return false;
}

/**
 * Hook: Actualizar años cada vez que se actualiza un empleado
 */
add_action( 'hrm_after_employee_update', 'hrm_actualizar_anos_empleado' );

/**
 * Hook: Actualizar días disponibles después de actualizar años
 */
add_action( 'hrm_after_employee_update', 'hrm_actualizar_dias_disponibles_empleado', 11 );

/**
 * Hook: Inicializar días disponibles cuando se CREA un empleado nuevo
 */
add_action( 'hrm_after_employee_create', 'hrm_inicializar_dias_disponibles_empleado' );


/**
 * Redirigir al listado de empleados tras iniciar sesión.
 * Si el usuario tiene acceso al panel HRM, enviarlo a la lista de empleados.
 */
function hrm_login_redirect_after_login( $redirect_to, $requested_redirect_to, $user ) {
    if ( ! $user ) {
        return $redirect_to;
    }

    $wp_user = null;
    if ( is_a( $user, 'WP_User' ) ) {
        $wp_user = $user;
    } elseif ( is_array( $user ) && isset( $user['ID'] ) ) {
        $wp_user = get_user_by( 'id', intval( $user['ID'] ) );
    }

    if ( ! $wp_user ) {
        return $redirect_to;
    }

    // Si existe una redirección solicitada explícitamente por otro plugin, respetarla
    if ( ! empty( $requested_redirect_to ) ) {
        return $redirect_to;
    }

    $employees_url = admin_url( 'admin.php?page=hrm-empleados&tab=list' );

    // Redirigir según rol: administrador_anaconda y supervisor -> listado de empleados
    // editor_vacaciones -> panel de Vacaciones
    $user_roles = (array) $wp_user->roles;
    $is_admin_anaconda = in_array( 'administrador_anaconda', $user_roles, true );
    $is_supervisor = in_array( 'supervisor', $user_roles, true );
    $is_editor_vacaciones = in_array( 'editor_vacaciones', $user_roles, true );
    $is_regular_employee = in_array( 'empleado', $user_roles, true );

    // 1. Prioridad: Administradores y Supervisores (Gestión de Empleados)
    if ( user_can( $wp_user, 'manage_options' ) || $is_admin_anaconda || $is_supervisor || user_can( $wp_user, 'edit_hrm_employees' ) || user_can( $wp_user, 'view_hrm_admin_views' ) ) {
        return $employees_url;
    }

    // 2. Prioridad: Editores de Vacaciones
    if ( $is_editor_vacaciones || user_can( $wp_user, 'manage_hrm_vacaciones' ) ) {
        return admin_url( 'admin.php?page=hrm-vacaciones&tab=solicitudes' );
    }

    // 3. Prioridad: Empleados Regulares (Su propio panel)
    if ( $is_regular_employee ) {
        return admin_url( 'admin.php?page=hrm-mi-perfil-info' );
    }

    // Si no cumple las condiciones, respetar la redirección por defecto
    return $redirect_to;
}

add_filter( 'login_redirect', 'hrm_login_redirect_after_login', 10, 3 );

