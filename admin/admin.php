<?php
namespace Tripetto;

require_once __DIR__ . "/header/header.php";
require_once __DIR__ . "/dashboard/dashboard.php";
require_once __DIR__ . "/forms/forms.php";
require_once __DIR__ . "/builder/builder.php";
require_once __DIR__ . "/templates/templates.php";
require_once __DIR__ . "/results/results.php";
require_once __DIR__ . "/onboarding/onboarding.php";
require_once __DIR__ . "/settings/settings.php";

final class Tripetto
{
    static function scripts()
    {
        $plugin_url = Helpers::pluginUrl();

        wp_register_style("wp-tripetto", $plugin_url . "/css/wp-tripetto.css", [], $GLOBALS["TRIPETTO_PLUGIN_VERSION"]);
        wp_register_script(
            "wp-tripetto",
            $plugin_url . "/js/wp-tripetto.js",
            ["vendor-tripetto-builder"],
            $GLOBALS["TRIPETTO_PLUGIN_VERSION"],
            true
        );
    }

    static function assert($capabilities)
    {
        if (is_admin() && is_user_logged_in()) {
            if (is_string($capabilities)) {
                $capabilities = [$capabilities];
            }

            foreach ($capabilities as $capability) {
                switch ($capability) {
                    case "create-forms":
                        if (Capabilities::createForms()) {
                            return true;
                        }
                        break;
                    case "edit-forms":
                        if (Capabilities::editForms()) {
                            return true;
                        }
                        break;
                    case "delete-forms":
                        if (Capabilities::deleteForms()) {
                            return true;
                        }
                        break;
                    case "run-forms":
                        if (Capabilities::runForms()) {
                            return true;
                        }
                        break;
                    case "view-results":
                        if (Capabilities::viewResults()) {
                            return true;
                        }
                        break;
                    case "export-results":
                        if (Capabilities::exportResults()) {
                            return true;
                        }
                        break;
                    case "delete-results":
                        if (Capabilities::deleteResults()) {
                            return true;
                        }
                        break;
                }
            }
        }

        wp_die("⛔ " . __("You cannot access this page. You are not authorized.", "tripetto"));

        return false;
    }

