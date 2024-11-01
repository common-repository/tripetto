<?php
namespace Tripetto;

class Helpers
{
    static function pluginUrl()
    {
        return rtrim(plugin_dir_url(dirname(__FILE__)), "/");
    }

    static function isValidSHA256($hash)
    {
        if (!empty($hash) && strlen($hash) == 64 && preg_match("/^([a-f0-9]{64})$/", $hash) == 1) {
            return true;
        }

        return false;
    }

    static function isValidJSON($json)
    {
        if (!isset($json) || empty($json)) {
            return false;
        }

        $result = json_decode($json, false);

        return json_last_error() === JSON_ERROR_NONE && is_object($result);
    }

    static function createJSON()
    {
        return new \stdClass();
    }

    static function stringToJSON($str, $fallback = null)
    {
        if (!empty($str)) {
            $json = json_decode($str, false);

            if (json_last_error() === JSON_ERROR_NONE && is_object($json)) {
                return $json;
            }
        }

        if (is_object($fallback)) {
            return $fallback;
        }

        return Helpers::createJSON();
    }

    static function JSONToString($object)
    {
        if (is_object($object)) {
            $json = json_encode($object);

            if ($json !== false && !empty($json)) {
                if (version_compare(PHP_VERSION, "7.1.0") >= 0) {
                    return $json;
                }

                return str_replace('"_empty_":', '"":', $json);
            }
        }

        return "{}";
    }

    static function get($object, $key)
    {
        if (is_object($object) && isset($object->{$key}) && !empty($object->{$key})) {
            return $object->{$key};
        }

        if (is_array($object) && isset($object[$key]) && !empty($object[$key])) {
            return $object[$key];
        }

        return null;
    }

    static function limitString($str, $max)
    {
        return Polyfill::mb_substr($str, 0, $max);
    }

    static function formatDate($date)
    {
        if (function_exists("wp_date")) {
            return wp_date(get_option("date_format") . " " . get_option("time_format"), mysql2date("U", $date));
        }

        $timeZone = get_option("gmt_offset");
        $timeZone = !empty($timeZone) ? intval($timeZone) * 60 * 60 : 0;

        return date_i18n(get_option("date_format") . " " . get_option("time_format"), mysql2date("U", $date) + $timeZone);
    }

    static function cleanOutputBuffer()
    {
        if (ob_get_length() !== false && ob_get_length() > 0) {
            ob_clean();
        }
    }
}
?>
