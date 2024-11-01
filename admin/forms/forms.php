<?php
namespace Tripetto;

require_once __DIR__ . "/list.php";

class Forms
{
    static function activate($network_wide)
    {
        if (!Capabilities::activatePlugins()) {
            return;
        }

        if (is_multisite() && $network_wide) {
            return;
        }

        Forms::database();
    }

    static function database()
    {
        Database::assert(
            "tripetto_forms",
            [
                "indx int(10) unsigned NOT NULL DEFAULT 0",
                "reference varchar(65) NOT NULL DEFAULT ''",
                "token varchar(33) NOT NULL DEFAULT ''",
                "name text NOT NULL",
                "definition longtext NOT NULL",
                "fingerprint varchar(65) NOT NULL DEFAULT ''",
                "stencil varchar(65) NOT NULL DEFAULT ''",
                "actionables varchar(65) NOT NULL DEFAULT ''",
                "runner tinytext NOT NULL DEFAULT ''",
                "styles longtext NOT NULL DEFAULT ''",
                "l10n longtext NOT NULL DEFAULT ''",
                "hooks longtext NOT NULL DEFAULT ''",
                "trackers longtext NOT NULL DEFAULT ''",
                "settings longtext NOT NULL DEFAULT ''",
                "shortcode longtext NOT NULL DEFAULT ''",
                "created datetime NULL DEFAULT NULL",
                "modified datetime NULL DEFAULT NULL",
            ],
            ["reference", "fingerprint", "stencil", "created", "modified"]
        );
    }

    static function menu()
    {
        add_submenu_page(
            "tripetto",
            __("Build Form", "tripetto"),
            __("Build Form", "tripetto"),
            Capabilities::key("tripetto_create_forms"),
            "tripetto-create",
            ["Tripetto\Templates", "page"]
        );

        add_submenu_page(
            "tripetto",
            __("All Forms", "tripetto"),
            __("All Forms", "tripetto") . " (" . Forms::total() . ")",
            Capabilities::key(),
            "tripetto-forms",
            ["Tripetto\Forms", "page"]
        );

        add_submenu_page(
            "tripetto",
            __("Onboarding", "tripetto"),
            __("Onboarding", "tripetto"),
            Capabilities::key(),
            "tripetto-onboarding",
            ["Tripetto\Onboarding", "page"]
        );
    }

    static function page()
    {
        if (Onboarding::assert()) {
            return;
        }

        global $wpdb;

        $action = !empty($_REQUEST["action"]) ? $_REQUEST["action"] : "";
        $id = !empty($_REQUEST["id"]) ? intval($_REQUEST["id"]) : 0;
        $reference = !empty($_REQUEST["reference"]) ? $_REQUEST["reference"] : "";

        switch ($action) {
            case "create":
                Builder::create(
                    !empty($_REQUEST["runner"]) ? $_REQUEST["runner"] : "",
                    true,
                    !empty($_REQUEST["template"]) ? $_REQUEST["template"] : ""
                );
                break;
            case "builder":
            case "share":
            case "styles":
            case "l10n":
            case "notifications":
            case "connections":
            case "tracking":
                Builder::run($id, $action != "builder" ? $action : "");
                break;
            case "results":
                Results::overview($id);
                break;
            case "view":
                if (!$id && !empty($reference)) {
                    Results::view($reference, true);
                } else {
                    Results::view($id);
                }

                break;
            case "columns":
                Columns::edit($id);
                break;
            default:
                wp_enqueue_style("wp-tripetto");
                wp_enqueue_script("wp-tripetto");

                $forms = new FormsList();
                $forms->prepare_items();

                $message = "";

                if ($forms->current_action() === "delete" && !empty($_REQUEST["id"])) {
                    $message = '<div class="updated below-h2" id="message"><p>' . __("Form deleted!", "tripetto") . "</p></div>";
                }

                if ($forms->current_action() === "duplicate" && !empty($_REQUEST["id"])) {
                    $message = '<div class="updated below-h2" id="message"><p>' . __("Form duplicated!", "tripetto") . "</p></div>";
                }

                echo '<div class="wrap" id="wp-tripetto-admin" style="opacity: 0;">';
                echo $message;
                echo '<form id="tripetto_forms_table" method="GET">';
                echo '<input type="hidden" name="page" value="' . esc_attr($_REQUEST["page"]) . '"/>';
                echo $forms->display();
                echo "</form>";
                echo "</div>";

                Header::generate(
                    __("All Forms", "tripetto"),
                    "?page=tripetto",
                    Forms::total(),
                    Capabilities::createForms()
                        ? "<a href='?page=tripetto-create' class='wp-tripetto-header-button wp-tripetto-header-button-icon wp-tripetto-header-button-icon-add'>" .
                            __("Build Form", "tripetto") .
                            "</a>"
                        : null
                );

                break;
        }
    }

    static function delete($ids)
    {
        Tripetto::assert("delete-forms");

        if (!empty($ids)) {
            global $wpdb;

            Results::delete($wpdb->get_col("SELECT id FROM {$wpdb->prefix}tripetto_entries WHERE form_id IN ($ids)"));

            $attachments = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}tripetto_attachments WHERE form_id IN ($ids)");

            foreach ($attachments as $attachment) {
                Attachments::delete($attachment);
            }

            $wpdb->query("DELETE FROM {$wpdb->prefix}tripetto_announcements WHERE form_id IN ($ids)");
            $wpdb->query("DELETE FROM {$wpdb->prefix}tripetto_snapshots WHERE form_id IN ($ids)");
            $wpdb->query("DELETE FROM {$wpdb->prefix}tripetto_forms WHERE id IN ($ids)");
        }
    }

    static function total()
    {
        global $wpdb;

        $total = intval($wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}tripetto_forms"));

        return $total > 0 ? $total : 0;
    }

    static function register($plugin)
    {
        register_activation_hook($plugin, ["Tripetto\Forms", "activate"]);

        Builder::register($plugin);
        Results::register($plugin);
    }
}
?>
