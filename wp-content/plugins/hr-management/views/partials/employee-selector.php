<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Si estamos en la pestaña list, no renderizamos el selector aquí
if ( isset( $tab ) && $tab === 'list' ) return;

// Detectar la página actual para mantenerla en los enlaces del selector
$current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'hrm-empleados';

// Obtener empleados si no fueron provistos por el scope
if ( ! isset( $all_emps ) ) {
    if ( isset( $db_emp ) && is_object( $db_emp ) ) {
        $all_emps = $db_emp->get_visible_for_user( get_current_user_id(), null );
    } else {
        $all_emps = array();
    }
}
$curr_id = isset( $id ) ? $id : 0;
$curr_name = '';

// Obtener nombre del empleado actual si existe
if ( $curr_id > 0 ) {
    foreach ( $all_emps as $e ) {
        if ( $e->id == $curr_id ) {
            $curr_name = $e->nombre . ' ' . $e->apellido;
            break;
        }
    }
}
?>

<div class="hrm-employee-selector mt-3 mb-3">
    <div class="dropdown">
        <button 
            class="btn btn-outline-secondary dropdown-toggle" 
            type="button" 
            id="hrm-employee-selector-btn" 
            data-bs-toggle="dropdown" 
            aria-expanded="false">
            <span class="d-inline-flex align-items-center gap-2">
                <span class="dashicons dashicons-admin-users"></span>
                <?= $curr_name ?: 'Seleccionar Empleado' ?>
            </span>
        </button>
        
        <div class="dropdown-menu shadow hrm-employee-dropdown" id="hrm-employee-list" aria-labelledby="hrm-employee-selector-btn">
            <!-- Search input -->
            <div class="p-2 border-bottom sticky-top bg-white">
                <input 
                    type="text" 
                    class="form-control form-control-sm" 
                    id="hrm-employee-search" 
                    placeholder="Buscar empleado..."
                    autocomplete="off">
            </div>
            
            <!-- Employee list -->
            <div id="hrm-employee-items" class="hrm-employee-items">
                <!-- Opción: Ninguno -->
                <a 
                    class="dropdown-item hrm-employee-item py-2" 
                    href="?page=<?= esc_attr( $current_page ) ?>&tab=<?= esc_attr( $tab ?? 'list' ) ?>&id=0"
                    data-employee-id="0"
                    data-employee-name="Ninguno"
                    data-employee-search="ninguno">
                    <div class="d-flex align-items-center gap-2">
                        <div>
                            <strong class="d-block hrm-table-text-main">Ninguno</strong>
                            <small class="hrm-table-text-secondary">Sin selección</small>
                        </div>
                    </div>
                </a>
                
                <?php if ( ! empty( $all_emps ) ) : ?>
                    <?php foreach ( $all_emps as $e ) : ?>
                        <a 
                            class="dropdown-item hrm-employee-item py-2" 
                            href="?page=<?= esc_attr( $current_page ) ?>&tab=<?= esc_attr( $tab ?? 'list' ) ?>&id=<?= esc_attr( $e->id ) ?>"
                            data-employee-id="<?= esc_attr( $e->id ) ?>"
                            data-employee-name="<?= esc_attr( $e->nombre . ' ' . $e->apellido ) ?>"
                            data-employee-search="<?= esc_attr( strtolower( $e->nombre . ' ' . $e->apellido . ' ' . $e->rut ) ) ?>">
                            <div class="d-flex align-items-center gap-2">
                                <div>
                                    <strong class="d-block hrm-table-text-main"><?= esc_html( $e->nombre . ' ' . $e->apellido ) ?></strong>
                                    <small class="hrm-table-text-secondary"><?= esc_html( $e->rut ) ?></small>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else : ?>
                    <span class="dropdown-item disabled text-center py-3">
                        <span class="dashicons dashicons-info"></span>
                        No hay empleados disponibles
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
