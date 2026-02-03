<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Esta vista muestra el formulario para crear un nuevo empleado
// Variables disponibles: $hrm_departamentos, $hrm_puestos, $hrm_tipos_contrato

// Obtener los roles permitidos de forma segura
$wp_roles = function_exists( 'hrm_get_allowed_employee_roles' ) ? hrm_get_allowed_employee_roles() : wp_roles()->get_names();

// Determinar el área de gerencia del usuario que está viendo la página
// y preparar un listado de puestos permitidos para mostrar (solo para no-admins)
$viewer_allowed_puestos = array();
$current_user = wp_get_current_user();
if ( ! current_user_can( 'manage_options' ) && $current_user && $current_user->ID ) {
    global $wpdb;
    $user_id = absint( $current_user->ID );
    $area_gerencia = $wpdb->get_var( $wpdb->prepare( "SELECT area_gerencia FROM {$wpdb->prefix}rrhh_empleados WHERE user_id = %d", $user_id ) );

    if ( $area_gerencia ) {
        // Obtener departamentos asociados al área (usa la función existente si está disponible)
        $deptos_por_area = function_exists( 'hrm_get_deptos_predefinidos_por_area' ) ? hrm_get_deptos_predefinidos_por_area( $area_gerencia ) : array();

        // Guardar también los departamentos permitidos para filtrar el select de departamento
        $viewer_allowed_departamentos = $deptos_por_area;
        $viewer_allowed_departamentos_lower = array_map( 'strtolower', $viewer_allowed_departamentos );

        // Mapa departamento -> puestos (paralelo al mapa JS en la vista)
        $mapa_puestos_php = array(
            'soporte' => array('Ingeniero de Soporte', 'Practicante'),
            'desarrollo' => array('Desarrollador de Software', 'Diseñador Gráfico'),
            'ventas' => array('Asistente Comercial'),
            'administracion' => array('Administrativo(a) Contable'),
            'gerencia' => array('Gerente'),
            'sistemas' => array('Ingeniero en Sistemas'),
        );

        foreach ( $deptos_por_area as $d ) {
            $key = strtolower( $d );
            if ( isset( $mapa_puestos_php[ $key ] ) ) {
                $viewer_allowed_puestos = array_merge( $viewer_allowed_puestos, $mapa_puestos_php[ $key ] );
            }
        }
        $viewer_allowed_puestos = array_unique( $viewer_allowed_puestos );
    }
}
?>

