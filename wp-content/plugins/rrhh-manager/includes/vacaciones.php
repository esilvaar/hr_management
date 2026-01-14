<?php

// Vacaciones - funciones administrativas

function rrhh_admin_vacaciones_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'rrhh_vacaciones';
		$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC" );
		echo '<div class="wrap"><h1>Vacaciones</h1>';
		echo '<table class="widefat fixed"><thead><tr><th>ID</th><th>Empleado</th><th>Desde</th><th>Hasta</th><th>Días</th><th>Estado</th></tr></thead><tbody>';
		if ( $rows ) {
			foreach ( $rows as $r ) {
				echo '<tr><td>' . esc_html( $r->id ) . '</td><td>' . esc_html( $r->employee_id ) . '</td><td>' . esc_html( $r->start_date ) . '</td><td>' . esc_html( $r->end_date ) . '</td><td>' . esc_html( $r->days ) . '</td><td>' . esc_html( $r->status ) . '</td></tr>';
			}
		} else {
			echo '<tr><td colspan="6">No hay registros.</td></tr>';
		}
		echo '</tbody></table></div>';
}
