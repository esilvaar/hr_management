# Sistema de Solicitud de Medio Día - Documentación

## Descripción General
Se ha creado un nuevo sistema para que los empleados soliciten faltar medio día. Las solicitudes se almacenan en la misma tabla de `rrhh_solicitudes_ausencia` pero con diferencias importantes en cómo se procesan.

## Archivos Creados/Modificados

### 1. **Archivo: `medio-dia-form.php`**
- **Ubicación:** `/wp-content/plugins/hr-management/views/medio-dia-form.php`
- **Descripción:** Formulario para solicitar medio día
- **Características:**
  - Campo para seleccionar la fecha del medio día
  - Radio buttons para elegir entre Mañana (08:00-12:00) o Tarde (14:00-18:00)
  - Validación de fin de semana en frontend
  - Descuento automático de 0.5 días
  - Modal de confirmación al crear la solicitud

### 2. **Archivo: `medio-dia-empleado.php`**
- **Ubicación:** `/wp-content/plugins/hr-management/views/medio-dia-empleado.php`
- **Descripción:** Vista del portal del empleado para ver y gestionar solicitudes de medio día
- **Características:**
  - Tabla con lista de solicitudes de medio día
  - Botón para crear nueva solicitud
  - Opción de cancelar solicitudes pendientes
  - Información sobre descuento de 0.5 días

### 3. **Función: `hrm_enviar_medio_dia_handler()`**
- **Ubicación:** `/wp-content/plugins/hr-management/includes/vacaciones.php` (línea ~1001)
- **Descripción:** Manejador del formulario de solicitud de medio día
- **Funcionalidades:**
  - Valida nonce de seguridad
  - Obtiene datos del empleado
  - Inserta solicitud en BD con `periodo_ausencia` (mañana/tarde)
  - Fecha inicio = Fecha fin (mismo día)
  - Envía emails a: empleado, gerente directo, editores de vacaciones
  - Redirige con alerta de confirmación

## Flujo de Funcionamiento

### 1. **Creación de Solicitud**
```
Empleado accede a "Solicitar Medio Día"
    ↓
Completa formulario (fecha + período)
    ↓
Valida que no sea fin de semana
    ↓
Envía formulario
    ↓
Se inserta en BD con:
  - fecha_inicio = fecha elegida
  - fecha_fin = fecha elegida (igual)
  - periodo_ausencia = 'mañana' o 'tarde'
  - estado = 'PENDIENTE'
    ↓
Se envían emails a gerente y editores
    ↓
Modal de confirmación
```

### 2. **Validaciones**
- **Frontend:**
  - Fecha mínima: 1 mes después de hoy
  - No permite fin de semana
  - Campo período obligatorio (mañana/tarde)

- **Backend:**
  - Nonce válido
  - Usuario logueado
  - ID de empleado existe
  - Fecha en formato Y-m-d
  - No es fin de semana
  - Período válido (mañana/tarde)

## Estructura en Base de Datos

### Tabla: `rrhh_solicitudes_ausencia`
Se reutiliza la tabla existente con la columna `periodo_ausencia` que se agregó mediante SQL:

```sql
ALTER TABLE wp_rrhh_solicitudes_ausencia 
ADD COLUMN periodo_ausencia ENUM('completo', 'mañana', 'tarde') 
NOT NULL DEFAULT 'completo' 
AFTER estado;
```

### Diferencias en los datos:
| Campo | Vacaciones | Medio Día |
|-------|-----------|----------|
| fecha_inicio | Fecha de inicio del período | Fecha del día elegido |
| fecha_fin | Fecha de fin del período | Igual a fecha_inicio |
| periodo_ausencia | 'completo' | 'mañana' o 'tarde' |
| total_dias | Número de días (1, 2, 5, etc) | 0.5 |

## Sistema de Emails

### Destinatarios:
1. **Empleado solicitante** - Email de confirmación
2. **Gerente directo** - Para revisión/aprobación
3. **Editores de vacaciones** - Para gestión administrativa

### Contenido:
- Datos del empleado
- Fecha y período solicitado
- Descuento de 0.5 días
- Motivo/descripción (si la proporciona)

## Integración con Sistema de Vacaciones

### Cálculo de Descuento:
- Cada solicitud de medio día descontará **0.5 días** del saldo de vacaciones
- Se reutiliza la función `hrm_descontar_dias_vacaciones_empleado()` con ajustes
- Compatible con cálculo de antigüedad y renovación anual

### Aprobación y Rechazo:
- Usa el mismo sistema que vacaciones
- Gerente directo o editor de vacaciones puede aprobar/rechazar
- Al aprobar, descuenta automáticamente 0.5 días

### Cancelación:
- Empleados pueden cancelar solicitudes en estado PENDIENTE
- Se puede reutilizar `hrm_cancelar_solicitud_vacaciones()`

## Cómo Agregar al Menú del Empleado

Para mostrar esta opción en el portal del empleado, agregar a `hr-managment.php`:

```php
add_submenu_page(
    'hrm-mi-perfil',
    'Mis Medios Días',
    'Mis Medios Días',
    'read',
    'hrm-mi-perfil-medio-dia',
    function() {
        echo '<div class="wrap">';
        echo '<div class="hrm-admin-layout">';
            hrm_get_template_part( 'partials/sidebar-loader' );
            echo '<main class="hrm-content">';
                hrm_get_template_part( 'medio-dia-empleado', '', [ 'current_user_id' => get_current_user_id() ] );
            echo '</main>';
        echo '</div>';
        echo '</div>';
    }
);
```

## Consultas SQL Útiles

### Ver solicitudes de medio día:
```sql
SELECT * FROM wp_rrhh_solicitudes_ausencia 
WHERE fecha_inicio = fecha_fin 
AND periodo_ausencia IN ('mañana', 'tarde')
ORDER BY fecha_creacion DESC;
```

### Ver medios días por empleado:
```sql
SELECT 
    e.nombre, e.apellido,
    s.fecha_inicio,
    s.periodo_ausencia,
    s.estado,
    COUNT(*) as total_solicitudes
FROM wp_rrhh_solicitudes_ausencia s
JOIN wp_rrhh_empleados e ON s.id_empleado = e.id_empleado
WHERE s.fecha_inicio = s.fecha_fin
AND s.periodo_ausencia IN ('mañana', 'tarde')
GROUP BY e.id_empleado, s.estado;
```

### Medios días aprobados por empleado (para descuento):
```sql
SELECT 
    id_empleado,
    COUNT(*) * 0.5 as dias_descontados
FROM wp_rrhh_solicitudes_ausencia
WHERE estado = 'APROBADA'
AND fecha_inicio = fecha_fin
AND periodo_ausencia IN ('mañana', 'tarde')
AND YEAR(fecha_inicio) = YEAR(NOW())
GROUP BY id_empleado;
```

## Próximos Pasos (Opcionales)

1. **Descuento Automático:** Modificar `hrm_descontar_dias_vacaciones_empleado()` para soportar 0.5 días
2. **Reportes:** Agregar reporte de medios días por departamento
3. **Restricciones:** Implementar límite de medios días por mes/año
4. **Notificaciones:** Agregar notificaciones a Slack o Teams
5. **Calendario:** Mostrar medios días en el calendario de vacaciones
