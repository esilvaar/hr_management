# FLUJO COMPLETO PARA EDITOR_VACACIONES

## 1. LOGIN
- Usuario inicia sesión
- Hook: `login_redirect` de `helpers.php` se ejecuta
- Condición: `in_array('editor_vacaciones', roles) || user_can('manage_hrm_vacaciones')`
- Resultado: Redirige a `admin.php?page=hrm-vacaciones`

## 2. ADMIN_INIT
- Se carga `vacaciones.php`
- Se ejecutan `hrm_ensure_capabilities()`
- Se valida: Si es `editor_vacaciones` SOLO, verifica que esté en `hrm-vacaciones`
  - Si no está, redirige a `hrm-vacaciones`

## 3. ADMIN_MENU
- Se registran menús
- Para `editor_vacaciones`:
  - NO entra en el bloque `if (manage_options || manage_hrm_employees || view_hrm_admin_views)`
  - SÍ entra en el bloque `if (is_logged_in && !manage_options && !manage_hrm_employees)`
  - Dentro de ese bloque, hay un `if (current_user_can('manage_hrm_vacaciones'))` 
  - Se registra un menú top-level "Vacaciones" con opción "Vacaciones"

## 4. HOOKS (hooks.php)
- `map_meta_cap`: Mapea `manage_hrm_vacaciones` para páginas `hrm-vacaciones` y `hrm-vacaciones-formulario`
- `user_has_cap`: Asegura que usuarios con `manage_hrm_vacaciones` pueden acceder

## 5. RENDERIZADO DE PÁGINA
- Se llama `hrm_render_vacaciones_admin_page()`
- Verifica: `current_user_can('manage_options') || current_user_can('view_hrm_admin_views') || current_user_can('manage_hrm_vacaciones')`
- Se carga template `vacaciones-admin.php` con datos de solicitudes
- Se renderiza sidebar con sección "Gestión de Vacaciones" abierta

## 6. SIDEBAR
- Renderiza si: `$can_vacation || $can_admin_views || $is_editor_role`
- Abre detalles si: `$section === 'vacaciones'`
- Marca link como activo si: `$current_page === 'hrm-vacaciones'`

---

## PUNTOS CRÍTICOS ARREGLADOS

1. ✅ Capability mapping para `manage_hrm_vacaciones` en páginas de vacaciones
2. ✅ Menú placement: `manage_hrm_vacaciones` NO forma parte de `$has_admin_access` para el menú principal
3. ✅ Admin init redirect: Solo redirige si está en página NO permitida
4. ✅ Login redirect: Usa solo una función (la de helpers.php)
5. ✅ Sidebar: Renderiza correctamente para `editor_vacaciones`

