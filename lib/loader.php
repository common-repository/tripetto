<?php
namespace Tripetto;

class Loader
{
    static function data($reference, $token)
    {
        if (Helpers::isValidSHA256($reference)) {
            global $wpdb;

            $form = $wpdb->get_row($wpdb->prepare("SELECT * from {$wpdb->prefix}tripetto_forms where reference=%s", $reference));

            if (!is_null($form) && Helpers::isValidJSON($form->definition)) {
                $response = Helpers::createJSON();
                $runner = "autoscroll";

                if (!empty($form->runner)) {
                    $runner = $form->runner;
                } elseif (!empty($form->collector)) {
                    switch ($form->collector) {
                        case "standard-bootstrap":
                            $runner = "classic";
                            break;
                        default:
                            $runner = "autoscroll";
                            break;
                    }
                }

                $response->definition = Migration::definition($form, Helpers::stringToJSON(Helpers::get($form, "definition")));
                $response->styles = Helpers::stringToJSON(Helpers::get($form, "styles"), Migration::styles($form, $runner));
                $response->l10n = Helpers::stringToJSON(Helpers::get($form, "l10n"), Migration::l10n($form));

                if (Helpers::isValidSHA256($token)) {
                    $snapshot = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT snapshot from {$wpdb->prefix}tripetto_snapshots where form_id=%d AND reference=%s",
                            $form->id,
                            $token
                        )
                    );

                    if (!is_null($snapshot) && !empty($snapshot->snapshot)) {
                        $response->snapshot = Helpers::stringToJSON($snapshot->snapshot);
                    }
                }

                header("Content-Type: application/json");

                echo Helpers::JSONToString($response);

                http_response_code(200);
            } else {
                http_response_code(404);
            }
        } else {
            http_response_code(400);
        }

        die();
    }

    static function load()
    {
        Loader::data(!empty($_POST["reference"]) ? $_POST["reference"] : "", !empty($_POST["token"]) ? $_POST["token"] : "");
    }

    static function process()
    {
        define("SHORTINIT", true);

        if (file_exists(stream_resolve_include_path($_SERVER["DOCUMENT_ROOT"] . "/wp-includes/l10n.php"))) {
            require_once $_SERVER["DOCUMENT_ROOT"] . "/wp-includes/l10n.php";
        } else {
            $root = dirname(dirname(dirname(dirname(__DIR__))));

            if (file_exists(stream_resolve_include_path($root . "/wp-includes/l10n.php"))) {
                require_once $root . "/wp-includes/l10n.php";
            }
        }

        if (file_exists(stream_resolve_include_path($_SERVER["DOCUMENT_ROOT"] . "/wp-load.php"))) {
            require_once $_SERVER["DOCUMENT_ROOT"] . "/wp-load.php";
        } else {
            $root = dirname(dirname(dirname(dirname(__DIR__))));

            if (file_exists(stream_resolve_include_path($root . "/wp-load.php"))) {
                require_once $root . "/wp-load.php";
            } else {
                http_response_code(303);

                die();

                return;
            }
        }

        require_once __DIR__ . "/helpers.php";
        require_once __DIR__ . "/migration.php";

        define("DOING_AJAX", true);

        ob_clean();
        send_nosniff_header();

        header("Cache-Control: no-store, max-age=0");
        header("Pragma: no-cache");

        Loader::data(!empty($_GET["reference"]) ? $_GET["reference"] : "", !empty($_GET["token"]) ? $_GET["token"] : "");
    }

    static function register($plugin)
    {
        add_action("wp_ajax_tripetto_load", ["Tripetto\Loader", "load"]);
        add_action("wp_ajax_nopriv_tripetto_load", ["Tripetto\Loader", "load"]);
    }
}
?>