<div class="d-flex justify-content-center">
    <div class="rounded shadow-sm w-100">
        
        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center p-3 bg-dark text-white rounded-top">
            <h2 class="mb-0">Crear Nuevo Empleado</h2>
        </div>
        
        <div class="card-body p-4">

            <form method="post">
                <?php wp_nonce_field( 'hrm_create_employee', 'hrm_create_employee_nonce' ); ?>
                <input type="hidden" name="hrm_action" value="create_employee">
                <!-- Hidden inputs para guardar los años calculados -->
                <input type="hidden" id="hrm_anos_en_la_empresa_hidden" name="anos_en_la_empresa" value="0">
                <input type="hidden" id="hrm_anos_totales_trabajados_hidden" name="anos_totales_trabajados" value="0">
                
                <!-- SECCIÓN 1: DATOS REQUERIDOS -->
                <fieldset class="hrm-form-section mb-4">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="hrm_rut" class="form-label">RUT <span class="text-danger">*</span></label>
                            <input id="hrm_rut" name="rut" type="text" placeholder="12345678-9" class="form-control" required>
                            <div id="hrm_rut_feedback" class="mt-2" class="myplugin-hidden"></div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="hrm_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input id="hrm_email" name="email" type="email" class="form-control" required title="Ingresa un correo electrónico válido">
                            <div id="hrm_email_feedback" class="mt-2" class="myplugin-hidden"></div>
                        </div>
                    </div>
                    <!-- area_gerencia moved next to departamento select -->
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="hrm_nombre" class="form-label">Nombres <span class="text-danger">*</span></label>
                            <input id="hrm_nombre" name="nombre" type="text" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="hrm_apellido" class="form-label">Apellidos <span class="text-danger">*</span></label>
                            <input id="hrm_apellido" name="apellido" type="text" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                                    <label for="hrm_fecha_ingreso" class="form-label">Fecha de Ingreso <span class="text-danger">*</span></label>
                                    <input id="hrm_fecha_ingreso" name="fecha_ingreso" type="date" class="form-control" title="Fecha de ingreso a la empresa" required>
                        </div>
                    </div>
                    <div class="row mt-3 mb-3">
                        <div class="col-md-6">
                            <label for="hrm_departamento" class="form-label">Departamento <span class="text-danger">*</span></label>
                            <select id="hrm_departamento" name="departamento" class="form-select" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ( $hrm_departamentos as $dept ) : ?>
                                    <?php if ( isset( $viewer_allowed_departamentos_lower ) && ! empty( $viewer_allowed_departamentos_lower ) ) {
                                        if ( ! in_array( strtolower( $dept ), $viewer_allowed_departamentos_lower, true ) ) continue;
                                    } ?>
                                    <option value="<?= esc_attr( $dept ) ?>"><?= esc_html( $dept ) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="area_gerencia_section" class="myplugin-hidden myplugin-mt-12">
                                <label for="hrm_area_gerencia" class="form-label">Área de Gerencia</label>
                                <select id="hrm_area_gerencia" name="area_gerencia" class="form-select">
                                    <option value="">Selecciona...</option>
                                    <option value="Proyectos">Proyectos</option>
                                    <option value="Comercial">Comercial</option>
                                    <option value="Operaciones">Operaciones</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="hrm_puesto" class="form-label">Puesto <span class="text-danger">*</span></label>
                            <select id="hrm_puesto" name="puesto" class="form-select" required data-all-puestos='<?= esc_attr( wp_json_encode( array_values( $hrm_puestos ) ) ) ?>'>
                                <option value="">Selecciona...</option>
                                <?php
                                foreach ( $hrm_puestos as $puesto ) :
                                    if ( ! empty( $viewer_allowed_puestos ) && ! in_array( $puesto, $viewer_allowed_puestos, true ) ) continue;
                                ?>
                                    <option value="<?= esc_attr( $puesto ) ?>"><?= esc_html( $puesto ) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </fieldset>
                
                <!-- SECCIÓN 2: INFORMACIÓN LABORAL -->
                <fieldset class="hrm-form-section mb-4">
                    <legend class="text-primary fs-5 pb-2 mb-3">
                        <button type="button" id="hrm_toggle_options" class="btn btn-sm btn-outline-secondary float-end">
                            <i class="dashicons dashicons-arrow-down"></i> Más opciones
                        </button>
                    </legend>
                    
                    <div id="hrm_more_options" class="myplugin-hidden">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="hrm_telefono" class="form-label">Teléfono</label>
                                <input id="hrm_telefono" name="telefono" type="text" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="hrm_fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                                <input id="hrm_fecha_nacimiento" name="fecha_nacimiento" type="date" class="form-control">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                            </div>

                            <?php // JS moved to assets/js/employees-create.js - puesto filtering moved to centralized script. ?>

                        </div>

                        <!-- Sección: Departamentos a cargo (solo para gerentes) -->
                        <div id="hrm_deptos_a_cargo_container" class="myplugin-hidden">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="form-label"><strong>Departamentos a Cargo</strong></label>
                                    <div id="hrm_deptos_checkboxes" class="border p-3 rounded">
                                        <!-- Los checkboxes se cargarán dinámicamente -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="hrm_tipo_contrato" class="form-label">Tipo de Contrato</label>
                                <select id="hrm_tipo_contrato" name="tipo_contrato" class="form-select">
                                    <option value="">Selecciona...</option>
                                    <?php if ( ! empty( $hrm_tipos_contrato ) ) : ?>
                                        <?php foreach ( $hrm_tipos_contrato as $contrato ) : ?>
                                            <option value="<?= esc_attr( $contrato ) ?>"><?= esc_html( $contrato ) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="hrm_salario" class="form-label">Salario</label>
                                <input id="hrm_salario" name="salario" type="number" step="0.01" class="form-control" placeholder="0.00">
                            </div>
                        </div>

                        <!-- SECCIÓN: ANTIGÜEDAD LABORAL -->
                        <hr class="my-4">
                        <fieldset id="hrm_antiguedad_section" class="hrm-form-section mb-4">
                            <legend class="h5 mb-3">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                Antigüedad Laboral
                            </legend>
                            <div class="alert alert-info border-start border-4 border-info mb-3">
                                <small><strong>Nota:</strong> Los años en la empresa se actualizan automáticamente cada aniversario de la fecha de ingreso. El total de años trabajados es la suma de años anteriores + años en la empresa.</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="hrm_anos_acreditados_anteriores" class="form-label">Años Acreditados Anteriores</label>
                                    <input id="hrm_anos_acreditados_anteriores" name="anos_acreditados_anteriores" type="number" step="0.5" min="0" value="0" class="form-control" title="Años de experiencia en otras empresas">
                                    <small class="form-text text-muted d-block mt-1">Experiencia laboral en otras empresas antes de entrar aquí (opcional)</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="hrm_anos_en_la_empresa" class="form-label">Años en la Empresa</label>
                                    <div class="input-group">
                                        <input id="hrm_anos_en_la_empresa" name="anos_en_la_empresa" type="number" step="0.1" min="0" value="0" class="form-control" readonly title="Se calcula automáticamente">
                                        <span class="input-group-text"><small>años</small></span>
                                    </div>
                                    <small class="form-text text-muted d-block mt-1">Se actualiza automáticamente</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="hrm_anos_totales_trabajados" class="form-label">Total de Años Trabajados</label>
                                    <div class="input-group">
                                        <input id="hrm_anos_totales_trabajados" name="anos_totales_trabajados" type="number" step="0.1" min="0" value="0" class="form-control" readonly title="Suma de años anteriores + años en la empresa">
                                        <span class="input-group-text"><small>años</small></span>
                                    </div>
                                    <small class="form-text text-muted d-block mt-1">Cálculo automático</small>
                                </div>
                            </div>

                        </fieldset>

                        <!-- Fecha de Término (fuera del fieldset de antigüedad para poder mostrarse independientemente) -->
                        <div id="hrm_fecha_termino_row" class="myplugin-hidden myplugin-mt-16">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="hrm_fecha_termino" class="form-label">Fecha de Término</label>
                                    <input id="hrm_fecha_termino" name="fecha_termino" type="date" class="form-control">
                                    <small class="form-text text-muted d-block mt-1">Fecha estimada de término (solo para practicantes).</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- SECCIÓN 3: USUARIO WORDPRESS -->
                <fieldset class="hrm-form-section mb-4 border-top pt-4">
                    <legend class="text- fs-5 pb-2 mb-3">
                        <strong>Crear Usuario WordPress</strong>
                    </legend>

                    <div class="mb-3">
                        <div class="d-flex align-items-center form-check">
                            <input
                                type="checkbox"
                                name="crear_usuario_wp"
                                id="hrm_crear_usuario_wp"
                                class="form-check-input"
                                checked
                            >
                            <label class="form-check-label" for="hrm_crear_usuario_wp">
                                Crear cuenta de usuario en WordPress
                            </label>
                        </div>

                        <small class="form-text text-muted">
                            Si activas esta opción, se creará una cuenta con usuario y contraseña generados automáticamente, y se enviarán las credenciales al email del empleado.
                        </small>
                    </div>


                    <div id="hrm_rol_row" class="myplugin-hidden" class="mb-3">
                        <label for="hrm_rol_usuario_wp" class="form-label">Rol en WordPress <span class="text-danger">*</span></label>
                        <?php
                        // Determinar roles disponibles en el select dependiendo del usuario que mira la página
                        $available_wp_roles = $wp_roles;
                        $current_user_obj = wp_get_current_user();
                        $has_supervisor_role = ( $current_user_obj && ! empty( $current_user_obj->roles ) && in_array( 'supervisor', (array) $current_user_obj->roles, true ) );
                        $is_supervisor_view = ( $has_supervisor_role || current_user_can( 'edit_hrm_employees' ) ) && ! current_user_can( 'manage_options' );

                        if ( $is_supervisor_view ) {
                            // Para supervisores: mostrar SOLO roles de tipo "empleado".
                            // Preferir la función de whitelist si está presente, pero
                            // forzar que el conjunto resultante contenga únicamente roles
                            // cuyo slug o nombre indiquen "empleado".
                            $all_roles = wp_roles()->get_names();
                            $filtered = array();

                            if ( function_exists( 'hrm_get_allowed_employee_roles' ) ) {
                                $allowed = hrm_get_allowed_employee_roles(); // expected array role_key => role_name
                                $allowed_keys = is_array( $allowed ) ? array_keys( $allowed ) : array();

                                if ( ! empty( $allowed_keys ) ) {
                                    foreach ( $all_roles as $rk => $rn ) {
                                        // Incluir solo si está en la whitelist y parece un rol de empleado
                                        if ( in_array( $rk, $allowed_keys, true ) && (
                                                $rk === 'empleado' || stripos( $rk, 'emplead' ) !== false || stripos( $rk, 'employee' ) !== false ||
                                                stripos( $rn, 'emplead' ) !== false || stripos( $rn, 'employee' ) !== false
                                            ) ) {
                                            $filtered[ $rk ] = $rn;
                                        }
                                    }
                                }
                            }

                            // Si no hay resultados todavía, buscar roles por heurística
                            if ( empty( $filtered ) ) {
                                foreach ( $all_roles as $rk => $rn ) {
                                    if ( $rk === 'empleado' || stripos( $rk, 'emplead' ) !== false || stripos( $rk, 'employee' ) !== false || stripos( $rn, 'emplead' ) !== false || stripos( $rn, 'employee' ) !== false ) {
                                        $filtered[ $rk ] = $rn;
                                    }
                                }
                            }

                            // Último recurso: roles de baja confianza por defecto
                            if ( empty( $filtered ) ) {
                                $fallback_keys = array( 'subscriber', 'contributor' );
                                foreach ( $all_roles as $rk => $rn ) {
                                    if ( in_array( $rk, $fallback_keys, true ) ) $filtered[ $rk ] = $rn;
                                }
                            }

                            $available_wp_roles = $filtered;
                        }
                        ?>
                        <select id="hrm_rol_usuario_wp" name="rol_usuario_wp" class="form-select">
                            <option value="">Selecciona un rol...</option>
                            <?php foreach ( $available_wp_roles as $role_key => $role_name ) : ?>
                                <option value="<?= esc_attr( $role_key ) ?>"><?= esc_html( $role_name ) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">
                            El rol determina qué permisos tendrá el usuario en el sistema
                        </small>
                    </div>
                </fieldset>

                <!-- BOTONES DE ACCIÓN -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="javascript:history.back()" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="dashicons dashicons-plus-alt2"></i> Crear Empleado
                    </button>
                </div>
            </form>

        </div>
<?php // JS moved to assets/js/employees-create.js - behavior consolidated there ?>
    </div>
<?php // JS moved to assets/js/employees-create.js - behavior consolidated there ?>