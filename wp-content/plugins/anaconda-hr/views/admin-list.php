<div class="wrap">
    <h1 class="wp-heading-inline">Directorio de Empleados</h1>
    <a href="<?php echo admin_url('admin.php?page=ahr-nuevo-empleado'); ?>" class="page-title-action">Añadir Nuevo</a>
    <hr class="wp-header-end">

    <style>
        .ahr-table-container {
            margin-top: 20px;
            background: #fff;
            border: 1px solid #c3c4c7;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        /* Evitar que los títulos se pongan verticales */
        .ahr-table th {
            white-space: nowrap; 
            vertical-align: middle;
        }
        /* Ajuste de columnas */
        .col-avatar { width: 50px; text-align: center; }
        .col-estado { width: 100px; }
        .col-acciones { width: 100px; text-align: right; }
        
        /* El email a veces es largo, dejamos que se ajuste pero sin romper la tabla */
        .col-email a {
            word-break: break-all; /* Si es larguísimo, que corte la palabra */
            color: #2271b1;
            text-decoration: none;
        }
        .col-email a:hover { text-decoration: underline; }
    </style>

    <div class="ahr-table-container">
        <table class="wp-list-table widefat fixed striped ahr-table">
            <thead>
                <tr>
                    <th scope="col" class="col-avatar">Avatar</th>
                    <th scope="col">Empleado</th>
                    <th scope="col">Departamento / Cargo</th>
                    <th scope="col" class="col-email">Contacto</th>
                    <th scope="col">F. Ingreso</th>
                    <th scope="col" class="col-estado">Estado</th>
                    <th scope="col" class="col-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $lista_empleados ) ) : ?>
                    <?php foreach ( $lista_empleados as $empleado ) : ?>
                        <?php 
                            $avatar = get_avatar( $empleado->email, 40 ); 
                        ?>
                        <tr>
                            <td class="col-avatar">
                                <div style="border-radius: 50%; overflow: hidden; width: 40px; height: 40px; margin: 0 auto;">
                                    <?php echo $avatar; ?>
                                </div>
                            </td>

                            <td>
                                <strong>
                                    <?php echo esc_html( $empleado->nombres . ' ' . $empleado->apellidos ); ?>
                                </strong>
                                <?php if($empleado->wp_user_id): ?>
                                    <span class="dashicons dashicons-wordpress" title="Usuario WP Conectado" style="color:#2271b1; font-size:16px; vertical-align: middle;"></span>
                                <?php endif; ?>
                                <br>
                                <span style="color: #646970; font-size: 12px;">RUT: <?php echo esc_html( $empleado->rut ); ?></span>
                            </td>

                            <td>
                                <span style="font-weight: 500;"><?php echo esc_html( $empleado->cargo ); ?></span><br>
                                <span style="background: #f0f0f1; color: #50575e; padding: 2px 6px; border-radius: 4px; font-size: 11px; text-transform: uppercase; display: inline-block; margin-top: 2px;">
                                    <?php echo esc_html( $empleado->departamento ); ?>
                                </span>
                            </td>

                            <td class="col-email">
                                <a href="mailto:<?php echo esc_attr( $empleado->email ); ?>">
                                    <?php echo esc_html( $empleado->email ); ?>
                                </a>
                            </td>

                            <td>
                                <?php echo date_i18n( 'd/m/Y', strtotime( $empleado->fecha_ingreso ) ); ?>
                            </td>

                            <td class="col-estado">
                                <?php 
                                $bg_color = ($empleado->estado === 'Activo') ? '#dcfce7' : '#fce7e7';
                                $text_color = ($empleado->estado === 'Activo') ? '#166534' : '#991b1b';
                                ?>
                                <span style="background: <?php echo $bg_color; ?>; color: <?php echo $text_color; ?>; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: bold;">
                                    <?php echo esc_html( $empleado->estado ); ?>
                                </span>
                            </td>

                            <td class="col-acciones">
                                <?php if($empleado->wp_user_id): ?>
                                    <a href="<?php echo get_edit_user_link($empleado->wp_user_id); ?>" class="button button-small" title="Administrar Usuario y Contraseña">
                                        <span class="dashicons dashicons-admin-users" style="margin-top:3px;"></span> Cuenta
                                    </a>
                                <?php else: ?>
                                    <span style="color:#ccc;">Sin Usuario</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">
                            No hay empleados registrados.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>