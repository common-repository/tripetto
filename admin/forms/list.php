<?php
namespace Tripetto;

class FormsList extends ListFactory
{
    /**
     * Set up a constructor that references the parent constructor. We
     * use the parent reference to set some default configs.
     */
    public function __construct()
    {
        parent::__construct([
            "singular" => __("form", "tripetto"),
            "plural" => __("forms", "tripetto"),
            "ajax" => false,
        ]);
    }

    /**
     * This is a default column renderer
     *
     * @param $item - row (key, value array)
     * @param $column_name - string (key)
     * @return HTML
     */
    function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    /**
     * Render checkbox column
     *
     * @param $item - row (key, value array)
     * @return HTML
     */
    function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="id[]" value="%s" />', esc_attr($item["id"]));
    }

    public function column_name($item)
    {
        global $wpdb;

        $actions = [];

        if (Capabilities::editForms()) {
            $actions["edit"] = sprintf(
                '<a href="?page=%s&action=builder&id=%s">%s</a>',
                esc_attr($_REQUEST["page"]),
                $item["id"],
                "<span style='white-space:nowrap;'>✏ " . __("Edit", "tripetto") . "</span>"
            );
        }

        if (Capabilities::runForms()) {
            $actions["run"] = sprintf(
                '<a href="%s?tripetto=%s" target="_blank">%s</a>',
                home_url(),
                $item["reference"],
                "<span style='white-space:nowrap;'>💬 " . __("Run", "tripetto") . "</span>"
            );
        }

        if (Capabilities::viewResults()) {
            $actions["results"] = sprintf(
                '<a href="?page=%s&action=results&id=%s">%s</a>',
                esc_attr($_REQUEST["page"]),
                $item["id"],
                "<span style='white-space:nowrap;'>📥 " . __("Results", "tripetto") . "</span>"
            );
        }

        /*
        if (Capabilities::editForms()) {
            $actions["styles"] = sprintf(
                '<a href="?page=%s&action=styles&id=%s">%s</a>',
                esc_attr($_REQUEST["page"]),
                $item["id"],
                "<span style='white-space:nowrap;'>🎨 " .
                    __("Styles", "tripetto") .
                    "</span>"
            );

            $actions["l10n"] = sprintf(
                '<a href="?page=%s&action=l10n&id=%s">%s</a>',
                esc_attr($_REQUEST["page"]),
                $item["id"],
                "<span style='white-space:nowrap;'>🌎 " .
                    __("Translations", "tripetto") .
                    "</span>"
            );

            $actions["automate"] = sprintf(
                '<a href="?page=%s&action=automate&id=%s">%s</a>',
                esc_attr($_REQUEST["page"]),
                $item["id"],
                "<span style='white-space:nowrap;'>⚙ " .
                    __("Automate", "tripetto") .
                    "</span>"
            );
        }
        */

        if (Capabilities::createForms()) {
            $actions["duplicate"] = sprintf(
                '<a href="?page=%s&action=duplicate&id=%s&nonce=%s">%s</a>',
                esc_attr($_REQUEST["page"]),
                $item["id"],
                wp_create_nonce("tripetto:duplicate:" . strval($item["id"])),
                "<span style='white-space:nowrap;'>✨ " . __("Duplicate", "tripetto") . "</span>"
            );
        }

        if (Capabilities::deleteForms()) {
            $actions["delete"] = sprintf(
                '<a href="javascript:;"%s><span style="white-space:nowrap;">❌ %s</span></a>',
                intval(
                    $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}tripetto_entries WHERE form_id=%d", $item["id"]))
                ) === 0
                    ? sprintf(
                        ' onclick="WPTripetto.showModal(\'%s\',\'%s\',\'%s\',\'%s\',\'?page=%s&action=delete&id=%s&nonce=%s\',440,270);"',
                        __("Are you sure?", "tripetto"),
                        __(
                            "Do you really want to delete this form? All related data and the form itself will be deleted. This cannot be undone.",
                            "tripetto"
                        ),
                        __("Delete form", "tripetto"),
                        __("Cancel", "tripetto"),
                        esc_attr($_REQUEST["page"]),
                        $item["id"],
                        wp_create_nonce("tripetto:delete:" . strval($item["id"]))
                    )
                    : sprintf(
                        ' style="opacity: 0.25;" title="ℹ %s"',
                        esc_attr(__("You need to remove the results first before you can delete the form.", "tripetto"))
                    ),
                __("Delete", "tripetto")
            );
        }

        $isInProMode = License::isInProMode();
        $hasProFeatures = License::hasProFeatures($item["id"]);

        if (!Capabilities::editForms()) {
            if (Capabilities::viewResults()) {
                return sprintf(
                    '<a href="?page=%s&action=results&id=%s">%s %s %s</a>',
                    esc_attr($_REQUEST["page"]),
                    $item["id"],
                    esc_html($item["name"] == "" ? "Unnamed form" : $item["name"]),
                    !$isInProMode && $hasProFeatures ? '<span class="wp-tripetto-pro-badge">Pro</span>' : "",
                    $this->row_actions($actions)
                );
            } elseif (Capabilities::runForms()) {
                return sprintf(
                    '<a href="%s?tripetto=%s" target="_blank">%s %s %s</a>',
                    home_url(),
                    $item["reference"],
                    esc_html($item["name"] == "" ? "Unnamed form" : $item["name"]),
                    !$isInProMode && $hasProFeatures ? '<span class="wp-tripetto-pro-badge">Pro</span>' : "",
                    $this->row_actions($actions)
                );
            }
        }

        return sprintf(
            '<a href="?page=%s&action=builder&id=%s">%s %s %s</a>',
            esc_attr($_REQUEST["page"]),
            $item["id"],
            esc_html($item["name"] == "" ? "Unnamed form" : $item["name"]),
            !$isInProMode && $hasProFeatures ? '<span class="wp-tripetto-pro-badge">Pro</span>' : "",
            $this->row_actions($actions)
        );
    }

    public function column_shortcode($item)
    {
        $actions = [
            "clipboard" => sprintf(
                '<a href="javascript:;" onclick="WPTripetto.copyShortcodeToClipboard(%s,\'%s\');">%s</a>',
                $item["id"],
                !empty($item["shortcode"]) ? base64_encode($item["shortcode"]) : "",
                "<span style='white-space:nowrap;'>📋 " . __("Copy to clipboard", "tripetto") . "</span>"
            ),
        ];

        if (Capabilities::editForms()) {
            $actions["shortcode"] = sprintf(
                '<a href="?page=%s&action=share&id=%s">%s</a>',
                esc_attr($_REQUEST["page"]),
                $item["id"],
                "<span style='white-space:nowrap;'>📝 " . __("Customize", "tripetto") . "</span>"
            );
        }

        return sprintf(
            "<code>[tripetto id='%d'%s]</code>%s",
            $item["id"],
            !empty($item["shortcode"]) ? " ... " : "",
            $this->row_actions($actions)
        );

        return;
    }

    /**
     * Render Creation date column
     *
     * @param $item - row (key, value array)
     * @return HTML
     */
    public function column_created($item)
    {
        return Helpers::formatDate($item["created"]);
    }

    public function column_modified($item)
    {
        return Helpers::formatDate($item["modified"]);
    }

    public function column_results($item)
    {
        global $wpdb;

        $id = $item["id"];

        $results = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}tripetto_entries WHERE form_id=%d", $id));
        return "<a href='?page=tripetto-forms&action=results&id={$id}'>$results</a>";
    }

    /**
     * This method return columns to display in table
     *
     * @return array
     */
    function get_columns()
    {
        $columns = [
            "name" => __("Name", "tripetto"),
        ];

        if (Capabilities::viewResults()) {
            $columns["results"] = __("# Results", "tripetto");
        }

        $columns["created"] = __("Created", "tripetto");
        $columns["modified"] = __("Modified", "tripetto");
        $columns["shortcode"] = __("Shortcode", "tripetto");

        return $columns;
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
            "name" => ["name", false],
            "id" => ["id", true],
            "created" => ["created", true],
            "modified" => ["modified", true],
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
        global $wpdb;

        $ids = !empty($_REQUEST["id"]) ? $_REQUEST["id"] : [];
        $nonce = !empty($_REQUEST["nonce"]) ? $_REQUEST["nonce"] : "";

        if (is_array($ids)) {
            $ids = array_map("intval", $ids);
            $ids = implode(",", $ids);
        } else {
            $ids = intval($ids);
        }

        if ("delete" === $this->current_action() && wp_verify_nonce($nonce, "tripetto:delete:" . strval($ids))) {
            Forms::delete($ids);
        }

        if (
            "duplicate" === $this->current_action() &&
            Capabilities::createForms() &&
            wp_verify_nonce($nonce, "tripetto:duplicate:" . strval($ids))
        ) {
            if (!empty($ids)) {
                $created = date("Y-m-d H:i:s");
                $wpdb->query(
                    "INSERT INTO {$wpdb->prefix}tripetto_forms (name,definition,styles,l10n,runner,fingerprint,stencil,actionables,shortcode,created,modified) SELECT name,definition,styles,l10n,runner,fingerprint,stencil,actionables,shortcode,'{$created}' as created,'{$created}' as modified FROM {$wpdb->prefix}tripetto_forms WHERE id IN ($ids)"
                );
            }
        }

        $formsWithoutRef = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}tripetto_forms WHERE reference IS NULL OR reference=''");

        foreach ($formsWithoutRef as $formWithoutRef) {
            $wpdb->update(
                $wpdb->prefix . "tripetto_forms",
                [
                    "reference" => hash(
                        "sha256",
                        wp_create_nonce("tripetto:form:" . strval($formWithoutRef)) . ":" . strval($formWithoutRef)
                    ),
                ],
                ["id" => $formWithoutRef]
            );
        }
    }

    /**
     * Prepare table list items.
     */
    public function prepare_items()
    {
        global $wpdb;

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        // here we configure table headers, defined in our methods
        $this->_column_headers = [$columns, $hidden, $sortable];

        // [OPTIONAL] process bulk action if any
        $this->process_bulk_action();

        // will be used in pagination settings
        $total = Forms::total();
        $per_page = $total > 0 ? $total : 1;

        // prepare query params, as usual current page, order by and order direction
        $paged = !empty($_REQUEST["paged"]) ? max(0, intval($_REQUEST["paged"] - 1) * $per_page) : 0;
        $orderby =
            !empty($_REQUEST["orderby"]) && in_array($_REQUEST["orderby"], array_keys($this->get_sortable_columns()))
                ? $_REQUEST["orderby"]
                : "id";
        $order = !empty($_REQUEST["order"]) && in_array($_REQUEST["order"], ["asc", "desc"]) ? $_REQUEST["order"] : "asc";

        // [REQUIRED] define $items array
        // notice that last argument is ARRAY_A, so we will retrieve array
        $this->items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}tripetto_forms ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $paged),
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
