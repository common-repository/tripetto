<?php
namespace Tripetto;

final class Gutenberg
{
    static function init()
    {
        global $wp_version;

        if (!function_exists("register_block_type")) {
            return;
        }

        $plugin_url = Helpers::pluginUrl();

        wp_register_script(
            "wp-tripetto-gutenberg-block",
            $plugin_url . "/js/wp-tripetto-gutenberg.js",
            [
                version_compare($wp_version, "5.2", "<") ? "wp-editor" : "wp-block-editor",
                "wp-blocks",
                "wp-components",
                "wp-element",
                "wp-tripetto",
                "vendor-tripetto-runner-autoscroll",
                "vendor-tripetto-runner-chat",
                "vendor-tripetto-runner-classic",
            ],
            $GLOBALS["TRIPETTO_PLUGIN_VERSION"]
        );

        wp_localize_script("wp-tripetto-gutenberg-block", "tripetto_gutenberg_block", [
            "base" => $plugin_url,
            "url" => admin_url("admin-ajax.php"),
            "language" => get_locale(),
            "capability_create" => Capabilities::createForms(),
            "capability_edit" => Capabilities::editForms(),
            "capability_run" => Capabilities::runForms(),
            "capability_results" => Capabilities::viewResults(),
            "l10n_title" => __("Tripetto Form", "tripetto"),
            "l10n_description" => __("Show a Tripetto form or survey.", "tripetto"),
            "l10n_keyword_form" => __("form", "tripetto"),
            "l10n_keyword_survey" => __("survey", "tripetto"),
            "l10n_loading_preview" => __("Preparing live preview...", "tripetto"),
            "l10n_loading_forms" => __("Loading forms...", "tripetto"),
            "l10n_error_loading_preview" => __("Live preview not available!", "tripetto"),
            "l10n_error_loading_forms" => __("Error while loading your list of available forms!", "tripetto"),
            "l10n_error_loading_form" => __("Error while loading form (is the form deleted?)", "tripetto"),
            "l10n_insufficient_rights" => __("You have insufficient rights to select existing forms or create new forms.", "tripetto"),
            "l10n_select_form" => __("Select existing form", "tripetto"),
            "l10n_build_form" => __("Build new form", "tripetto"),
            "l10n_or" => __("Or", "tripetto"),
            "l10n_unnamed_form" => __("Unnamed form", "tripetto"),
            "l10n_select_another" => __("Select another form", "tripetto"),
            "l10n_retry" => __("Retry", "tripetto"),
            "l10n_form_face" => __("Form face", "tripetto"),
            "l10n_form_face_label" => __("Select form face", "tripetto"),
            "l10n_edit_form" => __("Edit form", "tripetto"),
            "l10n_edit_form_fullscreen" => __("Edit form (fullscreen)", "tripetto"),
            "l10n_edit_form_side_by_side" => __("Edit form (side by side)", "tripetto"),
            "l10n_edit_styles" => __("Edit styles", "tripetto"),
            "l10n_edit_translations" => __("Edit translations", "tripetto"),
            "l10n_automate" => __("Automate", "tripetto"),
            "l10n_notifications" => __("Notifications", "tripetto"),
            "l10n_connections" => __("Connections", "tripetto"),
            "l10n_tracking" => __("Tracking", "tripetto"),
            "l10n_view_results" => __("View results", "tripetto"),
            "l10n_restart" => __("Restart form preview", "tripetto"),
            "l10n_options" => __("Options", "tripetto"),
            "l10n_pausable" => __("Allow pausing and resuming", "tripetto"),
            "l10n_pausable_help" => __(
                "Allows users to pause the form and continue with it later by sending a resume link to the user's email address.",
                "tripetto"
            ),
            "l10n_persistent" => __("Save and restore uncompleted forms", "tripetto"),
            "l10n_persistent_help" => __(
                "Saves uncompleted forms in the local storage of the browser. Next time the user visits the form it is restored so the user can continue.",
                "tripetto"
            ),
            "l10n_async" => __("Disable asynchronous loading", "tripetto"),
            "l10n_async_help" => __(
                "Asynchronous loading helps avoiding caching issues, but sometimes results in longer form loading times because the page has already been rendered before the form is loaded. Enabling this option will load the form together with the page itself. Some cache plugins cache the page including the form. If this option is checked and your form doesn't update when you make a change, you probably need to clear the cache of your cache plugin.",
                "tripetto"
            ),
            "l10n_width" => __("Width", "tripetto"),
            "l10n_width_label" => __("Specify a fixed width", "tripetto"),
            "l10n_height" => __("Height", "tripetto"),
            "l10n_height_label" => __("Specify a fixed height", "tripetto"),
            "l10n_placeholder" => __("Placeholder", "tripetto"),
            "l10n_placeholder_label" => __("Specify a loader placeholder message", "tripetto"),
            "l10n_placeholder_help" => __("This message is shown while the form is loading. You can specify text or HTML.", "tripetto"),
            "l10n_css" => __("Custom CSS", "tripetto"),
            "l10n_css_label" => __("Specify custom CSS styles", "tripetto"),
            "l10n_css_help" => __(
                "Don't use this, unless you know what you are doing. We don't give support on forms with custom CSS! If you have a problem with a form that uses custom CSS, then first disable the custom CSS and check if the problem persists.",
                "tripetto"
            ),
            "l10n_css_placeholder" => sprintf(
                /* translators: %s is replaced with a code example for the CSS selector */
                __("To specify rules for a specific block, use this selector: %s", "tripetto"),
                '[data-block="<block identifier>"] { ... }'
            ),
            "l10n_detach_form" => __("Detach from block", "tripetto"),
            "l10n_help" => __("Help", "tripetto"),
        ]);

        register_block_type(version_compare($wp_version, "5.8", ">=") ? __DIR__ : "tripetto/form", [
            "editor_script" => "wp-tripetto-gutenberg-block",
            "render_callback" => ["Tripetto\Gutenberg", "render"],
        ]);
    }

