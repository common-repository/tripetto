<?php
namespace Tripetto;

class Export
{
    static function CSV()
    {
        if (!empty($_REQUEST["action"]) && $_REQUEST["action"] == "tripetto-export-csv" && Tripetto::assert("export-results")) {
            $id = !empty($_REQUEST["id"]) ? intval($_REQUEST["id"]) : 0;
            $stencil = !empty($_REQUEST["stencil"]) ? $_REQUEST["stencil"] : "";

            if (!empty($id) && !empty($stencil)) {
                header("Content-Type: text/csv;charset=utf-8;");
                header("Content-Disposition: attachment; filename=tripetto-export-$stencil.csv");

                $out = fopen("php://output", "w");

                global $wpdb;

                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}tripetto_entries WHERE form_id=%d AND (stencil=%s OR fingerprint=%s) ORDER BY created DESC",
                        $id,
                        $stencil,
                        $stencil
                    )
                );

                // Add bom (see https://en.wikipedia.org/wiki/Byte_order_mark#UTF-8)
                fputs($out, chr(0xef) . chr(0xbb) . chr(0xbf));

                $columns = 0;

                foreach ($results as $result) {
                    $fields = Helpers::stringToJSON(Helpers::get($result, "entry"));

                    if ($columns === 0) {
                        $values = [];

                        if (isset($fields->fields) && is_array($fields->fields)) {
                            foreach ($fields->fields as $field) {
                                array_push($values, $field->name);
                            }
                        }

                        array_unshift($values, __("Date submitted", "tripetto"), __("# Number", "tripetto"), __("Reference", "tripetto"));
                        fputcsv($out, $values, ";");

                        $columns = count($values);
                    }

                    $values = [];

                    array_push($values, Helpers::formatDate($result->created));
                    array_push($values, Results::index($result->indx, $result->id, $result->form_id, $result->created));
                    array_push($values, $result->reference);

                    if (isset($fields->fields) && is_array($fields->fields)) {
                        foreach ($fields->fields as $field) {
                            array_push($values, Runner::parseFieldValue($field, "export"));
                        }
                    }

                    fputcsv($out, array_slice($values, 0, $columns), ";");
                }

                fclose($out);

                return die();
            }

            http_response_code(404);

            die();
        }
    }

    static function register($plugin)
    {
        add_action("init", ["Tripetto\Export", "CSV"]);
    }
}
