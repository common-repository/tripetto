<?php
namespace Tripetto;

class Theme
{
    static function style($css, $rule, $property)
    {
        $rule = strpos($css, $rule);

        if ($rule !== false) {
            $begin = strpos($css, $property . ":", $rule);

            if ($begin !== false) {
                $a = strpos($css, ";", $begin);
                $b = strpos($css, "}", $begin);
                $end = $a !== false && $b !== false ? min($a, $b) : ($a !== false ? $a : $b);

                if ($end !== false) {
                    $length = strlen($property) + 1;

                    return substr($css, $begin + $length, $end - $begin - $length);
                }
            }
        }

        return "";
    }

    static function colors()
    {
        global $_wp_admin_css_colors;

        $colors = Helpers::createJSON();

        $colors->background = "#f1f1f1";
        $colors->primary = "#2271b1";
        $colors->secondary = "#1d2327";

        $theme = get_user_option("admin_color", get_current_user_id());

        if (!empty($theme)) {
            $theme_colors = $_wp_admin_css_colors[$theme];

            if (!empty($theme_colors) && isset($theme_colors->url) && !empty($theme_colors->url)) {
                $response = wp_safe_remote_get($theme_colors->url);

                if (is_array($response) && !is_wp_error($response) && isset($response["body"])) {
                    $css = strval($response["body"]);

                    if (!empty($css)) {
                        $css = str_replace(" ", "", $css);

                        $background = Theme::style($css, "body{", "background");

                        if (empty($background)) {
                            $background = Theme::style($css, "body{", "background-color");
                        }

                        $primary = Theme::style($css, ".button-primary{", "background");

                        if (empty($primary)) {
                            $primary = Theme::style($css, ".button-primary{", "background-color");
                        }

                        if (!empty($background)) {
                            $colors->background = $background;
                        }

                        if (!empty($primary)) {
                            $colors->primary = $primary;
                        }
                    }
                }
            }
        }

        return $colors;
    }
}
?>
