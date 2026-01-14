#!/bin/bash

# Validation script para verificar que los archivos están correctamente configurados

echo "======================================"
echo "HR Management - Responsive Validation"
echo "======================================"

PLUGIN_DIR="/home/rrhhanacondaweb/public_html/wp-content/plugins/hr-management"

echo ""
echo "✓ Verificando archivos CSS..."

# CSS Files
if [ -f "$PLUGIN_DIR/assets/css/layout-sidebar-admin.css" ]; then
    echo "  ✅ layout-sidebar-admin.css exists"
else
    echo "  ❌ layout-sidebar-admin.css missing"
fi

if [ -f "$PLUGIN_DIR/assets/css/sidebar-responsive.css" ]; then
    echo "  ✅ sidebar-responsive.css exists"
else
    echo "  ❌ sidebar-responsive.css missing"
fi

echo ""
echo "✓ Verificando archivos JavaScript..."

# JS Files
if [ -f "$PLUGIN_DIR/assets/js/sidebar-responsive.js" ]; then
    echo "  ✅ sidebar-responsive.js exists"
else
    echo "  ❌ sidebar-responsive.js missing"
fi

echo ""
echo "✓ Verificando HTML..."

# Check sidebar HTML
if grep -q 'hrm-sidebar-toggle' "$PLUGIN_DIR/views/partials/sidebar-admin.php"; then
    echo "  ✅ Hamburger button found in sidebar-admin.php"
else
    echo "  ❌ Hamburger button not found"
fi

if grep -q 'hrm-sidebar-overlay' "$PLUGIN_DIR/views/partials/sidebar-admin.php"; then
    echo "  ✅ Overlay element found in sidebar-admin.php"
else
    echo "  ❌ Overlay element not found"
fi

echo ""
echo "✓ Verificando enqueuing en hr-managment.php..."

if grep -q "hrm-sidebar-responsive" "$PLUGIN_DIR/hr-managment.php"; then
    echo "  ✅ hrm-sidebar-responsive style registered"
else
    echo "  ❌ hrm-sidebar-responsive style not registered"
fi

if grep -q 'HRM_PLUGIN_URL.*sidebar-responsive.js' "$PLUGIN_DIR/hr-managment.php"; then
    echo "  ✅ sidebar-responsive.js script registered"
else
    echo "  ❌ sidebar-responsive.js script not registered"
fi

echo ""
echo "======================================"
echo "Validation complete!"
echo "======================================"
echo ""
echo "Próximos pasos:"
echo "1. Ir a wp-admin del sitio"
echo "2. Cambiar el tamaño del navegador (DevTools: Ctrl+Shift+M)"
echo "3. En móvil, debería aparecer un icono de 3 líneas (hamburger)"
echo "4. Al hacer clic, la sidebar debe deslizarse desde la izquierda"
echo "5. En desktop, la sidebar debe estar siempre visible"
echo ""
