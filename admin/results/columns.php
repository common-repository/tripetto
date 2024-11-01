<?php
namespace Tripetto;

class Columns
{
    static function edit($id)
    {
        Tripetto::assert("view-results");

        if (!empty($id)) {
            global $wpdb;

            $form = $wpdb->get_row($wpdb->prepare("SELECT id,name,settings from {$wpdb->prefix}tripetto_forms where id=%d", intval($id)));

            if (!is_null($form)) {
                echo '<div class="wrap">';

                $entry = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT entry from {$wpdb->prefix}tripetto_entries where form_id=%d ORDER BY created DESC",
                        intval($form->id)
                    )
                );

                if (!is_null($entry) && Helpers::isValidJSON($entry->entry)) {
                    $succeeded = false;
                    $settings = Helpers::stringToJSON(Helpers::get($form, "settings"));
                    $source = Helpers::stringToJSON(Helpers::get($entry, "entry"));
                    $fields = isset($source->fields) && is_array($source->fields) ? $source->fields : [];
                    $nonce = !empty($_POST["nonce"]) ? $_POST["nonce"] : "";

                    if (!isset($settings->columns) || !is_array($settings->columns) || count($settings->columns) === 0) {
                        $settings->columns = ["index", "reference", "created"];
                    }

                    if (!empty($nonce) && wp_verify_nonce($nonce, "columns-" . strval($form->id))) {
                        $settings->columns = [];

                        if (!empty($_POST["index"])) {
                            array_push($settings->columns, "index");
                        }

                        if (!empty($_POST["reference"])) {
                            array_push($settings->columns, "reference");
                        }

                        if (!empty($_POST["created"])) {
                            array_push($settings->columns, "created");
                        }

                        foreach ($fields as $field) {
                            if (!empty($_POST[$field->key])) {
                                array_push($settings->columns, $field->key);
                            }
                        }

                        if (count($settings->columns) > 0) {
                            $wpdb->query(
                                $wpdb->prepare(
                                    "UPDATE {$wpdb->prefix}tripetto_forms SET settings=%s WHERE id=%d",
                                    Helpers::JSONToString($settings),
                                    strval($form->id)
                                )
                            );

                            echo "<br />⌛ <strong>" . __("One moment please...", "tripetto") . "</strong></div>";
                            echo "<script>window.location='?page=tripetto-forms&action=results&id=" .
                                strval($form->id) .
                                "&no-cache=" .
                                $nonce .
                                "';</script>";

                            return;
                        }
                    }

                    echo __("Select the columns you want to show in your results list and click 'Save'.", "tripetto");

                    if (count($settings->columns) === 0) {
                        echo '<div style="color: red; margin-top: 12px;">⚠️ <b>' .
                            __("You need to select at least one field!", "tripetto") .
                            "</b></div>";
                    }

                    echo '<form method="post" style="margin-top: 16px;">';
                    echo '<input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce("columns-" . strval($form->id))) . '" />';

                    echo '<div><input type="checkbox"' .
                        (array_search("index", $settings->columns) !== false ? ' checked="checked"' : "") .
                        ' id="_index" name="index" /><label for="_index" style="margin-left: 2px; position: relative; top: -2px;"><b>' .
                        __("# Number", "tripetto") .
                        "</b></label></div>";

                    echo '<div><input type="checkbox"' .
                        (array_search("reference", $settings->columns) !== false ? ' checked="checked"' : "") .
                        ' id="_reference" name="reference" /><label for="_reference" style="margin-left: 2px; position: relative; top: -2px;"><b>' .
                        __("Identifier", "tripetto") .
                        "</b></label></div>";

                    echo '<div><input type="checkbox"' .
                        (array_search("created", $settings->columns) !== false ? ' checked="checked"' : "") .
                        ' id="_created" name="created" /><label for="_created" style="margin-left: 2px; position: relative; top: -2px;"><b>' .
                        __("Date submitted", "tripetto") .
                        "</b></label></div>";

                    foreach ($fields as $field) {
                        if (!empty($field->key) && !empty($field->name)) {
                            echo '<div><input type="checkbox"' .
                                (array_search($field->key, $settings->columns) !== false ? ' checked="checked"' : "") .
                                ' id="_' .
                                esc_attr($field->key) .
                                '" name="' .
                                esc_attr($field->key) .
                                '" /><label for="_' .
                                esc_attr($field->key) .
                                '" style="margin-left: 2px; position: relative; top: -2px;">' .
                                esc_html($field->name) .
                                "</label></div>";
                        }
                    }

                    echo '<p class="submit" style="margin-top: 8px;"><input type="submit" name="submit" id="submit" class="button button-primary" value="' .
                        __("Save", "tripetto") .
                        '"></p>';

                    echo "</form>";
                } else {
                    echo "ℹ️ " . __("You need to collect at least one result before you can select columns!", "tripetto");
                }

                wp_enqueue_style("wp-tripetto");
                wp_enqueue_script("wp-tripetto");

                Header::generate(
                    (!empty($form->name) ? $form->name . " / " : "") . __("Choose columns", "tripetto"),
                    "?page=tripetto-forms&action=results&id=" . strval($form->id)
                );

                echo "</div>";

                return;
            }
        }

        wp_die(__("Something went wrong!", "tripetto"));
    }
}
?>
