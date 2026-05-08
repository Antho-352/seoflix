<?php
/**
 * Plugin Name:       Seoflix Core
 * Plugin URI:        https://seoflix.fr
 * Description:       Cœur métier de Seoflix : CPT (vidéos, chaînes, produits), taxonomies, ingestion YouTube, importer JSON, tracking affiliation, REST API, feature flags comptes utilisateurs.
 * Version:           0.14.1
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Anthony Russo
 * License:           Proprietary
 * Text Domain:       seoflix-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEOFLIX_VERSION', '0.14.1' );
define( 'SEOFLIX_PLUGIN_FILE', __FILE__ );
define( 'SEOFLIX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOFLIX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SEOFLIX_DB_VERSION', '1' );

require_once SEOFLIX_PLUGIN_DIR . 'includes/class-plugin.php';
require_once SEOFLIX_PLUGIN_DIR . 'includes/class-activator.php';
require_once SEOFLIX_PLUGIN_DIR . 'includes/class-deactivator.php';

register_activation_hook( __FILE__, [ 'Seoflix\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Seoflix\\Deactivator', 'deactivate' ] );

add_action( 'plugins_loaded', static function () {
	Seoflix\Plugin::instance()->boot();
} );
