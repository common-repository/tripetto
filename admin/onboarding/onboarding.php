<?php
namespace Tripetto;

class Onboarding
{
    static function scripts()
    {
        wp_register_script(
            "tripetto-onboarding-core",
            Helpers::pluginUrl() . "/vendors/tripetto-runner.js",
            [],
            $GLOBALS["TRIPETTO_PLUGIN_VERSION"],
            false
        );

        wp_register_script(
            "tripetto-onboarding-runner",
            Helpers::pluginUrl() . "/vendors/tripetto-runner-autoscroll.js",
            ["tripetto-onboarding-core"],
            $GLOBALS["TRIPETTO_PLUGIN_VERSION"],
            false
        );
    }

    static function store()
    {
        return Helpers::stringToJSON(get_option("tripetto_onboarding"));
    }

    static function request($type = "")
    {
        $onboarding = Onboarding::store();
        $userId = get_current_user_id();

        if ($userId !== 0) {
            $key = empty($type) ? strval($userId) : $type;

            if (
                !isset($onboarding->{$key}) ||
                empty($onboarding->{$key}) ||
                version_compare($onboarding->{$key}, $GLOBALS["TRIPETTO_PLUGIN_VERSION"]) < 0
            ) {
                return true;
            }
        }

        return false;
    }

    static function type()
    {
        $onboarding = Onboarding::store();
        $userId = get_current_user_id();

        if ($userId !== 0) {
            if (
                (!isset($onboarding->version) || empty($onboarding->version)) &&
                (!isset($onboarding->{strval($userId)}) || empty($onboarding->{strval($userId)})) &&
                (current_user_can("manage_options") || current_user_can("edit_users"))
            ) {
                return "setup";
            }

            if (!isset($onboarding->{strval($userId)}) || empty($onboarding->{strval($userId)})) {
                if (
                    isset($onboarding->version) &&
                    !empty($onboarding->version) &&
                    version_compare($onboarding->version, $GLOBALS["TRIPETTO_PLUGIN_VERSION"]) < 0
                ) {
                    return version_compare($onboarding->version, "3.5.1") <= 0 ? "upgrade" : "update";
                }
            } elseif (version_compare($onboarding->{strval($userId)}, $GLOBALS["TRIPETTO_PLUGIN_VERSION"]) < 0) {
                return version_compare($onboarding->{strval($userId)}, "3.5.1") <= 0 ? "upgrade" : "update";
            }
        }

        return false;
    }

    static function update($didSettings = false, $didRoles = false)
    {
        $onboarding = Onboarding::store();
        $userId = get_current_user_id();

        if ($userId !== 0) {
            if (
                isset($onboarding->{strval($userId)}) &&
                !empty($onboarding->{strval($userId)}) &&
                version_compare($onboarding->{strval($userId)}, $GLOBALS["TRIPETTO_PLUGIN_VERSION"]) < 0
            ) {
                $onboarding->{"^" . strval($userId)} = $onboarding->{strval($userId)};
            } elseif (isset($onboarding->version) && !empty($onboarding->version) && version_compare($onboarding->version, "3.5.1") <= 0) {
                $onboarding->{"^" . strval($userId)} = $onboarding->version;
            }

            if (current_user_can("manage_options") || current_user_can("edit_users")) {
                $onboarding->version = $GLOBALS["TRIPETTO_PLUGIN_VERSION"];
            }

            $onboarding->{strval($userId)} = $GLOBALS["TRIPETTO_PLUGIN_VERSION"];

            if ($didSettings) {
                $onboarding->settings = $GLOBALS["TRIPETTO_PLUGIN_VERSION"];
            }

            if ($didRoles) {
                $onboarding->roles = $GLOBALS["TRIPETTO_PLUGIN_VERSION"];
            }

            if (empty(get_option("tripetto_onboarding"))) {
                delete_option("tripetto_onboarding");

                add_option("tripetto_onboarding", Helpers::JSONToString($onboarding), "", true);
            } else {
                update_option("tripetto_onboarding", Helpers::JSONToString($onboarding), true);
            }
        }
    }

    static function read($name)
    {
        $value = get_option("tripetto_" . $name);

        return empty($value) ? "" : strval($value);
    }

    static function write($name, $value)
    {
        $key = "tripetto_" . $name;

        if (empty($value)) {
            delete_option($key);
        } else {
            if (!is_string(get_option($key))) {
                delete_option($key);

                add_option($key, $value, "", true);
            } else {
                update_option($key, $value, true);
            }
        }
    }

    static function roles()
    {
        return ["editor", "author", "contributor", "subscriber"];
    }

