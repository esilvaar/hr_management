<?php
/**
 * Plugin Name: RRHH Manager
 * Plugin URI: https://example.com
 * Description: Plugin para gestionar recursos humanos.
 * Version: 1.0.0
 * Author: Practicantes Anacondaweb
 * Author URI: https://example.com
 * License: GPLv2 or later
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
		exit;
}
require_once plugin_dir_path( __FILE__ ) . 'includes/employees.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/vacaciones.php';

// Shortcode principal: [rrhh_manager]
add_shortcode( 'rrhh_manager', 'rrhh_manager_shortcode' );



// Encolar assets del plugin (registrar handles)
add_action( 'wp_enqueue_scripts', 'rrhh_manager_register_assets' );
function rrhh_manager_register_assets() {
		$css_file = plugin_dir_path( __FILE__ ) . 'assets/css/style.css';
		$js_file = plugin_dir_path( __FILE__ ) . 'assets/js/tabs.js';
		$css_ver = file_exists( $css_file ) ? filemtime( $css_file ) : null;
		$js_ver = file_exists( $js_file ) ? filemtime( $js_file ) : null;
		wp_register_style( 'rrhh-manager-style', plugins_url( 'assets/css/style.css', __FILE__ ), array(), $css_ver );
		wp_register_script( 'rrhh-manager-tabs', plugins_url( 'assets/js/tabs.js', __FILE__ ), array(), $js_ver, true );

		// FullCalendar (CDN) - used for a graphical calendar in the plugin
		wp_register_style( 'rrhh-fullcalendar-style', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css', array(), '6.1.8' );
		wp_register_script( 'rrhh-fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', array(), '6.1.8', true );
}

// Shortcode principal: [rrhh_manager]
add_shortcode( 'rrhh_manager', 'rrhh_manager_shortcode' );
function rrhh_manager_shortcode( $atts = array() ) {
		// Asegurar que los handles están registrados
		rrhh_manager_register_assets();

		// Encolar assets cuando se usa el shortcode
		wp_enqueue_style( 'rrhh-manager-style' );
		wp_enqueue_script( 'rrhh-manager-tabs' );

		// Mensajes
		$message = '';
		$errors = array();

		// Manejar intento de login desde el mismo shortcode
		if ( isset( $_POST['rrhh_login'] ) ) {
				if ( ! isset( $_POST['rrhh_login_nonce'] ) || ! wp_verify_nonce( $_POST['rrhh_login_nonce'], 'rrhh_login_action' ) ) {
						$message = '<div class="rrhh-error">Error de seguridad. Intenta de nuevo.</div>';
				} else {
						$creds = array();
						$creds['user_login'] = isset( $_POST['rrhh_user'] ) ? sanitize_text_field( wp_unslash( $_POST['rrhh_user'] ) ) : '';
						$creds['user_password'] = isset( $_POST['rrhh_pass'] ) ? $_POST['rrhh_pass'] : '';
						$creds['remember'] = isset( $_POST['rememberme'] ) ? true : false;

						$user = wp_signon( $creds, false );
						if ( is_wp_error( $user ) ) {
								$message = '<div class="rrhh-error">' . esc_html( $user->get_error_message() ) . '</div>';
						} else {
								// Login correcto, redirigir a la home indicando la pestaña a abrir (Interfaz)
								$redirect_to = add_query_arg( 'rrhh_tab', 'vacaciones', home_url( '/' ) );
								wp_safe_redirect( esc_url_raw( $redirect_to ) );
								exit;
						}
				}
		}

		// Procesar acciones: guardar perfil, solicitar vacaciones, actualizar estado (aprobar/rechazar)
			$current_user = wp_get_current_user();
			global $wpdb;
			$user_wp_id = $current_user && $current_user->ID ? intval( $current_user->ID ) : 0;
			// Obtener datos de empleado tempranamente para procesar acciones que lo requieran
			$employee = null;
			if ( $current_user && $current_user->ID ) {
				$employee = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rrhh_employees WHERE user_id = %d", $current_user->ID ) );
			}
			$emp_id = $employee ? intval( $employee->id ) : ( $current_user->ID ? intval( $current_user->ID ) : 0 );
		// Guardar perfil desde 'Datos Personales'
		if ( isset( $_POST['rrhh_save_profile'] ) ) {
			if ( isset( $_POST['rrhh_profile_nonce'] ) && wp_verify_nonce( $_POST['rrhh_profile_nonce'], 'rrhh_profile_action' ) && $current_user && $current_user->ID ) {
				$phone = isset( $_POST['rrhh_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['rrhh_phone'] ) ) : '';
				$address = isset( $_POST['rrhh_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rrhh_address'] ) ) : '';
				$position = isset( $_POST['rrhh_position'] ) ? sanitize_text_field( wp_unslash( $_POST['rrhh_position'] ) ) : '';
				$table = $wpdb->prefix . 'rrhh_employees';
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE user_id = %d", $current_user->ID ) );
				if ( $exists ) {
					$wpdb->update( $table, array( 'phone' => $phone, 'address' => $address, 'position' => $position ), array( 'user_id' => $current_user->ID ), array( '%s', '%s', '%s' ), array( '%d' ) );
				} else {
					$wpdb->insert( $table, array( 'user_id' => $current_user->ID, 'phone' => $phone, 'address' => $address, 'position' => $position ), array( '%d', '%s', '%s', '%s' ) );
				}
			}
		}

		// Solicitar vacaciones (empleados)
		if ( isset( $_POST['rrhh_request_vacation'] ) ) {
			if ( isset( $_POST['rrhh_vac_nonce'] ) && wp_verify_nonce( $_POST['rrhh_vac_nonce'], 'rrhh_vacation_action' ) && $current_user && $current_user->ID ) {
				$start = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
				$end = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
				$raw_days = isset( $_POST['days'] ) ? wp_unslash( $_POST['days'] ) : '';
				$raw_days = str_replace( ',', '.', $raw_days );
				$days = $raw_days !== '' ? floatval( $raw_days ) : 0;
				$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'Vacaciones';
				$table_v = $wpdb->prefix . 'rrhh_vacaciones';
				// If days not provided or zero, compute from dates (inclusive)
				if ( ( $days <= 0 ) && $start && $end ) {
					$sd = DateTime::createFromFormat( 'Y-m-d', $start );
					$ed = DateTime::createFromFormat( 'Y-m-d', $end );
					if ( $sd && $ed && $ed >= $sd ) {
						// Count business days (Mon-Fri) inclusive
						$period = new DatePeriod( clone $sd, new DateInterval( 'P1D' ), (clone $ed)->modify('+1 day') );
						$computed = 0;
						foreach ( $period as $d ) {
							$dow = (int) $d->format( 'N' ); // 1 (Mon) .. 7 (Sun)
							if ( $dow < 6 ) { // Mon-Fri
								$computed++;
							}
						}
						$days = $computed;
					}
				}
				// store employee as WP user ID to keep consistency
				$inserted = $wpdb->insert( $table_v, array( 'employee_id' => $user_wp_id, 'start_date' => $start, 'end_date' => $end, 'days' => number_format( $days, 1, '.', '' ), 'status' => 'requested', 'type' => $type ), array( '%d', '%s', '%s', '%s', '%s', '%s' ) );
				$vacation_id = $wpdb->insert_id;
				if ( $inserted && $vacation_id ) {
					$message = '<div class="updated"><p>Solicitud enviada correctamente.</p></div>';
				} else {
					$err = '';
					if ( ! empty( $wpdb->last_error ) && current_user_can( 'manage_options' ) ) {
						$err = '<br><small>Error DB: ' . esc_html( $wpdb->last_error ) . '</small>';
					}
					$message = '<div class="rrhh-error"><p>No se pudo guardar la solicitud. Revisa los datos e intenta de nuevo.' . $err . '</p></div>';
				}
				// notify admins and managers about the new request
				if ( $vacation_id ) {
					$managers = get_users( array( 'meta_key' => 'rrhh_role', 'meta_value' => 'manager' ) );
					$admins = get_users( array( 'role__in' => array( 'administrator' ) ) );
					$targets = array();
					foreach ( $managers as $m ) { $targets[ $m->user_email ] = $m; }
					foreach ( $admins as $a ) { $targets[ $a->user_email ] = $a; }
					$link_admin = admin_url( 'admin.php?page=rrhh-manager' );
					$link_front = home_url( '/?rrhh_tab=solicitudes' );
					foreach ( $targets as $email => $user ) {
						$subject = 'Nueva solicitud de vacaciones #' . $vacation_id;
						$msg = sprintf( "Hola %s,\n\nSe ha creado una nueva solicitud de vacaciones (ID: %d) por el empleado ID: %s\nDesde: %s\nHasta: %s\nDías: %s\nTipo: %s\n\nRevisar: %s (admin)\nInterfaz: %s\n\n--\n", $user->display_name ? $user->display_name : $user->user_login, $vacation_id, $emp_id, $start, $end, number_format( $days, 1, '.', '' ), $type, $link_admin, $link_front );
						$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
						wp_mail( $email, $subject, $msg, $headers );
					}
				}
			}
		}

		// Actualizar estado de una solicitud (aprobar/rechazar) — sólo para managers o administradores
		if ( isset( $_POST['rrhh_action'] ) && $_POST['rrhh_action'] === 'vacation_update' ) {
			if ( isset( $_POST['rrhh_update_nonce'] ) && wp_verify_nonce( $_POST['rrhh_update_nonce'], 'rrhh_update_action' ) ) {
				$allowed = false;
				if ( current_user_can( 'manage_options' ) ) { $allowed = true; }
				$user_rrhh_role = $current_user ? get_user_meta( $current_user->ID, 'rrhh_role', true ) : '';
				if ( $user_rrhh_role === 'manager' ) { $allowed = true; }
				if ( $allowed ) {
					$vid = isset( $_POST['vacation_id'] ) ? intval( $_POST['vacation_id'] ) : 0;
					$new_status = isset( $_POST['new_status'] ) ? sanitize_text_field( wp_unslash( $_POST['new_status'] ) ) : '';
					$feedback = isset( $_POST['admin_feedback'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_feedback'] ) ) : '';
					if ( $vid && in_array( $new_status, array( 'approved', 'rejected', 'requested', 'registered', 'approved' ), true ) ) {
						$table_v = $wpdb->prefix . 'rrhh_vacaciones';
						$wpdb->update( $table_v, array( 'status' => $new_status, 'admin_feedback' => $feedback ), array( 'id' => $vid ), array( '%s', '%s' ), array( '%d' ) );
						// After updating status, send notification email to the employee
						$vac = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_v WHERE id = %d", $vid ) );
						if ( $vac ) {
							// employee_id may be rrhh_employees.id or a WP user ID; try to resolve to WP user ID
							$table_e = $wpdb->prefix . 'rrhh_employees';
							$emp_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_e WHERE id = %d", intval( $vac->employee_id ) ) );
							if ( $emp_row && ! empty( $emp_row->user_id ) ) {
								$to_user_id = intval( $emp_row->user_id );
							} else {
								$to_user_id = intval( $vac->employee_id );
							}
							$user = get_user_by( 'ID', $to_user_id );
							if ( $user ) {
								$subject = sprintf( 'Solicitud de vacaciones #%d: %s', $vac->id, $new_status );
								$message = sprintf( "Hola %s,\n\nTu solicitud de vacaciones del %s al %s (%s días) ha sido %s.\n\nFeedback: %s\n\nSaludos.", $user->display_name ? $user->display_name : $user->user_login, $vac->start_date, $vac->end_date, $vac->days, $new_status, $feedback );
								$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
								wp_mail( $user->user_email, $subject, $message, $headers );
							}
						}
					}
				}
			}
		}
			//holasss
		// Cancelar una solicitud propia (empleado) o eliminarla si es manager
		if ( isset( $_POST['rrhh_cancel_vacation'] ) ) {
			if ( isset( $_POST['rrhh_cancel_nonce'] ) && wp_verify_nonce( $_POST['rrhh_cancel_nonce'], 'rrhh_cancel_action' ) && $current_user && $current_user->ID ) {
				$vid = isset( $_POST['vacation_id'] ) ? intval( $_POST['vacation_id'] ) : 0;
				if ( $vid ) {
					$table_v = $wpdb->prefix . 'rrhh_vacaciones';
					$vac = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_v WHERE id = %d", $vid ) );
					if ( $vac ) {
						$allowed_cancel = false;
						// owner can cancel if status requested
						if ( intval( $vac->employee_id ) === $emp_id && $vac->status === 'requested' ) { $allowed_cancel = true; }
						// manager or admin can delete/update
						$user_rrhh_role = $current_user ? get_user_meta( $current_user->ID, 'rrhh_role', true ) : '';
						if ( current_user_can( 'manage_options' ) || $user_rrhh_role === 'manager' ) { $allowed_cancel = true; }
						if ( $allowed_cancel ) {
							$wpdb->update( $table_v, array( 'status' => 'cancelled' ), array( 'id' => $vid ), array( '%s' ), array( '%d' ) );
						}
					}
				}
			}
		}

		// Crear nuevo usuario desde la interfaz (sólo managers/admins)
		if ( isset( $_POST['rrhh_create_user'] ) && isset( $_POST['rrhh_create_user_nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['rrhh_create_user_nonce'], 'rrhh_create_user_action' ) ) {
				// permiso: manager o admin
				$user_rrhh_role = $current_user ? get_user_meta( $current_user->ID, 'rrhh_role', true ) : '';
				if ( current_user_can( 'manage_options' ) || $user_rrhh_role === 'manager' ) {
					$n_login = isset( $_POST['new_user_login'] ) ? sanitize_user( wp_unslash( $_POST['new_user_login'] ) ) : '';
					$n_email = isset( $_POST['new_user_email'] ) ? sanitize_email( wp_unslash( $_POST['new_user_email'] ) ) : '';
					$n_pass = isset( $_POST['new_user_pass'] ) && $_POST['new_user_pass'] ? $_POST['new_user_pass'] : wp_generate_password( 12, false );
					$n_name = isset( $_POST['new_user_name'] ) ? sanitize_text_field( wp_unslash( $_POST['new_user_name'] ) ) : '';
					$n_rrhh_role = isset( $_POST['rrhh_role'] ) ? sanitize_text_field( wp_unslash( $_POST['rrhh_role'] ) ) : '';
					$n_phone = isset( $_POST['rrhh_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['rrhh_phone'] ) ) : '';
					$n_position = isset( $_POST['rrhh_position'] ) ? sanitize_text_field( wp_unslash( $_POST['rrhh_position'] ) ) : '';
					$n_address = isset( $_POST['rrhh_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rrhh_address'] ) ) : '';

					$errors = array();
					if ( empty( $n_login ) ) { $errors[] = 'Usuario requerido'; }
					if ( empty( $n_email ) || ! is_email( $n_email ) ) { $errors[] = 'Email inválido'; }
					if ( empty( $errors ) ) {
						$exists = username_exists( $n_login );
						if ( ! $exists && email_exists( $n_email ) === false ) {
							$uid = wp_create_user( $n_login, $n_pass, $n_email );
							if ( ! is_wp_error( $uid ) ) {
								wp_update_user( array( 'ID' => $uid, 'display_name' => $n_name ) );
								$u = new WP_User( $uid );
								$u->set_role( 'subscriber' );
								if ( $n_rrhh_role ) update_user_meta( $uid, 'rrhh_role', $n_rrhh_role );
								$wpdb->insert( $table, array( 'user_id' => $uid, 'phone' => $n_phone, 'address' => $n_address, 'position' => $n_position ), array( '%d', '%s', '%s', '%s' ) );
								wp_safe_redirect( esc_url_raw( add_query_arg( array( 'rrhh_tab' => 'lista-empleados', 'created' => 1 ), remove_query_arg( array( 'new_user' ) ) ) ) );
								exit;
							} else {
								$errors[] = 'Error creando usuario: ' . $uid->get_error_message();
							}
						} else {
							$errors[] = 'Usuario o email ya registrado.';
						}
					}
				}
			}
		}

		// Renderizamos la interfaz completa (mockup). Si no está logueado, mostramos el formulario dentro del layout.
		$current_user = wp_get_current_user();
		global $wpdb;

		// Obtener datos del empleado si existen
		$employee = null;
		if ( $current_user && $current_user->ID ) {
				$employee = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rrhh_employees WHERE user_id = %d", $current_user->ID ) );
		}

		// Datos de vacaciones y estadísticas (valores por defecto si no hay datos)
		$emp_id = $employee ? intval( $employee->id ) : ( $current_user->ID ? intval( $current_user->ID ) : 0 );
		// Days assigned / accumulated can be stored in user meta; fallback to sensible defaults
		$days_assigned = intval( get_user_meta( $current_user->ID, 'rrhh_days_assigned', true ) ? get_user_meta( $current_user->ID, 'rrhh_days_assigned', true ) : 20 );
		$days_accumulated = floatval( get_user_meta( $current_user->ID, 'rrhh_days_accumulated', true ) ? get_user_meta( $current_user->ID, 'rrhh_days_accumulated', true ) : 0 );
		$days_used = 0.0;
		$vacaciones = array();
		if ( $emp_id ) {
			// Count only approved days as used
				// Calculate used days and list vacations for this WP user (handle legacy rows storing rrhh_employees.id)
				$days_used = floatval( $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(v.days),0) FROM {$wpdb->prefix}rrhh_vacaciones v LEFT JOIN {$wpdb->prefix}rrhh_employees e ON v.employee_id = e.id WHERE (v.employee_id = %d OR e.user_id = %d) AND v.status = %s", $user_wp_id, $user_wp_id, 'approved' ) ) );
				$vacaciones = $wpdb->get_results( $wpdb->prepare( "SELECT v.* FROM {$wpdb->prefix}rrhh_vacaciones v LEFT JOIN {$wpdb->prefix}rrhh_employees e ON v.employee_id = e.id WHERE (v.employee_id = %d OR e.user_id = %d) ORDER BY v.created_at DESC", $user_wp_id, $user_wp_id ) );
		}
		$days_remaining = max( 0, ( $days_assigned + $days_accumulated ) - $days_used );

		ob_start();
		?>
		<div class="rrhh-mock">
			<aside class="sidebar">
				<h2>HR SYSTEM</h2>
				<ul class="menu">
					<li>Gestión de Empleados
						<ul class="submenu">
							<li><a href="#" class="menu-link" data-tab="tab-lista-empleados">Lista de empleados</a></li>
							<li><a href="#" class="menu-link" data-tab="tab-nuevo-empleado">Nuevo empleado</a></li>
						</ul>
					</li>
					<li>Vacaciones y Ausencias
						<ul class="submenu">
							<li><a href="#" class="menu-link" data-tab="tab-mi-resumen">Mi resumen</a></li>
							<li><a href="#" class="menu-link" data-tab="tab-solicitudes">Solicitudes</a></li>
							<li><a href="#" class="menu-link" data-tab="tab-calendario">Calendario</a></li>
						</ul>
					</li>
				</ul>
			</aside>

			<main class="content-area">
				<div class="content">
					<div class="header">
						<div class="avatar"></div>
						<div>
							<?php if ( is_user_logged_in() ) : ?>
								<h1><?php echo esc_html( $current_user->display_name ? $current_user->display_name : $current_user->user_login ); ?></h1>
								<p><?php echo esc_html( $employee && $employee->position ? $employee->position : 'Empleado' ); ?></p>
								<p>ID: <?php echo esc_html( $employee ? 'EMP' . str_pad( $employee->id, 3, '0', STR_PAD_LEFT ) : 'U' . $current_user->ID ); ?> | Departamento: <?php echo esc_html( $employee && $employee->position ? $employee->position : 'General' ); ?></p>
							<?php else : ?>
								<?php echo $message; ?>
								<h1>Bienvenido</h1>
								<p>Inicia sesión con tu usuario de WordPress</p>
							<?php endif; ?>
						</div>
					</div>

					<?php if ( ! is_user_logged_in() ) : ?>
						<div class="rrhh-login">
							<?php echo $message; ?>
							<form method="post">
								<?php wp_nonce_field( 'rrhh_login_action', 'rrhh_login_nonce' ); ?>
								<p>
									<label for="rrhh_user">Usuario o email</label><br>
									<input type="text" id="rrhh_user" name="rrhh_user" required />
								</p>
								<p>
									<label for="rrhh_pass">Contraseña</label><br>
									<input type="password" id="rrhh_pass" name="rrhh_pass" required />
								</p>
								<p>
									<label><input type="checkbox" name="rememberme" /> Recuérdame</label>
								</p>
								<p>
									<button type="submit" name="rrhh_login">Iniciar sesión</button>
								</p>
							</form>
						</div>
					<?php else : ?>
						<?php
						// Detectar rol interno RRHH (meta) y capacidades
						$user_rrhh_role = $current_user ? get_user_meta( $current_user->ID, 'rrhh_role', true ) : '';
						$is_admin = current_user_can( 'manage_options' );
						$is_manager = ( $user_rrhh_role === 'manager' ) || $is_admin;
						// Si es manager o admin, obtener solicitudes pendientes
						$pending_requests = array();
						if ( $is_manager ) {
							$pending_requests = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}rrhh_vacaciones WHERE status = 'requested' ORDER BY created_at DESC" );
						}
						?>

						<div class="tabs" role="tablist">
							<div class="tab" data-tab="tab-datos-personales">Datos Personales</div>
							<div class="tab" data-tab="tab-datos-laborales">Datos Laborales</div>
							<div class="tab" data-tab="tab-documentos">Documentos</div>
							<div class="tab" data-tab="tab-historial">Historial</div>
							<div class="tab active" data-tab="tab-vacaciones">Vacaciones</div>
						</div>

						<!-- Pane: Datos Personales -->
						<div id="tab-datos-personales" class="tab-pane">
							<form method="post">
								<?php wp_nonce_field( 'rrhh_profile_action', 'rrhh_profile_nonce' ); ?>
								<p><label>Teléfono<br><input type="text" name="rrhh_phone" value="<?php echo esc_attr( $employee ? $employee->phone : '' ); ?>" /></label></p>
								<p><label>Dirección<br><textarea name="rrhh_address"><?php echo esc_textarea( $employee ? $employee->address : '' ); ?></textarea></label></p>
								<p><label>Posición<br><input type="text" name="rrhh_position" value="<?php echo esc_attr( $employee ? $employee->position : '' ); ?>" /></label></p>
								<p><button type="submit" name="rrhh_save_profile">Guardar</button></p>
							</form>
						</div>

						<!-- Pane: Datos Laborales -->
						<div id="tab-datos-laborales" class="tab-pane">
							<h3>Datos Laborales</h3>
							<p><strong>Posición:</strong> <?php echo esc_html( $employee && $employee->position ? $employee->position : 'N/A' ); ?></p>
							<p><strong>Fecha de ingreso:</strong> N/D</p>
							<p><strong>Departamento:</strong> <?php echo esc_html( $employee && $employee->position ? $employee->position : 'General' ); ?></p>
						</div>

						<!-- Pane: Documentos -->
						<div id="tab-documentos" class="tab-pane">
							<h3>Documentos</h3>
							<p>Sube o revisa tus documentos aquí. (pendiente de implementar subida)</p>
						</div>

						<!-- Pane: Historial -->
						<div id="tab-historial" class="tab-pane">
							<h3>Historial de Vacaciones</h3>
							<table>
								<thead><tr><th>Inicio</th><th>Fin</th><th>Tipo</th><th>Días</th><th>Estado</th></tr></thead>
								<tbody>
								<?php if ( $vacaciones ) : foreach ( $vacaciones as $v ) :
									$st = strtolower( $v->status ); $badge = 'registered';
									if ( strpos( $st, 'aprob' ) !== false || $st === 'approved' ) { $badge = 'approved'; }
									if ( strpos( $st, 'rech' ) !== false || $st === 'rejected' ) { $badge = 'rejected'; }
								?>
								<tr>
									<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $v->start_date ) ) ); ?></td>
									<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $v->end_date ) ) ); ?></td>
									<td><?php echo esc_html( isset( $v->type ) && $v->type ? $v->type : 'Vacaciones' ); ?></td>
									<td><?php echo esc_html( $v->days ); ?></td>
									<td><span class="badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $v->status ); ?></span></td>
								</tr>
								<?php endforeach; else : ?>
								<tr><td colspan="5">No hay registros.</td></tr>
								<?php endif; ?>
								</tbody>
							</table>
						</div>

						<!-- Pane: Vacaciones -->
						<div id="tab-vacaciones" class="tab-pane active">
							<div class="cards">
								<div class="card"><h3>Días Asignados</h3><span><?php echo esc_html( $days_assigned ); ?></span></div>
								<div class="card"><h3>Días Utilizados</h3><span><?php echo esc_html( $days_used ); ?></span></div>
								<div class="card"><h3>Días Restantes</h3><span><?php echo esc_html( $days_remaining ); ?></span></div>
								<div class="card"><h3>Días Acumulados</h3><span><?php echo esc_html( $days_accumulated ); ?></span></div>
							</div>

							<h3>Solicitar Vacaciones</h3>
														<form method="post">
																<?php wp_nonce_field( 'rrhh_vacation_action', 'rrhh_vac_nonce' ); ?>
																<p><label>Fecha inicio<br><input type="date" name="start_date"></label></p>
																<p><label>Fecha fin<br><input type="date" name="end_date"></label></p>
																<p>
																	<label>Días<br>
																		<input type="number" name="days" min="0.5" step="0.5" class="vacation-days-field">
																	</label>
																	<span class="calculated-days-note" aria-live="polite"></span>
																</p>
																<p><label>Tipo<br><input type="text" name="type" value="Vacaciones"></label></p>
																<p><button type="submit" name="rrhh_request_vacation">Solicitar</button></p>
														</form>

							<h3>Mis Solicitudes</h3>
							<table>
								<thead><tr><th>Inicio</th><th>Fin</th><th>Tipo</th><th>Días</th><th>Estado</th></tr></thead>
								<tbody>
								<?php if ( $vacaciones ) : foreach ( $vacaciones as $v ) :
									$st = strtolower( $v->status ); $badge = 'registered';
									if ( strpos( $st, 'aprob' ) !== false || $st === 'approved' ) { $badge = 'approved'; }
									if ( strpos( $st, 'rech' ) !== false || $st === 'rejected' ) { $badge = 'rejected'; }
								?>
								<tr>
									<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $v->start_date ) ) ); ?></td>
									<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $v->end_date ) ) ); ?></td>
									<td><?php echo esc_html( isset( $v->type ) && $v->type ? $v->type : 'Vacaciones' ); ?></td>
									<td><?php echo esc_html( $v->days ); ?></td>
									<td><span class="badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $v->status ); ?></span></td>
								</tr>
								<?php endforeach; else : ?>
								<tr><td colspan="5">No hay registros.</td></tr>
								<?php endif; ?>
								</tbody>
							</table>

							<?php if ( $is_manager ) : ?>
								<h3>Solicitudes Pendientes (Aprobación)</h3>
								<?php if ( $pending_requests ) : ?>
									<table>
										<thead><tr><th>ID</th><th>Empleado</th><th>Desde</th><th>Hasta</th><th>Días</th><th>Acciones</th></tr></thead>
										<tbody>
										<?php foreach ( $pending_requests as $pr ) : ?>
										<tr>
											<td><?php echo esc_html( $pr->id ); ?></td>
											<td><?php echo esc_html( $pr->employee_id ); ?></td>
											<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $pr->start_date ) ) ); ?></td>
											<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $pr->end_date ) ) ); ?></td>
											<td><?php echo esc_html( $pr->days ); ?></td>
											<td>
												<form method="post" style="display:inline-block; vertical-align:top;">
													<?php wp_nonce_field( 'rrhh_update_action', 'rrhh_update_nonce' ); ?>
													<input type="hidden" name="rrhh_action" value="vacation_update" />
													<input type="hidden" name="vacation_id" value="<?php echo esc_attr( $pr->id ); ?>" />
													<p><label>Feedback (opcional)<br><textarea name="admin_feedback" rows="2" cols="30"></textarea></label></p>
													<button type="submit" name="new_status" value="approved">Aprobar</button>
												</form>
												<form method="post" style="display:inline-block; vertical-align:top; margin-left:8px;">
													<?php wp_nonce_field( 'rrhh_update_action', 'rrhh_update_nonce' ); ?>
													<input type="hidden" name="rrhh_action" value="vacation_update" />
													<input type="hidden" name="vacation_id" value="<?php echo esc_attr( $pr->id ); ?>" />
													<p><label>Feedback (opcional)<br><textarea name="admin_feedback" rows="2" cols="30"></textarea></label></p>
													<button type="submit" name="new_status" value="rejected">Rechazar</button>
												</form>
											</td>
										</tr>
										<?php endforeach; ?>
										</tbody>
									</table>
								<?php else : ?>
									<p>No hay solicitudes pendientes.</p>
								<?php endif; ?>
							<?php endif; ?>
						</div>

						<p style="margin-top:12px;"><a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">Cerrar sesión</a></p>
					<?php endif; ?>

					<?php if ( is_user_logged_in() ) : ?>
					<!-- Pane: Lista de empleados (visible para managers/admin) -->
					 <?php require_once plugin_dir_path(__FILE__) . 'views/dashboard.php'; ?>
					<div id="tab-lista-empleados" class="tab-pane">
						<?php if ( $is_manager ) : ?>
							<h3>Lista de Empleados</h3>
							<table>
								<thead><tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Email</th><th>Posición</th><th>Teléfono</th><th>RRHH Role</th></tr></thead>
								<tbody>
								<?php $all_users = get_users( array( 'orderby' => 'ID', 'order' => 'DESC' ) );
								if ( $all_users ) : foreach ( $all_users as $au ) :
									$emp_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rrhh_employees WHERE user_id = %d", $au->ID ) );
									$rrhh_role_u = get_user_meta( $au->ID, 'rrhh_role', true );
									?>
									<tr>
										<td><?php echo esc_html( $au->ID ); ?></td>
										<td><?php echo esc_html( $au->user_login ); ?></td>
										<td><?php echo esc_html( $au->display_name ); ?></td>
										<td><?php echo esc_html( $au->user_email ); ?></td>
										<td><?php echo esc_html( $emp_row ? $emp_row->position : '' ); ?></td>
										<td><?php echo esc_html( $emp_row ? $emp_row->phone : '' ); ?></td>
										<td><?php echo esc_html( $rrhh_role_u ? $rrhh_role_u : '' ); ?></td>
									</tr>
								<?php endforeach; else : ?>
								<tr><td colspan="7">No hay usuarios.</td></tr>
								<?php endif; ?>
								</tbody>
							</table>
						<?php else : ?>
							<p>No tienes permisos para ver la lista de empleados.</p>
						<?php endif; ?>
					</div>
					<?php endif; ?>

					<!-- Pane: Nuevo Empleado (visible para managers/admin) -->
					<div id="tab-nuevo-empleado" class="tab-pane">
						<?php if ( $is_manager ) : ?>
							<h3>Crear Nuevo Empleado</h3>
							<?php if ( ! empty( $errors ) ) : ?>
								<div class="error"><p><?php echo implode( '<br>', array_map( 'esc_html', $errors ) ); ?></p></div>
							<?php endif; ?>
							<form method="post">
								<?php wp_nonce_field( 'rrhh_create_user_action', 'rrhh_create_user_nonce' ); ?>
								<p><label>Usuario (login)<br><input type="text" name="new_user_login" required value=""></label></p>
								<p><label>Nombre completo<br><input type="text" name="new_user_name"></label></p>
								<p><label>Email<br><input type="email" name="new_user_email" required></label></p>
								<p><label>Contraseña (dejar vacío para autogenerar)<br><input type="password" name="new_user_pass"></label></p>
								<p><label>Teléfono<br><input type="text" name="rrhh_phone"></label></p>
								<p><label>Posición<br><input type="text" name="rrhh_position"></label></p>
								<p><label>Dirección<br><textarea name="rrhh_address"></textarea></label></p>
								<p><label>RRHH Role<br><select name="rrhh_role"><option value="">(ninguno)</option><option value="employee">Employee</option><option value="manager">Manager</option></select></label></p>
								<p><button type="submit" name="rrhh_create_user">Crear empleado</button></p>
							</form>
						<?php else : ?>
							<p>No tienes permisos para crear empleados.</p>
						<?php endif; ?>
					</div>

					<!-- Pane: Mi resumen (vacaciones) -->
					<div id="tab-mi-resumen" class="tab-pane">
						<h3>Mi Resumen</h3>
						<div class="cards">
							<div class="card"><h3>Días Asignados</h3><span><?php echo esc_html( $days_assigned ); ?></span></div>
							<div class="card"><h3>Días Utilizados</h3><span><?php echo esc_html( $days_used ); ?></span></div>
							<div class="card"><h3>Días Restantes</h3><span><?php echo esc_html( $days_remaining ); ?></span></div>
							<div class="card"><h3>Días Acumulados</h3><span><?php echo esc_html( $days_accumulated ); ?></span></div>
						</div>
					</div>

					<!-- Pane: Solicitudes (mis solicitudes / pendientes para manager) -->
					<div id="tab-solicitudes" class="tab-pane">
						<h3>Solicitudes</h3>
						<?php if ( $is_manager ) : ?>
							<h4>Solicitudes pendientes</h4>
							<?php if ( $pending_requests ) : ?>
								<table>
								<thead><tr><th>ID</th><th>Empleado</th><th>Desde</th><th>Hasta</th><th>Días</th><th>Acciones</th></tr></thead>
								<tbody>
								<?php foreach ( $pending_requests as $pr ) : ?>
								<tr>
									<td><?php echo esc_html( $pr->id ); ?></td>
									<td><?php echo esc_html( $pr->employee_id ); ?></td>
									<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $pr->start_date ) ) ); ?></td>
									<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $pr->end_date ) ) ); ?></td>
									<td><?php echo esc_html( $pr->days ); ?></td>
									<td>
										<form method="post" style="display:inline">
											<?php wp_nonce_field( 'rrhh_update_action', 'rrhh_update_nonce' ); ?>
											<input type="hidden" name="rrhh_action" value="vacation_update" />
											<input type="hidden" name="vacation_id" value="<?php echo esc_attr( $pr->id ); ?>" />
											<button type="submit" name="new_status" value="approved">Aprobar</button>
										</form>
										<form method="post" style="display:inline">
											<?php wp_nonce_field( 'rrhh_update_action', 'rrhh_update_nonce' ); ?>
											<input type="hidden" name="rrhh_action" value="vacation_update" />
											<input type="hidden" name="vacation_id" value="<?php echo esc_attr( $pr->id ); ?>" />
											<button type="submit" name="new_status" value="rejected">Rechazar</button>
										</form>
									</td>
								</tr>
								<?php endforeach; ?>
								</tbody>
								</table>
							<?php else : ?>
								<p>No hay solicitudes pendientes.</p>
							<?php endif; ?>
						<?php endif; ?>

						<h4>Mis solicitudes</h4>
						<?php if ( $vacaciones ) : ?>
							<table>
							<thead><tr><th>Inicio</th><th>Fin</th><th>Tipo</th><th>Días</th><th>Estado</th><th>Acción</th></tr></thead>
							<tbody>
							<?php foreach ( $vacaciones as $v ) : $st = strtolower( $v->status ); $badge = 'registered';
								if ( strpos( $st, 'aprob' ) !== false || $st === 'approved' ) { $badge = 'approved'; }
								if ( strpos( $st, 'rech' ) !== false || $st === 'rejected' ) { $badge = 'rejected'; }
								?>
								<tr>
									<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $v->start_date ) ) ); ?></td>
									<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $v->end_date ) ) ); ?></td>
									<td><?php echo esc_html( isset( $v->type ) && $v->type ? $v->type : 'Vacaciones' ); ?></td>
									<td><?php echo esc_html( $v->days ); ?></td>
									<td>
										<span class="badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $v->status ); ?></span>
										<?php if ( ! empty( $v->admin_feedback ) ) : ?>
											<div class="admin-feedback"><strong>Feedback:</strong> <?php echo esc_html( $v->admin_feedback ); ?></div>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $v->status === 'requested' ) : ?>
											<form method="post" style="display:inline">
												<?php wp_nonce_field( 'rrhh_cancel_action', 'rrhh_cancel_nonce' ); ?>
												<input type="hidden" name="vacation_id" value="<?php echo esc_attr( $v->id ); ?>" />
												<button type="submit" name="rrhh_cancel_vacation">Cancelar</button>
											</form>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
							</table>
						<?php else : ?>
							<p>No tienes solicitudes.</p>
						<?php endif; ?>
					</div>

					<!-- Pane: Calendario (listado simple de próximas vacaciones) -->
					<div id="tab-calendario" class="tab-pane">
						<h3>Calendario</h3>
						<?php
						// Render a simple month calendar and mark vacation days
						$month = isset( $_GET['m'] ) ? intval( $_GET['m'] ) : intval( date( 'n' ) );
						$year = isset( $_GET['y'] ) ? intval( $_GET['y'] ) : intval( date( 'Y' ) );
						$first = strtotime( "{$year}-{$month}-01" );
						$days_in_month = intval( date( 't', $first ) );
						$start_weekday = intval( date( 'N', $first ) );
						// get all vacations overlapping this month
						$start_month = date( 'Y-m-d', $first );
						$end_month = date( 'Y-m-t', $first );
						$vacations_month = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rrhh_vacaciones WHERE NOT (end_date < %s OR start_date > %s)", $start_month, $end_month ) );
						$vac_map = array();
						foreach ( $vacations_month as $vv ) {
							$s = strtotime( $vv->start_date );
							$e = strtotime( $vv->end_date );
							for ( $d = $s; $d <= $e; $d = strtotime( '+1 day', $d ) ) {
								$k = date( 'Y-m-d', $d );
								if ( ! isset( $vac_map[ $k ] ) ) $vac_map[ $k ] = array();
								$vac_map[ $k ][] = $vv;
							}
						}
						?>
						<div class="calendar">
							<div class="cal-header"><strong><?php echo esc_html( date_i18n( 'F Y', $first ) ); ?></strong></div>
							<div class="cal-grid">
								<?php $weekdays = array( 'Mon','Tue','Wed','Thu','Fri','Sat','Sun' ); foreach ( $weekdays as $wd ) : ?><div class="cal-weekday"><?php echo esc_html( $wd ); ?></div><?php endforeach; ?>
								<?php for ( $i = 1; $i < $start_weekday; $i++ ) : ?><div class="cal-cell empty"></div><?php endfor; ?>
								<?php for ( $day = 1; $day <= $days_in_month; $day++ ) :
									$d_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
									$has = isset( $vac_map[ $d_str ] ) ? true : false;
									?>
									<div class="cal-cell calendar-day<?php echo $has ? ' has-event' : ''; ?>" data-date="<?php echo esc_attr( $d_str ); ?>">
										<div class="cal-day-number"><?php echo esc_html( $day ); ?></div>
										<?php if ( $has ) : ?>
											<div class="cal-events">
												<?php foreach ( $vac_map[ $d_str ] as $ev ) : ?>
													<div class="cal-event"><?php echo esc_html( $ev->employee_id ) . ' (' . esc_html( $ev->status ) . ')'; ?></div>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>
									</div>
								<?php endfor; ?>
							</div>
						</div>
						<p class="muted">Haz click en un día para prefijar la fecha en el formulario de solicitud.</p>
							<?php
							// enqueue FullCalendar assets and render a graphical calendar
							wp_enqueue_style( 'rrhh-fullcalendar-style' );
							wp_enqueue_script( 'rrhh-fullcalendar' );
							$events = array();
							foreach ( $vacations_month as $vv ) {
								// use WP user display name if available
								$u = get_user_by( 'ID', intval( $vv->employee_id ) );
								$name = $u ? ( $u->display_name ? $u->display_name : $u->user_login ) : 'Empleado ' . intval( $vv->employee_id );
								$name = sanitize_text_field( $name );
								$title = $name . ' (' . sanitize_text_field( $vv->status ) . ') - ' . sanitize_text_field( strval( $vv->days ) ) . 'd';
								$color = '#007bff';
								switch ( strtolower( $vv->status ) ) {
									case 'approved': $color = '#28a745'; break;
									case 'requested': $color = '#ff9800'; break;
									case 'rejected': $color = '#dc3545'; break;
									case 'cancelled': $color = '#6c757d'; break;
								}
								$events[] = array(
									'title' => $title,
									'start' => $vv->start_date,
									'end' => date( 'Y-m-d', strtotime( $vv->end_date . ' +1 day' ) ),
									'allDay' => true,
									'color' => $color,
									'admin_feedback' => isset( $vv->admin_feedback ) ? $vv->admin_feedback : '',
								);
							}
							$events_json = wp_json_encode( $events );
							$init = "document.addEventListener('DOMContentLoaded', function(){ var el = document.getElementById('rrhh-calendar'); if(!el) return; var calendar = new FullCalendar.Calendar(el, { initialView: 'dayGridMonth', events: " . $events_json . ", eventDidMount: function(info){ if(info.event && info.event.extendedProps && info.event.extendedProps.admin_feedback){ info.el.setAttribute('title', info.event.title + '\nFeedback: ' + info.event.extendedProps.admin_feedback); } } }); calendar.render(); });";
							wp_add_inline_script( 'rrhh-fullcalendar', $init );
							?>
							<div id="rrhh-calendar" style="max-width:900px;margin-top:12px;"></div>
					</div>
				</div>
			</main>
		</div>
		<?php
		return ob_get_clean();
}

// Añadir entrada en el menú de administración de WordPress
add_action( 'admin_menu', 'rrhh_manager_admin_menu' );
function rrhh_manager_admin_menu() {
		add_menu_page(
				'RRHH Manager',           // Page title
				'RRHH Manager',           // Menu title
				'read',                   // Capability required
				'rrhh-manager',           // Menu slug
				'rrhh_manager_admin_page',// Callback
				'dashicons-groups',       // Icon
				6                         // Position
		);
//submenu recien agregado
		add_submenu_page(
    'hr-management',
    'Nueva solicitud de vacaciones',
    'Nueva solicitud',
    'manage_options',
    'hrm-vacaciones-nueva',
    'hrm_render_vacaciones_form_admin'
);

    
}















//hasta aqui el submenu





function rrhh_manager_admin_page() {
		echo '<div class="wrap">';
		echo do_shortcode( '[rrhh_manager]' );
		echo '</div>';
}

// Encolar assets cuando se está en la página de administración del plugin
add_action( 'admin_enqueue_scripts', 'rrhh_manager_admin_enqueue_assets' );
function rrhh_manager_admin_enqueue_assets( $hook ) {
		if ( 'toplevel_page_rrhh-manager' !== $hook ) {
				return;
		}
		rrhh_manager_register_assets();
		wp_enqueue_style( 'rrhh-manager-style' );
		wp_enqueue_script( 'rrhh-manager-tabs' );
}

// Reemplazar frontend público por la interfaz del plugin para usuarios no-admin
add_action( 'template_redirect', 'rrhh_manager_replace_frontend' );
function rrhh_manager_replace_frontend() {
	// Only replace the public frontend on the site homepage to avoid breaking admin or other routes
	if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() ) {
		return;
	}

	// Only intercept the main front page or blog index
	if ( ! ( is_front_page() || is_home() ) ) {
		return;
	}

	// permitir a administradores ver el sitio normal
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}

	// Registrar/enqueue de assets
	rrhh_manager_register_assets();
	wp_enqueue_style( 'rrhh-manager-style' );
	wp_enqueue_script( 'rrhh-manager-tabs' );

	// Renderizar la plantilla completa
	echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
	wp_head();
	echo '</head><body class="rrhh-frontend">';
	echo do_shortcode( '[rrhh_manager]' );
	wp_footer();
	echo '</body></html>';
	exit;
}

// Crear tablas al activar el plugin
register_activation_hook( __FILE__, 'rrhh_manager_install' );
function rrhh_manager_install() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_employees = $wpdb->prefix . 'rrhh_employees';
		$table_cotizaciones = $wpdb->prefix . 'rrhh_cotizaciones';
		$table_vacaciones = $wpdb->prefix . 'rrhh_vacaciones';

		$sql = "CREATE TABLE $table_employees (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT(20) UNSIGNED NOT NULL,
				phone VARCHAR(50) DEFAULT '',
				address TEXT,
				birthdate DATE DEFAULT NULL,
				position VARCHAR(150) DEFAULT '',
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY user_id (user_id)
		) $charset_collate;";
		dbDelta( $sql );

		$sql = "CREATE TABLE $table_cotizaciones (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				employee_id BIGINT(20) UNSIGNED NOT NULL,
				description TEXT,
				amount DECIMAL(12,2) DEFAULT 0,
				status VARCHAR(50) DEFAULT 'pending',
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY employee_id (employee_id)
		) $charset_collate;";
		dbDelta( $sql );

		$sql = "CREATE TABLE $table_vacaciones (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				employee_id BIGINT(20) UNSIGNED NOT NULL,
				start_date DATE NOT NULL,
				end_date DATE NOT NULL,
				days INT DEFAULT 0,
				status VARCHAR(50) DEFAULT 'requested',
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY employee_id (employee_id)
		) $charset_collate;";
		dbDelta( $sql );
}

// Ensure schema is up-to-date (add columns if missing)
add_action( 'init', 'rrhh_maybe_update_schema' );
function rrhh_maybe_update_schema() {
	global $wpdb;
	$table_v = $wpdb->prefix . 'rrhh_vacaciones';
	// check if admin_feedback column exists
	$col = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table_v} LIKE %s", 'admin_feedback' ) );
	if ( empty( $col ) ) {
		$wpdb->query( "ALTER TABLE {$table_v} ADD COLUMN admin_feedback TEXT DEFAULT ''" );
	}

	// Ensure days column can accept decimal (allow half-days)
	$col_days = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$table_v} LIKE %s", 'days' ) );
	if ( $col_days && isset( $col_days->Type ) ) {
		// if days is an integer type, alter to decimal(4,1)
		if ( stripos( $col_days->Type, 'int' ) !== false ) {
			$wpdb->query( "ALTER TABLE {$table_v} MODIFY COLUMN days DECIMAL(4,1) DEFAULT 0" );
		}
	}
}

// Añadir submenús para gestionar las tablas
add_action( 'admin_menu', 'rrhh_manager_admin_submenus' );
function rrhh_manager_admin_submenus() {
		add_submenu_page( 'rrhh-manager', 'Empleados', 'Empleados', 'manage_options', 'rrhh-empleados', 'rrhh_admin_employees_page' );
		add_submenu_page( 'rrhh-manager', 'Cotizaciones', 'Cotizaciones', 'manage_options', 'rrhh-cotizaciones', 'rrhh_admin_cotizaciones_page' );
		add_submenu_page( 'rrhh-manager', 'Vacaciones', 'Vacaciones', 'manage_options', 'rrhh-vacaciones', 'rrhh_admin_vacaciones_page' );
}




function rrhh_admin_cotizaciones_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'rrhh_cotizaciones';
		$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC" );
		echo '<div class="wrap"><h1>Cotizaciones</h1>';
		echo '<table class="widefat fixed"><thead><tr><th>ID</th><th>Empleado</th><th>Monto</th><th>Estado</th></tr></thead><tbody>';
		if ( $rows ) {
				foreach ( $rows as $r ) {
						echo '<tr><td>' . esc_html( $r->id ) . '</td><td>' . esc_html( $r->employee_id ) . '</td><td>' . esc_html( $r->amount ) . '</td><td>' . esc_html( $r->status ) . '</td></tr>';
				}
		} else {
				echo '<tr><td colspan="4">No hay registros.</td></tr>';
		}
		echo '</tbody></table></div>';
}


