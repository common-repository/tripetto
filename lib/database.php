<?php
namespace Tripetto;

class Database
{
    static function indexes($index)
    {
        return ",\n  KEY {$index} ({$index})";
    }

    static function assert($table, $fields, $indexes)
    {
        global $wpdb;

        $log = Database::option("db");
        $log = Helpers::stringToJSON($log);

        if (empty($log->{$table}) || version_compare($log->{$table}, $GLOBALS["TRIPETTO_PLUGIN_VERSION"]) < 0) {
            $log->{$table} = $GLOBALS["TRIPETTO_PLUGIN_VERSION"];

            if (empty(Database::option("db"))) {
                delete_option("tripetto_db");

                add_option("tripetto_db", Helpers::JSONToString($log), "", false);
            } else {
                update_option("tripetto_db", Helpers::JSONToString($log), false);
            }

            require_once ABSPATH . "wp-admin/includes/upgrade.php";

            $columns = implode(",\n  ", $fields);
            $keys = implode(array_map("Tripetto\Database::indexes", $indexes));
            $sql = "CREATE TABLE {$wpdb->prefix}{$table} (\n  id int(10) unsigned NOT NULL AUTO_INCREMENT,\n  {$columns},\n  PRIMARY KEY  (id){$keys}\n) {$wpdb->get_charset_collate()};";

            dbDelta($sql);

            if (!empty($wpdb->last_error)) {
                die(
                    "There was a problem activating Tripetto. Please report the following error to support@tripetto.com: " .
                        $wpdb->last_error
                );
            }
        }
    }

    static function option($name)
    {
        global $wpdb;

        $name = "tripetto_" . $name;
        $option = $wpdb->get_row($wpdb->prepare("SELECT option_value from {$wpdb->prefix}options where option_name=%s", $name));

        if (!empty($option) && !empty($option->option_value)) {
            return $option->option_value;
        }

        return get_option($name);
    }
}
?>
