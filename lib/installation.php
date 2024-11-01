<?php
namespace Tripetto;

class Installation
{
    static function activate($network_wide)
    {
        if (!Capabilities::activatePlugins()) {
            return;
        }

        if (is_multisite() && $network_wide) {
            return;
        }

        // Update .htaccess to protect attachments
        if (!function_exists("get_home_path")) {
            include_once ABSPATH . "/wp-admin/includes/file.php";
        }

        if (!function_exists("insert_with_markers")) {
            include_once ABSPATH . "/wp-admin/includes/misc.php";
        }

        insert_with_markers(get_home_path() . ".htaccess", "Tripetto", [
            "<IfModule mod_alias.c>",
            "RedirectMatch 401 ^/wp-content/uploads/.*/tripetto/.*$",
            "</IfModule>",
            "<files tripetto.php>",
            "  Order allow,deny",
            "  Allow from all",
            "</files>",
        ]);
    }

    static function loaded()
    {
        $version = Database::option("version");

        if (empty($version) || version_compare($version, $GLOBALS["TRIPETTO_PLUGIN_VERSION"]) < 0) {
            if (empty($version)) {
                delete_option("tripetto_version");

                add_option("tripetto_version", $GLOBALS["TRIPETTO_PLUGIN_VERSION"], "", false);
            } else {
                update_option("tripetto_version", $GLOBALS["TRIPETTO_PLUGIN_VERSION"], false);

                if (empty(get_option("tripetto_onboarding"))) {
                    $onboarding = Helpers::createJSON();

                    $onboarding->version = $version;

                    delete_option("tripetto_onboarding");

                    add_option("tripetto_onboarding", Helpers::JSONToString($onboarding), "", true);
                }
            }

            Forms::database();
            Results::database();
            Attachments::database();
            Runner::database();
            Capabilities::install($version);
            Installation::upgrade($version);

            if (!empty($version)) {
                if (!License::isInProMode()) {
                    if (version_compare($version, "3.5.1") <= 0) {
                        add_option("tripetto_legacy", hash("sha256", "tripetto-legacy-" . $GLOBALS["TRIPETTO_PLUGIN_VERSION"]), "", false);
                    } else {
                        $legacy = Database::option("legacy");

                        if (!empty($legacy) && $legacy == hash("sha256", "tripetto-legacy-" . $version)) {
                            update_option(
                                "tripetto_legacy",
                                hash("sha256", "tripetto-legacy-" . $GLOBALS["TRIPETTO_PLUGIN_VERSION"]),
                                false
                            );
                        }
                    }
                }

                License::upgrade();
                Migration::apply($version);
            }
        }

        if (License::isInProMode()) {
            $plan = get_option("tripetto_plan");

            if (empty($plan) || $plan !== "pro") {
                global $wpdb;

                delete_option("tripetto_plan");

                add_option("tripetto_plan", "pro", "", true);

                $forms = $wpdb->get_results("SELECT id,styles FROM {$wpdb->prefix}tripetto_forms");

                if (count($forms) > 0) {
                    foreach ($forms as $form) {
                        if (Helpers::isValidJSON(Helpers::get($form, "styles"))) {
                            $styles = Helpers::stringToJSON(Helpers::get($form, "styles"));

                            $styles->noBranding = true;

                            $wpdb->query(
                                $wpdb->prepare(
                                    "UPDATE {$wpdb->prefix}tripetto_forms SET styles=%s WHERE id=%d",
                                    Helpers::JSONToString($styles),
                                    $form->id
                                )
                            );
                        }
                    }
                }
            }
        }
    }

    static function upgrade($version)
    {
        if (!empty($version) && version_compare($version, "3.5.1") <= 0 && !is_string(get_option("tripetto_sender"))) {
            add_option("tripetto_sender", "admin", "", true);
        }
    }

    static function uninstall()
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tripetto_forms");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tripetto_announcements");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tripetto_snapshots");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tripetto_entries");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tripetto_attachments");

        Capabilities::uninstall();

        delete_option("tripetto_version");
        delete_option("tripetto_plan");
        delete_option("tripetto_db");
        delete_option("tripetto_legacy");
        delete_option("tripetto_onboarding");
        delete_option("tripetto_onboarding_introduction");
        delete_option("tripetto_free_premium");
        delete_option("tripetto_sender");
        delete_option("tripetto_sender_name");
        delete_option("tripetto_sender_address");
        delete_option("tripetto_spam_protection");
        delete_option("tripetto_spam_protection_allowlist");

        // Update .htaccess and remove rule to protect attachments
        if (!function_exists("get_home_path")) {
            include_once ABSPATH . "/wp-admin/includes/file.php";
        }

        if (!function_exists("insert_with_markers")) {
            include_once ABSPATH . "/wp-admin/includes/misc.php";
        }

        insert_with_markers(
            get_home_path() . ".htaccess",
            "Tripetto",
            "# The Tripetto plugin is uninstalled and all directives that were here are removed."
        );
    }

    static function notification()
    {
        if (!version_compare(PHP_VERSION, "5.6.20", ">=")) {
            echo '<div class="notice notice-error">';
            echo "<p><strong>Tripetto requires PHP version 5.6.20 or above!</strong></p>";
            echo "</div>";
        }
    }

    static function currency()
    {
        return "usd";
    }

    static function register($plugin)
    {
        add_action("wp_loaded", ["Tripetto\Installation", "loaded"]);
        add_action("admin_notices", ["Tripetto\Installation", "notification"]);

        tripetto_fs()->add_action("after_uninstall", ["Tripetto\Installation", "uninstall"]);
        tripetto_fs()->add_filter("default_currency", ["Tripetto\Installation", "currency"]);

        if (is_admin()) {
            register_activation_hook($plugin, ["Tripetto\Installation", "activate"]);
        }
    }
}
?>
