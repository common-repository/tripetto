<?php
namespace Tripetto;

class Builder
{
    static function scripts()
    {
        wp_register_script(
            "vendor-tripetto-builder",
            Helpers::pluginUrl() . "/vendors/tripetto-builder.js",
            [],
            $GLOBALS["TRIPETTO_PLUGIN_VERSION"],
            false
        );
    }

    static function create($runner, $redirect = true, $template = "")
    {
        Tripetto::assert("create-forms");

        global $wpdb;

        switch ($runner) {
            case "chat":
                $runner = "chat";
                break;
            case "classic":
                $runner = "classic";
                break;
            default:
                $runner = "autoscroll";
                break;
        }

        $created = date("Y-m-d H:i:s");
        $name = "";
        $definition = Helpers::createJSON();
        $styles = Helpers::createJSON();

        if (!empty($template)) {
            $template = Templates::retrieve($template);

            if (!empty($template)) {
                $name = $template->name;
                $definition = $template->definition;
                $styles = $template->styles;
            }
        }

        if (License::isInProMode()) {
            $styles->noBranding = true;
        }

        $wpdb->insert($wpdb->prefix . "tripetto_forms", [
            "runner" => $runner,
            "name" => empty($name) ? __("Unnamed form", "tripetto") : $name,
            "definition" => Helpers::JSONToString($definition),
            "styles" => Helpers::JSONToString($styles),
            "indx" => 0,
            "created" => $created,
            "modified" => $created,
        ]);

        $id = intval($wpdb->insert_id);

        if (!empty($id)) {
            $reference = hash("sha256", wp_create_nonce("tripetto:form:" . strval($id)) . ":" . strval($id));

            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}tripetto_forms SET reference=%s WHERE id=%d", $reference, $id));

            if ($redirect) {
                $redirect = sprintf("admin.php?page=%s&action=builder&id=%s", esc_attr($_REQUEST["page"]), $id);

                echo "<br />🚀 <strong>" . __("Creating new form...", "tripetto") . "</strong>";
                echo '<script type="text/javascript">window.location="' . $redirect . '";</script>';
            }

            return $id;
        }

        if ($redirect) {
            /* translators: %s contains the error message */
            wp_die(sprintf(__("Something went wrong, could not create a new form (%s).", "tripetto"), $wpdb->last_error));
        }

