<?php

namespace Tripetto;

class License {
    /**
     * Returns whether a form has pro features.
     *
     * @param array $formId The id of the form.
     */
    static function hasProFeatures( $formId ) {
        if ( License::isInProMode() || !empty( get_option( "tripetto_" . hash( "sha256", "premium" . strval( $formId ) ) ) ) ) {
            return true;
        }
        return false;
    }

    /**
     * Returns whether a form has legacy features.
     */
    static function hasLegacyFeatures() {
        $legacy = Database::option( "legacy" );
        if ( !empty( $legacy ) && $legacy == hash( "sha256", "tripetto-legacy-" . $GLOBALS["TRIPETTO_PLUGIN_VERSION"] ) ) {
            return true;
        }
        return false;
    }

    /**
     * Returns whether the plugin is in pro mode.
     */
    static function isInProMode() {
        return false;
    }

    static function upgrade() {
        if ( !License::isInProMode() ) {
            global $wpdb;
            $freePro = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
                DB_NAME,
                $wpdb->prefix . "tripetto_forms",
                "premium"
            ) );
            delete_option( "tripetto_free_premium" );
            if ( !empty( $freePro ) ) {
                $proForm = $wpdb->get_var( "SELECT id from {$wpdb->prefix}tripetto_forms where premium > 0 ORDER BY id LIMIT 1" );
                if ( !is_null( $proForm ) && !empty( $proForm ) ) {
                    add_option(
                        "tripetto_free_premium",
                        "yes",
                        "",
                        false
                    );
                    add_option(
                        "tripetto_" . hash( "sha256", "premium" . strval( $proForm ) ),
                        "premium",
                        "",
                        false
                    );
                }
            }
        }
    }

}