    static function assert()
    {
        Tripetto::assert(["edit-forms", "run-forms", "view-results"]);

        if (Onboarding::request() && Onboarding::page()) {
            return true;
        }

        return false;
    }

    static function colors($styles)
    {
        $colors = Theme::colors();

        $styles = str_replace("{{background}}", $colors->background, $styles);
        $styles = str_replace("{{primary}}", $colors->primary, $styles);
        $styles = str_replace("{{secondary}}", $colors->secondary, $styles);

        return $styles;
    }

    static function page()
    {
        Tripetto::assert(["edit-forms", "run-forms", "view-results"]);

        $definition = @file_get_contents(__DIR__ . "/definition.json");
        $l10n = @file_get_contents(__DIR__ . "/l10n.json");
        $styles = @file_get_contents(__DIR__ . "/styles.json");

        if (!empty($definition) && !empty($l10n) && !empty($styles)) {
            $dataset = Helpers::createJSON();

            switch (Onboarding::type()) {
                case "setup":
                    $dataset->type = "setup";
                    break;
                case "update":
                    $dataset->type = "update";
                    break;
                case "upgrade":
                    $dataset->type = "upgrade";
                    break;
                default:
                    $dataset->type = "";
                    break;
            }

            if ($dataset->type !== "setup") {
                Onboarding::update();
            }

            $dataset->version = $GLOBALS["TRIPETTO_PLUGIN_VERSION"];
            $dataset->plan = License::isInProMode() ? "Pro" : "Free";
            $dataset->ip_address = Runner::getIP();
            $dataset->has_legacy_features = !License::isInProMode() && License::hasLegacyFeatures();
            $dataset->has_free_premium = !License::isInProMode() && !empty(get_option("tripetto_free_premium")) ? true : false;
            $dataset->allow_settings =
                current_user_can("manage_options") && (empty($dataset->type) || Onboarding::request("settings")) ? true : false;
            $dataset->allow_roles =
                current_user_can("edit_users") && (empty($dataset->type) || Onboarding::request("roles")) ? true : false;
            $dataset->changelog = Onboarding::changelog();
            $dataset->dashboard_url = admin_url("/admin.php?page=tripetto", "admin");

            if ($dataset->allow_roles) {
                $roles = Onboarding::roles();

                if ($dataset->type !== "setup") {
                    $dataset->access = "default";
                }

                foreach ($roles as $role_name) {
                    $role = wp_roles()->get_role($role_name);
                    $hasAccess = false;
                    $hasAllCapabilities = true;
                    $capabilities = Capabilities::get();

                    foreach ($capabilities as $capability) {
                        $hasCap = isset($role) && $role->has_cap($capability);

                        $dataset->{"role_" . $role_name . " / " . $capability} = $hasCap;

                        if ($hasCap) {
                            $hasAccess = true;
                        } else {
                            $hasAllCapabilities = false;
                        }
                    }

                    $dataset->{"role_" . $role_name . "_available"} = isset($role);
                    $dataset->{"role_" . $role_name} = $hasAccess;
                    $dataset->{"role_" . $role_name . " / capabilities"} = $hasAccess && !$hasAllCapabilities ? "custom" : "all";

                    if (
                        $dataset->type !== "setup" &&
                        (($role_name === "editor" && (!$hasAccess || !$hasAllCapabilities)) || ($role_name !== "editor" && $hasAccess))
                    ) {
                        $dataset->access = "custom";
                    }
                }
            }

            if ($dataset->allow_settings) {
                $sender = Onboarding::read("sender");

                switch ($sender) {
                    case "admin":
                    case "custom":
                        $dataset->sender = $sender;
                        break;
                    default:
                        $dataset->sender = "default";
                        break;
                }

                $dataset->sender_name = $sender === "custom" ? Onboarding::read("sender_name") : "";
                $dataset->sender_address = $sender === "custom" ? Onboarding::read("sender_address") : "";

                $dataset->sender_admin = "Site administrator address";
                $address = get_bloginfo("admin_email");

                if (!empty($address)) {
                    $name = get_bloginfo("name");

                    if (!empty($name)) {
                        $dataset->sender_admin = $name . "<" . $address . ">";
                    } else {
                        $dataset->sender_admin = $address;
                    }
                }

                $mode = Onboarding::read("spam_protection");

                switch ($mode) {
                    case "maximal":
                    case "minimal":
                    case "off":
                        $dataset->spam_protection_mode = $mode;
                        break;
                    default:
                        $dataset->spam_protection_mode = "default";
                        break;
                }

                $dataset->spam_protection_allowlist = Onboarding::read("spam_protection_allowlist");

                if ($dataset->type !== "setup") {
                    $dataset->spam_protection =
                        $dataset->spam_protection_mode === "default" && empty($dataset->spam_protection_allowlist) ? "default" : "custom";
                }
            }

            wp_enqueue_script("tripetto-onboarding-core");
            wp_enqueue_script("tripetto-onboarding-runner");
            wp_enqueue_style("wp-tripetto");
            wp_enqueue_script("wp-tripetto");

            $definition = Helpers::JSONToString(Helpers::stringToJSON($definition));
            $l10n = Helpers::JSONToString(Helpers::stringToJSON($l10n));
            $styles = Helpers::JSONToString(Helpers::stringToJSON(Onboarding::colors($styles)));
            $dataset = Helpers::JSONToString($dataset);
            $nonce = wp_create_nonce("tripetto:onboarding");

            wp_add_inline_script("wp-tripetto", "WPTripetto.onboarding($definition,$l10n,$styles,$dataset,ajaxurl,\"$nonce\");");

            return true;
        }

        return false;
    }