    static function assets()
    {
        wp_enqueue_style(
            "wp-tripetto-gutenberg",
            Helpers::pluginUrl() . "/css/wp-tripetto-gutenberg.css",
            [],
            $GLOBALS["TRIPETTO_PLUGIN_VERSION"]
        );
    }

    static function render($attributes)
    {
        $id = !empty($attributes["form"]) ? intval($attributes["form"]) : 0;
        $pausable = !empty($attributes["pausable"]) && $attributes["pausable"] === true ? true : false;
        $persistent = !empty($attributes["persistent"]) && $attributes["persistent"] === true ? true : false;
        $async = isset($attributes["async"]) && $attributes["async"] === false ? false : true;
        $width = !empty($attributes["width"]) ? $attributes["width"] : "";
        $height = !empty($attributes["height"]) ? $attributes["height"] : "";
        $placeholder = !empty($attributes["placeholder"]) ? urldecode($attributes["placeholder"]) : "";
        $css = !empty($attributes["css"]) ? urldecode($attributes["css"]) : "";
        $className = !empty($attributes["className"]) ? $attributes["className"] : "";

        if (!empty($id)) {
            global $wpdb;

            $form = $wpdb->get_row($wpdb->prepare("SELECT * from {$wpdb->prefix}tripetto_forms where id=%d", $id));

            if (!is_null($form)) {
                return Runner::script(
                    Runner::props($form, $async, false, $pausable, $persistent, $css, $width, $height, $className),
                    Runner::trackers($form),
                    $placeholder
                );
            }
        }

        return "";
    }

    static function fetch()
    {
        Tripetto::assert(["edit-forms", "run-forms"]);

        global $wpdb;

        $response = Helpers::createJSON();
        $response->forms = [];

        $forms = $wpdb->get_results("SELECT id,name,modified FROM {$wpdb->prefix}tripetto_forms ORDER BY name,modified DESC");

        foreach ($forms as $form) {
            $item = Helpers::createJSON();

            $item->id = intval($form->id);
            $item->name = !empty($form->name) ? $form->name : __("Unnamed form", "tripetto");
            $item->date = Helpers::formatDate($form->modified);

            array_push($response->forms, $item);
        }

        header("Content-Type: application/json");

        echo Helpers::JSONToString($response);

        http_response_code(200);

        die();
    }

    static function create()
    {
        Tripetto::assert(["create-forms"]);

        $runner = !empty($_POST["runner"]) ? $_POST["runner"] : "";
        $id = Builder::create($runner, false);

        if (!empty($id)) {
            global $wpdb;

            $form = $wpdb->get_row($wpdb->prepare("SELECT * from {$wpdb->prefix}tripetto_forms where id=%d", $id));

            if (!is_null($form)) {
                header("Content-Type: application/json");

                echo Helpers::JSONToString(Builder::props($form));

                http_response_code(200);

                return die();
            }
        }

        http_response_code(400);

        die();
    }

    static function load()
    {
        $id = !empty($_POST["id"]) ? intval($_POST["id"]) : 0;

        if (!empty($id)) {
            global $wpdb;

            $form = $wpdb->get_row($wpdb->prepare("SELECT * from {$wpdb->prefix}tripetto_forms where id=%d", $id));

            if (!is_null($form)) {
                header("Content-Type: application/json");

                echo Helpers::JSONToString(Builder::props($form));

                http_response_code(200);

                return die();
            }
        }

        http_response_code(400);

        die();
    }

    static function register($plugin)
    {
        add_action("init", ["Tripetto\Gutenberg", "init"]);
        add_action("enqueue_block_editor_assets", ["Tripetto\Gutenberg", "assets"]);
        add_action("wp_ajax_tripetto_gutenberg_fetch", ["Tripetto\Gutenberg", "fetch"]);
        add_action("wp_ajax_tripetto_gutenberg_create", ["Tripetto\Gutenberg", "create"]);
        add_action("wp_ajax_tripetto_gutenberg_load", ["Tripetto\Gutenberg", "load"]);
    }
}
?>
