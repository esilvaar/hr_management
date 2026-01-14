<div class="wrap">
    <h1 class="wp-heading-inline">Panel de Control - Solicitudes Pendientes</h1>
    <hr class="wp-header-end">

    <div class="card" style="margin-top: 20px; padding: 0; max-width: 100%;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" width="15%">Empleado</th>
                    <th scope="col" width="10%">Tipo</th>
                    <th scope="col" width="15%">Fechas</th>
                    <th scope="col" width="5%">Días</th>
                    <th scope="col">Motivo</th>
                    <th scope="col" width="10%">Estado</th>
                    <th scope="col" width="15%">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $solicitudes ) ) : ?>
                    <?php foreach($solicitudes as $s): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($s->display_name); ?></strong><br>
                            <span style="font-size: 11px; color: #666;"><?php echo esc_html($s->user_email); ?></span>
                        </td>
                        
                        <td><?php echo esc_html($s->tipo); ?></td>
                        
                        <td>
                            <?php 
                            $f_inicio = date('d/m/Y', strtotime($s->fecha_inicio));
                            $f_fin = date('d/m/Y', strtotime($s->fecha_fin));
                            echo $f_inicio . ' <br>al<br> ' . $f_fin; 
                            ?>
                        </td>

                        <td>
                            <?php 
                            $d1 = new DateTime($s->fecha_inicio);
                            $d2 = new DateTime($s->fecha_fin);
                            $diff = $d1->diff($d2);
                            echo ($diff->days + 1); 
                            ?>
                        </td>
                        
                        <td><?php echo esc_html($s->motivo); ?></td>
                        
                        <td>
                            <?php 
                            $color_style = 'background:#999;'; // Gris por defecto
                            if($s->estado === 'APROBADO') $color_style = 'background:#46b450;';
                            if($s->estado === 'RECHAZADO') $color_style = 'background:#dc3232;';
                            if($s->estado === 'PENDIENTE') $color_style = 'background:#ffb900; color:black;';
                            ?>
                            <span style="<?php echo $color_style; ?> color:white; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase;">
                                <?php echo esc_html($s->estado); ?>
                            </span>
                        </td>
                        
                        <td>
                            <div style="display:flex; gap: 5px; align-items: center;">
                                <a href="<?php echo admin_url('admin.php?page=ahr-view-pdf&id=' . $s->id); ?>" 
                                   target="_blank" 
                                   class="button button-small button-secondary" 
                                   title="Ver Documento Oficial">
                                    <span class="dashicons dashicons-media-document" style="margin-top: 3px;"></span>
                                </a>

                                <?php if($s->estado == 'PENDIENTE'): ?>
                                <form method="post" style="display:flex; gap: 5px; margin:0;">
                                    <?php wp_nonce_field('ahr_nonce_status', 'ahr_security'); ?>
                                    <input type="hidden" name="ahr_action" value="cambiar_estado">
                                    <input type="hidden" name="solicitud_id" value="<?php echo $s->id; ?>">
                                    
                                    <button type="submit" name="nuevo_estado" value="APROBADO" class="button button-small button-primary" title="Aprobar">
                                        <span class="dashicons dashicons-yes" style="margin-top: 3px;"></span>
                                    </button>
                                    
                                    <button type="submit" name="nuevo_estado" value="RECHAZADO" class="button button-small" style="color: #a00; border-color: #d63638;" title="Rechazar">
                                        <span class="dashicons dashicons-no" style="margin-top: 3px;"></span>
                                    </button>
                                </form>
                                <?php else: ?>
                                    <span class="dashicons dashicons-lock" style="color: #ccc; margin-left: 5px;"></span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                
                <?php else : ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px; color: #666;">
                            <span class="dashicons dashicons-yes-alt" style="font-size: 40px; color: #46b450;"></span><br>
                            ¡Todo al día! No hay solicitudes pendientes de revisión.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
