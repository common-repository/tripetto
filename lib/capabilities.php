<?php
namespace Tripetto;

class Capabilities
{
    static function get()
    {
        return [
            "tripetto_create_forms",
            "tripetto_edit_forms",
            "tripetto_run_forms",
            "tripetto_delete_forms",
            "tripetto_view_results",
            "tripetto_export_results",
            "tripetto_delete_results",
        ];
    }

    static function install($version)
    {
        if (
            is_admin() &&
            (empty($version) || version_compare($version, "3.5.1") <= 0) &&
            function_exists("get_editable_roles") &&
            isset($GLOBALS["wp_roles"]) &&
            isset($GLOBALS["wp_roles"]->role_objects)
        ) {
            $roles = get_editable_roles();
            $capabilities = Capabilities::get();

            foreach ($GLOBALS["wp_roles"]->role_objects as $key => $role) {
                if (isset($roles[$key]) && $key === "editor") {
                    foreach ($capabilities as $capability) {
                        if (!$role->has_cap($capability)) {
                            $role->add_cap($capability);
                        }
                    }

                    break;
                }
            }
        }
    }

    static function uninstall()
    {
        if (isset($GLOBALS["wp_roles"]) && isset($GLOBALS["wp_roles"]->role_objects)) {
            $capabilities = Capabilities::get();

            foreach ($GLOBALS["wp_roles"]->role_objects as $key => $role) {
                foreach ($capabilities as $capability) {
                    if ($role->has_cap($capability)) {
                        $role->remove_cap($capability);
                    }
                }
            }
        }
    }

    static function key($capabilities = ["tripetto_edit_forms", "tripetto_run_forms", "tripetto_view_results"])
    {
        if (is_string($capabilities)) {
            $capabilities = [$capabilities];
        }

        foreach ($capabilities as $capability) {
            if (current_user_can($capability) && ($capability !== "tripetto_create_forms" || current_user_can("tripetto_edit_forms"))) {
                return $capability;
            }
        }

        if (Capabilities::isAdministrator()) {
            return "manage_options";
        }

        return "tripetto";
    }

    static function isAdministrator()
    {
        return is_super_admin() || in_array("administrator", wp_get_current_user()->roles);
    }

    static function activatePlugins()
    {
        return current_user_can("activate_plugins");
    }

    static function createForms()
    {
        return Capabilities::editForms() && (Capabilities::isAdministrator() || current_user_can("tripetto_create_forms"));
    }

    static function editForms()
    {
        return Capabilities::isAdministrator() || current_user_can("tripetto_edit_forms");
    }

    static function deleteForms()
    {
        return Capabilities::isAdministrator() || current_user_can("tripetto_delete_forms");
    }

    static function runForms()
    {
        return Capabilities::isAdministrator() || current_user_can("tripetto_run_forms");
    }

    static function viewResults()
    {
        return Capabilities::isAdministrator() || current_user_can("tripetto_view_results");
    }

    static function exportResults()
    {
        return Capabilities::viewResults() && (Capabilities::isAdministrator() || current_user_can("tripetto_export_results"));
    }

    static function deleteResults()
    {
        return Capabilities::viewResults() && (Capabilities::isAdministrator() || current_user_can("tripetto_delete_results"));
    }
}
?>
