<?php
require_once __DIR__ . '/../../../../wp-load.php';

// Use admin user id 1
$user_id = 1;
wp_set_current_user( $user_id );

// Ensure functions exist
if ( ! function_exists('hrm_count_vacaciones_visibles') || ! function_exists('hrm_count_medio_dia_visibles') ) {
    echo "Required functions not available\n";
    exit(1);
}

$full = hrm_count_vacaciones_visibles('PENDIENTE');
$half = hrm_count_medio_dia_visibles('PENDIENTE');

echo "hrm_count_vacaciones_visibles('PENDIENTE') = $full\n";
echo "hrm_count_medio_dia_visibles('PENDIENTE') = $half\n";
echo "total = " . ($full + $half) . "\n";

return 0;
