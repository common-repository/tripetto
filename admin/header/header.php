<?php
namespace Tripetto;

class Header
{
    static function generate($title, $back = null, $count = null, $left = null, $right = null)
    {
        $header = Helpers::createJSON();

        $header->title = $title;

        if (is_string($back) && !empty($back)) {
            $header->back = $back;
        }

        if (is_numeric($count)) {
            $header->count = intval($count);
        }

        if (is_string($left) && !empty($left)) {
            $header->left = $left;
        }

        if (is_string($right) && !empty($right)) {
            $header->right = $right;
        }

        if (tripetto_fs()->is_not_paying()) {
            $now = strtotime("now");

            $header->notification = Helpers::createJSON();
            $header->notification->title = __("You have the Free version.", "tripetto");
            $header->notification->description = __("Tripetto Pro includes even more features.", "tripetto");

            if ($now >= strtotime("21-11-22") && $now < strtotime("21-11-30")) {
                $header->notification->description = __("Check out our Black Friday offer for upgrading to Tripetto Pro.", "tripetto");
                $header->notification->color = "#FA3A69";
            }
        }

        wp_add_inline_script("wp-tripetto", "WPTripetto.header(" . Helpers::JSONToString($header) . ");");
    }
}
?>
