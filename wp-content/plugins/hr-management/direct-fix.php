<?php
/**
 * Script directo para reparar capacidades del supervisor
 * Ejecutar desde terminal: php direct-fix.php
 */

// Cargar WordPress
require_once( dirname(__FILE__) . '/../../../../wp-load.php' );

echo "=== Reparación de capacidades del Supervisor ===\n\n";

// 1. Verificar estado actual del rol
$supervisor = get_role( 'supervisor' );

if ( ! $supervisor ) {
    echo "❌ El rol 'supervisor' NO existe.\n";
    echo "Creando rol supervisor...\n";
    
    add_role( 'supervisor', 'Supervisor', array(
        'read' => true,
        'upload_files' => true,
        'view_hrm_employee_admin' => true,
        'edit_hrm_employees' => true,
        'view_hrm_own_profile' => true,
    ) );
    
    $supervisor = get_role( 'supervisor' );
    echo "✅ Rol 'supervisor' creado.\n\n";
} else {
    echo "✅ El rol 'supervisor' existe.\n\n";
}

// 2. Mostrar capacidades actuales
echo "Capacidades actuales del rol supervisor:\n";
foreach ( $supervisor->capabilities as $cap => $enabled ) {
    $status = $enabled ? '✅' : '❌';
    echo "  $status $cap\n";
}
echo "\n";

// 3. Agregar capacidades faltantes
echo "Agregando/actualizando capacidades...\n";
$caps_to_add = array(
    'read' => true,
    'upload_files' => true,
    'view_hrm_employee_admin' => true,
    'edit_hrm_employees' => true,
    'view_hrm_own_profile' => true,
);

foreach ( $caps_to_add as $cap => $value ) {
    $supervisor->add_cap( $cap, $value );
    echo "  ✅ $cap agregado\n";
}
echo "\n";

// 4. Actualizar usuarios supervisores
$supervisors = get_users( array( 'role' => 'supervisor' ) );
echo "Usuarios con rol supervisor: " . count($supervisors) . "\n";

if ( count($supervisors) > 0 ) {
    echo "Actualizando usuarios...\n";
    foreach ( $supervisors as $user ) {
        // Remover y agregar el rol para forzar actualización
        $user->remove_role( 'supervisor' );
        $user->add_role( 'supervisor' );
        
        // Verificar capacidades
        $has_edit = $user->has_cap( 'edit_hrm_employees' );
        $status = $has_edit ? '✅' : '❌';
        echo "  $status " . $user->user_login . " (ID: " . $user->ID . ") - edit_hrm_employees: " . ($has_edit ? 'SÍ' : 'NO') . "\n";
    }
    echo "\n";
}

// 5. Verificación final
$supervisor = get_role( 'supervisor' );
echo "=== Verificación Final ===\n";
echo "Capacidades del rol supervisor:\n";
foreach ( $supervisor->capabilities as $cap => $enabled ) {
    $status = $enabled ? '✅' : '❌';
    echo "  $status $cap\n";
}

if ( $supervisor->has_cap( 'edit_hrm_employees' ) ) {
    echo "\n✅✅✅ ¡ÉXITO! El rol supervisor tiene 'edit_hrm_employees'\n";
} else {
    echo "\n❌❌❌ ERROR: El rol supervisor AÚN NO tiene 'edit_hrm_employees'\n";
}

echo "\n=== Reparación completada ===\n";
