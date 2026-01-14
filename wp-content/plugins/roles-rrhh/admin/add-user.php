<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('create_users')) {
    wp_die('No tienes permisos.');
}

/**
 * Procesar formulario
 */
if (isset($_POST['rrhh_create_user'])) {

    check_admin_referer('rrhh_create_user_nonce');

    $username = sanitize_user($_POST['username']);
    $email    = sanitize_email($_POST['email']);
    $password = $_POST['password'];
    $role     = sanitize_text_field($_POST['role']);

    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        echo '<div class="notice notice-error"><p>' . esc_html($user_id->get_error_message()) . '</p></div>';
    } else {
        wp_update_user([
            'ID'   => $user_id,
            'role' => $role
        ]);

        echo '<div class="notice notice-success"><p>Usuario creado correctamente.</p></div>';
    }
}

/**
 * Obtener usuarios RRHH
 */
$args = [
    'role__in' => ['empleado', 'editor_vacaciones', 'supervisor'],
    'orderby'  => 'ID',
    'order'    => 'DESC',
];

$usuarios = get_users($args);
?>

<div class="wrap">
    <h1>Gestión RRHH</h1>

    <!-- FORMULARIO -->
    <h2>Añadir Usuario</h2>

    <form method="post">
        <?php wp_nonce_field('rrhh_create_user_nonce'); ?>

        <table class="form-table">
            <tr>
                <th>Usuario</th>
                <td><input type="text" name="username" required class="regular-text"></td>
            </tr>

            <tr>
                <th>Email</th>
                <td><input type="email" name="email" required class="regular-text"></td>
            </tr>

            <tr>
                <th>Contraseña</th>
                <td><input type="password" name="password" required class="regular-text"></td>
            </tr>

            <tr>
                <th>Rol</th>
                <td>
                    <select name="role" required>
                        <option value="empleado">Empleado</option>
                        <option value="editor_vacaciones">Editor de Vacaciones</option>
                        <option value="supervisor">Supervisor</option>
                    </select>
                </td>
            </tr>
        </table>

        <p>
            <input type="submit" name="rrhh_create_user" class="button button-primary" value="Crear Usuario">
        </p>
    </form>

    <!-- TABLA DE USUARIOS -->
    <hr>

    <h2>Usuarios RRHH</h2>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th width="80">ID</th>
                <th>Usuario</th>
                <th>Email</th>
                <th>Rol</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($usuarios)) : ?>
                <?php foreach ($usuarios as $user) : ?>
                    <tr>
                        <td><?php echo esc_html($user->ID); ?></td>
                        <td><?php echo esc_html($user->user_login); ?></td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td>
                            <?php
                                $roles = array_map('ucwords', str_replace('_', ' ', $user->roles));
                                echo esc_html(implode(', ', $roles));
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="4">No hay usuarios RRHH registrados.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
