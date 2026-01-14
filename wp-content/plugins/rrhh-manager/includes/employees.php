<?php

// Empleados - funciones administrativas

function rrhh_admin_employees_page() {
		// Mostrar todos los usuarios de WordPress y datos RRHH si existen
		if ( ! current_user_can( 'manage_options' ) ) {
				echo '<div class="wrap"><h1>Empleados</h1><p>No tienes permisos para ver esta página.</p></div>';
				return;
		}

		global $wpdb;
		$errors = array();
		$table = $wpdb->prefix . 'rrhh_employees';

		// Procesar guardado rápido desde esta pantalla (admin edits)
		if ( isset( $_POST['rrhh_admin_save'] ) && check_admin_referer( 'rrhh_admin_save_action', 'rrhh_admin_save_nonce' ) ) {
			$uid = isset( $_POST['uid'] ) ? intval( $_POST['uid'] ) : 0;
			$phone = isset( $_POST['rrhh_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['rrhh_phone'] ) ) : '';
			$position = isset( $_POST['rrhh_position'] ) ? sanitize_text_field( wp_unslash( $_POST['rrhh_position'] ) ) : '';
			$address = isset( $_POST['rrhh_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rrhh_address'] ) ) : '';
			$rrhh_role = isset( $_POST['rrhh_role'] ) ? sanitize_text_field( wp_unslash( $_POST['rrhh_role'] ) ) : '';
			$days_assigned_meta = isset( $_POST['rrhh_days_assigned'] ) ? floatval( wp_unslash( $_POST['rrhh_days_assigned'] ) ) : null;
			$days_accumulated_meta = isset( $_POST['rrhh_days_accumulated'] ) ? floatval( wp_unslash( $_POST['rrhh_days_accumulated'] ) ) : null;
			$start_date_meta = isset( $_POST['rrhh_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['rrhh_start_date'] ) ) : '';
			if ( $uid ) {
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE user_id = %d", $uid ) );
				if ( $exists ) {
					$wpdb->update( $table, array( 'phone' => $phone, 'address' => $address, 'position' => $position ), array( 'user_id' => $uid ), array( '%s', '%s', '%s' ), array( '%d' ) );
				} else {
					$wpdb->insert( $table, array( 'user_id' => $uid, 'phone' => $phone, 'address' => $address, 'position' => $position ), array( '%d', '%s', '%s', '%s' ) );
				}
				if ( $rrhh_role ) {
					update_user_meta( $uid, 'rrhh_role', $rrhh_role );
				}
				// Save additional RRHH metas if provided
				if ( null !== $days_assigned_meta ) {
					update_user_meta( $uid, 'rrhh_days_assigned', $days_assigned_meta );
				}
				if ( null !== $days_accumulated_meta ) {
					update_user_meta( $uid, 'rrhh_days_accumulated', $days_accumulated_meta );
				}
				if ( $start_date_meta ) {
					update_user_meta( $uid, 'rrhh_start_date', $start_date_meta );
					// Optionally prorrate assigned days for the current year based on start date
					$assigned = get_user_meta( $uid, 'rrhh_days_assigned', true );
					if ( $assigned ) {
						$start_ts = strtotime( $start_date_meta );
						if ( $start_ts ) {
							$year_start = strtotime( date( 'Y-01-01', $start_ts ) );
							$year_end = strtotime( date( 'Y-12-31', $start_ts ) );
							$months = ( ( date( 'Y', $year_end ) - date( 'Y', $start_ts ) ) * 12 ) + ( date( 'n', $year_end ) - date( 'n', $start_ts ) ) + 1;
							$months_employed = max( 0, 12 - ( date( 'n', $start_ts ) - 1 ) );
							$prorated = round( ( floatval( $assigned ) * $months_employed / 12 ), 1 );
							update_user_meta( $uid, 'rrhh_days_assigned', $prorated );
						}
					}
				}
			}
		}

		// Crear nuevo usuario RRHH (admin UI)
		if ( isset( $_GET['new_user'] ) ) {
			// Prefill variables for the form (use submitted values if present)
			$new_user_login = isset( $_POST['new_user_login'] ) ? sanitize_user( wp_unslash( $_POST['new_user_login'] ) ) : '';
			$new_user_name = isset( $_POST['new_user_name'] ) ? sanitize_text_field( wp_unslash( $_POST['new_user_name'] ) ) : '';
			$new_user_email = isset( $_POST['new_user_email'] ) ? sanitize_email( wp_unslash( $_POST['new_user_email'] ) ) : '';
			$new_user_pass = isset( $_POST['new_user_pass'] ) ? $_POST['new_user_pass'] : '';
			$new_rrhh_role = isset( $_POST['rrhh_role'] ) ? sanitize_text_field( wp_unslash( $_POST['rrhh_role'] ) ) : '';
			$new_phone = isset( $_POST['rrhh_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['rrhh_phone'] ) ) : '';
			$new_position = isset( $_POST['rrhh_position'] ) ? sanitize_text_field( wp_unslash( $_POST['rrhh_position'] ) ) : '';
			$new_address = isset( $_POST['rrhh_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rrhh_address'] ) ) : '';

			if ( isset( $_POST['rrhh_create_user'] ) && check_admin_referer( 'rrhh_create_user_action', 'rrhh_create_user_nonce' ) ) {
				$username = $new_user_login;
				$email = $new_user_email;
				// generate password if left empty
				$password = ! empty( $new_user_pass ) ? $new_user_pass : wp_generate_password( 12, false );
				$display_name = $new_user_name;
				$rrhh_role = $new_rrhh_role;
				$phone = $new_phone;
				$position = $new_position;
				$address = $new_address;

				$errors = array();
				if ( empty( $username ) ) { $errors[] = 'Usuario requerido'; }
				if ( empty( $email ) || ! is_email( $email ) ) { $errors[] = 'Email inválido'; }

				if ( empty( $errors ) ) {
					// crear usuario WP
					$user_id = username_exists( $username );
					if ( ! $user_id && email_exists( $email ) === false ) {
						$user_id = wp_create_user( $username, $password, $email );
						if ( ! is_wp_error( $user_id ) ) {
							wp_update_user( array( 'ID' => $user_id, 'display_name' => $display_name ) );
							// asignar rol WP mínimo
							$basic_role = 'subscriber';
							$u = new WP_User( $user_id );
							$u->set_role( $basic_role );
							// guardar meta y fila RRHH
							if ( $rrhh_role ) update_user_meta( $user_id, 'rrhh_role', $rrhh_role );
							$wpdb->insert( $table, array( 'user_id' => $user_id, 'phone' => $phone, 'address' => $address, 'position' => $position ), array( '%d', '%s', '%s', '%s' ) );
							wp_safe_redirect( admin_url( 'admin.php?page=rrhh-empleados&created=1' ) );
							exit;
						} else {
							$errors[] = 'Error creando usuario: ' . $user_id->get_error_message();
						}
					} else {
						$errors[] = 'Usuario o email ya registrado.';
					}
				}
			}

			// Mostrar formulario nuevo usuario
			echo '<div class="wrap"><h1>Crear Nuevo Empleado</h1>';
			if ( ! empty( $errors ) ) {
				echo '<div class="error"><p>' . implode( '<br>', array_map( 'esc_html', $errors ) ) . '</p></div>';
			}
			echo '<form method="post">';
			wp_nonce_field( 'rrhh_create_user_action', 'rrhh_create_user_nonce' );
			echo '<p><label>Usuario (login)<br><input type="text" name="new_user_login" required value="' . esc_attr( $new_user_login ) . '"></label></p>';
			echo '<p><label>Nombre completo<br><input type="text" name="new_user_name" value="' . esc_attr( $new_user_name ) . '"></label></p>';
			echo '<p><label>Email<br><input type="email" name="new_user_email" required value="' . esc_attr( $new_user_email ) . '"></label></p>';
			echo '<p><label>Contraseña (dejar vacío para autogenerar)<br><input type="password" name="new_user_pass"></label></p>';
			echo '<p><label>Teléfono<br><input type="text" name="rrhh_phone" value="' . esc_attr( $new_phone ) . '"></label></p>';
			echo '<p><label>Posición<br><input type="text" name="rrhh_position" value="' . esc_attr( $new_position ) . '"></label></p>';
			echo '<p><label>Dirección<br><textarea name="rrhh_address">' . esc_textarea( $new_address ) . '</textarea></label></p>';
			echo '<p><label>RRHH Role<br><select name="rrhh_role"><option value="">(ninguno)</option><option value="employee" ' . selected( $new_rrhh_role, 'employee', false ) . '>Employee</option><option value="manager" ' . selected( $new_rrhh_role, 'manager', false ) . '>Manager</option></select></label></p>';
			echo '<p><button type="submit" name="rrhh_create_user">Crear empleado</button></p>';
			echo '</form></div>';
			return;
		}

		// Si se solicita editar un usuario, mostrar formulario de edición
		$edit_user = isset( $_GET['edit_user'] ) ? intval( $_GET['edit_user'] ) : 0;
		if ( $edit_user ) {
			$user = get_user_by( 'ID', $edit_user );
			if ( ! $user ) {
				echo '<div class="wrap"><p>Usuario no encontrado.</p></div>';
				return;
			}
			$emp = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d", $edit_user ) );
			echo '<div class="wrap"><h1>Editar RRHH: ' . esc_html( $user->user_login ) . '</h1>';
			echo '<form method="post">';
			wp_nonce_field( 'rrhh_admin_save_action', 'rrhh_admin_save_nonce' );
			echo '<input type="hidden" name="uid" value="' . esc_attr( $edit_user ) . '" />';
			echo '<p><label>Teléfono<br><input type="text" name="rrhh_phone" value="' . esc_attr( $emp ? $emp->phone : '' ) . '" /></label></p>';
			echo '<p><label>Dirección<br><textarea name="rrhh_address">' . esc_textarea( $emp ? $emp->address : '' ) . '</textarea></label></p>';
			echo '<p><label>Posición<br><input type="text" name="rrhh_position" value="' . esc_attr( $emp ? $emp->position : '' ) . '" /></label></p>';
			// rrhh_role
			$meta_role = get_user_meta( $edit_user, 'rrhh_role', true );
			echo '<p><label>RRHH Role<br><select name="rrhh_role">';
			echo '<option value="">(ninguno)</option>';
			echo '<option value="employee" ' . selected( $meta_role, 'employee', false ) . '>Employee</option>';
			echo '<option value="manager" ' . selected( $meta_role, 'manager', false ) . '>Manager</option>';
			echo '</select></label></p>';

			// Days assigned / accumulated / start date
			$meta_assigned = get_user_meta( $edit_user, 'rrhh_days_assigned', true );
			$meta_acc = get_user_meta( $edit_user, 'rrhh_days_accumulated', true );
			$meta_start = get_user_meta( $edit_user, 'rrhh_start_date', true );
			echo '<p><label>Días asignados<br><input type="number" name="rrhh_days_assigned" step="0.5" min="0" value="' . esc_attr( $meta_assigned ? $meta_assigned : '' ) . '" /></label></p>';
			echo '<p><label>Días acumulados<br><input type="number" name="rrhh_days_accumulated" step="0.1" min="0" value="' . esc_attr( $meta_acc ? $meta_acc : '' ) . '" /></label></p>';
			echo '<p><label>Fecha de ingreso<br><input type="date" name="rrhh_start_date" value="' . esc_attr( $meta_start ? $meta_start : '' ) . '" /></label></p>';
			echo '<p><button type="submit" name="rrhh_admin_save">Guardar RRHH</button></p>';
			echo '</form></div>';
			return;
		}

		$users = get_users( array( 'orderby' => 'ID', 'order' => 'DESC' ) );

		echo '<div class="wrap"><h1>Empleados</h1>';
		// Add New button to open the new user form
		echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'rrhh-empleados', 'new_user' => 1 ), admin_url( 'admin.php' ) ) ) . '" class="page-title-action">Nuevo empleado</a>';
		if ( isset( $_GET['created'] ) ) {
			echo '<div class="updated"><p>Empleado creado correctamente.</p></div>';
		}
		echo '<table class="widefat fixed"><thead><tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Email</th><th>Posición</th><th>Teléfono</th><th>RRHH Role</th><th>Acciones</th></tr></thead><tbody>';
		if ( $users ) {
			foreach ( $users as $u ) {
				$emp = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d", $u->ID ) );
				$rrhh_role = get_user_meta( $u->ID, 'rrhh_role', true );
				echo '<tr>';
				echo '<td>' . esc_html( $u->ID ) . '</td>';
				echo '<td>' . esc_html( $u->user_login ) . '</td>';
				// WP roles
				$roles = implode( ', ', $u->roles );
				echo '<td>' . esc_html( $roles ) . '</td>';
				echo '<td>' . esc_html( $u->display_name ) . '</td>';
				echo '<td>' . esc_html( $u->user_email ) . '</td>';
				echo '<td>' . esc_html( $emp ? $emp->position : '' ) . '</td>';
				echo '<td>' . esc_html( $emp ? $emp->phone : '' ) . '</td>';
				echo '<td>' . esc_html( $rrhh_role ? $rrhh_role : '' ) . '</td>';
				echo '<td><a href="' . esc_url( get_edit_user_link( $u->ID ) ) . '">Editar WP</a> | ';
				echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'rrhh-empleados', 'edit_user' => $u->ID ), admin_url( 'admin.php' ) ) ) . '">Ver RRHH</a></td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="8">No hay usuarios.</td></tr>';
		}
		echo '</tbody></table></div>';
}
