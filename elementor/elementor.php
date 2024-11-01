<?php
namespace Tripetto;

final class Elementor
{
    private static $instance = null;

    public function __construct()
    {
        add_action("plugins_loaded", [$this, "load"]);
    }

    public function load()
    {
        if ($this->isCompatible()) {
            add_action("elementor/init", [$this, "init"]);
        }
    }

    public function isCompatible()
    {
        if (!did_action("elementor/loaded")) {
            return false;
        }

        if (!version_compare(ELEMENTOR_VERSION, "2.0.0", ">=")) {
            return false;
        }

        return true;
    }

    public function init()
    {
        Translation::load();

        add_action("elementor/widgets/widgets_registered", [$this, "registerWidget"]);
    }

    public function registerWidget()
    {
        require_once __DIR__ . "/widget.php";

        \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new ElementorWidget());
    }

    static function register($plugin)
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
    }
}
?>
