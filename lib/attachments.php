<?php
namespace Tripetto;

class Attachments
{
    static function activate($network_wide)
    {
        if (!Capabilities::activatePlugins()) {
            return;
        }

        if (is_multisite() && $network_wide) {
            return;
        }

        Attachments::database();
    }

    static function database()
    {
        Database::assert(
            "tripetto_attachments",
            [
                "form_id int(10) unsigned NOT NULL",
                "entry_id int(10) unsigned NULL",
                "reference varchar(65) NOT NULL DEFAULT ''",
                "name text NOT NULL",
                "path text NOT NULL",
                "type tinytext NOT NULL",
                "created datetime NULL DEFAULT NULL",
            ],
            ["form_id", "entry_id", "reference"]
        );
    }

    static function setUploadDir($param)
    {
        $customDir = "/tripetto";

        $param["subdir"] = $param["subdir"] . $customDir;
        $param["path"] = $param["path"] . $customDir;

        return $param;
    }

    static function upload()
    {
        $reference = !empty($_POST["reference"]) ? $_POST["reference"] : "";
        $nonce = !empty($_POST["nonce"]) ? $_POST["nonce"] : "";
        $file = $_FILES["file"];

        if (!empty($reference) && isset($file)) {
            global $wpdb;

            $form = $wpdb->get_row(
                $wpdb->prepare("SELECT id,fingerprint from {$wpdb->prefix}tripetto_forms where reference=%s", $reference)
            );

            if (!is_null($form) && (empty($nonce) || wp_verify_nonce($nonce, Runner::runnerNonce($form->id)))) {
                $filename = sanitize_file_name($file["name"]);

                add_filter("upload_dir", ["Tripetto\Attachments", "setUploadDir"]);

                $uploadDir = wp_upload_dir();

                $wpdb->insert($wpdb->prefix . "tripetto_attachments", [
                    "form_id" => $form->id,
                    "entry_id" => 0,
                    "reference" => "",
                    "name" => $filename,
                    "path" => $uploadDir["path"],
                    "type" => sanitize_mime_type($file["type"]),
                    "created" => date("Y-m-d H:i:s"),
                ]);

                $attachmentId = intval($wpdb->insert_id);

                if (!empty($attachmentId)) {
                    $reference = hash("sha256", $form->fingerprint . wp_create_nonce("tripetto:attachments:upload:" . $attachmentId));

                    // Change the filename.
                    $file["name"] = $reference;

                    // Save the file to disk.
                    $uploaded_file = wp_handle_upload($file, [
                        "test_form" => false,
                        "test_type" => false,
                        "action" => "upload_attachment",
                    ]);

                    if ($uploaded_file && empty($uploaded_file["error"])) {
                        $wpdb->update($wpdb->prefix . "tripetto_attachments", ["reference" => $reference], ["id" => $attachmentId]);

                        header("Content-Type: application/json");

                        $response = Helpers::createJSON();
                        $response->reference = $reference;

                        http_response_code(200);

                        echo Helpers::JSONToString($response);

                        return die();
                    } else {
                        $wpdb->delete($wpdb->prefix . "tripetto_attachments", [
                            "id" => $attachmentId,
                        ]);
                    }
                }
            }
        }

        http_response_code(500);

        die();
    }

    static function download()
    {
        $reference = !empty($_POST["reference"]) ? $_POST["reference"] : "";
        $nonce = !empty($_POST["nonce"]) ? $_POST["nonce"] : "";
        $file = !empty($_POST["file"]) ? $_POST["file"] : "";

        if (!empty($reference) && !empty($file)) {
            global $wpdb;

            $form = $wpdb->get_row($wpdb->prepare("SELECT id from {$wpdb->prefix}tripetto_forms where reference=%s", $reference));

            $attachment = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$wpdb->prefix}tripetto_attachments WHERE reference=%s AND entry_id=0", $file)
            );

