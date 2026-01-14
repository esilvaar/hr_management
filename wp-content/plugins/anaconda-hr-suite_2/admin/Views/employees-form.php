<?php
/**
 * Vista: Formulario de Empleado (Crear/Editar)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap anaconda-hrsuite">
    <h1><?php echo $employee ? esc_html( __( 'Editar Empleado', 'anaconda-hr-suite' ) ) : esc_html( __( 'Crear Nuevo Empleado', 'anaconda-hr-suite' ) ); ?></h1>

    <form method="POST" id="employee-form">
        <?php wp_nonce_field( 'anaconda_hrsuite_nonce' ); ?>
        <input type="hidden" name="action" value="save_employee">
        <?php if ( $employee ) : ?>
            <input type="hidden" name="employee_id" value="<?php echo esc_attr( $employee->id ); ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="rut"><?php echo esc_html( __( 'RUT', 'anaconda-hr-suite' ) ); ?> *</label>
            <input type="text" id="rut" name="rut" value="<?php echo $employee ? esc_attr( $employee->rut ) : ''; ?>" required>
        </div>

        <div class="form-group">
            <label for="nombre"><?php echo esc_html( __( 'Nombre', 'anaconda-hr-suite' ) ); ?> *</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo $employee ? esc_attr( $employee->nombre ) : ''; ?>" required>
        </div>

        <div class="form-group">
            <label for="apellido"><?php echo esc_html( __( 'Apellido', 'anaconda-hr-suite' ) ); ?> *</label>
            <input type="text" id="apellido" name="apellido" value="<?php echo $employee ? esc_attr( $employee->apellido ) : ''; ?>" required>
        </div>

        <div class="form-group">
            <label for="email"><?php echo esc_html( __( 'Email', 'anaconda-hr-suite' ) ); ?></label>
            <input type="email" id="email" name="email" value="<?php echo $employee ? esc_attr( $employee->email ) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="telefono"><?php echo esc_html( __( 'Teléfono', 'anaconda-hr-suite' ) ); ?></label>
            <input type="text" id="telefono" name="telefono" value="<?php echo $employee ? esc_attr( $employee->telefono ) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="fecha_nacimiento"><?php echo esc_html( __( 'Fecha de Nacimiento', 'anaconda-hr-suite' ) ); ?></label>
            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo $employee ? esc_attr( $employee->fecha_nacimiento ) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="fecha_ingreso"><?php echo esc_html( __( 'Fecha de Ingreso', 'anaconda-hr-suite' ) ); ?></label>
            <input type="date" id="fecha_ingreso" name="fecha_ingreso" value="<?php echo $employee ? esc_attr( $employee->fecha_ingreso ) : date( 'Y-m-d' ); ?>">
        </div>

        <div class="form-group">
            <label for="departamento"><?php echo esc_html( __( 'Departamento', 'anaconda-hr-suite' ) ); ?></label>
            <input type="text" id="departamento" name="departamento" value="<?php echo $employee ? esc_attr( $employee->departamento ) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="puesto"><?php echo esc_html( __( 'Puesto', 'anaconda-hr-suite' ) ); ?></label>
            <input type="text" id="puesto" name="puesto" value="<?php echo $employee ? esc_attr( $employee->puesto ) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="tipo_contrato"><?php echo esc_html( __( 'Tipo de Contrato', 'anaconda-hr-suite' ) ); ?></label>
            <select id="tipo_contrato" name="tipo_contrato">
                <option value=""><?php echo esc_html( __( 'Seleccionar...', 'anaconda-hr-suite' ) ); ?></option>
                <option value="Indefinido" <?php selected( $employee->tipo_contrato ?? '', 'Indefinido' ); ?>>
                    <?php echo esc_html( __( 'Indefinido', 'anaconda-hr-suite' ) ); ?>
                </option>
                <option value="Plazo Fijo" <?php selected( $employee->tipo_contrato ?? '', 'Plazo Fijo' ); ?>>
                    <?php echo esc_html( __( 'Plazo Fijo', 'anaconda-hr-suite' ) ); ?>
                </option>
                <option value="Temporal" <?php selected( $employee->tipo_contrato ?? '', 'Temporal' ); ?>>
                    <?php echo esc_html( __( 'Temporal', 'anaconda-hr-suite' ) ); ?>
                </option>
            </select>
        </div>

        <div class="form-group">
            <label for="salario"><?php echo esc_html( __( 'Salario', 'anaconda-hr-suite' ) ); ?></label>
            <input type="number" id="salario" name="salario" step="0.01" value="<?php echo $employee ? esc_attr( $employee->salario ) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="estado">
                <input type="checkbox" id="estado" name="estado" value="1" <?php checked( $employee->estado ?? 1, 1 ); ?>>
                <?php echo esc_html( __( 'Activo', 'anaconda-hr-suite' ) ); ?>
            </label>
        </div>

        <div class="form-group">
            <button type="submit" class="button button-primary">
                <?php echo $employee ? esc_html( __( 'Actualizar Empleado', 'anaconda-hr-suite' ) ) : esc_html( __( 'Crear Empleado', 'anaconda-hr-suite' ) ); ?>
            </button>
            <a href="<?php echo esc_url( anaconda_hrsuite_admin_url( 'anaconda-hr-suite-employees' ) ); ?>" class="button">
                <?php echo esc_html( __( 'Cancelar', 'anaconda-hr-suite' ) ); ?>
            </a>
        </div>
    </form>

    <style>
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group select {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-group input[type="checkbox"] {
            margin-right: 5px;
        }
    </style>
</div>
