<?php
namespace Tripetto;

class ElementorWidget extends \Elementor\Widget_Base
{
    public function __construct($data = [], $args = null)
    {
        parent::__construct($data, $args);

        wp_register_script(
            "wp-tripetto-elementor",
            Helpers::pluginUrl() . "/js/wp-tripetto-elementor.js",
            ["jquery-core", "elementor-frontend"],
            $GLOBALS["TRIPETTO_PLUGIN_VERSION"],
            true
        );

        wp_register_style(
            "wp-tripetto-elementor",
            Helpers::pluginUrl() . "/css/wp-tripetto-elementor.css",
            [],
            $GLOBALS["TRIPETTO_PLUGIN_VERSION"]
        );
    }

    public function get_script_depends()
    {
        return ["wp-tripetto-elementor"];
    }

    public function get_style_depends()
    {
        return ["wp-tripetto-elementor"];
    }

    public function get_name()
    {
        return "tripetto";
    }

    public function get_title()
    {
        return __("Tripetto Form", "tripetto");
    }

    public function get_icon()
    {
        return "eicon-comments";
    }

    public function get_categories()
    {
        return ["general"];
    }

    public function get_keywords()
    {
        return [];
    }

    protected function _register_controls()
    {
        global $wpdb;

        $options = ["" => "📂 " . __("Select a form...", "tripetto")];

        if (Capabilities::editForms() || Capabilities::runForms()) {
            $forms = $wpdb->get_results("SELECT id,name,modified FROM {$wpdb->prefix}tripetto_forms ORDER BY name,modified DESC");

            foreach ($forms as $form) {
                $options[strval($form->id)] =
                    (!empty($form->name) ? $form->name : __("Unnamed form", "tripetto")) .
                    " (" .
                    Helpers::formatDate($form->modified) .
                    ")";
            }
        }

        $this->start_controls_section("tripetto", [
            "label" => __("Tripetto Form", "tripetto"),
            "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $help = '<span style="font-style: normal;">';

        if (Capabilities::editForms()) {
            if (count($options) === 0) {
                $help .=
                    "⚠️ " .
                    __("You currently don't have any Tripetto forms.", "tripetto") .
                    (Capabilities::createForms()
                        ? '<br /><br /><a href="' .
                            admin_url("/admin.php?page=tripetto-create", "admin") .
                            '" target="_blank">' .
                            __("Build a new form", "tripetto") .
                            "</a>"
                        : "");
            } else {
                $help .=
                    '<a href="' .
                    admin_url("/admin.php?page=tripetto-forms", "admin") .
                    '" target="_blank">' .
                    __("Build a new form or edit existing one", "tripetto") .
                    "</a>";
            }
        } elseif (count($options) === 0) {
            $help = "⚠️ " . __("You currently don't have any Tripetto forms or insufficient access rights to use forms.", "tripetto");
        }

        $help .= "</span>";

        $this->add_control("form", [
            "label" => __("Use the following form", "tripetto"),
            "description" => $help,
            "label_block" => true,
            "type" => \Elementor\Controls_Manager::SELECT,
            "options" => $options,
            "default" => "",
        ]);

        $this->end_controls_section();

        // Options
        $this->start_controls_section("tripetto_options", [
            "label" => __("Options", "tripetto"),
            "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);
        $this->add_control("pausable", [
            "label" => __("Allow pausing and resuming", "tripetto"),
            "description" => __(
                "Allows users to pause the form and continue with it later by sending a resume link to the user's email address.",
                "tripetto"
            ),
            "type" => \Elementor\Controls_Manager::SWITCHER,
            "label_on" => __("Yes", "tripetto"),
            "label_off" => __("No", "tripetto"),
            "return_value" => "true",
            "default" => "false",
        ]);
        $this->add_control("persistent", [
            "label" => __("Save and restore uncompleted forms", "tripetto"),
            "description" => __(
                "Saves uncompleted forms in the local storage of the browser. Next time the user visits the form it is restored so the user can continue.",
                "tripetto"
            ),
            "type" => \Elementor\Controls_Manager::SWITCHER,
            "label_on" => __("Yes", "tripetto"),
            "label_off" => __("No", "tripetto"),
            "return_value" => "true",
            "default" => "false",
        ]);
        $this->add_control("async", [
            "label" => __("Disable asynchronous loading", "tripetto"),
            "description" => __(
                "Asynchronous loading helps avoiding caching issues, but sometimes results in longer form loading times because the page has already been rendered before the form is loaded. Enabling this option will load the form together with the page itself. Some cache plugins cache the page including the form. If this option is checked and your form doesn't update when you make a change, you probably need to clear the cache of your cache plugin.",
                "tripetto"
            ),
            "type" => \Elementor\Controls_Manager::SWITCHER,
            "label_on" => __("Yes", "tripetto"),
            "label_off" => __("No", "tripetto"),
            "return_value" => "false",
            "default" => "true",
        ]);
        $this->end_controls_section();

        // Width
        $this->start_controls_section("tripetto_width", [
            "label" => __("Width", "tripetto"),
            "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);
        $this->add_control("width", [
            "label" => __("Specify a fixed width", "tripetto"),
            "label_block" => true,
            "type" => \Elementor\Controls_Manager::TEXT,
            "placeholder" => "100%",
        ]);
        $this->end_controls_section();

        // Height
        $this->start_controls_section("tripetto_height", [
            "label" => __("Height", "tripetto"),
            "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);
        $this->add_control("height", [
            "label" => __("Specify a fixed height", "tripetto"),
            "label_block" => true,
            "type" => \Elementor\Controls_Manager::TEXT,
            "placeholder" => "auto",
        ]);
        $this->end_controls_section();

        // Placeholder
        $this->start_controls_section("tripetto_placeholder", [
            "label" => __("Placeholder", "tripetto"),
            "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);
        $this->add_control("placeholder", [
            "label" => __("Specify a loader placeholder message", "tripetto"),
            "description" => __("This message is shown while the form is loading. You can specify text or HTML.", "tripetto"),
            "type" => \Elementor\Controls_Manager::TEXTAREA,
            "rows" => 4,
        ]);
        $this->end_controls_section();

        // Custom CSS
        $this->start_controls_section("tripetto_css", [
            "label" => __("Custom CSS", "tripetto"),
            "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);
        $this->add_control("css", [
            "label" => __("Specify custom CSS styles", "tripetto"),
            "description" => __(
                "Don't use this, unless you know what you are doing. We don't give support on forms with custom CSS! If you have a problem with a form that uses custom CSS, then first disable the custom CSS and check if the problem persists.",
                "tripetto"
            ),
            "type" => \Elementor\Controls_Manager::TEXTAREA,
            "rows" => 10,
            "placeholder" => sprintf(
                /* translators: %s is replaced with a code example for the CSS selector */
                __("To specify rules for a specific block, use this selector: %s", "tripetto"),
                '[data-block="<block identifier>"] { ... }'
            ),
        ]);
        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        if (isset($settings["form"]) && !empty($settings["form"])) {
            global $wpdb;

            $form = $wpdb->get_row($wpdb->prepare("SELECT * from {$wpdb->prefix}tripetto_forms where id=%d", intval($settings["form"])));

            if (!is_null($form)) {
                $props = Runner::props(
                    $form,
                    $settings["async"] !== "false",
                    false,
                    $settings["pausable"] === "true",
                    $settings["persistent"] === "true",
                    is_string($settings["css"]) ? $settings["css"] : "",
                    is_string($settings["width"]) ? $settings["width"] : "",
                    is_string($settings["height"]) ? $settings["height"] : "",
                    "",
                    $this->get_id()
                );

                echo Runner::script(
                    $props,
                    Runner::trackers($form),
                    is_string($settings["placeholder"]) ? $settings["placeholder"] : "",
                    true
                );

                return;
            }
        }

        if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
            echo '<div class="placeholder" style="background-image: url(\'' . Helpers::pluginUrl() . '/assets/background.svg\');">';
            echo '<div><img src="' . Helpers::pluginUrl() . '/assets/tripetto.png" /><span>Tripetto</span></div>';
            echo "<div>" . __("Select a form and see a live preview here.", "tripetto") . "</div>";
            echo "</div>";
        }
    }
}
?>
