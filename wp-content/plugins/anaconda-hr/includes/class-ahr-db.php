<?php
if (!defined('ABSPATH'))
    exit;

class AHR_DB
{
    // Definimos variables para manejar múltiples tablas
    private $tbl_vacaciones;
    private $tbl_empleados;

    public function __construct()
    {
        global $wpdb;
        // Asignamos los nombres de las tablas con el prefijo de WP (usualmente wp_)
        $this->tbl_vacaciones = $wpdb->prefix . 'ahr_vacaciones';
        $this->tbl_empleados = $wpdb->prefix . 'ahr_empleados';
    }

    // =========================================================
    //  SECCIÓN 1: GESTIÓN DE EMPLEADOS (NUEVO)
    // =========================================================

    // Insertar nuevo empleado
    public function insert_empleado($data)
    {
        global $wpdb;

        // Insertamos en la tabla wp_ahr_empleados
        return $wpdb->insert(
            $this->tbl_empleados,
            [
                'rut' => $data['rut'],
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'],
                'email' => $data['email'],
                'departamento' => $data['departamento'],
                'cargo' => $data['cargo'],
                'fecha_ingreso' => $data['fecha_ingreso'],
                'wp_user_id' => $data['wp_user_id'], // ID del usuario WP para login
                'estado' => 'Activo' // Por defecto entra activo
            ],
            // Definimos el formato de cada dato (%s = string/texto, %d = número)
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );
    }

