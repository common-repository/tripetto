<?php
namespace Tripetto;

class Translation
{
    static function verify($locale, $context)
    {
        if (empty($locale) || !preg_match('/^[A-Za-z]{2,4}([_-]([A-Za-z]{4}|[0-9]{3}))?([_-]([A-Za-z]{2}|[0-9]{3}))?$/', $locale)) {
            return "";
        }

        $data = @file_get_contents(dirname(__DIR__) . "/languages/" . (!empty($context) ? $context . "-" : "") . $locale . ".json");

        if (!empty($data)) {
            return $data;
        }

        $i = strpos($locale, "_");

        if ($i !== false) {
            $locale = substr($locale, 0, $i);
            $data = @file_get_contents(dirname(__DIR__) . "/languages/" . (!empty($context) ? $context . "-" : "") . $locale . ".json");

            if (!empty($data)) {
                return $data;
            }
        }

        return "";
    }

    static function data($locale, $context)
    {
        if (!empty($context)) {
            $context = str_replace("@tripetto/", "tripetto-", $context);

            if (!preg_match('/^[a-z-]+$/', $context)) {
                return "";
            }
        }

        if ($locale == "en") {
            return "";
        }

        if (!empty($locale)) {
            $i = strpos($locale, ";");

            if ($i !== false) {
                $locale = substr($locale, 0, $i);
            }

            $i = strpos($locale, ",");

            if ($i !== false) {
                $locale = substr($locale, 0, $i);
            }

            $i = strpos($locale, "_");

            if ($i === false) {
                $locale .= "_" . strtoupper($locale);
            }

            $data = Translation::verify($locale, $context);

            if (!empty($data)) {
                return $data;
            }
        }

        return "";
    }

    static function process()
    {
        $language = !empty($_GET["l"]) ? $_GET["l"] : (!empty($_POST["language"]) ? $_POST["language"] : $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
        $context = !empty($_GET["c"]) ? $_GET["c"] : (!empty($_POST["context"]) ? $_POST["context"] : "tripetto");
        $data = Translation::data(str_replace("-", "_", $language), $context);

        if ($data != "") {
            header("Content-Type: application/json");

            echo $data;
        }

        die();
    }

    static function load()
    {
        load_plugin_textdomain("tripetto", false, dirname(dirname(plugin_basename(__FILE__))) . "/languages");
    }

    static function register($plugin)
    {
        add_action("wp_ajax_tripetto_translation", ["Tripetto\Translation", "process"]);
        add_action("wp_ajax_nopriv_tripetto_translation", ["Tripetto\Translation", "process"]);
        add_action("init", ["Tripetto\Translation", "load"]);
    }
}
?>
