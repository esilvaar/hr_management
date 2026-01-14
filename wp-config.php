<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'rrhhanacondaweb_wp' );

/** Database username */
define( 'DB_USER', 'rrhhanacondaweb_wp' );

/** Database password */
define( 'DB_PASSWORD', 'O4@!1xy5ET9GXHC5' );

/** Database hostname */
define( 'DB_HOST', 'localhost:3306' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', 'T*&:0Y7!gzxCCx*X8m;Q#T0TCSP4i09pBAe2%7!QaYxTRPIn~V2Y-3Iyh]Q3S(2f');
define('SECURE_AUTH_KEY', '61(!r7ro1[A@6/+Od2[~RkCX+UvBx!7f@&#)n6:d5)/I9Jr%v(2@39CEs4ds!]Kg');
define('LOGGED_IN_KEY', 'z575qUcR&4)inkg#6)9)H@3hG1+9K5fF-:/9(/:24P)0!&2B72i6235tK#Ht90:2');
define('NONCE_KEY', 'sDIe&+qy33S4&6gj/a@3Wt138(6FNN1X!p7JAD+2[)+21!qC6OB!8o:Y8JodM9i0');
define('AUTH_SALT', 'K3)x60Ta|!wPc3M9R*#62knq%M)7UQH5dF#p2h_/S)sqRQ*Wv:&s9Xif491+4K2z');
define('SECURE_AUTH_SALT', '*Z8vjMHB/5mhBbr*dbd[k:%723oQ]Q9(~oN04q-t4QY/dZ00#I2GOaF2-Hd~56@h');
define('LOGGED_IN_SALT', 'arCBPD-1x:378&;wDOtz]q38nY99n4rn@3RYDl;V_!q-TXR!bxA1e[]WBjtEy0l8');
define('NONCE_SALT', 'J]8+oAAXxIb5:80lM1+#3;%:NB|;/:6NHx;1G7DrfK3f]CoENmc3@R7rQBw]0Skc');


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'Bu6K9_';


/* Add any custom values between this line and the "stop editing" line. */

define('WP_ALLOW_MULTISITE', true);
/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', true );
@ini_set( 'display_errors', 1 );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
