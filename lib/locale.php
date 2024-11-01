<?php
namespace Tripetto;

class Locale
{
    static function verify($locale)
    {
        if (empty($locale) || !preg_match("/^[A-Za-z]{2,4}([_-]([A-Za-z]{4}|[0-9]{3}))?([_-]([A-Za-z]{2}|[0-9]{3}))?$/", $locale)) {
            return "";
        }

        $data = @file_get_contents(dirname(__DIR__) . "/locales/" . $locale . ".json");

        if (!empty($data)) {
            return $data;
        }

        $i = strpos($locale, "-");

        if ($i !== false) {
            $locale = substr($locale, 0, $i);
            $data = @file_get_contents(dirname(__DIR__) . "/locales/" . $locale . ".json");

            if (!empty($data)) {
                return $data;
            }
        }

        return "";
    }

    static function data($locale)
    {
        if (!empty($locale)) {
            $i = strpos($locale, ";");

            if ($i !== false) {
                $locale = substr($locale, 0, $i);
            }

            $i = strpos($locale, ",");

            if ($i !== false) {
                $locale = substr($locale, 0, $i);
            }

            $data = Locale::verify($locale);

            if (!empty($data)) {
                return $data;
            }
        }

        return Locale::verify("en");
    }

    static function process()
    {
        $locale = !empty($_GET["l"]) ? $_GET["l"] : (!empty($_POST["locale"]) ? $_POST["locale"] : $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
        $data = Locale::data(str_replace("_", "-", $locale));

        if ($data != "") {
            header("Content-Type: application/json");

            echo $data;
        }

        die();
    }

    static function register($plugin)
    {
        add_action("wp_ajax_tripetto_locale", ["Tripetto\Locale", "process"]);
        add_action("wp_ajax_nopriv_tripetto_locale", ["Tripetto\Locale", "process"]);
    }
}
?>
