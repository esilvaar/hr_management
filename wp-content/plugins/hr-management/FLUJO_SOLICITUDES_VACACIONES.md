# Flujo de Solicitudes de Vacaciones - Actualizado

## Respuesta: ¿Hacia dónde se envían las solicitudes de gerentes?

**ACTUALIZADO:** Todas las solicitudes de gerentes se envían al **Gerente de Operaciones**.

Las solicitudes de empleados regulares se envían al **gerente correspondiente al departamento del empleado** que solicita las vacaciones.

---

## Flujo Actual de Solicitudes

### Para Empleados Regulares:

1. **Empleado** envía solicitud de vacaciones
2. Sistema obtiene el **departamento del empleado** (columna `departamento` en `Bu6K9_rrhh_empleados`)
3. Sistema busca en `Bu6K9_rrhh_gerencia_deptos`:
   - `WHERE depto_a_cargo = 'Departamento del Empleado'`
   - `AND estado = 1`
4. **Email se envía al gerente de ese departamento**

**Ejemplo:**
```
Empleado: Carlos García
Departamento: Ventas
↓
Sistema busca: ¿Quién tiene "Ventas" a cargo?
↓
Resultado: María López (Gerente de Ventas)
↓
Email enviado a: maria@empresa.com
```

### Para Gerentes/Supervisores:

1. **Gerente** (empleado del departamento "Gerencia") envía solicitud
2. Sistema detecta que es gerente (departamento = "Gerencia")
3. **Busca al Gerente de Operaciones** en `Bu6K9_rrhh_empleados`:
   - `WHERE departamento = 'Gerencia'`
   - `AND area_gerencia = 'Operaciones'`
   - `AND estado = 1`
4. **Email se envía al Gerente de Operaciones**

**Código (línea ~146 de vacaciones.php):**
```php
// CASO ESPECIAL: Si el empleado es gerente
if ( $departamento_empleado === 'Gerencia' ) {
    $gerente_operaciones = $wpdb->get_row(
        "SELECT id_empleado, 
                CONCAT(nombre, ' ', apellido) as nombre_gerente, 
                correo as correo_gerente
         FROM empleados
         WHERE departamento = 'Gerencia'
         AND area_gerencia = 'Operaciones'
         AND estado = 1"
    );
    // Enviar solicitud al Gerente de Operaciones
}

---

## Información en BD Relevante

### Tabla: `Bu6K9_rrhh_empleados`
Tabla maestra de empleados. **NOTA:** El campo `area_gerencia` aquí es lo que define qué tipo de gerente es un empleado.
```
┌──────────────────┬────────────────┬─────────────────────────────────────┐
│ Columna          │ Ejemplo        │ Uso                                 │
├──────────────────┼────────────────┼─────────────────────────────────────┤
│ id_empleado      │ 42             │ Identificador único                 │
│ nombre           │ "Carlos"       │ Parte del nombre (para CONCAT)      │
│ apellido         │ "García"       │ Parte del apellido (para CONCAT)    │
│ departamento     │ "Ventas"       │ Determina su gerente                │
│ correo           │ "carlos@..."   │ Email del empleado / gerente        │
│ user_id          │ 5              │ Vinculación con usuario WordPress   │
│ area_gerencia    │ "Operaciones"  │ **CLAVE:** Tipo de gerente si es de Gerencia │
│ estado           │ 1              │ Activo/Inactivo                     │
└──────────────────┴────────────────┴─────────────────────────────────────┘

**IMPORTANTE:** 
- Si `departamento = 'Gerencia'` y `area_gerencia = 'Operaciones'` → Es el Gerente de Operaciones
- Si `departamento = 'Gerencia'` y `area_gerencia = otra cosa` → Es otro tipo de gerente
```

### Tabla: `Bu6K9_rrhh_gerencia_deptos`
Define qué gerente está a cargo de qué departamento regular.
```
┌──────────────────┬────────────────┬──────────────────────────────────────┐
│ Columna          │ Ejemplo        │ Propósito                            │
├──────────────────┼────────────────┼──────────────────────────────────────┤
│ depto_a_cargo    │ "Ventas"       │ Qué departamento cuida este gerente  │
│ nombre_gerente   │ "María López"  │ Nombre del gerente responsable       │
│ correo_gerente   │ "maria@..."    │ Email para enviar solicitudes        │
│ area_gerencial   │ "Comercial"    │ Clasificación del área gerencial     │
│ estado           │ 1              │ Activo/Inactivo                      │
└──────────────────┴────────────────┴──────────────────────────────────────┘

**NOTA:** Esta tabla se usa para empleados regulares (no Gerencia)
- Se busca el gerente por el `depto_a_cargo` del empleado
```

---

## Función Responsable

**Función:** `hrm_obtener_gerente_departamento($id_empleado)`

**Ubicación:** `/includes/vacaciones.php` (línea ~117)

**Algoritmo:**
```php
1. Obtener departamento del empleado
2. SI departamento = "Gerencia" (es gerente):
   - Buscar en gerencia_deptos donde depto_a_cargo = 'Operaciones'
   - Retornar Gerente de Operaciones