    static function complete()
    {
        if (is_admin() && is_user_logged_in()) {
            $nonce = !empty($_POST["nonce"]) ? $_POST["nonce"] : "";
            $dataset = !empty($_POST["dataset"]) ? wp_unslash($_POST["dataset"]) : "";

            if (!empty($nonce) && wp_verify_nonce($nonce, "tripetto:onboarding")) {
                $dataset = Helpers::stringToJSON($dataset);

                if (isset($dataset->version) && $dataset->version === $GLOBALS["TRIPETTO_PLUGIN_VERSION"]) {
                    $updateSettings = false;
                    $updateRoles = false;

                    if (isset($dataset->configure) && is_bool($dataset->configure) && $dataset->configure) {
                        if (current_user_can("manage_options")) {
                            $updateSettings = true;

                            switch (isset($dataset->sender) ? $dataset->sender : "") {
                                case "admin":
                                    Onboarding::write("sender", "admin");
                                    break;
                                case "custom":
                                    Onboarding::write("sender", "custom");
                                    Onboarding::write(
                                        "sender_name",
                                        isset($dataset->sender_name) && is_string($dataset->sender_name) ? $dataset->sender_name : ""
                                    );
                                    Onboarding::write(
                                        "sender_address",
                                        isset($dataset->sender_address) && is_string($dataset->sender_address)
                                            ? $dataset->sender_address
                                            : ""
                                    );
                                    break;
                                default:
                                    Onboarding::write("sender", "");
                                    break;
                            }

                            switch (isset($dataset->spam_protection) ? $dataset->spam_protection : "") {
                                case "custom":
                                    Onboarding::write(
                                        "spam_protection",
                                        isset($dataset->spam_protection_mode)
                                            ? ($dataset->spam_protection_mode === "maximal" ||
                                            $dataset->spam_protection_mode === "minimal" ||
                                            $dataset->spam_protection_mode === "off"
                                                ? $dataset->spam_protection_mode
                                                : "")
                                            : ""
                                    );
                                    Onboarding::write(
                                        "spam_protection_allowlist",
                                        isset($dataset->spam_protection_allowlist) && is_string($dataset->spam_protection_allowlist)
                                            ? $dataset->spam_protection_allowlist
                                            : ""
                                    );
                                    break;
                                default:
                                    Onboarding::write("spam_protection", "");
                                    Onboarding::write("spam_protection_allowlist", "");
                                    break;
                            }
                        }

                        if (current_user_can("edit_users")) {
                            $roles = Onboarding::roles();
                            $updateRoles = true;

                            foreach ($roles as $role_name) {
                                $role = wp_roles()->get_role($role_name);

                                if (isset($role)) {
                                    $capabilities = Capabilities::get();

                                    foreach ($capabilities as $capability) {
                                        if (isset($dataset->access) && $dataset->access === "custom") {
                                            if (
                                                isset($dataset->{"role_" . $role_name}) &&
                                                is_bool($dataset->{"role_" . $role_name}) &&
                                                $dataset->{"role_" . $role_name}
                                            ) {
                                                if (
                                                    isset($dataset->{"role_" . $role_name . " / capabilities"}) &&
                                                    $dataset->{"role_" . $role_name . " / capabilities"} == "all"
                                                ) {
                                                    $dataset->{"role_" . $role_name . " / " . $capability} = true;
                                                } elseif (
                                                    !isset($dataset->{"role_" . $role_name . " / " . $capability}) ||
                                                    !is_bool($dataset->{"role_" . $role_name . " / " . $capability})
                                                ) {
                                                    $dataset->{"role_" . $role_name . " / " . $capability} = false;
                                                }
                                            } else {
                                                $dataset->{"role_" . $role_name . " / " . $capability} = false;
                                            }
                                        } else {
                                            $dataset->{"role_" . $role_name . " / " . $capability} = $role_name === "editor";
                                        }

                                        $hasCap = $role->has_cap($capability);

                                        if ($dataset->{"role_" . $role_name . " / " . $capability} !== $hasCap) {
                                            if (!$hasCap) {
                                                $role->add_cap($capability);
                                            } else {
                                                $role->remove_cap($capability);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    Onboarding::update($updateSettings, $updateRoles);

                    switch (isset($dataset->action) ? $dataset->action : "") {
                        case "create_form":
                            echo admin_url("/admin.php?page=tripetto-create", "admin");
                            break;
                        default:
                            echo admin_url("/admin.php?page=tripetto", "admin");
                            break;
                    }

                    http_response_code(200);

                    return die();
                }
            }
        }

        http_response_code(400);

        die();
    }

    static function changelog()
    {
        $changelog = @file_get_contents(dirname(dirname(__DIR__)) . "/readme.txt");

        if (!empty($changelog)) {
            $from = Polyfill::mb_strpos($changelog, "**VERSION " . $GLOBALS["TRIPETTO_PLUGIN_VERSION"] . " ");

            if ($from === false) {
                $from = Polyfill::mb_strpos($changelog, "**VERSION ");
            }

            if ($from !== false) {
                $from = Polyfill::mb_strpos($changelog, "\n", $from);

                if ($from !== false) {
                    $onboarding = Onboarding::store();
                    $userId = get_current_user_id();
                    $to = false;
                    $from++;

                    if ($userId !== 0) {
                        $key = strval($userId);

                        if (
                            isset($onboarding->{$key}) &&
                            !empty($onboarding->{$key}) &&
                            version_compare($onboarding->{$key}, $GLOBALS["TRIPETTO_PLUGIN_VERSION"]) < 0
                        ) {
                            $to = Polyfill::mb_strpos($changelog, "**VERSION " . $onboarding->{$key} . " ", $from);
                        } else {
                            $key = "^" . strval($userId);

                            if (
                                isset($onboarding->{$key}) &&
                                !empty($onboarding->{$key}) &&
                                version_compare($onboarding->{$key}, $GLOBALS["TRIPETTO_PLUGIN_VERSION"]) < 0
                            ) {
                                $to = Polyfill::mb_strpos($changelog, "**VERSION " . $onboarding->{$key} . " ", $from);
                            }
                        }
                    }

                    if ($to === false) {
                        $to = Polyfill::mb_strpos($changelog, "**VERSION ", $from);

                        if ($to === false) {
                            $to = Polyfill::mb_strlen($changelog) - 1;
                        }
                    }

                    $section = Polyfill::mb_substr($changelog, $from, $to - $from) . "\n";
                    $log = "";

                    foreach (["✔️", "⚡", "🐛", "❌"] as $typeOfChange) {
                        $offset = Polyfill::mb_strpos($section, $typeOfChange . " ");
                        $changes = "";

                        while ($offset !== false) {
                            $offset += 2;
                            $end = Polyfill::mb_strpos($section, "\n", $offset);

                            if ($end !== false) {
                                $change = Polyfill::mb_substr($section, $offset, $end - $offset);

                                if (!empty($change)) {
                                    $changes .= "\n• " . $change;
                                }

                                $offset = $end + 1;
                            }

                            $offset = Polyfill::mb_strpos($section, $typeOfChange . " ", $offset);
                        }

                        if (!empty($changes)) {
                            $log .= ($log === "" ? "" : "\n\n") . $typeOfChange . " ";

                            switch ($typeOfChange) {
                                case "✔️":
                                    $log .= strtoupper(__("New features", "tripetto"));
                                    break;
                                case "⚡":
                                    $log .= strtoupper(__("Improvements", "tripetto"));
                                    break;
                                case "🐛":
                                    $log .= strtoupper(__("Bugfixes", "tripetto"));
                                    break;
                                case "❌":
                                    $log .= strtoupper(__("Deprecated or removed features", "tripetto"));
                                    break;
                            }

                            $log .= $changes;
                        }
                    }

                    return $log;
                }
            }
        }

        return "";
    }

    static function register($plugin)
    {
        add_action("admin_enqueue_scripts", ["Tripetto\Onboarding", "scripts"]);
        add_action("wp_ajax_tripetto_onboarding", ["Tripetto\Onboarding", "complete"]);
    }
}
?>
