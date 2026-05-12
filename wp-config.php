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
define( 'AUTH_KEY',          'm52/.vvLioM+t;#c2,Jj;dRn|/yt~[UN|w,jR]Z>+Y;5ntg(N^ [VgI/,eDkN6Jb' );
define( 'SECURE_AUTH_KEY',   'bj]Brmq@P0-YN-=:?Wa*4lhFV=PldWQ.[Pf@Iy=4OL&ak<sK3jCz)2|<_LNlsYs>' );
define( 'LOGGED_IN_KEY',     '^+Zoe}j K]wZ6T e=#`e;LDNU9QAwXgR.r1S70MP`y-z&]7%pX<vxQ-joD1trd?C' );
define( 'NONCE_KEY',         'cMyO<&]QWIz{`3bb h:`&KzQ-<:m9;a[?NH2@u-284P-+;okPRF<i`1+w<.`GVlp' );
define( 'AUTH_SALT',         'd<Kij:YKU$z[=`scW,DF3hn$I{(/YNfv:l<-_$1^75/hr`(@zM?owl,f,(7P16%H' );
define( 'SECURE_AUTH_SALT',  '?NbR/rSH<OdvDC!uNn3Ox#M3P:<GG4M)jnO!}*:UO~lgM=%ni!Xyr=W3nF}I*`~i' );
define( 'LOGGED_IN_SALT',    'CfG7nG)^al>.dsERqX`[l&w|L7x{~xSTBRHc!<+3uwdD{@_3M1XXGNa]ZmrhRSqv' );
define( 'NONCE_SALT',        'T0>,(=m(.+W(;>/F^dC3IrT>AKH]xAf`vAYd|6p;mg.By][51{|PU30`7QnK>C2%' );
define( 'WP_CACHE_KEY_SALT', '*I/7uZ>cky(JWB/cpb(I~f^GUNrvXZ3U~vZ%n<8%Mn0U{*L>|84C{WLGtw>/{[CC' );


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
