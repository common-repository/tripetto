<?php
namespace Tripetto;

require_once __DIR__ . "/columns.php";
require_once __DIR__ . "/list.php";
require_once __DIR__ . "/export.php";

class Results
{
    static function activate($network_wide)
    {
        if (!Capabilities::activatePlugins()) {
            return;
        }

        if (is_multisite() && $network_wide) {
            return;
        }

        Results::database();
    }

    static function database()
    {
        Database::assert(
            "tripetto_entries",
            [
                "form_id int(10) unsigned NOT NULL",
                "indx int(10) unsigned NOT NULL DEFAULT 0",
                "reference varchar(65) NOT NULL DEFAULT ''",
                "entry longtext NOT NULL",
                "fingerprint varchar(65) NOT NULL",
                "stencil varchar(65) NOT NULL DEFAULT ''",
                "signature varchar(65) NOT NULL DEFAULT ''",
                "lang varchar(6) NOT NULL DEFAULT ''",
                "locale varchar(6) NOT NULL DEFAULT ''",
                "created datetime NULL DEFAULT NULL",
            ],
            ["form_id", "indx", "reference", "fingerprint", "stencil", "signature", "created"]
        );
    }

    static function overview($id)
    {
        Tripetto::assert("view-results");

        if (!empty($id)) {
            global $wpdb;

            $form = $wpdb->get_row(
                $wpdb->prepare("SELECT id,name,reference,settings from {$wpdb->prefix}tripetto_forms where id=%d", intval($id))
            );

            if (!is_null($form)) {
                $customColumns = [];

                if (Helpers::isValidJSON($form->settings)) {
                    $settings = Helpers::stringToJSON(Helpers::get($form, "settings"));

                    if (isset($settings->columns) && is_array($settings->columns) && count($settings->columns) > 0) {
                        $fields = [];
                        $entry = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT entry from {$wpdb->prefix}tripetto_entries where form_id=%d ORDER BY created DESC",
                                intval($form->id)
                            )
                        );

                        if (!is_null($entry) && Helpers::isValidJSON($entry->entry)) {
                            $source = Helpers::stringToJSON(Helpers::get($entry, "entry"));
                            $fields = isset($source->fields) && is_array($source->fields) ? $source->fields : [];
                        }

                        foreach ($settings->columns as $column) {
                            switch ($column) {
                                case "index":
                                    $customColumns["index"] = __("# Number", "tripetto");
                                    break;
                                case "reference":
                                    $customColumns["reference"] = __("Identifier", "tripetto");
                                    break;
                                case "created":
                                    $customColumns["created"] = __("Date submitted", "tripetto");
                                    break;
                                default:
                                    foreach ($fields as $field) {
                                        if ($field->key === $column) {
                                            $customColumns[$field->key] = $field->name;

                                            break;
                                        }
                                    }
                                    break;
                            }
                        }
                    }
                }

                wp_enqueue_style("wp-tripetto");
                wp_enqueue_script("wp-tripetto");

                $results = new ResultsList($customColumns);
                $results->prepare_items();
                $message = "";

                if (!empty($_REQUEST["result_id"])) {
                    $message =
                        '<div class="updated below-h2" id="message"><p>' .
                        sprintf(
                            /* translators: %d is replaced with the number of results deleted */
                            __("Results deleted: %d", "tripetto"),
                            is_array($_REQUEST["result_id"]) ? count($_REQUEST["result_id"]) : 1
                        ) .
                        "</p></div>";
                }

                echo '<div class="wrap" id="wp-tripetto-admin" style="opacity: 0;">';

                echo $message;

                echo '<form id="tripetto_results_table" method="GET">';
                echo '<input type="hidden" name="page" value="' . esc_attr($_REQUEST["page"]) . '" />';
                echo '<input type="hidden" name="id" value="' . esc_attr($_REQUEST["id"]) . '" />';

                $results->display();

                echo "</form>";
                echo "</div>";

                $downloads = null;

                if (Capabilities::exportResults()) {
                    $noCache = hash("sha256", wp_create_nonce("tripetto:export:" . strtotime("now")));
                    $result_groups = $wpdb->get_results(
                        "SELECT COALESCE(NULLIF(stencil, ''),fingerprint) as stencil_hash, count(*) as entryCount FROM {$wpdb->prefix}tripetto_entries WHERE form_id=$form->id GROUP BY stencil_hash ORDER BY created DESC"
                    );

                    if (count($result_groups) == 1) {
                        $result_group = $result_groups[0];

                        $downloads =
                            "<a href='?action=tripetto-export-csv&id=$form->id&stencil=$result_group->stencil_hash&no-cache=$noCache' class='wp-tripetto-header-button wp-tripetto-header-button-icon wp-tripetto-header-button-icon-download'>" .
                            __("Download to CSV", "tripetto") .
                            "</a>";
                    } elseif (count($result_groups) > 1) {
                        $downloads =
                            "<div class='wp-tripetto-header-dropdown' id='wp-tripetto-header-dropdown'><div class='wp-tripetto-header-button wp-tripetto-header-button-icon wp-tripetto-header-button-icon-download wp-tripetto-header-button-dropdown' id='wp-tripetto-header-dropdown-button' role='button'>" .
                            __("Download to CSV", "tripetto") .
                            "<span></span></div>";
                        $downloads .= "<div class='wp-tripetto-header-dropdown-menu'>";

                        $version = count($result_groups);

                        foreach ($result_groups as $result_group) {
                            $resultCount = $result_group->entryCount;
                            $label = $resultCount == 1 ? "result" : "results";

                            $downloads .=
                                "<a href='?action=tripetto-export-csv&id=$form->id&stencil=$result_group->stencil_hash&no-cache=$noCache'>" .
                                __("Version", "tripetto") .
                                " $version ($resultCount $label)</a>";

                            $version--;
                        }

                        $downloads .=
                            "<div class='wp-tripetto-header-dropdown-info'><a href='https://tripetto.com/help/articles/troubleshooting-seeing-multiple-download-buttons/' target='_blank'>" .
                            __("Why do I see multiple CSV versions?", "tripetto") .
                            "</a></div>";
                        $downloads .= "</div></div>";
                    }
                }

                $results_count = intval(
                    $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}tripetto_entries WHERE form_id=%d", $form->id))
                );

