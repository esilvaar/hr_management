# Clarificación: area_gerencia vs depto_a_cargo

## Problema Identificado y Resuelto

**Confusión inicial:** Se asumía que "Operaciones" era un departamento en la tabla `Bu6K9_rrhh_gerencia_deptos`.

**Corrección:** "Operaciones" NO es un departamento, es un atributo `area_gerencia` en la tabla `Bu6K9_rrhh_empleados` que define el **tipo de gerente**.

---

## Estructura de Datos Correcta

### Tabla: `Bu6K9_rrhh_empleados`
Para identificar al Gerente de Operaciones, se busca:
```sql
SELECT id_empleado, CONCAT(nombre, ' ', apellido) as nombre_gerente, correo
FROM Bu6K9_rrhh_empleados
WHERE departamento = 'Gerencia'
  AND area_gerencia = 'Operaciones'  ← Aquí está la clave
  AND estado = 1
```

**Explicación:**
- `departamento = 'Gerencia'` → El empleado es un gerente
- `area_gerencia = 'Operaciones'` → Este gerente es específicamente el Gerente de Operaciones
- `estado = 1` → El gerente está activo

### Tabla: `Bu6K9_rrhh_gerencia_deptos`
Para empleados regulares, se busca su gerente:
```sql
SELECT nombre_gerente, correo_gerente, area_gerencial
FROM Bu6K9_rrhh_gerencia_deptos
WHERE depto_a_cargo = 'Ventas'  ← Departamento del empleado
  AND estado = 1
```

**Explicación:**
- `depto_a_cargo` → Qué departamento regular supervisa este gerente
- `nombre_gerente` / `correo_gerente` → Datos del gerente responsable
- `area_gerencial` → Tipo/clasificación del área (informativo)

---

## Implementación Actualizada

**Archivo:** `/includes/vacaciones.php`
**Función:** `hrm_obtener_gerente_departamento()`
**Líneas:** ~105-190

### Lógica Actual (Correcta):

```php
function hrm_obtener_gerente_departamento( $id_empleado ) {
    // 1. Obtener departamento del empleado
    $departamento_empleado = /* ... */;
    
    // 2. Si es gerente → Buscar Gerente de Operaciones
    if ( $departamento_empleado === 'Gerencia' ) {
        // Buscar en empleados (NO en gerencia_deptos)
        $gerente = wpdb->get_row(
            "SELECT id_empleado, 
                    CONCAT(nombre, ' ', apellido) as nombre_gerente, 
                    correo as correo_gerente
             FROM {$table_empleados}
             WHERE departamento = 'Gerencia'
             AND area_gerencia = 'Operaciones'  ← CORRECCIÓN CLAVE
             AND estado = 1
             LIMIT 1"
        );
        return $gerente;
    }
    
    // 3. Si es empleado regular → Buscar su gerente en gerencia_deptos
    $gerente = wpdb->get_row(
        "SELECT nombre_gerente, correo_gerente, area_gerencial 
         FROM {$table_gerencia}
         WHERE depto_a_cargo = '{$departamento_empleado}'
         AND estado = 1
         LIMIT 1"
    );
    return $gerente;
}
```

---

## Flujo de Solicitudes (Definitivo)

### Empleado Regular
```
Carlos García (Departamento: Ventas)
  ↓ Solicita vacaciones
  ↓ Sistema busca: ¿Quién supervisa Ventas?
  ↓ Consulta: Bu6K9_rrhh_gerencia_deptos WHERE depto_a_cargo='Ventas'
  ↓ Resultado: María López (gerente@empresa.com)
  ↓ Email enviado a: maria@empresa.com
```

### Gerente de Cualquier Tipo
```
Juan Pérez (Departamento: Gerencia, area_gerencia: Comercial)
  ↓ Solicita vacaciones
  ↓ Sistema detecta: Es gerente (departamento='Gerencia')
  ↓ Busca especialmente: Gerente de Operaciones
  ↓ Consulta: Bu6K9_rrhh_empleados 
           WHERE departamento='Gerencia' 
           AND area_gerencia='Operaciones'
  ↓ Resultado: Miguel González (operations@empresa.com)
  ↓ Email enviado a: operations@empresa.com
```

---

## Validación

✅ **Código:** Sin errores de sintaxis
✅ **Lógica:** Diferencia correcta entre empleados regulares y gerentes
✅ **Tablas:** Usa la tabla correcta para cada caso
✅ **Filtros:** area_gerencia='Operaciones' implementado correctamente
✅ **Documentación:** FLUJO_SOLICITUDES_VACACIONES.md actualizado

## Impacto

- ✅ Todas las solicitudes de empleados van a su gerente departamental
- ✅ Todas las solicitudes de gerentes van al Gerente de Operaciones (identificado por area_gerencia='Operaciones')
- ✅ Notificaciones de vacaciones continúan funcionando correctamente
- ✅ Filtrado de departamentos para supervisores intacto

---

**Fecha de clarificación:** 2024
**Estado:** RESUELTO Y VALIDADO