            if (
                !is_null($form) &&
                (empty($nonce) || wp_verify_nonce($nonce, Runner::runnerNonce($form->id))) &&
                !is_null($attachment) &&
                !empty($attachment->id)
            ) {
                $path = $attachment->path . "/" . $attachment->reference;

                // Some hosts tend to save files without an extension as a file without a name and the extension as name.
                if (!file_exists($path)) {
                    $path = $attachment->path . "/." . $attachment->reference;
                }

                if (file_exists($path)) {
                    header("Content-Type: {$attachment->type}");

                    readfile($path);

                    return die();
                }
            }
        }

        http_response_code(404);

        die();
    }

    static function unload()
    {
        $reference = !empty($_POST["reference"]) ? $_POST["reference"] : "";
        $nonce = !empty($_POST["nonce"]) ? $_POST["nonce"] : "";
        $file = !empty($_POST["file"]) ? $_POST["file"] : "";

        if (!empty($reference) && !empty($file)) {
            global $wpdb;

            $form = $wpdb->get_row($wpdb->prepare("SELECT id from {$wpdb->prefix}tripetto_forms where reference=%s", $reference));

            $attachment = $wpdb->get_row(
                $wpdb->prepare("SELECT id FROM {$wpdb->prefix}tripetto_attachments WHERE reference=%s AND entry_id=0", $file)
            );

            if (
                !is_null($form) &&
                (empty($nonce) || wp_verify_nonce($nonce, Runner::runnerNonce($form->id))) &&
                !is_null($attachment) &&
                !empty($attachment->id)
            ) {
                Attachments::delete($attachment->id);

                http_response_code(200);

                return die();
            }
        }

        http_response_code(404);

        die();
    }

    static function confirm($reference, $entryId)
    {
        if (!empty($reference)) {
            global $wpdb;

            $wpdb->update(
                $wpdb->prefix . "tripetto_attachments",
                ["entry_id" => $entryId],
                [
                    "reference" => $reference,
                    "entry_id" => 0,
                ]
            );
        }
    }

    static function delete($id)
    {
        global $wpdb;

        $attachment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tripetto_attachments WHERE id=%d", $id));

        if (!is_null($attachment)) {
            $wpdb->delete($wpdb->prefix . "tripetto_attachments", [
                "id" => $id,
            ]);

            wp_delete_file($attachment->path . "/" . $attachment->reference);
        }
    }

    static function export()
    {
        if (!empty($_REQUEST["action"]) && $_REQUEST["action"] == "tripetto-attachment") {
            $reference = !empty($_REQUEST["reference"]) ? $_REQUEST["reference"] : "";

            $origin = !empty($_REQUEST["origin"]) ? $_REQUEST["origin"] : "";

            if (!empty($reference)) {
                global $wpdb;

                $attachment = $wpdb->get_row(
                    $wpdb->prepare("SELECT * FROM {$wpdb->prefix}tripetto_attachments WHERE reference=%s AND entry_id>0", $reference)
                );

                // We had a bug in v6.0.0 where the attachments were not confirmed correctly. Let's fix that here.
                if (is_null($attachment)) {
                    $attachment = $wpdb->get_row(
                        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}tripetto_attachments WHERE reference=%s AND entry_id=0", $reference)
                    );

                    if (!is_null($attachment)) {
                        $entry = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT id FROM {$wpdb->prefix}tripetto_entries WHERE form_id=%d AND entry LIKE '%%\"%s\"%%'",
                                $attachment->form_id,
                                $attachment->reference
                            )
                        );

                        if (!is_null($entry)) {
                            Attachments::confirm($attachment->reference, $entry->id);
                        }
                    }
                }

                // Fallback on id for backwards compatibility with older versions of the plugin.
                if (is_null($attachment)) {
                    $reference = intval($reference);

                    if (!empty($reference)) {
                        $attachment = $wpdb->get_row(
                            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}tripetto_attachments WHERE id=%d AND entry_id>0", $reference)
                        );
                    }
                }

                if (!is_null($attachment)) {
                    $form = $wpdb->get_row("SELECT hooks FROM {$wpdb->prefix}tripetto_forms WHERE id={$attachment->form_id}");

                    if (!is_null($form)) {
                        $secured = true;

                        if (!empty($origin)) {
                            $hooks = Helpers::stringToJSON(Helpers::get($form, "hooks"), Migration::hooks($form));

                            switch ($origin) {
                                case "email":
                                    $secured =
                                        isset($hooks->email) &&
                                        is_object($hooks->email) &&
                                        isset($hooks->email->allowDownloads) &&
                                        !empty($hooks->email->allowDownloads)
                                            ? false
                                            : true;
                                    break;
                                case "slack":
                                    $secured =
                                        isset($hooks->slack) &&
                                        is_object($hooks->slack) &&
                                        isset($hooks->slack->allowDownloads) &&
                                        !empty($hooks->slack->allowDownloads)
                                            ? false
                                            : true;
                                    break;
                                case "webhook":
                                    $secured =
                                        isset($hooks->webhook) &&
                                        is_object($hooks->webhook) &&
                                        isset($hooks->webhook->allowDownloads) &&
                                        !empty($hooks->webhook->allowDownloads)
                                            ? false
                                            : true;
                                    break;
                                case "integromat":
                                    $secured =
                                        (isset($hooks->integromat) &&
                                            is_object($hooks->integromat) &&
                                            isset($hooks->integromat->allowDownloads) &&
                                            !empty($hooks->integromat->allowDownloads)) ||
                                        (isset($hooks->make) &&
                                            is_object($hooks->make) &&
                                            isset($hooks->make->allowDownloads) &&
                                            !empty($hooks->make->allowDownloads))
                                            ? false
                                            : true;
                                    break;
                                case "make":
                                    $secured =
                                        isset($hooks->make) &&
                                        is_object($hooks->make) &&
                                        isset($hooks->make->allowDownloads) &&
                                        !empty($hooks->make->allowDownloads)
                                            ? false
                                            : true;
                                    break;
                                case "zapier":
                                    $secured =
                                        isset($hooks->zapier) &&
                                        is_object($hooks->zapier) &&
                                        isset($hooks->zapier->allowDownloads) &&
                                        !empty($hooks->zapier->allowDownloads)
                                            ? false
                                            : true;
                                    break;
                                case "pabbly":
                                    $secured =
                                        isset($hooks->pabbly) &&
                                        is_object($hooks->pabbly) &&
                                        isset($hooks->pabbly->allowDownloads) &&
                                        !empty($hooks->pabbly->allowDownloads)
                                            ? false
                                            : true;
                                    break;
                            }
                        }

                        if (($secured && Tripetto::assert("view-results")) || (!$secured && is_admin())) {
                            $path = $attachment->path . "/" . $attachment->reference;

                            // Some hosts tend to save files without an extension as a file without a name (and the extension as name).
                            if (!file_exists($path)) {
                                $path = $attachment->path . "/." . $attachment->reference;
                            }

                            if (file_exists($path)) {
                                $contentLength = filesize($path);
                                $extension = strtolower(pathinfo($attachment->name, PATHINFO_EXTENSION));
                                $disposition = "inline";

                                switch ($extension) {
                                    case "svg":
                                    case "html":
                                    case "htm":
                                        $disposition = "attachment";
                                        break;
                                }

                                Helpers::cleanOutputBuffer();

                                header("Content-Type: " . $attachment->type);
                                header("Content-Disposition: " . $disposition . '; filename="' . $attachment->name . '"');
                                header("Content-Security-Policy: default-src 'self'");

                                if ($contentLength !== false) {
                                    header("Content-Length: " . strval($contentLength));
                                }

                                readfile($path);

                                return die();
                            }
                        } else {
                            wp_die(__("You cannot access this page. You are not authorized.", "tripetto"));

                            return;
                        }
                    }
                }

                http_response_code(404);
            }

            die();
        }
    }

    static function isAttachment($field)
    {
        return $field->type == "@tripetto/block-file-upload" ||
            $field->type == "tripetto-block-file-upload" ||
            $field->type == "file-upload" ||
            $field->type == "@tripetto/block-signature";
    }

    static function isImage($field)
    {
        if (Attachments::isAttachment($field) && !empty($field->string)) {
            $extension = substr($field->string, strrpos($field->string, "."));

            if ($extension == ".jpg" || $extension == ".jpeg" || $extension == ".png" || $extension == ".gif" || $extension == ".webp") {
                return true;
            }
        }

        return false;
    }

    static function validate($exportables, $id)
    {
        foreach ($exportables->fields as $field) {
            if (Attachments::isAttachment($field) && !empty($field->reference)) {
                Attachments::confirm($field->reference, $id);
            }
        }
    }

    static function register($plugin)
    {
        add_action("wp_ajax_tripetto_attachment_upload", ["Tripetto\Attachments", "upload"]);
        add_action("wp_ajax_nopriv_tripetto_attachment_upload", ["Tripetto\Attachments", "upload"]);
        add_action("wp_ajax_tripetto_attachment_download", ["Tripetto\Attachments", "download"]);
        add_action("wp_ajax_nopriv_tripetto_attachment_download", ["Tripetto\Attachments", "download"]);
        add_action("wp_ajax_tripetto_attachment_unload", ["Tripetto\Attachments", "unload"]);
        add_action("wp_ajax_nopriv_tripetto_attachment_unload", ["Tripetto\Attachments", "unload"]);

        if (is_admin()) {
            register_activation_hook($plugin, ["Tripetto\Attachments", "activate"]);
        }

        add_action("init", ["Tripetto\Attachments", "export"]);
    }
}
?>
