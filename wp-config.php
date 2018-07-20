<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'sql12248006');

/** MySQL database username */
define('DB_USER', 'sql12248006');

/** MySQL database password */
define('DB_PASSWORD', 'cm7wx867Ww');

/** MySQL hostname */
define('DB_HOST', 'sql12.freemysqlhosting.net');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'cW#Qz(tON&OwZ<E_ k-_1%VTBnsA>oO^0u|)=c%e;KvckbUuV+l<!h6_fui63wWb');
define('SECURE_AUTH_KEY',  'BJL2RgL.B|aFa>:HAG7tc>f>CZ`:!?eLb$9*yd@~aSBEol00/Fw=0F.1rC*3c7SU');
define('LOGGED_IN_KEY',    'GR0PQ,,KxBN5[.^_Qk#rx,:0!qj5h~3=#= n6oO0!h]Yt3p5%<R-kX$).x~>yUXT');
define('NONCE_KEY',        ',0vWYfP Floq8*/nB:]1}9Y9Bi[2&SUK1Q.6{:l.]DuL[9iLJfmQ}a_XeG(INH={');
define('AUTH_SALT',        'ZgK7E6eWR%<QXZY:D9R7VU37!3x7 4`R*.^7P7pXJM&L9c-otsod~Q]a~#/pWw=Q');
define('SECURE_AUTH_SALT', ':<G4.H#c-V^K>d}jov&zv6#dayX[X!jP%FGCu?ei-eP]$et/ju{Dc|*{<Bhg&>`h');
define('LOGGED_IN_SALT',   '.5sWr-9R8 byrx;&@}8l6A=uxAHHFi~b<dBkON_:^;~e4beavjaqYc}E~LM6<vqc');
define('NONCE_SALT',       'wL4T9M Z*!%Cmd^Gz`y$F,TV%MAv2wwb3^h/VQbw8& bVVMMP}uF6h9YRPnH?&26');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
