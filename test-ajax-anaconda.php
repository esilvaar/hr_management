<?php
// Test AJAX handler directly
define( 'WP_USE_THEMES', false );
require( dirname( __FILE__ ) . '/wp-load.php' );

// Simulate AJAX request
if ( ! isset( $_POST['action'] ) ) {
    $_POST['action'] = 'anaconda_documents_edit_doc';
    $_POST['doc_id'] = '9';
    $_POST['title'] = 'TEST';
    $_POST['nonce'] = wp_create_nonce( 'anaconda_documents_edit' );
    $_SERVER['REQUEST_METHOD'] = 'POST';
}

// Check if current user can manage
if ( ! is_user_logged_in() ) {
    echo "Not logged in\n";
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    echo "No permissions\n";
    exit;
}

echo "Testing handler...\n";
echo "User: " . get_current_user_id() . "\n";
echo "Can manage_options: " . ( current_user_can( 'manage_options' ) ? 'YES' : 'NO' ) . "\n";

// Test nonce
$nonce = $_POST['nonce'] ?? '';
$verified = wp_verify_nonce( $nonce, 'anaconda_documents_edit' );
echo "Nonce verified: " . ( $verified ? 'YES' : 'NO' ) . "\n";

// Call handler
do_action( 'wp_ajax_anaconda_documents_edit_doc' );
?>
