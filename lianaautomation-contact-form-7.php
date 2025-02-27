<?php
/**
 * Plugin Name:       LianaAutomation for Contact Form 7
 * Description:       LianaAutomation for Contact Form 7
 * Version:           1.0.33
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Liana Technologies Oy
 * Author URI:        https://www.lianatech.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0-standalone.html
 * Text Domain:       lianaautomation
 * Domain Path:       /languages
 *
 * PHP Version 7.4
 *
 * @package  LianaAutomation
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */

/**
 * Include cookie handler code
 */
require_once dirname( __FILE__ ) . '/includes/lianaautomation-cookie.php';

/**
 * Include Contact Form 7 code
 */
require_once dirname( __FILE__ ) . '/includes/lianaautomation-wpcf7.php';

/**
 * Conditionally include admin panel code
 */
if ( is_admin() ) {
	require_once dirname( __FILE__ ) . '/admin/class-lianaautomation-contactform7.php';
}
