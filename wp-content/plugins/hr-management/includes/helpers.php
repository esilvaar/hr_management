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
 * Actualiza los años en la empresa y total de años trabajados de un empleado
 * Se llama automáticamente cuando se actualiza un empleado
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
    
    // Actualizar en la base de datos
    $result = $wpdb->update(
        $table,
        array(
            'anos_en_la_empresa' => $anos_en_empresa,
            'anos_totales_trabajados' => $total_anos
        ),
        array( 'id_empleado' => $id_empleado ),
        array( '%f', '%f' ),
        array( '%d' )
    );
    
    if ( $result !== false ) {
        error_log( "HRM: Años actualizados para empleado {$id_empleado}: {$anos_en_empresa} años en empresa, {$total_anos} años totales" );
        return true;
    }
    
    return false;
}

/**
 * Hook: Actualizar años cada vez que se actualiza un empleado
 */
add_action( 'hrm_after_employee_update', 'hrm_actualizar_anos_empleado' );

