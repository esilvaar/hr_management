<?php
if (!defined('ABSPATH'))
    exit;

add_action('admin_init', 'ahr_handle_post_requests');

function ahr_handle_post_requests()
{
    // Instanciamos la clase de Base de Datos
    // Asegúrate de que tu clase DB tenga los métodos que llamamos aquí
    $db = new AHR_DB();

    // ---------------------------------------------------
    // CASO 1: Empleado envía solicitud de vacaciones/ausencia
    // ---------------------------------------------------
    if (isset($_POST['ahr_action']) && $_POST['ahr_action'] == 'nueva_solicitud') {
        if (!is_user_logged_in())
            return;

        // Verificación de seguridad (Nonce)
        check_admin_referer('ahr_nonce_create', 'ahr_security');

        // Preparamos los datos
        $datos = array(
            'tipo' => sanitize_text_field($_POST['tipo']),
            'fecha_inicio' => sanitize_text_field($_POST['fecha_inicio']),
            'fecha_fin' => sanitize_text_field($_POST['fecha_fin']),
            'motivo' => sanitize_textarea_field($_POST['motivo']),
            'user_id' => get_current_user_id() // Asocia al usuario logueado
        );

        // Guardamos en BD
        $db->create_solicitud($datos);

        // Redireccionamos con mensaje de éxito
        wp_redirect(add_query_arg('msg', 'enviado', wp_get_referer()));
        exit;
    }

    // ---------------------------------------------------
    // CASO 2: Admin aprueba/rechaza solicitud
    // ---------------------------------------------------
    if (isset($_POST['ahr_action']) && $_POST['ahr_action'] == 'cambiar_estado') {
        // Verificar capacidad (ahora usamos la capacidad del rol supervisor o admin)
        if (!current_user_can('manage_options') && !current_user_can('ahr_approve_requests'))
            return;

        check_admin_referer('ahr_nonce_status', 'ahr_security');

        $status = sanitize_text_field($_POST['nuevo_estado']);
        $id = intval($_POST['solicitud_id']);

        // --- AUDITORÍA NUEVA ---
        $approver_id = get_current_user_id();
        $date_res = current_time('mysql');

        // Pasamos los 4 argumentos
        $db->update_status($id, $status, $approver_id, $date_res);

        wp_redirect(add_query_arg('msg', 'actualizado', wp_get_referer()));
        exit;
    }

    // ---------------------------------------------------
    // CASO 3: NUEVO - Guardar Nuevo Empleado (Desde el Formulario Nuevo)
    // ---------------------------------------------------
    if (isset($_POST['btn_guardar_empleado'])) {
        // Solo admins pueden crear empleados
        if (!current_user_can('manage_options'))
            return;

        // Verificamos el token de seguridad del formulario de empleados
        check_admin_referer('crear_empleado_accion', 'crear_empleado_nonce');

        // 1. Recogemos y limpiamos los datos
        $rut = sanitize_text_field($_POST['rut']);
        $nombres = sanitize_text_field($_POST['nombres']);
        $apellidos = sanitize_text_field($_POST['apellidos']);
        $email = sanitize_email($_POST['email']);
        $departamento = sanitize_text_field($_POST['departamento']);
        $cargo = sanitize_text_field($_POST['cargo']);
        $fecha_ingreso = sanitize_text_field($_POST['fecha_ingreso']);

        // 2. Lógica para crear usuario de WordPress (si el checkbox venía marcado)
        $wp_user_id = 0; // Por defecto 0 si no se crea usuario

        // Nota: El checkbox no envía nada si no está marcado, por eso usamos empty()
        // En tu formulario anterior el name del checkbox era 'crear_usuario' o similar. 
        // Asumiremos que es automático para simplificar, o verifica si existe $_POST['crear_usuario']

        if (!email_exists($email)) {
            // Generamos una contraseña aleatoria
            $password = wp_generate_password();

            // Creamos el usuario WP (El nombre de usuario será el RUT limpio o el email)
            // Usaremos el email como username para evitar problemas con caracteres raros
            $wp_user_id = wp_create_user($email, $password, $email);

            if (!is_wp_error($wp_user_id)) {
                // Asignamos rol (por defecto suscriptor para que no rompa nada)
                $user = new WP_User($wp_user_id);
                $user->set_role('subscriber');

                // Actualizamos nombre y apellido en WP
                wp_update_user([
                    'ID' => $wp_user_id,
                    'first_name' => $nombres,
                    'last_name' => $apellidos
                ]);

                // Opcional: Enviar correo al usuario con su clave
                wp_new_user_notification($wp_user_id, null, 'both');
            }
        } else {
            // Si el email ya existe, buscamos su ID para asociarlo
            $user = get_user_by('email', $email);
            $wp_user_id = $user->ID;
        }

        // 3. Guardamos en la tabla personalizada de Empleados
        $datos_empleado = array(
            'rut' => $rut,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'email' => $email,
            'departamento' => $departamento,
            'cargo' => $cargo,
            'fecha_ingreso' => $fecha_ingreso,
            'wp_user_id' => $wp_user_id // Guardamos la relación
        );

        // Llamamos al método de inserción (debes tenerlo en class-ahr-db.php)
        $resultado = $db->insert_empleado($datos_empleado);

        if ($resultado) {
            wp_redirect(add_query_arg('msg', 'empleado_creado', wp_get_referer()));
        } else {
            wp_redirect(add_query_arg('msg', 'error_db', wp_get_referer()));
        }
        exit;
    }
}