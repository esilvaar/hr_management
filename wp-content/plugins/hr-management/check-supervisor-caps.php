<?php
/**
 * Script temporal para verificar capacidades de supervisores
 * Ejecutar desde: wp-admin/admin.php?page=hrm_check_caps
 */

// Agregar página de admin temporal
add_action( 'admin_menu', function() {
    add_submenu_page(
        null, // Parent slug (null = oculto del menú)
        'Verificar Capacidades HRM',
        'Verificar Capacidades HRM',
        'manage_options',
        'hrm_check_caps',
        'hrm_display_caps_check'
    );
} );

function hrm_display_caps_check() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No tienes permisos para acceder a esta página.' );
    }
    
    echo '<div class="wrap">';
    echo '<h1>Verificación de Capacidades - HR Management</h1>';
    
    // Verificar rol de supervisor
    $supervisor_role = get_role( 'supervisor' );
    
    echo '<h2>Rol: Supervisor</h2>';
    if ( $supervisor_role ) {
        echo '<p><strong>El rol "supervisor" existe.</strong></p>';
        echo '<h3>Capacidades del rol supervisor:</h3>';
        echo '<ul>';
        foreach ( $supervisor_role->capabilities as $cap => $enabled ) {
            $status = $enabled ? '✅' : '❌';
            echo '<li>' . $status . ' ' . esc_html( $cap ) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p style="color: red;"><strong>⚠️ El rol "supervisor" NO existe!</strong></p>';
        echo '<p>Ejecutando hrm_create_roles()...</p>';
        require_once plugin_dir_path( __FILE__ ) . 'includes/roles-capabilities.php';
        hrm_create_roles();
        echo '<p>✅ Roles creados. <a href="">Recargar página</a></p>';
    }
    
    // Listar usuarios con rol supervisor
    echo '<h2>Usuarios con rol Supervisor</h2>';
    $supervisors = get_users( array( 'role' => 'supervisor' ) );
    
    if ( empty( $supervisors ) ) {
        echo '<p><em>No hay usuarios con rol "supervisor".</em></p>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Usuario</th><th>Email</th><th>Capacidades clave</th></tr></thead>';
        echo '<tbody>';
        
        foreach ( $supervisors as $user ) {
            $caps_check = array(
                'read' => $user->has_cap( 'read' ) ? '✅' : '❌',
                'edit_hrm_employees' => $user->has_cap( 'edit_hrm_employees' ) ? '✅' : '❌',
                'view_hrm_employee_admin' => $user->has_cap( 'view_hrm_employee_admin' ) ? '✅' : '❌',
                'manage_options' => $user->has_cap( 'manage_options' ) ? '✅' : '❌',
            );
            
            echo '<tr>';
            echo '<td>' . $user->ID . '</td>';
            echo '<td>' . esc_html( $user->user_login ) . '</td>';
            echo '<td>' . esc_html( $user->user_email ) . '</td>';
            echo '<td>';
            echo 'read: ' . $caps_check['read'] . ' | ';
            echo 'edit_hrm_employees: ' . $caps_check['edit_hrm_employees'] . ' | ';
            echo 'view_hrm_employee_admin: ' . $caps_check['view_hrm_employee_admin'];
            echo '</td>';
            echo '</tr>';
            
            // Mostrar TODAS las capacidades del usuario
            echo '<tr>';
            echo '<td colspan="4" style="padding-left: 40px; font-size: 0.9em; color: #666;">';
            echo '<strong>Todas las capacidades:</strong> ';
            if ( ! empty( $user->allcaps ) ) {
                $all_caps = array_keys( array_filter( $user->allcaps ) );
                echo esc_html( implode( ', ', $all_caps ) );
            } else {
                echo '<em>ninguna</em>';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    // Botón para forzar actualización de capacidades
    echo '<hr>';
    echo '<h2>Acciones</h2>';
    
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'force_update_caps' ) {
        require_once plugin_dir_path( __FILE__ ) . 'includes/roles-capabilities.php';
        hrm_ensure_capabilities();
        echo '<div class="notice notice-success"><p>✅ Capacidades actualizadas. <a href="?page=hrm_check_caps">Volver a verificar</a></p></div>';
    }
    
    echo '<p>';
    echo '<a href="?page=hrm_check_caps&action=force_update_caps" class="button button-primary">Forzar actualización de capacidades</a>';
    echo '</p>';
    
    echo '</div>';
}
