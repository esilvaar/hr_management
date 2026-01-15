<?php
/**
 * Shortcode y página de confirmación de solicitud de vacaciones
 * Mantiene compatibilidad si se desea usar una página separada en el futuro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode para mostrar la página de confirmación
 * (Opcional - puede usarse en una página si se desea)
 */
function hrm_confirmation_solicitud_shortcode() {
    // Verificar que el usuario esté logueado
    if ( ! is_user_logged_in() ) {
        return '<p class="notice notice-warning">Debes iniciar sesión para acceder a esta página.</p>';
    }
    
    ob_start();
    ?>
    <div class="hrm-confirmation-container" style="max-width: 700px; margin: 40px auto; padding: 0 20px;">
        
        <!-- ALERTA DE ÉXITO -->
        <div class="notice notice-success hrm-success-alert" style="
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            border: none;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
            margin-bottom: 30px;
        ">
            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                <span style="
                    display: inline-block;
                    width: 60px;
                    height: 60px;
                    background: rgba(255, 255, 255, 0.2);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 30px;
                    margin-right: 20px;
                ">✓</span>
                <div>
                    <h1 style="margin: 0; font-size: 28px; color: white;">¡Solicitud Creada Exitosamente!</h1>
                    <p style="margin: 5px 0 0 0; font-size: 16px; opacity: 0.95;">Tu solicitud de vacaciones ha sido registrada</p>
                </div>
            </div>
        </div>
        
        <!-- INFORMACIÓN -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
            <p style="color: #666; line-height: 1.8;">
                Tu solicitud ha sido enviada a tu gerente directo y al editor de vacaciones para revisión.
            </p>
            <p style="color: #666; line-height: 1.8;">
                Recibirás un correo de confirmación en tu bandeja de entrada.
            </p>
            <div style="text-align: center; margin-top: 20px;">
                <a href="<?php echo esc_url( home_url( '/vacaciones/' ) ); ?>" style="
                    display: inline-block;
                    background: #4caf50;
                    color: white;
                    padding: 12px 30px;
                    border-radius: 6px;
                    text-decoration: none;
                    font-weight: bold;
                ">Ver mis Solicitudes</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Registrar el shortcode
add_shortcode( 'hrm_confirmation_solicitud_vacaciones', 'hrm_confirmation_solicitud_shortcode' );

