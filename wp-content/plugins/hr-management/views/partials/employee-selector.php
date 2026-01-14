<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Si estamos en la pestaña list, no renderizamos el selector aquí
if ( isset( $tab ) && $tab === 'list' ) return;

// Obtener empleados si no fueron provistos por el scope
if ( ! isset( $all_emps ) ) {
    if ( isset( $db_emp ) && is_object( $db_emp ) ) {
        $all_emps = $db_emp->get_all();
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
        
        <div class="dropdown-menu shadow" id="hrm-employee-list" aria-labelledby="hrm-employee-selector-btn" style="min-width: 300px; max-width: 500px;">
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
            <div id="hrm-employee-items" style="max-height: 350px; overflow-y: auto;">
                <?php if ( ! empty( $all_emps ) ) : ?>
                    <?php foreach ( $all_emps as $e ) : ?>
                        <a 
                            class="dropdown-item hrm-employee-item py-2" 
                            href="?page=hrm-empleados&tab=<?= esc_attr( $tab ?? 'list' ) ?>&id=<?= esc_attr( $e->id ) ?>"
                            data-employee-id="<?= esc_attr( $e->id ) ?>"
                            data-employee-name="<?= esc_attr( $e->nombre . ' ' . $e->apellido ) ?>"
                            data-employee-search="<?= esc_attr( strtolower( $e->nombre . ' ' . $e->apellido . ' ' . $e->rut ) ) ?>">
                            <div class="d-flex align-items-center gap-2">
                                <div>
                                    <strong class="d-block"><?= esc_html( $e->nombre . ' ' . $e->apellido ) ?></strong>
                                    <small class="text-muted"><?= esc_html( $e->rut ) ?></small>
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
