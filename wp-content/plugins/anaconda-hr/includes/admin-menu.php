<?php
if (!defined('ABSPATH'))
    exit;

// Hook para crear el menú
add_action('admin_menu', 'ahr_register_menu_pages');

function ahr_register_menu_pages()
{

    // 1. Menú Principal (Dashboard / Resumen)
    add_menu_page(
        'Gestión RRHH',              // Título de página
        'Anaconda RRHH',             // Título del menú
        'read',                      // Capacidad
        'ahr-dashboard',             // Slug
        'ahr_render_dashboard',      // Función
        'dashicons-groups',          // Icono
        6                            // Posición
    );

    // 2. Submenú: Mis Solicitudes (Para que el empleado pida vacaciones)
    add_submenu_page(
        'ahr-dashboard',
        'Mis Solicitudes',
        'Solicitar Vacaciones',
        'read',
        'ahr-vacaciones',
        'ahr_render_vacaciones_view'
    );

    // 3. Submenú: Lista de Empleados (Solo Admin)
    add_submenu_page(
        'ahr-dashboard',
        'Directorio de Empleados',
        'Empleados',
        'manage_options',
        'ahr-empleados',
        'ahr_render_lista_empleados'
    );

    // 4. Submenú: Nuevo Empleado (Solo Admin)
    add_submenu_page(
        'ahr-dashboard',
        'Registrar Nuevo',
        'Añadir Empleado',
        'manage_options',
        'ahr-nuevo-empleado',
        'ahr_render_nuevo_empleado'
    );

    // 5. Página oculta para Generar el Documento/PDF
    // Usamos NULL como padre para que no salga en el menú lateral, pero funcione el link
    add_submenu_page(
        null,
        'Generar Documento',
        'Generar Documento',
        'manage_options',
        'ahr-view-pdf',
        'ahr_render_pdf_view'
    );

    // 6. Nuevo Submenú: Matriz de Turnos
    add_submenu_page(
        'ahr-dashboard',
        'Planificación de Turnos',
        'Disponibilidad / Turnos',
        'manage_options', // Visible solo para Admins/Supervisores
        'ahr-turnos',
        'ahr_render_turnos_view'
    );
}

// ---------------------------------------------------------
// FUNCIONES CONTROLADORAS (VISTAS)
// ---------------------------------------------------------

// A. Vista Dashboard (Resumen de solicitudes)
// A. Vista Dashboard (Resumen de solicitudes)
function ahr_render_dashboard()
{
    if (current_user_can('manage_options')) {
        // NUEVO: Sistema de vistas (disponibilidad por defecto)
        $view = isset($_GET['view']) ? $_GET['view'] : 'availability';

        if ($view === 'requests') {
            // Vista tradicional de solicitudes
            $db = new AHR_DB();
            $solicitudes = $db->get_all();

            if (file_exists(AHR_PATH . 'views/dashboard-requests.php')) {
                require_once AHR_PATH . 'views/dashboard-requests.php';
            } else {
                echo '<div class="wrap"><h1>Panel de Control RRHH</h1><p>Falta el archivo views/dashboard-requests.php</p></div>';
            }
        } else {
            // Nueva vista de disponibilidad (POR DEFECTO)
            if (file_exists(AHR_PATH . 'views/dashboard-disponibilidad.php')) {
                require_once AHR_PATH . 'views/dashboard-disponibilidad.php';
            } else {
                echo '<div class="error"><p>Falta el archivo views/dashboard-disponibilidad.php</p></div>';
            }
        }
    } else {
        echo '<div class="wrap"><h1>Bienvenido a Anaconda RRHH</h1><p>Usa el menú "Solicitar Vacaciones" para gestionar tus ausencias.</p></div>';
    }
}

// B. Vista Solicitudes (Lado del Empleado)
function ahr_render_vacaciones_view()
{
    $db = new AHR_DB();
    $mis_solicitudes = $db->get_my_requests(get_current_user_id());

    // OJO: Aquí usamos request-form.php que es el nombre correcto del archivo de solicitud
    if (file_exists(AHR_PATH . 'views/request-form.php')) {
        require_once AHR_PATH . 'views/request-form.php';
    } else {
        // Fallback por si acaso sigue llamándose employe-form.php en tu servidor
        if (file_exists(AHR_PATH . 'views/employe-form.php')) {
            require_once AHR_PATH . 'views/employe-form.php';
        } else {
            echo '<div class="error"><p>Falta el archivo views/request-form.php</p></div>';
        }
    }
}

// C. Vista Lista de Empleados (Tabla General)
function ahr_render_lista_empleados()
{
    $db = new AHR_DB();
    $lista_empleados = $db->get_all_empleados();

    if (file_exists(AHR_PATH . 'views/admin-list.php')) {
        require_once AHR_PATH . 'views/admin-list.php';
    } else {
        echo '<div class="error"><p>Falta el archivo views/admin-list.php</p></div>';
    }
}

// D. Vista Nuevo Empleado (Formulario de Registro)
function ahr_render_nuevo_empleado()
{
    if (file_exists(AHR_PATH . 'views/new-employee.php')) {
        require_once AHR_PATH . 'views/new-employee.php';
    } else {
        echo '<div class="wrap"><h1>Añadir Nuevo Empleado</h1><p>Debes crear el archivo views/new-employee.php</p></div>';
    }
}

// E. Vista del Documento PDF (Lógica nueva)
function ahr_render_pdf_view()
{
    // Seguridad: Solo admins pueden ver documentos legales
    if (!current_user_can('manage_options'))
        wp_die('No tienes permisos para ver este documento.');

    // Verificamos que venga un ID en la URL
    if (!isset($_GET['id']))
        wp_die('Falta el ID de la solicitud.');

    $request_id = intval($_GET['id']);

    // Obtenemos los datos extendidos (Solicitud + Datos del Empleado)
    $db = new AHR_DB();
    $solicitud = $db->get_request_by_id($request_id);

    if (!$solicitud)
        wp_die('Solicitud no encontrada o ID inválido.');

    // Cargamos la plantilla visual que creamos en el paso anterior
    require_once AHR_PATH . 'views/document-template.php';
}

// F. Vista Matriz de Turnos
function ahr_render_turnos_view()
{
    if (file_exists(AHR_PATH . 'views/shift-matrix.php')) {
        require_once AHR_PATH . 'views/shift-matrix.php';
    } else {
        echo '<div class="error"><p>Falta el archivo views/shift-matrix.php</p></div>';
    }
}
