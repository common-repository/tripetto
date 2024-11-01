<?php
namespace Tripetto;

class Settings
{
    static function menu()
    {
        add_options_page("Tripetto", "Tripetto", "manage_options", "tripetto-settings", ["Tripetto\Settings", "page"]);
    }

    static function page()
    {
        echo '<div class="wrap">';
        echo "<h1>" . __("Tripetto Settings", "tripetto") . "</h1>";
        echo '<form action="options.php" method="post">';

        settings_fields("tripetto");
        do_settings_sections("tripetto-settings");
        submit_button();

        echo "</form>";
        echo "</div>";
    }

    static function options()
    {
        register_setting("tripetto", "tripetto_sender");
        register_setting("tripetto", "tripetto_sender_name");
        register_setting("tripetto", "tripetto_sender_address");
        register_setting("tripetto", "tripetto_spam_protection");
        register_setting("tripetto", "tripetto_spam_protection_allowlist");

        add_settings_section(
            "onboarding",
            "💡 " . __("Try the onboarding wizard", "tripetto"),
            ["Tripetto\Settings", "onboarding"],
            "tripetto-settings"
        );

        add_settings_section(
            "sender",
            "📧 " . __("Email settings", "tripetto"),
            ["Tripetto\Settings", "emailSection"],
            "tripetto-settings"
        );
        add_settings_field("tripetto_sender", __("Sender", "tripetto"), ["Tripetto\Settings", "sender"], "tripetto-settings", "sender");

        add_settings_field(
            "tripetto_sender_name",
            __("Sender name", "tripetto"),
            ["Tripetto\Settings", "senderName"],
            "tripetto-settings",
            "sender"
        );

        add_settings_field(
            "tripetto_sender_address",
            __("Sender email address", "tripetto"),
            ["Tripetto\Settings", "senderAddress"],
            "tripetto-settings",
            "sender"
        );

        add_settings_section("spam", "🛡️ " . __("SPAM protection", "tripetto"), ["Tripetto\Settings", "spamSection"], "tripetto-settings");

        add_settings_field(
            "tripetto_spam_protection",
            __("Spam protection mode", "tripetto"),
            ["Tripetto\Settings", "spam"],
            "tripetto-settings",
            "spam"
        );

        add_settings_field(
            "tripetto_spam_protection_allowlist",
            __("Allowlist", "tripetto"),
            ["Tripetto\Settings", "allowlist"],
            "tripetto-settings",
            "spam"
        );
    }

    static function onboarding()
    {
        echo "<p>" .
            __(
                "You can also use the Tripetto onboarding wizard to adjust settings. The onboarding wizard also lets you configure user access and capabilities so you can decide who has access and what they can do within Tripetto.",
                "tripetto"
            ) .
            "</p>";
        echo '<p><a href="admin.php?page=tripetto-onboarding" class="button button-secondary">🚀 ' .
            __("Start Onboarding Wizard", "tripetto") .
            "</a></p>";
    }

    static function emailSection()
    {
        echo "<p>" . __("These settings are used when Tripetto sends an email message.", "tripetto") . "</p>";
    }

    static function sender()
    {
        $sender = get_option("tripetto_sender");

        switch ($sender) {
            case "admin":
            case "custom":
                break;
            default:
                $sender = "default";
                break;
        }

        echo "<fieldset>";
        echo "<script>function tripettoSender(s) {";
        echo 'var tripettoSenderName=document.getElementById("tripetto_sender_name");';
        echo 'var tripettoSenderAddress=document.getElementById("tripetto_sender_address");';
        echo 'if (tripettoSenderName){tripettoSenderName.disabled=s.value!=="custom";}';
        echo 'if (tripettoSenderAddress){tripettoSenderAddress.disabled=s.value!=="custom";tripettoSenderAddress.required=s.value==="custom";}';
        echo "}</script>";
        echo '<legend class="screen-reader-text"><span>' . __("Sender", "tripetto") . "</span></legend>";
        echo '<label><input type="radio" name="tripetto_sender" onchange="tripettoSender(this);" value="default" ' .
            checked("default", $sender, false) .
            ">" .
            __("WordPress default sender", "tripetto") .
            "</label>";

        $name = get_bloginfo("name");
        $address = get_bloginfo("admin_email");

        if (!empty($address)) {
            echo '<br/><label><input type="radio" name="tripetto_sender" onchange="tripettoSender(this);" value="admin" ' .
                checked("admin", $sender, false) .
                ">";

            if (!empty($name)) {
                echo esc_html($name) . " &lt;" . esc_html($address) . "&gt;";
            } else {
                echo esc_html($address);
            }

            echo "</label>";
        }

        echo '<br/><label><input type="radio" name="tripetto_sender" onchange="tripettoSender(this);" value="custom" ' .
            checked("custom", $sender, false) .
            ">" .
            __("Custom sender", "tripetto") .
            "</label>";

        echo "</fieldset>";
    }

