<?php
/**
 * Página de Prueba
 * Vista de prueba para verificar permisos y acceso
 */

if (!defined('ABSPATH'))
    exit;

// Verificar permisos
$current_user = wp_get_current_user();
$has_manage = current_user_can('manage_options');
$is_anaconda = in_array('administrador_anaconda', (array) $current_user->roles, true);
$is_admin = in_array('administrator', (array) $current_user->roles, true);

?>

<div class="wrap hrm-wrap">
    <div class="hrm-header">
        <h1 class="hrm-title">
            <i class="dashicons dashicons-admin-tools"></i>
            Página de Prueba
        </h1>
        <p class="hrm-subtitle">Vista de prueba para verificar permisos y acceso</p>
    </div>

    <div class="hrm-content-wrapper">
        <div class="hrm-card">
            <div class="hrm-card-header">
                <h2>✅ Información del Usuario</h2>
            </div>
            <div class="hrm-card-body">
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <th style="width: 200px;">ID de Usuario:</th>
                            <td><strong>
                                    <?php echo esc_html($current_user->ID); ?>
                                </strong></td>
                        </tr>
                        <tr>
                            <th>Nombre de Usuario:</th>
                            <td><strong>
                                    <?php echo esc_html($current_user->user_login); ?>
                                </strong></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td>
                                <?php echo esc_html($current_user->user_email); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Roles:</th>
                            <td>
                                <?php
                                $roles = $current_user->roles;
                                if (!empty($roles)) {
                                    echo '<ul style="margin: 0; padding-left: 20px;">';
                                    foreach ($roles as $role) {
                                        echo '<li><code>' . esc_html($role) . '</code></li>';
                                    }
                                    echo '</ul>';
                                } else {
                                    echo '<em>Sin roles asignados</em>';
                                }
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="hrm-card" style="margin-top: 20px;">
            <div class="hrm-card-header">
                <h2>🔐 Verificación de Permisos</h2>
            </div>
            <div class="hrm-card-body">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Permiso</th>
                            <th style="text-align: center;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>manage_options</code></td>
                            <td style="text-align: center;">
                                <?php if ($has_manage): ?>
                                    <span style="color: #46b450; font-weight: bold;">✅ SÍ</span>
                                <?php else: ?>
                                    <span style="color: #dc3232; font-weight: bold;">❌ NO</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Rol <code>administrator</code></td>
                            <td style="text-align: center;">
                                <?php if ($is_admin): ?>
                                    <span style="color: #46b450; font-weight: bold;">✅ SÍ</span>
                                <?php else: ?>
                                    <span style="color: #dc3232; font-weight: bold;">❌ NO</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Rol <code>administrador_anaconda</code></td>
                            <td style="text-align: center;">
                                <?php if ($is_anaconda): ?>
                                    <span style="color: #46b450; font-weight: bold;">✅ SÍ</span>
                                <?php else: ?>
                                    <span style="color: #dc3232; font-weight: bold;">❌ NO</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><code>view_hrm_admin_views</code></td>
                            <td style="text-align: center;">
                                <?php if (current_user_can('view_hrm_admin_views')): ?>
                                    <span style="color: #46b450; font-weight: bold;">✅ SÍ</span>
                                <?php else: ?>
                                    <span style="color: #dc3232; font-weight: bold;">❌ NO</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><code>view_hrm_employee_admin</code></td>
                            <td style="text-align: center;">
                                <?php if (current_user_can('view_hrm_employee_admin')): ?>
                                    <span style="color: #46b450; font-weight: bold;">✅ SÍ</span>
                                <?php else: ?>
                                    <span style="color: #dc3232; font-weight: bold;">❌ NO</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><code>view_hrm_own_profile</code></td>
                            <td style="text-align: center;">
                                <?php if (current_user_can('view_hrm_own_profile')): ?>
                                    <span style="color: #46b450; font-weight: bold;">✅ SÍ</span>
                                <?php else: ?>
                                    <span style="color: #dc3232; font-weight: bold;">❌ NO</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="hrm-card" style="margin-top: 20px;">
            <div class="hrm-card-header">
                <h2>📊 Información del Sistema</h2>
            </div>
            <div class="hrm-card-body">
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <th style="width: 200px;">Hora del Servidor:</th>
                            <td>
                                <?php echo esc_html(current_time('Y-m-d H:i:s')); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Versión de WordPress:</th>
                            <td>
                                <?php echo esc_html(get_bloginfo('version')); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Versión de PHP:</th>
                            <td>
                                <?php echo esc_html(phpversion()); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>URL del Sitio:</th>
                            <td>
                                <?php echo esc_html(get_site_url()); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="hrm-card" style="margin-top: 20px; background: #d4edda; border-left: 4px solid #28a745;">
            <div class="hrm-card-body">
                <h3 style="margin-top: 0; color: #155724;">
                    <span class="dashicons dashicons-yes-alt" style="color: #28a745;"></span>
                    ¡Acceso Exitoso!
                </h3>
                <p style="margin-bottom: 0; color: #155724;">
                    Si puedes ver esta página, significa que tienes los permisos correctos para acceder a las vistas
                    administrativas.
                </p>
            </div>
        </div>
    </div>
</div>

<style>
    .hrm-wrap {
        margin: 20px 20px 20px 0;
    }

    .hrm-header {
        background: #fff;
        padding: 20px;
        border-left: 4px solid #2271b1;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .hrm-title {
        margin: 0 0 10px 0;
        font-size: 24px;
        font-weight: 600;
        color: #1d2327;
    }

    .hrm-title .dashicons {
        color: #2271b1;
        font-size: 28px;
        vertical-align: middle;
        margin-right: 10px;
    }

    .hrm-subtitle {
        margin: 0;
        color: #646970;
        font-size: 14px;
    }

    .hrm-content-wrapper {
        max-width: 1200px;
    }

    .hrm-card {
        background: #fff;
        border: 1px solid #c3c4c7;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .hrm-card-header {
        padding: 15px 20px;
        border-bottom: 1px solid #c3c4c7;
        background: #f6f7f7;
    }

    .hrm-card-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #1d2327;
    }

    .hrm-card-body {
        padding: 20px;
    }

    .widefat th {
        font-weight: 600;
        color: #1d2327;
    }

    code {
        background: #f0f0f1;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
        font-size: 13px;
    }
</style>