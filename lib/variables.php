<?php
namespace Tripetto;

class Variables
{
    static function serverVariable($variable)
    {
        return isset($_SERVER[$variable]) && !empty($_SERVER[$variable]) ? strval($_SERVER[$variable]) : "";
    }

    static function userVariable($variable)
    {
        $user = wp_get_current_user();

        return !empty($user) && isset($user->{$variable}) && !empty($user->{$variable}) ? strval($user->{$variable}) : "";
    }

    static function websiteVariable($variable)
    {
        $data = get_bloginfo($variable);

        return !empty($data) ? strval($data) : "";
    }

    static function store()
    {
        $variables = Helpers::createJSON();

        $variables->{"user:login"} = __("User / Username", "tripetto");
        $variables->{"user:nicename"} = __("User / Username (URL sanitized)", "tripetto");
        $variables->{"user:nickname"} = __("User / Nickname", "tripetto");
        $variables->{"user:displayname"} = __("User / Display name", "tripetto");
        $variables->{"user:firstname"} = __("User / First name", "tripetto");
        $variables->{"user:lastname"} = __("User / Last name", "tripetto");
        $variables->{"user:email"} = __("User / Email address", "tripetto");
        $variables->{"user:website"} = __("User / Website", "tripetto");
        $variables->{"user:bio"} = __("User / Biographical info", "tripetto");
        $variables->{"user:avatar"} = __("User / Avatar (URL)", "tripetto");
        $variables->{"user:language"} = __("User / Language", "tripetto");
        $variables->{"user:id"} = __("User / ID", "tripetto");

        $variables->{"website:url"} = __("Website / URL");
        $variables->{"website:title"} = __("Website / Title", "tripetto");
        $variables->{"website:tagline"} = __("Website / Tagline", "tripetto");
        $variables->{"website:email"} = __("Website / Administration email address", "tripetto");
        $variables->{"website:language"} = __("Website / Language", "tripetto");

        $variables->{"visitor:ip"} = __("Visitor / IP address", "tripetto");
        $variables->{"visitor:language"} = __("Visitor / Language", "tripetto");
        $variables->{"visitor:referer"} = __("Visitor / Referrer URL", "tripetto");

        $variables->{"server:url"} = __("Server / URL", "tripetto");
        $variables->{"server:protocol"} = __("Server / Protocol", "tripetto");
        $variables->{"server:port"} = __("Server / Port", "tripetto");
        $variables->{"server:host"} = __("Server / Host name", "tripetto");
        $variables->{"server:path"} = __("Server / Path", "tripetto");
        $variables->{"server:querystring"} = __("Server / Querystring");
        $variables->{"server:ip"} = __("Server / IP address", "tripetto");
        $variables->{"server:software"} = __("Server / Software", "tripetto");
        $variables->{"server:wordpress"} = __("Server / WordPress version", "tripetto");
        $variables->{"server:php"} = __("Server / PHP version", "tripetto");

        return $variables;
    }

    static function get($variable)
    {
        global $wp_version;

        switch ($variable) {
            case "user:login":
                return Variables::userVariable("user_login");
            case "user:nicename":
                return Variables::userVariable("user_nicename");
            case "user:nickname":
                return Variables::userVariable("nickname");
            case "user:firstname":
                return Variables::userVariable("user_firstname");
            case "user:lastname":
                return Variables::userVariable("user_lastname");
            case "user:displayname":
                return Variables::userVariable("display_name");
            case "user:email":
                return Variables::userVariable("user_email");
            case "user:bio":
                $id = get_current_user_id();
                return !empty($id) ? get_the_author_meta("description", $id) : "";
            case "user:website":
                $id = get_current_user_id();
                return !empty($id) ? get_the_author_meta("user_url", $id) : "";
            case "user:avatar":
                $id = get_current_user_id();

                if (!empty($id)) {
                    $avatar = get_avatar($id);

                    if (!empty($avatar)) {
                        $start = strpos($avatar, " src='");

                        if ($start !== false) {
                            $end = strpos($avatar, "'", $start + 6);

                            if ($end !== false) {
                                $avatar = substr($avatar, $start + 6, $end - $start - 6);

                                if (filter_var($avatar, FILTER_VALIDATE_URL)) {
                                    return $avatar;
                                }
                            }
                        }
                    }
                }

                return "";
            case "user:language":
                $id = get_current_user_id();
                return !empty($id) ? get_user_locale($id) : "";
            case "user:id":
                $id = get_current_user_id();
                return !empty($id) ? strval($id) : "";

            case "website:url":
                return home_url();
            case "website:title":
                return Variables::websiteVariable("name");
            case "website:tagline":
                return Variables::websiteVariable("description");
            case "website:email":
                return Variables::websiteVariable("admin_email");
            case "website:language":
                return Variables::websiteVariable("language");

            case "visitor:ip":
                return Runner::getIP();
            case "visitor:language":
                $language = Variables::serverVariable("HTTP_ACCEPT_LANGUAGE");
                $separator = strpos($language, ",");

                return $separator !== false ? substr($language, 0, $separator) : $language;
            case "visitor:referer":
                return Variables::serverVariable("HTTP_REFERER");

            case "server:url":
                return home_url() . Variables::serverVariable("REQUEST_URI");
            case "server:protocol":
                return stripos(Variables::serverVariable("SERVER_PROTOCOL"), "https") === 0 ? "https://" : "http://";
            case "server:port":
                return Variables::serverVariable("SERVER_PORT");
            case "server:host":
                return Variables::serverVariable("SERVER_NAME");
            case "server:path":
                $path = Variables::serverVariable("REQUEST_URI");
                $querystring = strpos($path, "?");

                return $querystring !== false ? substr($path, 0, $querystring) : $path;
            case "server:querystring":
                $path = Variables::serverVariable("REQUEST_URI");
                $querystring = strpos($path, "?");

                return $querystring !== false ? substr($path, $querystring) : "";
                break;
            case "server:ip":
                return Variables::serverVariable("SERVER_ADDR");
            case "server:software":
                return Variables::serverVariable("SERVER_SOFTWARE");
            case "server:wordpress":
                return !empty($wp_version) ? $wp_version : "";
            case "server:php":
                return !empty(PHP_VERSION) ? PHP_VERSION : "";
        }

        return "";
    }

    static function inventory()
    {
        $variables = Helpers::createJSON();

        foreach (Variables::store() as $variable => $description) {
            $variables->{$variable} = Helpers::createJSON();
            $variables->{$variable}->description = $description;
            $variables->{$variable}->value = Variables::get($variable);
        }

        $variables = Helpers::JSONToString($variables);

        return !empty($variables) && $variables !== "{}" ? base64_encode($variables) : "";
    }

    static function filter($definition)
    {
        $values = Helpers::createJSON();

        foreach (Variables::store() as $variable => $description) {
            if (strpos($definition, "custom-variable:" . $variable) !== false) {
                $values->{$variable} = Variables::get($variable);
            }
        }

        $values = Helpers::JSONToString($values);

        return !empty($values) && $values !== "{}" ? base64_encode($values) : "";
    }
}
?>