    static function senderName()
    {
        $mode = get_option("tripetto_sender");
        $name = get_option("tripetto_sender_name");

        if (empty($name)) {
            $name = get_bloginfo("name");
        }

        echo '<input name="tripetto_sender_name" id="tripetto_sender_name" type="text" value="' .
            esc_attr($name) .
            '" class="regular-text"' .
            ($mode != "custom" ? " disabled" : "") .
            ">";
    }

    static function senderAddress()
    {
        $mode = get_option("tripetto_sender");
        $address = get_option("tripetto_sender_address");

        if (empty($address)) {
            $address = get_bloginfo("admin_email");
        }

        echo '<input name="tripetto_sender_address" id="tripetto_sender_address" type="email" value="' .
            esc_attr($address) .
            '" class="regular-text"' .
            ($mode != "custom" ? " disabled" : " required") .
            ">";
    }

    static function spamSection()
    {
        echo "<p>" .
            __(
                "Tripetto has a unique approach for preventing spambots abusing your forms. This solution makes CAPTCHAs a thing of the past. We simply don't like those strange, inaccessible puzzles, and so we've engineered a solution where we could eliminate them once and for all. It works out-of-the-box and we strongly advise to only change the mode of operation if you have a good reason for it. You can choose between:",
                "tripetto"
            ) .
            "<br/><br/>- <b>" .
            __("Maximal", "tripetto") .
            "</b>: " .
            __("Maximum protection against spambots, but form submission might be a bit slower in certain conditions", "tripetto") .
            ";" .
            "<br/>- <b>" .
            __("Normal", "tripetto") .
            "</b>: " .
            __("The default protection mode provides good protection against spambots while maintaining optimal performance", "tripetto") .
            ";" .
            "<br/>- <b>" .
            __("Minimal", "tripetto") .
            "</b>: " .
            __("IP filtering will be disabled (use this mode if you expect lots of submission from the same IP address)", "tripetto") .
            ";" .
            "<br/>- <b>" .
            __("Off", "tripetto") .
            "</b>: " .
            __("Disables the spam protection (not recommended)", "tripetto") .
            "." .
            "</p>";
    }

    static function spam()
    {
        $sender = get_option("tripetto_spam_protection");

        switch ($sender) {
            case "maximal":
            case "minimal":
            case "off":
                break;
            default:
                $sender = "default";
                break;
        }

        echo "<fieldset>";
        echo "<script>function tripettoSpam(s) {";
        echo 'var tripettoAllowlist=document.getElementById("tripetto_spam_protection_allowlist");';
        echo 'if (tripettoAllowlist){tripettoAllowlist.disabled=s.value==="off";}';
        echo "}</script>";
        echo '<legend class="screen-reader-text"><span>' . __("Spam protection mode", "tripetto") . "</span></legend>";

        echo '<label><input type="radio" name="tripetto_spam_protection" onchange="tripettoSpam(this);" value="maximal" ' .
            checked("maximal", $sender, false) .
            ">" .
            __("Maximal", "tripetto") .
            "</label>";

        echo '<br/><label><input type="radio" name="tripetto_spam_protection" onchange="tripettoSpam(this);" value="default" ' .
            checked("default", $sender, false) .
            ">" .
            __("Normal", "tripetto") .
            "</label>";

        echo '<br/><label><input type="radio" name="tripetto_spam_protection" onchange="tripettoSpam(this);" value="minimal" ' .
            checked("minimal", $sender, false) .
            ">" .
            __("Minimal", "tripetto") .
            "</label>";

        echo '<br/><label><input type="radio" name="tripetto_spam_protection" onchange="tripettoSpam(this);" value="off" ' .
            checked("off", $sender, false) .
            ">" .
            __("Off", "tripetto") .
            "</label>";

        echo "</fieldset>";
    }

    static function allowlist()
    {
        $mode = get_option("tripetto_spam_protection");
        $allowlist = get_option("tripetto_spam_protection_allowlist");

        if (empty($allowlist)) {
            $allowlist = "";
        }

        echo '<input name="tripetto_spam_protection_allowlist" id="tripetto_spam_protection_allowlist" type="text" value="' .
            esc_attr($allowlist) .
            '" class="regular-text"' .
            ($mode == "off" ? " disabled" : "") .
            ">";
        echo '<p class="description">' .
            __(
                "Fill in the IP addresses (separated with a comma) you wish to exclude from the spam protection algorithm. Both IPv4 and IPv6 addresses are allowed.",
                "tripetto"
            ) .
            "</p>";
    }

    static function register($plugin)
    {
        if (is_admin()) {
            add_action("admin_menu", ["Tripetto\Settings", "menu"]);
            add_action("admin_init", ["Tripetto\Settings", "options"]);
        }
    }
}
?>
