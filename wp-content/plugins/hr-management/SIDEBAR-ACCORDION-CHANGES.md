# Cambios Implementados - Funcionamiento de Acordeón en Sidebar

## Resumen
Se ha implementado y mejorado la funcionalidad de acordeón (accordion) en la sidebar del plugin HR Management, garantizando que:
- ✅ Solo una sección esté abierta a la vez
- ✅ La sección seleccionada permanezca abierta cuando se navega dentro de ella
- ✅ Las animaciones sean suaves y visuales
- ✅ El indicador de apertura (marker) sea personalizado y visible

---

## Cambios Realizados

### 1. **JavaScript** - `/assets/js/sidebar.js`
**Mejoras:**
- Actualizado el selector para obtener TODOS los `details` de `.hrm-profile-mid` (no solo el primero)
- Agregado soporte para la sección "Ajustes" (`.myplugin-settings`)
- Mejorada la lógica de cierre de secciones cuando una se abre
- Agregados comentarios explicativos sobre el comportamiento de las secciones abiertas

**Funcionamiento:**
```javascript
// Selecciona todos los details principales
var navDetails = document.querySelectorAll('.hrm-nav > details');
var profileMidDetails = document.querySelectorAll('.hrm-profile-mid > details');
var settingsDetails = document.querySelector('.myplugin-settings');

// Cuando se abre uno, cierra todos los demás
allDetails.forEach(function(details){
    details.addEventListener('toggle', function(){
        if(this.open){
            allDetails.forEach(function(other){
                if(other !== details && other.open){
                    other.open = false;
                }
            });
        }
    });
});
```

---

### 2. **CSS** - `/assets/css/sidebar-responsive.css`
**Mejoras:**
- Reemplazado el marker por defecto del navegador con un indicador visual personalizado (flecha rotativa)
- Agregadas animaciones suaves al abrir/cerrar secciones (0.3s)
- Mejorados los estilos de hover para las secciones y enlaces
- Agregada animación de deslizamiento (`slideDown`) para el contenido
- Estilos para `.hrm-profile-mid` y `.myplugin-settings`
- Mejorada la visualización de enlaces activos con borde izquierdo

**Estilos Clave:**
```css
/* Marker personalizado que rota */
.hrm-nav summary::before {
    content: '';
    transform: rotate(-45deg);
    transition: transform 0.3s ease;
}

.hrm-nav details[open] > summary::before {
    transform: rotate(45deg);  /* Rotación cuando está abierto */
}

/* Animación de deslizamiento */
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
```

---

### 3. **PHP** - `/views/partials/sidebar.php`
**Mejoras:**
- Ampliada la lista de páginas que mantienen abierta la sección "Mi Perfil"
- Agregado soporte dinámico para páginas de tipo de documento (`hrm-mi-documentos-type-*`)
- Agregada sección "empresa" cuando se visualiza `hrm-anaconda-documents`
- El atributo `open` en `<details>` se establece automáticamente según la página actual

**Páginas que mantienen "Mi Perfil" abierto:**
```php
if (in_array($current_page, [
    'hrm-mi-perfil',
    'hrm-mi-perfil-info',
    'hrm-mi-perfil-vacaciones',
    'hrm-mi-documentos-contratos',
    'hrm-mi-documentos-liquidaciones',
    'hrm-convivencia',
    'hrm-debug-vacaciones-empleado'
], true)) {
    $section = 'perfil';
}
```

---

## Comportamiento Esperado

### En Escritorio (≥768px)
- La sidebar está siempre visible
- Solo una sección del acordeón está abierta a la vez
- Al hacer clic en una sección, se abre y las demás se cierran automáticamente
- El indicador visual (flecha) rota para mostrar el estado

### En Móvil (<768px)
- La sidebar se abre como overlay al pulsar el botón hamburguesa
- Al abrir una sección del acordeón, permanece abierta
- Al pulsar cualquier enlace, se cierra la sidebar
- Las mismas reglas del acordeón aplican

### Navegación
- Cuando te encuentras en `hrm-mi-perfil-vacaciones`, la sección "Mi Perfil" está abierta y el enlace está marcado como activo
- Cuando navegas a `hrm-anaconda-documents`, se abre la sección "Documentos empresa"
- Las páginas de documentos dinámicos mantienen abierta "Mi Perfil"

---

## Pruebas Recomendadas

1. **Acordeón Básico:**
   - Abre "Mi Perfil" → Abre "Gestión de Empleados" → Verifica que "Mi Perfil" se cierre
   - Abre "Gestión de Vacaciones" → Verifica que la anterior se cierre

2. **Persistencia de Secciones:**
   - Abre "Mi Perfil" → Haz clic en "Mis vacaciones" → Verifica que "Mi Perfil" siga abierta
   - Abre "Documentos empresa" → Haz clic en un documento → Verifica que se mantenga abierta

3. **Responsive:**
   - En móvil: Abre la sidebar → Abre una sección → Haz clic en un enlace → La sidebar debe cerrarse

4. **Indicadores Visuales:**
   - Verifica que la flecha del marker rote suavemente
   - Verifica que el contenido se deslice suavemente al abrir/cerrar

---

## Archivos Modificados

```
/assets/js/sidebar.js              ← Lógica de acordeón mejorada
/assets/css/sidebar-responsive.css ← Estilos visuales del acordeón
/views/partials/sidebar.php        ← Lógica de secciones activas
```

---

## Notas Técnicas

- Se utilizan elementos HTML nativos `<details>` y `<summary>` (sin librerías externas)
- Compatible con navegadores modernos (Chrome, Firefox, Safari, Edge)
- Accesibilidad mejorada con atributos `aria-*` existentes
- El marker personalizado se implementa con CSS (`::before`) sin depender del navegador
- Sin cambios en la estructura HTML, solo se agregaron selectores CSS y lógica JavaScript