        return 0;
    }

    static function props($form, $open = "")
    {
        global $wpdb;

        $props = Helpers::createJSON();
        $reference = $form->reference;

        if (empty($reference)) {
            $reference = hash("sha256", wp_create_nonce("tripetto:form:" . strval($form->id)) . ":" . strval($form->id));

            $wpdb->update(
                $wpdb->prefix . "tripetto_forms",
                [
                    "reference" => $reference,
                ],
                ["id" => $form->id]
            );
        }

        $props->id = intval($form->id);
        $props->token = !empty($form->token) ? $form->token : "";
        $props->runner = "autoscroll";
        $props->shareUrl = home_url() . "?tripetto=" . $reference;

        if (!empty($open)) {
            $props->open = $open;
        }

        if (!empty($form->runner)) {
            $props->runner = $form->runner;
        } elseif (!empty($form->collector)) {
            switch ($form->collector) {
                case "standard-bootstrap":
                    $props->runner = "classic";
                    break;
                default:
                    $props->runner = "autoscroll";
                    break;
            }
        }

        switch ($props->runner) {
            case "chat":
            case "classic":
                break;
            default:
                $props->runner = "autoscroll";
                break;
        }

        $props->definition = Migration::definition($form, Helpers::stringToJSON(Helpers::get($form, "definition")));
        $props->styles = Helpers::stringToJSON(Helpers::get($form, "styles"), Migration::styles($form, $props->runner));
        $props->l10n = Helpers::stringToJSON(Helpers::get($form, "l10n"), Migration::l10n($form));
        $props->hooks = Helpers::stringToJSON(Helpers::get($form, "hooks"), Migration::hooks($form));
        $props->trackers = Helpers::stringToJSON(Helpers::get($form, "trackers"));
        $props->shortcode = Helpers::stringToJSON(Helpers::get($form, "shortcode"));
        $props->results = Capabilities::viewResults();
        $props->entries = 0;

        if ($props->results) {
            $props->entries = intval(
                $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}tripetto_entries WHERE form_id=%d", $props->id))
            );
        }

        if (License::hasProFeatures($props->id)) {
            $props->tier = "pro";
        } else {
            if (isset($props->styles->noBranding) && $props->styles->noBranding) {
                $props->styles->noBranding = false;
            }

            if (License::hasLegacyFeatures()) {
                $props->tier = "legacy";
            }
        }

        $variables = Variables::inventory();

        if (!empty($variables)) {
            $props->data = $variables;
        }

        return $props;
    }

    static function run($id, $open = "")
    {
        Tripetto::assert(["edit-forms"]);

        $id = intval($id);

        if (!empty($id)) {
            global $wpdb;

            $form = $wpdb->get_row($wpdb->prepare("SELECT * from {$wpdb->prefix}tripetto_forms where id=%d", $id));

            if (!is_null($form)) {
                echo "<br /><span id=\"WPTripettoStatus\">⌛ <strong>" . __("One moment please...", "tripetto") . "</strong></span>";

                $props = Helpers::JSONToString(Builder::props($form, $open));
                $base = Helpers::pluginUrl();
                $language = get_locale();

                wp_enqueue_style("wp-tripetto");
                wp_enqueue_script("wp-tripetto");
                wp_add_inline_script("wp-tripetto", "WPTripetto.builder($props, \"$base\", ajaxurl, \"$language\");");

                return;
            }
        }

        wp_die(__("Something went wrong, could not fetch the form (the form is probably deleted).", "tripetto"));
    }

    static function verifyToken($id, $type, $data)
    {
        global $wpdb;

        $form = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT token,definition,runner,styles,l10n,hooks,trackers,shortcode from {$wpdb->prefix}tripetto_forms where id=%d",
                $id
            )
        );

        if (!is_null($form)) {
            $token = !empty($_POST["token"]) ? wp_unslash($_POST["token"]) : "";
            $overwrite = !empty($_POST["overwrite"]) ? boolval($_POST["overwrite"]) : false;
            $definition = $type === "definition" ? $data : $form->definition;
            $runner = $type === "runner" ? $data : $form->runner;
            $styles = $type === "styles" ? $data : $form->styles;
            $l10n = $type === "l10n" ? $data : $form->l10n;
            $hooks = $type === "hooks" ? $data : $form->hooks;
            $trackers = $type === "trackers" ? $data : $form->trackers;
            $shortcode = $type === "shortcode" ? $data : $form->shortcode;
            $response = Helpers::createJSON();
            $response->token = md5($definition . $runner . $styles . $l10n . $hooks . $trackers . $shortcode);

            if ($overwrite || empty($form->token) || strpos($token, $form->token) === 0 || strpos($response->token, $form->token) === 0) {
                header("Content-Type: application/json");

                echo Helpers::JSONToString($response);

                return $response->token;
            } else {
                http_response_code(409);
            }
        } else {
            http_response_code(410);
        }

        return false;
    }

    static function updateDefinition()
    {
        Tripetto::assert(["edit-forms"]);

        $definition = !empty($_POST["definition"]) ? wp_unslash($_POST["definition"]) : "";
        $name = !empty($_POST["name"]) ? wp_unslash($_POST["name"]) : "";
        $fingerprint = !empty($_POST["fingerprint"]) ? wp_unslash($_POST["fingerprint"]) : "";
        $stencil = !empty($_POST["stencil"]) ? wp_unslash($_POST["stencil"]) : "";
        $actionables = !empty($_POST["actionables"]) ? wp_unslash($_POST["actionables"]) : "";
        $id = !empty($_POST["id"]) ? intval($_POST["id"]) : 0;

        if (
            !empty($id) &&
            Helpers::isValidJSON($definition) &&
            Helpers::isValidSHA256($fingerprint) &&
            Helpers::isValidSHA256($stencil) &&
            Helpers::isValidSHA256($actionables) &&
            strlen($definition) < 4294967295
        ) {
            $token = Builder::verifyToken($id, "definition", $definition);

            if (!empty($token)) {
                global $wpdb;

                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}tripetto_forms SET name=%s,definition=%s,fingerprint=%s,stencil=%s,actionables=%s,token=%s,modified=%s WHERE id=%d",
                        Helpers::limitString($name, 65534),
                        $definition,
                        $fingerprint,
                        $stencil,
                        $actionables,
                        $token,
                        date("Y-m-d H:i:s"),
                        $id
                    )
                );

                http_response_code(!empty($wpdb->last_error) ? 500 : 200);
            }
        } else {
            http_response_code(500);
        }

        die();
    }

    static function updateRunner()
    {
        Tripetto::assert(["edit-forms"]);

        $runner = !empty($_POST["runner"]) ? wp_unslash($_POST["runner"]) : "";
        $id = !empty($_POST["id"]) ? intval($_POST["id"]) : 0;

        if (!empty($id) && ($runner === "autoscroll" || $runner === "chat" || $runner === "classic")) {
            $token = Builder::verifyToken($id, "runner", $runner);

            if (!empty($token)) {
                global $wpdb;

                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}tripetto_forms SET runner=%s,token=%s,modified=%s WHERE id=%d",
                        $runner,
                        $token,
                        date("Y-m-d H:i:s"),
                        $id
                    )
                );

                http_response_code(!empty($wpdb->last_error) ? 500 : 200);
            }
        } else {
            http_response_code(500);
        }

        die();
    }

    static function updateStyles()
    {
        Tripetto::assert(["edit-forms"]);

        $styles = !empty($_POST["styles"]) ? wp_unslash($_POST["styles"]) : "";
        $id = !empty($_POST["id"]) ? intval($_POST["id"]) : 0;

        if (!empty($id) && Helpers::isValidJSON($styles)) {
            $token = Builder::verifyToken($id, "styles", $styles);

            if (!empty($token)) {
                global $wpdb;

                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}tripetto_forms SET styles=%s,token=%s,modified=%s WHERE id=%d",
                        $styles,
                        $token,
                        date("Y-m-d H:i:s"),
                        $id
                    )
                );

                http_response_code(!empty($wpdb->last_error) ? 500 : 200);
            }
        } else {
            http_response_code(500);
        }

        die();
    }

    static function updateL10n()
    {
        Tripetto::assert(["edit-forms"]);

        $l10n = !empty($_POST["l10n"]) ? wp_unslash($_POST["l10n"]) : "";
        $id = !empty($_POST["id"]) ? intval($_POST["id"]) : 0;

        if (!empty($id) && Helpers::isValidJSON($l10n)) {
            $token = Builder::verifyToken($id, "l10n", $l10n);

            if (!empty($token)) {
                global $wpdb;

                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}tripetto_forms SET l10n=%s,token=%s,modified=%s WHERE id=%d",
                        $l10n,
                        $token,
                        date("Y-m-d H:i:s"),
                        $id
                    )
                );

                http_response_code(!empty($wpdb->last_error) ? 500 : 200);
            }
        } else {
            http_response_code(500);
        }

        die();
    }

    static function updateHooks()
    {
        Tripetto::assert(["edit-forms"]);

        $hooks = !empty($_POST["hooks"]) ? wp_unslash($_POST["hooks"]) : "";
        $id = !empty($_POST["id"]) ? intval($_POST["id"]) : 0;

        if (!empty($id) && Helpers::isValidJSON($hooks)) {
            $token = Builder::verifyToken($id, "hooks", $hooks);

            if (!empty($token)) {
                global $wpdb;

                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}tripetto_forms SET hooks=%s,token=%s,modified=%s WHERE id=%d",
                        $hooks,
                        $token,
                        date("Y-m-d H:i:s"),
                        $id
                    )
                );

                http_response_code(!empty($wpdb->last_error) ? 500 : 200);
            }
        } else {
            http_response_code(500);
        }

        die();
    }

    static function updateTrackers()
    {
        Tripetto::assert(["edit-forms"]);

        $trackers = !empty($_POST["trackers"]) ? wp_unslash($_POST["trackers"]) : "";
        $id = !empty($_POST["id"]) ? intval($_POST["id"]) : 0;

        if (!empty($id) && Helpers::isValidJSON($trackers)) {
            $token = Builder::verifyToken($id, "trackers", $trackers);

            if (!empty($token)) {
                global $wpdb;

                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}tripetto_forms SET trackers=%s,token=%s,modified=%s WHERE id=%d",
                        $trackers,
                        $token,
                        date("Y-m-d H:i:s"),
                        $id
                    )
                );

                http_response_code(!empty($wpdb->last_error) ? 500 : 200);
            }
        } else {
            http_response_code(500);
        }

        die();
    }

    static function updateShortcode()
    {
        Tripetto::assert(["edit-forms"]);

        $shortcode = !empty($_POST["shortcode"]) ? wp_unslash($_POST["shortcode"]) : "";
        $id = !empty($_POST["id"]) ? intval($_POST["id"]) : 0;

        if (!empty($id) && Helpers::isValidJSON($shortcode)) {
            $token = Builder::verifyToken($id, "shortcode", $shortcode);

            if (!empty($token)) {
                global $wpdb;

                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}tripetto_forms SET shortcode=%s,token=%s,modified=%s WHERE id=%d",
                        $shortcode,
                        $token,
                        date("Y-m-d H:i:s"),
                        $id
                    )
                );

                http_response_code(!empty($wpdb->last_error) ? 500 : 200);
            }
        } else {
            http_response_code(500);
        }

        die();
    }

    static function testSlack()
    {
        Tripetto::assert(["edit-forms"]);

        $url = !empty($_POST["url"]) ? wp_unslash($_POST["url"]) : "";
        $includeFields = !empty($_POST["includeFields"]) && $_POST["includeFields"] == "true" ? true : false;
        $mockup = !empty($_POST["mockup"]) ? wp_unslash($_POST["mockup"]) : "";
        $name = !empty($_POST["name"]) ? wp_unslash($_POST["name"]) : "";

        if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
            $message = Helpers::createJSON();

            /* translators: %1$s is replaced with the form name */
            $message->text = sprintf(__('Test submission for `%1$s`', "tripetto"), !empty($name) ? $name : __("Unnamed form", "tripetto"));
            $message->username = "Tripetto";
            $message->icon_url = Helpers::pluginUrl() . "/assets/tripetto.png";

            if ($includeFields && Helpers::isValidJSON($mockup)) {
                $dataset = Helpers::stringToJSON($mockup);
                $attachments = [];

                if (isset($dataset->fields) && is_array($dataset->fields)) {
                    foreach ($dataset->fields as $field) {
                        array_push($attachments, [
                            "title" => $field->name,
                            "value" => "`" . $field->datatype . "`",
                            "short" => false,
                        ]);
                    }
                }

                $message->attachments = [
                    [
                        "fields" => $attachments,
                    ],
                ];
            }

            $response = wp_safe_remote_post($url, [
                "headers" => [
                    "Content-Type" => "application/json; charset=utf-8",
                ],
                "body" => Helpers::JSONToString($message),
                "method" => "POST",
                "data_format" => "body",
                "timeout" => 90,
                "redirection" => 5,
                "blocking" => true,
                "httpversion" => "1.0",
            ]);

            if (!is_wp_error($response)) {
                http_response_code(wp_remote_retrieve_response_code($response));

                return die();
            }
        }

        http_response_code(400);

        die();
    }

    static function testWebhook()
    {
        Tripetto::assert(["edit-forms"]);

        $url = !empty($_POST["url"]) ? wp_unslash($_POST["url"]) : "";
        $nvp = !empty($_POST["nvp"]) && $_POST["nvp"] == "true" ? true : false;
        $data = !empty($_POST["mockup"]) ? wp_unslash($_POST["mockup"]) : "";

        if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL) && Helpers::isValidJSON($data)) {
            if ($nvp) {
                $dataset = Helpers::stringToJSON($data);
                $data = Helpers::createJSON();

                if (isset($dataset->fields) && is_array($dataset->fields)) {
                    foreach ($dataset->fields as $field) {
                        if (!empty($field->name)) {
                            $name = $field->name;
                            $counter = 1;

                            while (!empty($data->{$name})) {
                                $counter++;
                                $name = $field->name . "(" . $counter . ")";
                            }

                            $data->{$name} = $field->datatype;
                        }
                    }
                }

                $data->tripettoId = $dataset->id;
                $data->tripettoIndex = $dataset->index;
                $data->tripettoCreateDate = $dataset->created;
                $data->tripettoFingerprint = $dataset->fingerprint;

                $data = Helpers::JSONToString($data);
            }

            $response = wp_remote_post($url, [
                "headers" => [
                    "Content-Type" => "application/json; charset=utf-8",
                ],
                "body" => $data,
                "method" => "POST",
                "data_format" => "body",
                "timeout" => 90,
                "redirection" => 5,
                "blocking" => true,
                "httpversion" => "1.0",
            ]);

            if (!is_wp_error($response)) {
                http_response_code(wp_remote_retrieve_response_code($response));

                return die();
            }
        }

        http_response_code(400);

        die();
    }

    static function register($plugin)
    {
        add_action("wp_ajax_tripetto_definition", ["Tripetto\Builder", "updateDefinition"]);
        add_action("wp_ajax_tripetto_runner", ["Tripetto\Builder", "updateRunner"]);
        add_action("wp_ajax_tripetto_styles", ["Tripetto\Builder", "updateStyles"]);
        add_action("wp_ajax_tripetto_l10n", ["Tripetto\Builder", "updateL10n"]);
        add_action("wp_ajax_tripetto_hooks", ["Tripetto\Builder", "updateHooks"]);
        add_action("wp_ajax_tripetto_trackers", ["Tripetto\Builder", "updateTrackers"]);
        add_action("wp_ajax_tripetto_shortcode", ["Tripetto\Builder", "updateShortcode"]);
        add_action("wp_ajax_tripetto_test_slack", ["Tripetto\Builder", "testSlack"]);
        add_action("wp_ajax_tripetto_test_webhook", ["Tripetto\Builder", "testWebhook"]);
        add_action("admin_enqueue_scripts", ["Tripetto\Builder", "scripts"]);
    }
}
?>