                Header::generate(
                    !empty($form->name) ? $form->name : __("Unnamed form", "tripetto"),
                    "?page=tripetto-forms",
                    $results_count,
                    $downloads,
                    (Capabilities::runForms() && !empty($form->reference)
                        ? '<a href="' .
                            home_url() .
                            "?tripetto=" .
                            $form->reference .
                            '" target="_blank" class="wp-tripetto-header-button wp-tripetto-header-button-secondary">' .
                            __("Run", "tripetto") .
                            "</a>"
                        : null) .
                        (Capabilities::editForms()
                            ? '<a href="?page=tripetto-forms&action=builder&id=' .
                                strval($form->id) .
                                '" class="wp-tripetto-header-button wp-tripetto-header-button-secondary">' .
                                __("Edit", "tripetto") .
                                "</a>"
                            : null) .
                        ('<a href="?page=tripetto-forms&action=columns&id=' .
                            strval($form->id) .
                            '" class="wp-tripetto-header-button wp-tripetto-header-button-secondary' .
                            ($results_count === 0 ? " wp-tripetto-header-button-disabled" : "") .
                            '">' .
                            __("Choose columns", "tripetto") .
                            "</a>")
                );

                return;
            }
        }

        wp_die(__("Something went wrong, could not fetch the results.", "tripetto"));
    }

    static function view($id, $byReference = false)
    {
        Tripetto::assert("view-results");

        if (!empty($id)) {
            global $wpdb;

            $result = $wpdb->get_row(
                $byReference
                    ? $wpdb->prepare(
                        "SELECT id,form_id,entry,created,reference,indx from {$wpdb->prefix}tripetto_entries where reference=%s",
                        $id
                    )
                    : $wpdb->prepare(
                        "SELECT id,form_id,entry,created,reference,indx from {$wpdb->prefix}tripetto_entries where id=%d",
                        intval($id)
                    )
            );

            if (!is_null($result)) {
                if (Helpers::isValidJSON($result->entry)) {
                    wp_enqueue_style("wp-tripetto");
                    wp_enqueue_script("wp-tripetto");

                    $data = Helpers::stringToJSON(Helpers::get($result, "entry"));

                    echo '<div class="wrap" id="wp-tripetto-admin" style="opacity: 0;">';
                    echo '<div id="poststuff">';
                    echo '<div id="post-body" class="metabox-holder columns-2">';
                    echo '<div id="post-body-content">';
                    echo '<div class="meta-box-sortables ui-sortable">';

                    $count = 0;

                    if (isset($data->fields) && is_array($data->fields)) {
                        foreach ($data->fields as $field) {
                            if (!empty($field->string) || $field->string == "0") {
                                $count++;
                                $value = Runner::parseFieldValue($field, "results");

                                echo '<div class="postbox">';
                                echo '<span class="wp-tripetto-results-block">' . nl2br(esc_html($field->name)) . "</span>";
                                echo '<div class="inside">';

                                if (Attachments::isAttachment($field) && !empty($value)) {
                                    if (Attachments::isImage($field)) {
                                        echo "<a href='{$value}' target='_blank' title='" .
                                            esc_html($field->string) .
                                            "'><img src='{$value}' height='120' /></a>";
                                    } else {
                                        echo "<a href='{$value}' target='_blank'>" . esc_html($field->string) . "</a>";
                                    }
                                } else {
                                    echo "<p>" . nl2br(esc_html($value)) . " </p>";
                                }

                                echo "</div>";
                                echo "</div>";
                            }
                        }
                    }

                    if (!$count) {
                        echo "This result didn't contain any exportable data.";
                    }

                    echo "</div>";
                    echo "</div>";
                    echo '<div id="postbox-container-1" class="postbox-container">';
                    echo '<div class="meta-box-sortables">';
                    echo '<div class="postbox">';
                    echo '<span class="wp-tripetto-results-info">' . __("Information", "tripetto") . "</span></h2>";
                    echo '<div class="inside">';
                    echo "<p>";

                    $name = $wpdb->get_var($wpdb->prepare("SELECT name from {$wpdb->prefix}tripetto_forms where id=%d", $result->form_id));

                    if (!empty($name)) {
                        echo "<b>" . __("Form name", "tripetto") . ":</b> " . esc_html($name) . "<br />";
                    }

                    echo "<b>" .
                        __("Number", "tripetto") .
                        ":</b> #" .
                        Results::index($result->indx, $result->id, $result->form_id, $result->created) .
                        "<br />";
                    echo "<b>" . __("Date submitted", "tripetto") . ":</b> " . Helpers::formatDate($result->created);

                    if (!empty($result->reference)) {
                        echo "<br /><b>" . __("Identifier", "tripetto") . ":</b> " . $result->reference;
                    }

                    echo "</p>";

                    echo "</div>";
                    echo "</div>";
                    echo "</div>";
                    echo "</div>";
                    echo "</div>";
                    echo '<br class="clear">';
                    echo "</div>";
                    echo "</div>";

                    Header::generate(
                        __("Result", "tripetto") .
                            " #" .
                            strval(Results::index($result->indx, $result->id, $result->form_id, $result->created)),
                        "?page=tripetto-forms&action=results&id=" . $result->form_id,
                        null,
                        null,
                        Capabilities::deleteResults()
                            ? '<a href="javascript:;" class="wp-tripetto-header-button wp-tripetto-header-button-danger wp-tripetto-header-button-icon wp-tripetto-header-button-icon-delete" onclick="WPTripetto.showModal(\'' .
                                __("Are you sure?", "tripetto") .
                                '\',\'' .
                                __(
                                    "Do you really want to delete this result? All data related to this result will be deleted. This cannot be undone.",
                                    "tripetto"
                                ) .
                                '\',\'' .
                                __("Delete result", "tripetto") .
                                '\',\'' .
                                __("Cancel", "tripetto") .
                                '\',\'?page=tripetto-forms&action=results&id=' .
                                $result->form_id .
                                "&result_id=" .
                                $id .
                                '\',440,270);">' .
                                __("Delete Result", "tripetto") .
                                "</a>"
                            : null
                    );

                    return;
                }
            }
        }

        wp_die(__("Something went wrong, could not fetch the result.", "tripetto"));
    }

    static function delete($ids)
    {
        if (!empty($ids)) {
            global $wpdb;

            Tripetto::assert("delete-results");

            if (is_array($ids)) {
                $ids = implode(",", $ids);
            }

            $attachments = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}tripetto_attachments WHERE entry_id IN ($ids)");

            foreach ($attachments as $attachment) {
                Attachments::delete($attachment);
            }

            $wpdb->query("DELETE FROM {$wpdb->prefix}tripetto_entries WHERE id IN ($ids)");
        }
    }

    static function index($index, $id, $form, $created)
    {
        if (empty($index)) {
            global $wpdb;

            $index = intval(
                $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(id) FROM {$wpdb->prefix}tripetto_entries WHERE form_id=%d AND created<=%s",
                        $form,
                        $created
                    )
                )
            );

            if ($index < 1) {
                $index = 1;
            }

            $wpdb->update($wpdb->prefix . "tripetto_entries", ["indx" => $index], ["id" => $id]);
        }

        return $index;
    }

    static function register($plugin)
    {
        register_activation_hook($plugin, ["Tripetto\Results", "activate"]);

        Export::register($plugin);
    }
}
?>