    static function menu()
    {
        add_menu_page(
            __("Tripetto", "tripetto"),
            __("Tripetto", "tripetto"),
            Capabilities::key(),
            "tripetto",
            ["Tripetto\Dashboard", "page"],
            "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyB3aWR0aD0iNTAwIiBoZWlnaHQ9IjUwMCIgdmVyc2lvbj0iMS4xIiB2aWV3Qm94PSIwIDAgNTAwIDUwMCIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczpjYz0iaHR0cDovL2NyZWF0aXZlY29tbW9ucy5vcmcvbnMjIiB4bWxuczpkYz0iaHR0cDovL3B1cmwub3JnL2RjL2VsZW1lbnRzLzEuMS8iIHhtbG5zOnJkZj0iaHR0cDovL3d3dy53My5vcmcvMTk5OS8wMi8yMi1yZGYtc3ludGF4LW5zIyI+PG1ldGFkYXRhPjxyZGY6UkRGPjxjYzpXb3JrIHJkZjphYm91dD0iIj48ZGM6Zm9ybWF0PmltYWdlL3N2Zyt4bWw8L2RjOmZvcm1hdD48ZGM6dHlwZSByZGY6cmVzb3VyY2U9Imh0dHA6Ly9wdXJsLm9yZy9kYy9kY21pdHlwZS9TdGlsbEltYWdlIi8+PGRjOnRpdGxlLz48L2NjOldvcms+PC9yZGY6UkRGPjwvbWV0YWRhdGE+PGRlZnM+PGNsaXBQYXRoIGlkPSJjbGlwUGF0aDI4Ij48cGF0aCBkPSJtMCA2MTJoNzkydi02MTJoLTc5MnoiLz48L2NsaXBQYXRoPjwvZGVmcz48ZyB0cmFuc2Zvcm09Im1hdHJpeCgxLjMzMzMgMCAwIC0xLjMzMzMgLTI5Mi4zMyA5NzEuODIpIj48ZyB0cmFuc2Zvcm09Im1hdHJpeCgyLjE3NDggMCAwIDIuMTYyOCAtNC40ODY5IC0xMjAuNDUpIj48ZyBjbGlwLXBhdGg9InVybCgjY2xpcFBhdGgyOCkiPjxnIHRyYW5zZm9ybT0idHJhbnNsYXRlKDIzMC44IDM0NC41MykiPjxwYXRoIGQ9Im0wIDAgNDEuNzEyIDI0LjA4My0yMC44NTYgMTIuMDQxLTIwLjg1NyAxMi4wNDF6IiBmaWxsPSIjYTBhNWFhIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiLz48L2c+PGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTg5LjA5IDMyMC40NSkiPjxwYXRoIGQ9Ik0gMCwwIDQxLjcxMywyNC4wODMgMCw0OC4xNjYgWiIgZmlsbD0iI2EwYTVhYSIgZmlsbC1ydWxlPSJldmVub2RkIi8+PC9nPjxnIHRyYW5zZm9ybT0idHJhbnNsYXRlKDIwOS45NCAzNTYuNTcpIj48cGF0aCBkPSJtMCAwIDIwLjg1Ni0xMi4wNDItMWUtMyA0OC4xNjUtMjAuODU1LTEyLjA0MS0yMC44NTYtMTIuMDR6IiBmaWxsPSIjYTBhNWFhIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiLz48L2c+PGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTg5LjA5IDMyMC40NSkiPjxwYXRoIGQ9Im0wIDB2NDguMTY1bC00MS43MTEtMjQuMDgyeiIgZmlsbD0iI2EwYTVhYSIgZmlsbC1ydWxlPSJldmVub2RkIi8+PC9nPjxnIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE0Ny4zNyAzNDQuNTMpIj48cGF0aCBkPSJtMCAwIDQxLjcxMiAyNC4wODMtNDEuNzEzIDI0LjA4MnoiIGZpbGw9IiNhMGE1YWEiIGZpbGwtcnVsZT0iZXZlbm9kZCIvPjwvZz48ZyB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNDcuMzcgMzQ0LjUzKSI+PHBhdGggZD0ibTAgMC0xZS0zIDI1Ljc3N3YyMi4zODhsLTQxLjcxMi0yNC4wODJ6IiBmaWxsPSIjYTBhNWFhIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiLz48L2c+PGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMjc1LjMgMzYzLjgpIj48cGF0aCBkPSJtMCAwLTQxLjcxMi0yNC4wODMgNDEuNzEyLTI0LjA4M3YyNC4wODN6IiBmaWxsPSIjYTBhNWFhIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiLz48L2c+PGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMjc1LjMgMzE1LjY0KSI+PHBhdGggZD0ibTAgMC00MS43MTEgMjQuMDgzdi00OC4xNjVsMjAuODU1IDEyLjA0eiIgZmlsbD0iI2EwYTVhYSIgZmlsbC1ydWxlPSJldmVub2RkIi8+PC9nPjxnIHRyYW5zZm9ybT0idHJhbnNsYXRlKDIzMy41OSAzMTUuNjQpIj48cGF0aCBkPSJNIDAsMCBWIDI0LjA4MiBMIC00MS43MTMsMCAwLC0yNC4wODMgWiIgZmlsbD0iI2EwYTVhYSIgZmlsbC1ydWxlPSJldmVub2RkIi8+PC9nPjxnIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE5MS44OCAzMTUuNjQpIj48cGF0aCBkPSJtMCAwIDFlLTMgLTQ4LjE2NSA0MS43MTIgMjQuMDgzLTIwLjg1NyAxMi4wNDF6IiBmaWxsPSIjYTBhNWFhIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiLz48L2c+PGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMjEyLjczIDI3OS41MSkiPjxwYXRoIGQ9Im0wIDAtMjAuODU1LTEyLjA0MSA0MS43MTEtMjQuMDgzdjQ4LjE2NnoiIGZpbGw9IiNhMGE1YWEiIGZpbGwtcnVsZT0iZXZlbm9kZCIvPjwvZz48ZyB0cmFuc2Zvcm09InRyYW5zbGF0ZSgyMzMuNTkgMjQzLjM5KSI+PHBhdGggZD0ibTAgMC0yMi41ODEgMTMuMDM3LTE5LjEzIDExLjA0Ni0xZS0zIC00OC4xNjYgMjAuODU2IDEyLjA0MnoiIGZpbGw9IiNhMGE1YWEiIGZpbGwtcnVsZT0iZXZlbm9kZCIvPjwvZz48ZyB0cmFuc2Zvcm09InRyYW5zbGF0ZSgxNDQuNTkgMzM5LjczKSI+PHBhdGggZD0ibTAgMC00MS43MTMgMjQuMDgzdi00OC4xNjZ6IiBmaWxsPSIjYTBhNWFhIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiLz48L2c+PGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTQ0LjU5IDMzOS43MykiPjxwYXRoIGQ9Im0wIDAtNDEuNzEzLTI0LjA4MyAyMC44NTYtMTIuMDQyIDIwLjg1Ny0xMi4wNDF6IiBmaWxsPSIjYTBhNWFhIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiLz48L2c+PGcgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTQ0LjU5IDMzOS43MykiPjxwYXRoIGQ9Im0wIDAtMWUtMyAtMjQuMDgzIDFlLTMgLTI0LjA4MyA0MS43MTEgMjQuMDgzeiIgZmlsbD0iI2EwYTVhYSIgZmlsbC1ydWxlPSJldmVub2RkIi8+PC9nPjxnIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE2NS40NCAzMDMuNikiPjxwYXRoIGQ9Im0wIDAtMjAuODU2LTEyLjA0MSA0MS43MTMtMjQuMDgyLTFlLTMgNDguMTY0eiIgZmlsbD0iI2EwYTVhYSIgZmlsbC1ydWxlPSJldmVub2RkIi8+PC9nPjxnIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE4Ni4zIDIxOS4zMSkiPjxwYXRoIGQ9Im0wIDAgMWUtMyA0OC4xNjYtNDEuNzEzLTI0LjA4NCAyMC44NTYtMTIuMDQxeiIgZmlsbD0iI2EwYTVhYSIgZmlsbC1ydWxlPSJldmVub2RkIi8+PC9nPjxnIHRyYW5zZm9ybT0idHJhbnNsYXRlKDE0NC41OSAyNDMuNCkiPjxwYXRoIGQ9Ik0gMCwwIDE5LjUwNiwxMS4yNjMgNDEuNzEzLDI0LjA4NCAyMC44NTYsMzYuMTI0IDAsNDguMTY2IFYgMjQuMDgzIFoiIGZpbGw9IiNhMGE1YWEiIGZpbGwtcnVsZT0iZXZlbm9kZCIvPjwvZz48L2c+PC9nPjwvZz48L3N2Zz4K",
            /**
             * NOTE: Never change this (back) to 58, since that position is used
             * by the popular Elementor plugin. It will remove the Tripetto menu
             * from the admin menu when a user does not have the `manage_options`
             * capability.
             * @see https://wordpress.org/support/topic/add_menu_page-breaking-other-plugins-in-2-4-0/
             */
            57
        );

        Forms::menu();
    }

    static function register($plugin)
    {
        if (is_admin()) {
            add_action("admin_enqueue_scripts", ["Tripetto\Tripetto", "scripts"]);
            add_action("admin_menu", ["Tripetto\Tripetto", "menu"]);

            Forms::register($plugin);
            Settings::register($plugin);
            Onboarding::register($plugin);
        }
    }
}
?>