3. ELSE (empleado regular):
   - Buscar en gerencia_deptos donde depto_a_cargo = departamento_empleado
   - Retornar gerente del departamento
4. Si no existe: retornar null (no enviar email)
```

---

## Casos de Uso Reales

### Caso 1: Empleado regular de Ventas
```
Juan Martínez (Ventas) → solicita vacaciones
→ Sistema busca: ¿Quién tiene a cargo Ventas?
→ Resultado: María López (correo_gerente: maria@empresa.com)
→ Email enviado a: maria@empresa.com
```

### Caso 2: Gerente de Ventas (NUEVO - ACTUALIZADO)
```
María López (Gerencia, gerente de Ventas) → solicita vacaciones
→ Sistema detecta: departamento = "Gerencia"
→ Sistema busca: ¿Quién tiene a cargo Operaciones?
→ Resultado: Juan Pérez (Gerente de Operaciones)
→ Email enviado a: juan@empresa.com (Gerente de Operaciones)
```

### Caso 3: Gerente de Operaciones (NUEVO - ACTUALIZADO)
```
Juan Pérez (Gerencia, Gerente de Operaciones) → solicita vacaciones
→ Sistema detecta: departamento = "Gerencia"
→ Sistema busca: ¿Quién tiene a cargo Operaciones?
→ Resultado: Juan Pérez (sí mismo como Gerente de Operaciones)
→ Email enviado a: juan@empresa.com (a sí mismo)
```

---

## Configuración Necesaria

Para que todo funcione correctamente, asegúrate de:

1. ✅ **Tabla `Bu6K9_rrhh_gerencia_deptos` esté llena:**
   - **Importante:** Debe existir un registro con `depto_a_cargo = 'Operaciones'`
   - Cada departamento debe tener un registro
   - `estado = 1`
   - `correo_gerente` válido

2. ✅ **Empleados asignados a departamentos:**
   - Cada empleado debe tener `departamento` asignado
   - Debe coincidir con `depto_a_cargo` en gerencia_deptos

3. ✅ **Gerentes con correo:**
   - Campo `correo_gerente` debe estar completo

4. ✅ **WordPress mail funcionar:**
   - `wp_mail()` debe estar configurado en el servidor

---

## Resumen (ACTUALIZADO)

| Quién Solicita | Departamento | Envío Hacia | Encontrado En |
|---|---|---|---|
| Empleado regular | Ventas | Gerente de Ventas | `gerencia_deptos` |
| Empleado regular | Operaciones | Gerente de Operaciones | `gerencia_deptos` |
| Gerente de Ventas | Gerencia | **Gerente de Operaciones** ⭐ | `gerencia_deptos` |
| Gerente de Operaciones | Gerencia | A sí mismo | `gerencia_deptos` |
| Cualquier otro gerente | Gerencia | **Gerente de Operaciones** ⭐ | `gerencia_deptos` |

---

## Cambio Implementado

Se modificó la función `hrm_obtener_gerente_departamento()` para que:

**ANTES:**
- Gerentes enviaban solicitudes a sí mismos

**DESPUÉS:**
- Todos los gerentes envían solicitudes al **Gerente de Operaciones**
- Lógica: Si `departamento = 'Gerencia'`, buscar quien tiene a cargo `'Operaciones'`
- Ubicación: `/includes/vacaciones.php` línea ~146

**Código principal:**
```php
// CASO ESPECIAL: Si el empleado es gerente (departamento Gerencia)
// Enviar solicitud al Gerente de Operaciones en lugar del propio gerente
if ( $departamento_empleado === 'Gerencia' ) {
    $gerente = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT nombre_gerente, correo_gerente, area_gerencial 
             FROM {$table_gerencia}
             WHERE depto_a_cargo = %s
             AND estado = 1
             LIMIT 1",
            'Operaciones'
        ),
        ARRAY_A
    );
    // Validar y retornar
}
```

---

## Testing

Para probar que funciona correctamente:

1. **Crear una solicitud como gerente (no de Operaciones):**
   - Acceder como usuario con departamento = "Gerencia"
   - Enviar solicitud de vacaciones
   - Verificar que el email se envía a Gerente de Operaciones (no a sí mismo)

2. **Crear una solicitud como Gerente de Operaciones:**
   - Acceder como Gerente de Operaciones
   - Enviar solicitud de vacaciones
   - Verificar que el email se envía a sí mismo

3. **Crear una solicitud como empleado regular:**
   - Acceder como empleado de un departamento regular
   - Enviar solicitud de vacaciones
   - Verificar que el email se envía al gerente del departamento (no a Operaciones)

---

## Notas Importantes

⚠️ **Asegúrate de que existe un registro en `Bu6K9_rrhh_gerencia_deptos` con `depto_a_cargo = 'Operaciones'`**

Si no existe, los gerentes no podrán enviar solicitudes porque no habrá Gerente de Operaciones registrado.
