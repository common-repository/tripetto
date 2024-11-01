<?php

/**
 * Plugin Name: Tripetto
 * Plugin URI: https://tripetto.com
 * Description: Advanced WordPress form builder plugin to build conversational contact forms, surveys, quizzes and more. Give life to forms and surveys.
 * Author: Tripetto
 * Author URI: https://tripetto.com
 * Text Domain: tripetto
 * Domain path: /languages
 * Version: 8.0.3
 * Requires at least: 4.9
 * Requires PHP: 5.6
 * License: GPLv2 or later
 *
 * @package Tripetto
 */
namespace Tripetto;

if ( !defined( "WPINC" ) ) {
    die;
}
if ( !defined( "ABSPATH" ) ) {
    die;
}
// Detect the Freemius function of older versions of the plugin and map it to the new one
if ( !function_exists( 'Tripetto\\tripetto_fs' ) && function_exists( 'Tripetto\\tripetto_freemius' ) ) {
    function tripetto_fs() {
        return tripetto_freemius();
    }

}
if ( function_exists( 'Tripetto\\tripetto_fs' ) ) {
    tripetto_fs()->set_basename( false, __FILE__ );
} else {
    if ( !function_exists( "tripetto_fs" ) ) {
        // Create a helper function for easy SDK access.
        function tripetto_fs() {
            global $tripetto_fs;
            if ( !isset( $tripetto_fs ) ) {
                // Activate multisite network integration.
                if ( !defined( "WP_FS__PRODUCT_3825_MULTISITE" ) ) {
                    define( "WP_FS__PRODUCT_3825_MULTISITE", true );
                }
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . "/freemius/start.php";
                $tripetto_fs = fs_dynamic_init( [
                    "id"             => "3825",
                    "slug"           => "tripetto",
                    "premium_slug"   => "tripetto-pro",
                    "type"           => "plugin",
                    "public_key"     => "pk_bbe3e39e20ddf86c6ff4721c5e30e",
                    "is_premium"     => false,
                    "premium_suffix" => "Pro",
                    "has_addons"     => false,
                    "has_paid_plans" => true,
                    "menu"           => [
                        "slug"    => "tripetto",
                        "contact" => false,
                        "support" => false,
                        "pricing" => stripos( dirname( plugin_basename( __FILE__ ) ), "tripetto-" ) !== 0,
                    ],
                    'is_live'        => true,
                ] );
            }
            return $tripetto_fs;
        }

        // Maintain compatibility with older versions of the plugin
        if ( !function_exists( 'Tripetto\\tripetto_freemius' ) ) {
            function tripetto_freemius() {
                return tripetto_fs();
            }

        }
        // Init Freemius.
        tripetto_fs();
        // Signal that SDK was initiated.
        do_action( "tripetto_fs_loaded" );
    }
    $GLOBALS["TRIPETTO_PLUGIN_VERSION"] = "8.0.3";
    // Libraries and helpers
    require_once __DIR__ . "/lib/polyfill.php";
    require_once __DIR__ . "/lib/attachments.php";
    require_once __DIR__ . "/lib/database.php";
    require_once __DIR__ . "/lib/capabilities.php";
    require_once __DIR__ . "/lib/helpers.php";
    require_once __DIR__ . "/lib/installation.php";
    require_once __DIR__ . "/lib/license.php";
    require_once __DIR__ . "/lib/list.php";
    require_once __DIR__ . "/lib/mailer.php";
    require_once __DIR__ . "/lib/migration.php";
    require_once __DIR__ . "/lib/template.php";
    require_once __DIR__ . "/lib/theme.php";
    require_once __DIR__ . "/lib/loader.php";
    require_once __DIR__ . "/lib/locale.php";
    require_once __DIR__ . "/lib/translation.php";
    require_once __DIR__ . "/lib/variables.php";
    // Views
    require_once __DIR__ . "/admin/admin.php";
    require_once __DIR__ . "/runner/runner.php";
    require_once __DIR__ . "/gutenberg/gutenberg.php";
    require_once __DIR__ . "/elementor/elementor.php";
    // Register components
    Installation::register( __FILE__ );
    Locale::register( __FILE__ );
    Translation::register( __FILE__ );
    Loader::register( __FILE__ );
    Attachments::register( __FILE__ );
    Tripetto::register( __FILE__ );
    Runner::register( __FILE__ );
    Gutenberg::register( __FILE__ );
    Elementor::register( __FILE__ );
}