    // Obtener todos los empleados (Para listar en el admin)
    public function get_all_empleados()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->tbl_empleados} ORDER BY apellidos ASC");
    }

    // =========================================================
    //  SECCIÓN 2: GESTIÓN DE SOLICITUDES / VACACIONES
    // =========================================================

    // Insertar nueva solicitud
    public function create_solicitud($data)
    {
        global $wpdb;
        return $wpdb->insert(
            $this->tbl_vacaciones,
            [
                'user_id' => get_current_user_id(),
                'fecha_inicio' => sanitize_text_field($data['fecha_inicio']),
                'fecha_fin' => sanitize_text_field($data['fecha_fin']),
                'tipo' => sanitize_text_field($data['tipo']),
                'motivo' => sanitize_textarea_field($data['motivo']),
                'estado' => 'PENDIENTE'
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );
    }

    // Obtener todas las solicitudes (Para Admin) - Con JOIN para ver nombres reales del empleado
    public function get_all()
    {
        global $wpdb;
        $users_table = $wpdb->users; // Tabla nativa de usuarios WP

        // Hacemos JOIN con la tabla de usuarios de WP para saber quién es quién
        // Y TAMBIÉN hacemos JOIN con nuestra tabla de empleados para tener el RUT y Cargo en la vista principal si fuera necesario
        $sql = "SELECT v.*, u.display_name, u.user_email 
                FROM {$this->tbl_vacaciones} v 
                LEFT JOIN $users_table u ON v.user_id = u.ID 
                ORDER BY v.created_at DESC";

        return $wpdb->get_results($sql);
    }

    // [NUEVO] Obtener una solicitud específica por ID (Para el PDF)
    public function get_request_by_id($id)
    {
        global $wpdb;

        // Aquí hacemos el JOIN clave: Unimos la solicitud (v) con la tabla de empleados (e)
        // usando el ID de usuario como puente.
        $sql = "SELECT v.*, e.rut, e.nombres, e.apellidos, e.cargo, e.departamento
                FROM {$this->tbl_vacaciones} v 
                LEFT JOIN {$this->tbl_empleados} e ON v.user_id = e.wp_user_id
                WHERE v.id = %d";

        return $wpdb->get_row($wpdb->prepare($sql, $id));
    }

    // Obtener mis solicitudes (Para el Dashboard del Empleado)
    public function get_my_requests($user_id)
    {
        global $wpdb;
        // OJO: Usamos $this->tbl_vacaciones aquí
        $sql = $wpdb->prepare("SELECT * FROM {$this->tbl_vacaciones} WHERE user_id = %d ORDER BY created_at DESC", $user_id);
        return $wpdb->get_results($sql);
    }

    // Aprobar/Rechazar solicitud CON AUDITORÍA
    public function update_status($id, $status, $approver_id, $date_resolution)
    {
        global $wpdb;
        return $wpdb->update(
            $this->tbl_vacaciones,
            [
                'estado' => $status,
                'aprobado_por_id' => $approver_id,
                'fecha_resolucion' => $date_resolution
            ],
            ['id' => $id],
            ['%s', '%d', '%s'], // Formatos
            ['%d']              // Formato del WHERE
        );
    }
    // [NUEVO] Obtener disponibilidad de un empleado para un rango de fechas
    public function get_availability_matrix($start_date, $days = 7)
    {
        global $wpdb;

        $empleados = $this->get_all_empleados();
        $matrix = [];

        // Iteramos por cada empleado
        foreach ($empleados as $emp) {
            $row = [
                'empleado' => $emp->nombres . ' ' . $emp->apellidos,
                'cargo' => $emp->cargo,
                'dias' => []
            ];

            // Iteramos los próximos X días
            for ($i = 0; $i < $days; $i++) {
                $current_date = date('Y-m-d', strtotime("$start_date +$i days"));

                // Buscamos si tiene ausencia aprobada para ese día
                // OJO: Usamos wp_user_id para cruzar
                $sql = "SELECT tipo FROM {$this->tbl_vacaciones} 
                        WHERE user_id = %d 
                        AND estado = 'APROBADO'
                        AND %s BETWEEN fecha_inicio AND fecha_fin 
                        LIMIT 1";

                $ausencia = $wpdb->get_var($wpdb->prepare($sql, $emp->wp_user_id, $current_date));

                // Si hay ausencia, guardamos el tipo (Vacaciones, Licencia). Si no, 'Disponible'.
                $row['dias'][$current_date] = $ausencia ? $ausencia : 'Disponible';
            }

            $matrix[] = $row;
        }

        return $matrix;
    }

    // MÉTODO 1: Empleados disponibles/ausentes HOY
    public function get_availability_today() {
        global $wpdb;
        
        $sql = "SELECT 
                    e.id, 
                    e.nombres, 
                    e.apellidos, 
                    e.cargo, 
                    e.departamento,
                    CASE 
                        WHEN v.id IS NOT NULL 
                        AND v.estado = 'APROBADO' 
                        AND CURDATE() BETWEEN v.fecha_inicio AND v.fecha_fin 
                        THEN 'AUSENTE' 
                        ELSE 'DISPONIBLE' 
                    END AS estado_hoy,
                    v.tipo AS motivo
                FROM {$this->tbl_empleados} e
                LEFT JOIN {$this->tbl_vacaciones} v 
                    ON e.wp_user_id = v.user_id 
                    AND v.estado = 'APROBADO'
                    AND CURDATE() BETWEEN v.fecha_inicio AND v.fecha_fin
                WHERE e.estado = 'Activo'
                ORDER BY e.apellidos ASC";
        
        return $wpdb->get_results($sql);
    }

    // MÉTODO 2: Estadísticas rápidas
    public function get_availability_stats() {
        global $wpdb;
        
        $result = $this->get_availability_today();
        
        $total = count($result);
        $ausentes = 0;
        
        foreach ($result as $emp) {
            if ($emp->estado_hoy == 'AUSENTE') {
                $ausentes++;
            }
        }
        
        return [
            'total' => $total,
            'ausentes' => $ausentes,
            'disponibles' => $total - $ausentes,
            'porcentaje' => $total > 0 ? round((($total - $ausentes) / $total) * 100) : 0
        ];
    }

    // MÉTODO 3: Contador de solicitudes pendientes
    public function get_pending_count() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tbl_vacaciones} WHERE estado = 'PENDIENTE'"
        );
    }
}
