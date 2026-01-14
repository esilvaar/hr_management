<div class="wrap">
    <h1 class="wp-heading-inline">Registrar Nuevo Empleado</h1>
    <hr class="wp-header-end">

    <?php 
    // Mostrar mensajes de éxito/error si existen en la URL
    if ( isset($_GET['msg']) && $_GET['msg'] == 'empleado_creado' ) {
        echo '<div class="notice notice-success is-dismissible"><p>¡Empleado creado y usuario de WordPress generado correctamente!</p></div>';
    }
    if ( isset($_GET['msg']) && $_GET['msg'] == 'error_db' ) {
        echo '<div class="notice notice-error is-dismissible"><p>Ocurrió un error al guardar en la base de datos.</p></div>';
    }
    ?>

    <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
        <form method="post" action="">
            <?php wp_nonce_field('crear_empleado_accion', 'crear_empleado_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="rut">RUT / DNI</label></th>
                    <td><input type="text" name="rut" id="rut" class="regular-text" placeholder="Ej: 12.345.678-9" required></td>
                </tr>

                <tr>
                    <th><label for="nombres">Nombres</label></th>
                    <td><input type="text" name="nombres" id="nombres" class="regular-text" required></td>
                </tr>

                <tr>
                    <th><label for="apellidos">Apellidos</label></th>
                    <td><input type="text" name="apellidos" id="apellidos" class="regular-text" required></td>
                </tr>

                <tr>
                    <th><label for="email">Correo Corporativo</label></th>
                    <td>
                        <input type="email" name="email" id="email" class="regular-text" required>
                        <p class="description">Este email se usará para crear su cuenta de acceso a WordPress.</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="departamento">Departamento</label></th>
                    <td>
                        <select name="departamento" id="departamento">
                            <option value="RRHH">Recursos Humanos</option>
                            <option value="TI">Tecnología / TI</option>
                            <option value="Operaciones">Operaciones</option>
                            <option value="Ventas">Ventas</option>
                            <option value="Administracion">Administración</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label for="cargo">Cargo</label></th>
                    <td><input type="text" name="cargo" id="cargo" class="regular-text" placeholder="Ej: Analista Senior"></td>
                </tr>

                <tr>
                    <th><label for="fecha_ingreso">Fecha de Ingreso</label></th>
                    <td><input type="date" name="fecha_ingreso" id="fecha_ingreso" required></td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="btn_guardar_empleado" class="button button-primary button-large">Guardar y Crear Usuario</button>
                <a href="<?php echo admin_url('admin.php?page=ahr-empleados'); ?>" class="button button-secondary">Cancelar</a>
            </p>
        </form>
    </div>
</div>