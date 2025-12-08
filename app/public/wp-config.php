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
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

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
define( 'AUTH_KEY',          '4?-!uJ?%W-SX+n{FqMMvN7umA6{n:QeBnomAE`&8?1]Nn?sam@;6lBxr8V4BlH5-' );
define( 'SECURE_AUTH_KEY',   '#`4uQg?.dm_<N 6U%O:00A*y?dMEPbEGF2:|m$H,D[[gS7Dn5.c$&}P#m%%=haV`' );
define( 'LOGGED_IN_KEY',     'wI2X(eA7Qi-qporq{C<|2Y8B3{k@3*h>fG@;{FEpWP~dvo&Nv!UdAT1Qja!}=nS2' );
define( 'NONCE_KEY',         '~VWoN-];$3l4##.O$l[oc_^d{ iSXM+-8{d4H(%GW~F5^.&ECzu9UKo=I6bX0 C?' );
define( 'AUTH_SALT',         '*Q3@!??)V{LT4[dpZa8<bf Wj&.=~hf&VC681% _!sOo<!CS2)f+}3(h2$YSYK O' );
define( 'SECURE_AUTH_SALT',  'HW&f<t4J)ssK+&D9$.Ge(/ehbRxMg$|KGVb3)YLV)8#n/1eo{W<pH[f) QJ:z7,x' );
define( 'LOGGED_IN_SALT',    '^rRdY}R_s/0we/tUn-6^Ox(0#ZE9Y<C~.kN{3=<f(]Uq*QCNr7PzyIz9/@SOn}M^' );
define( 'NONCE_SALT',        'G3XsL}&G$zf1hbPbj4u9o u)yMX.#0U@{B?=fG71hh~*LTB<)xBS$t?{E<9NR@i/' );
define( 'WP_CACHE_KEY_SALT', 'FAzfJUB9xkfS8$46sAj9#!oJ0yiF:&/(E|als?yx33+5twX* ]#aff OBKx/,Yga' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
