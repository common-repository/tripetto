<?php
namespace Tripetto;

class ResultsList extends ListFactory
{
    public $customColumns;

    public function __construct($customColumns)
    {
        parent::__construct([
            "singular" => __("result", "tripetto"),
            "plural" => __("results", "tripetto"),
            "ajax" => false,
        ]);

        $this->customColumns = $customColumns;
    }

    function column_default($item, $column_name)
    {
        if (!empty($column_name)) {
            if (isset($item[$column_name])) {
                return $esc_html($item[$column_name]);
            }

            if (Helpers::isValidJSON(Helpers::get($item, "entry"))) {
                $source = Helpers::stringToJSON(Helpers::get($item, "entry"));

                if (isset($source->fields) && is_array($source->fields)) {
                    foreach ($source->fields as $field) {
                        if ($field->key === $column_name) {
                            if (!empty($field->string) || $field->string == "0") {
                                $value = Runner::parseFieldValue($field, "results");

                                if (Attachments::isAttachment($field) && !empty($value)) {
                                    if (Attachments::isImage($field)) {
                                        return "<a href='{$value}' target='_blank' title='" .
                                            esc_html($field->string) .
                                            "'><img src='{$value}' height='36' /></a>";
                                    }

                                    return "<a href='{$value}' target='_blank'>" . esc_html($field->string) . "</a>";
                                } else {
                                    return nl2br(esc_html($value));
                                }
                            }

                            break;
                        }
                    }
                }
            }
        }

        return "-";
    }

    function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="result_id[]" value="%s" />', $item["id"]);
    }

    public function column_index($item, $value = "")
    {
        $actions = [
            "view" => sprintf(
                '<a href="?page=%s&action=view&id=%s">%s</a>',
                esc_attr($_REQUEST["page"]),
                $item["id"],
                "🔍 " . __("View Result", "tripetto")
            ),
        ];

        if (Capabilities::deleteResults()) {
            $actions["delete"] = sprintf(
                '<a href="javascript:;" onclick="WPTripetto.showModal(\'%s\',\'%s\',\'%s\',\'%s\',\'?page=%s&action=results&id=%s&result_id=%s\',440,270);">%s</a>',
                __("Are you sure?", "tripetto"),
                __(
                    "Do you really want to delete this result? All data related to this result will be deleted. This cannot be undone.",
                    "tripetto"
                ),
                __("Delete result", "tripetto"),
                __("Cancel", "tripetto"),
                esc_attr($_REQUEST["page"]),
                esc_attr($_REQUEST["id"]),
                $item["id"],
                "❌ " . __("Delete", "tripetto")
            );
        }

        return sprintf(
            '<a href="?page=%s&action=view&id=%s">%s %s</a>',
            esc_attr($_REQUEST["page"]),
            $item["id"],
            !empty($value) ? $value : "#" . Results::index($item["indx"], $item["id"], $item["form_id"], $item["created"]),
            $this->row_actions($actions)
        );
    }

    public function column_reference($item)
    {
        return empty($item["reference"]) ? "-" : $item["reference"];
    }

    public function column_created($item)
    {
        return Helpers::formatDate($item["created"]);
    }

    /**
     * This method return columns to display in table
     *
     * @return array
     */
    function get_columns()
    {
        if (is_array($this->customColumns) && count($this->customColumns) > 0) {
            return array_merge(
                [
                    "cb" => '<input type="checkbox" />',
                ],
                $this->customColumns
            );
        }

        return [
            "cb" => '<input type="checkbox" />',
            "index" => __("# Number", "tripetto"),
            "reference" => __("Identifier", "tripetto"),
            "created" => __("Date submitted", "tripetto"),
        ];
    }

    /**
     * This method return columns that may be used to sort table
     * all strings in array - is column names
     * notice that true on name column means that its default sort
     *
     * @return array
     */
    function get_sortable_columns()
    {
        return [
            "index" => ["indx", true],
            "reference" => ["reference", true],
            "created" => ["created", true],
        ];
    }

    /**
     * Return array of bulk actions if has any
     *
     * @return array
     */
    function get_bulk_actions()
    {
        return [
            "results" => "❌ " . __("Delete", "tripetto"),
        ];
    }

    /**
     * This method processes bulk actions
     * it can be outside of class
     * it can not use wp_redirect coz there is output already
     * in this example we are processing delete action
     * message about successful deletion will be shown on page in next part
     *
     * @see $this->prepare_items()
     */
    function process_bulk_action()
    {
        if ("results" === $this->current_action()) {
            $ids = !empty($_REQUEST["result_id"]) ? $_REQUEST["result_id"] : [];

            if (is_array($ids)) {
                $ids = array_map("intval", $ids);
            } else {
                $ids = intval($ids);
            }

            Results::delete($ids);
        }
    }

    /**
     * Prepare table list items.
     */
    public function prepare_items()
    {
        global $wpdb;

        $form_id = intval($_REQUEST["id"]);
        $per_page = 50;

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        // here we configure table headers, defined in our methods
        $this->_column_headers = [$columns, $hidden, $sortable];

        // [OPTIONAL] process bulk action if any
        $this->process_bulk_action();

        // will be used in pagination settings
        $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}tripetto_entries WHERE form_id=%d", $form_id));

        // prepare query params, as usual current page, order by and order direction
        $paged = !empty($_REQUEST["paged"]) ? max(0, intval($_REQUEST["paged"] - 1) * $per_page) : 0;
        $orderby =
            !empty($_REQUEST["orderby"]) && in_array($_REQUEST["orderby"], array_keys($this->get_sortable_columns()))
                ? $_REQUEST["orderby"]
                : "created";
        $order = !empty($_REQUEST["order"]) && in_array($_REQUEST["order"], ["asc", "desc"]) ? $_REQUEST["order"] : "desc";

        // [REQUIRED] define $items array
        // notice that last argument is ARRAY_A, so we will retrieve array
        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tripetto_entries WHERE form_id = %d ORDER BY $orderby $order LIMIT %d OFFSET %d",
                $form_id,
                $per_page,
                $paged
            ),
            ARRAY_A
        );

        // [REQUIRED] configure pagination
        $this->set_pagination_args([
            "total_items" => $total, // total items defined above
            "per_page" => $per_page, // per page constant defined at top of method
            "total_pages" => ceil($total / $per_page), // calculate pages count
        ]);
    }
}
?>
