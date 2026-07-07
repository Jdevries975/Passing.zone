<?php
define( 'WP_CACHE', true ); // Added by WP Rocket

define( 'WP_MEMORY_LIMIT', '256M' );
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', "d0426a37" );

/** Database username */
define( 'DB_USER', "d0426a37" );

/** Database password */
define( 'DB_PASSWORD', "9gPVti48aFtHmkr8uKSp" );

/** Database hostname */
define( 'DB_HOST', "localhost" );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         '#Q7Gi0@L(ozD&KG4u>e1wUoe#hbKr3$oZ*>q9SB?S;h|o7T-LN3Ko9i!C*>|c54D' );
define( 'SECURE_AUTH_KEY',  'T:Y@rUBCDof&dv<N_)lbBTT6h;I(3Yl-d#Z>C.o}UtBArLk+w6R$,o{meCea8r6*' );
define( 'LOGGED_IN_KEY',    'B.oL(D7aa@W}[r`4OgIJ`gUWS+]{;|s(CW39W`fl*:/1L?e7#J4zh;)SC!B4[>aU' );
define( 'NONCE_KEY',        ';;Iyh_utpM/k]z(UZk;HaJ8jlTeC0*B^u-PV|q<tDJ)DB>d:{xQ54;@FJwm.H-Dj' );
define( 'AUTH_SALT',        '<Nj#<=ur0]@G6Mt3dxpy$1C.J-|iA0:HegSz^{AaeaS>G1?01ux#tK6lQIVvhGl!' );
define( 'SECURE_AUTH_SALT', '=Lc(u77bGz&J1m+o_@VN!aH==ftNXh}kVn%ZdhJ.}14oBP^X_!,y{x&w6A#=!_ga' );
define( 'LOGGED_IN_SALT',   'sv0?9,y`|$;2cufk3BFX>Cr})G]]YiwYdznQjP//Vr3Q8rsqQg78):M]W#bcbqb_' );
define( 'NONCE_SALT',       ';zXCg>5%+L0c)oF`)j3di@cp=+Egz|OQk8CcQxn3W{|_}rB]00x4g`Wtvr]bir]R' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix  = '9g6kr_';
define( 'WP_POST_REVISIONS', 10 );

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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
/*define( 'WP_DEBUG', false );*/
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

/* Add any custom values between this line and the "stop editing" line. */


define( 'DUPLICATOR_AUTH_KEY', '_Y6A-:bfV[Z];2X38acGLkz Mo-Jr6&(%Rb&psH$=wdsJ%-ivW-xHKab1@JuR-].' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

define('JWT_AUTH_SECRET_KEY', '966777777');
