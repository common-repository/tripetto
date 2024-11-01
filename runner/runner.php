<?php
namespace Tripetto;

class Runner
{
    static function activate($network_wide)
    {
        if (!Capabilities::activatePlugins()) {
            return;
        }

        if (is_multisite() && $network_wide) {
            return;
        }

        Runner::database();
    }

    static function database()
    {
        Database::assert(
            "tripetto_announcements",
            [
                "form_id int(10) unsigned NOT NULL",
                "nonce varchar(65) NOT NULL DEFAULT ''",
                "fingerprint varchar(65) NOT NULL",
                "checksum varchar(65) NOT NULL",
                "difficulty int(10) unsigned NOT NULL",
                "signature varchar(65) NOT NULL DEFAULT ''",
                "created bigint(20) NOT NULL DEFAULT 0",
            ],
            ["form_id", "nonce", "signature", "created"]
        );

        Database::assert(
            "tripetto_snapshots",
            [
                "reference varchar(65) NOT NULL DEFAULT ''",
                "form_id int(10) unsigned NOT NULL",
                "snapshot longtext NOT NULL",
                "url text NOT NULL",
                "email text NOT NULL",
                "created datetime NULL DEFAULT NULL",
            ],
            ["reference", "form_id", "created"]
        );
    }

    static function scripts()
    {
        wp_register_script(
            "vendor-tripetto-runner",
            Helpers::pluginUrl() . "/vendors/tripetto-runner.js",
            [],
            $GLOBALS["TRIPETTO_PLUGIN_VERSION"],
            false
        );

        wp_register_script(
            "vendor-tripetto-runner-autoscroll",
            Helpers::pluginUrl() . "/vendors/tripetto-runner-autoscroll.js",
            ["vendor-tripetto-runner"],
            $GLOBALS["TRIPETTO_PLUGIN_VERSION"],
            false
        );

        wp_register_script(
            "vendor-tripetto-runner-chat",
            Helpers::pluginUrl() . "/vendors/tripetto-runner-chat.js",
            ["vendor-tripetto-runner"],
            $GLOBALS["TRIPETTO_PLUGIN_VERSION"],
            false
        );

        wp_register_script(
            "vendor-tripetto-runner-classic",
            Helpers::pluginUrl() . "/vendors/tripetto-runner-classic.js",
            ["vendor-tripetto-runner"],
            $GLOBALS["TRIPETTO_PLUGIN_VERSION"],
            false
        );
    }

    static function getIP()
    {
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $_SERVER["REMOTE_ADDR"] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }

        foreach (
            [
                "HTTP_CLIENT_IP",
                "HTTP_X_FORWARDED_FOR",
                "HTTP_X_FORWARDED",
                "HTTP_X_CLUSTER_CLIENT_IP",
                "HTTP_FORWARDED_FOR",
                "HTTP_FORWARDED",
                "REMOTE_ADDR",
            ]
            as $key
        ) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(",", $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);

                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return "";
    }

    static function getProtectionMode()
    {
        $mode = get_option("tripetto_spam_protection");
        $allowlist = get_option("tripetto_spam_protection_allowlist");

        if (
            $mode !== "off" &&
            !empty($allowlist) &&
            strpos("," . str_replace(" ", "", $allowlist) . ",", "," . Runner::getIP() . ",") !== false
        ) {
            $mode = "off";
        }

        switch ($mode) {
            case "maximal":
            case "minimal":
            case "off":
                return $mode;
        }

        return "default";
    }

    static function getSignature($fingerprint)
    {
        return hash("sha256", $fingerprint . ":" . Runner::getIP());
    }

    static function runnerNonce($id)
    {
        return "tripetto:runner:" . $id;
    }

    static function props(
        $form,
        $async = false,
        $fullPage = true,
        $pausable = true,
        $persistent = false,
        $css = "",
        $width = "",
        $height = "",
        $className = "",
        $id = ""
    ) {
        global $wpdb;
        static $count = 0;

        $count++;

        if (empty($form->reference)) {
            $form->reference = hash("sha256", wp_create_nonce("tripetto:form:" . strval($form->id)) . ":" . strval($form->id));

            $wpdb->update(
                $wpdb->prefix . "tripetto_forms",
                [
                    "reference" => $form->reference,
                ],
                ["id" => $form->id]
            );
        }

        $namespace = "TripettoAutoscroll";
        $props = Helpers::createJSON();
        $props->reference = $form->reference;
        $props->runner = "autoscroll";
        $props->element = "tripetto-runner-" . hash("crc32", "tripetto-runner-" . (!empty($id) ? $id : $count));
        $props->fullPage = $fullPage;
        $props->pausable = $pausable;
        $props->persistent = $persistent;
        $props->pro = License::hasProFeatures($form->id);
        $props->css = $css;
        $props->className = $className;
        $props->width = $width;
        $props->height = $height;
        $props->language = get_locale();
        $props->url = admin_url("admin-ajax.php");

        if (!empty($form->definition)) {
            $variables = Variables::filter($form->definition);

            if (!empty($variables)) {
                $props->data = $variables;
            }
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
                $namespace = "TripettoChat";
                break;
            case "classic":
                $namespace = "TripettoClassic";
                break;
            default:
                $props->runner = "autoscroll";
                break;
        }

        $props->uri = Helpers::pluginUrl();
        $props->bundles = ["/vendors/tripetto-runner", "/vendors/tripetto-runner-" . $props->runner, "/js/wp-tripetto-runner"];
        $props->namespaces = ["TripettoRunner", $namespace, "WPTripettoRunner"];

        $prefill = apply_filters("tripetto_prefill", [], clone $form, $props->language);

        if (is_array($prefill) && count($prefill) > 0) {
            $props->prefill = $prefill;
        }

        if (!$async) {
            $props->nonce = wp_create_nonce(Runner::runnerNonce($form->id));

            if (!empty($form->definition)) {
                $props->definition = Migration::definition($form, Helpers::stringToJSON(Helpers::get($form, "definition")));
            }

            if ($pausable) {
                $token = "";

                if (!empty($_REQUEST["tripetto-resume"])) {
                    $token = $_REQUEST["tripetto-resume"];
                } elseif (!empty($_REQUEST["tripetto"])) {
                    $token = $_REQUEST["tripetto"];
                }

                if (!empty($token)) {
                    $snapshot = $wpdb->get_row(
                        $wpdb->prepare("SELECT snapshot,reference from {$wpdb->prefix}tripetto_snapshots where reference=%s", $token)
                    );

                    if (!is_null($snapshot) && !empty($snapshot->snapshot)) {
                        $props->snapshot = Helpers::stringToJSON($snapshot->snapshot);
                        $props->snapshotToken = $snapshot->reference;
                    }
                }
            }

            $props->styles = apply_filters(
                "tripetto_styles",
                Helpers::stringToJSON(Helpers::get($form, "styles"), Migration::styles($form, $props->runner)),
                clone $form,
                $props->language
            );

            if (!$props->pro && isset($props->styles->noBranding) && $props->styles->noBranding) {
                $props->styles->noBranding = false;
            }

            $props->l10n = Helpers::stringToJSON(Helpers::get($form, "l10n"), Migration::l10n($form));

            unset($props->l10n->language);
        }

        return $props;
    }

    static function trackers($form, $embed = true)
    {
        $trackers = [];
        $src = Helpers::stringToJSON(Helpers::get($form, "trackers"));
        $formProps =
            ", form: form.name, id: '" . strval($form->id) . "', reference: '" . $form->reference . "', fingerprint: form.fingerprint";
        $blockProps = ", block: block.name, key: block.id";

        if (
            isset($src->ga) &&
            is_object($src->ga) &&
            isset($src->ga->enabled) &&
            !empty($src->ga->enabled) &&
            ((isset($src->ga->id) && !empty($src->ga->id)) || ($embed && isset($src->ga->useGlobal) && !empty($src->ga->useGlobal)))
        ) {
            $useGlobal = $embed && isset($src->ga->useGlobal) && $src->ga->useGlobal;
            $gtm = ($useGlobal && empty($src->ga->id)) || stripos($src->ga->id, "GTM-") !== false;

            $gaTracker = "function() {";

            if (!$useGlobal) {
                if ($gtm) {
                    $gaTracker .=
                        "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" .
                        esc_attr($src->ga->id) .
                        "');";
                } else {
                    $gaTracker .=
                        "(function(d,s,u,e,h){e=d.createElement(s);h=d.getElementsByTagName(s)[0];e.async=1;e.src=u;h.parentNode.insertBefore(e,h)})(document,'script','https://www.googletagmanager.com/gtag/js?id=" .
                        esc_attr($src->ga->id) .
                        "&l=tripettoGTAG');";
                    $gaTracker .= "window.tripettoGTAG = window.tripettoGTAG || [];";
                    $gaTracker .= "function tripettoGTAGHelper(){tripettoGTAG.push(arguments);}";
                    $gaTracker .= "tripettoGTAGHelper('js', new Date());";
                    $gaTracker .= "tripettoGTAGHelper('config', '" . esc_attr($src->ga->id) . "');";
                }
            } elseif (!$gtm) {
                $gaTracker .= "function tripettoGTAGHelper(){dataLayer.push(arguments);}";
            }

            if ($gtm || $useGlobal) {
                $gaTracker .= "window.dataLayer = window.dataLayer || [];";
            }

            $gaTracker .= "return function(event, form, block) {";
            $gaTracker .= "switch(event){";

            if ($gtm) {
                if (isset($src->ga->trackStart) && $src->ga->trackStart) {
                    $gaTracker .=
                        "case 'start': dataLayer.push({ event: 'tripetto_start', description: 'Form is started.'" .
                        $formProps .
                        " }); break;";
                }

                if (isset($src->ga->trackStart) && $src->ga->trackStage) {
                    $gaTracker .=
                        "case 'stage': dataLayer.push({ event: 'tripetto_stage', description: 'Form block becomes available.'" .
                        $formProps .
                        $blockProps .
                        " }); break;";
                }

                if (isset($src->ga->trackStart) && $src->ga->trackUnstage) {
                    $gaTracker .=
                        "case 'unstage': dataLayer.push({ event: 'tripetto_unstage', description: 'Form block becomes unavailable.'" .
                        $formProps .
                        $blockProps .
                        " }); break;";
                }

                if (isset($src->ga->trackStart) && $src->ga->trackFocus) {
                    $gaTracker .=
                        "case 'focus': dataLayer.push({ event: 'tripetto_focus', description: 'Form input element gained focus.'" .
                        $formProps .
                        $blockProps .
                        " }); break;";
                }

                if (isset($src->ga->trackStart) && $src->ga->trackBlur) {
                    $gaTracker .=
                        "case 'blur': dataLayer.push({ event: 'tripetto_blur', description: 'Form input element lost focus.'" .
                        $formProps .
                        $blockProps .
                        " }); break;";
                }

                if (isset($src->ga->trackStart) && $src->ga->trackPause) {
                    $gaTracker .=
                        "case 'pause': dataLayer.push({ event: 'tripetto_pause', description: 'Form is paused.'" .
                        $formProps .
                        " }); break;";
                }

                if (isset($src->ga->trackStart) && $src->ga->trackComplete) {
                    $gaTracker .=
                        "case 'complete': dataLayer.push({ event: 'tripetto_complete', description: 'Form is completed.'" .
                        $formProps .
                        " }); break;";
                }
            } else {
                if (isset($src->ga->trackStart) && $src->ga->trackStart) {
                    $gaTracker .=
                        "case 'start': tripettoGTAGHelper('event', 'tripetto_start', { description: 'Form is started.'" .
                        $formProps .
                        " }); break;";
                }

                if (isset($src->ga->trackStart) && $src->ga->trackStage) {
                    $gaTracker .=
                        "case 'stage': tripettoGTAGHelper('event', 'tripetto_stage', { description: 'Form block becomes available.'" .
                        $formProps .
                        $blockProps .
                        " }); break;";
                }

                if (isset($src->ga->trackStart) && $src->ga->trackUnstage) {
                    $gaTracker .=
                        "case 'unstage': tripettoGTAGHelper('event', 'tripetto_unstage', { description: 'Form block becomes unavailable.'" .
                        $formProps .
                        $blockProps .
                        " }); break;";
                }

                if (isset($src->ga->trackStart) && $src->ga->trackFocus) {
                    $gaTracker .=
                        "case 'focus': tripettoGTAGHelper('event', 'tripetto_focus', { description: 'Form input element gained focus.'" .
                        $formProps .
                        $blockProps .
                        " }); break;";
                }

                if (isset($src->ga->trackStart) && $src->ga->trackBlur) {
                    $gaTracker .=
                        "case 'blur': tripettoGTAGHelper('event', 'tripetto_blur', { description: 'Form input element lost focus.'" .
                        $formProps .
                        $blockProps .
                        " }); break;";
                }

                if (isset($src->ga->trackStart) && $src->ga->trackPause) {
                    $gaTracker .=
                        "case 'pause': tripettoGTAGHelper('event', 'tripetto_pause', { description: 'Form is paused.'" .
                        $formProps .
                        " }); break;";
                }

                if (isset($src->ga->trackStart) && $src->ga->trackComplete) {
                    $gaTracker .=
                        "case 'complete': tripettoGTAGHelper('event', 'tripetto_complete', { description: 'Form is completed.'" .
                        $formProps .
                        " }); break;";
                }
            }

            $gaTracker .= "}";
            $gaTracker .= "}";
            $gaTracker .= "}";

            array_push($trackers, $gaTracker);
        }

        if (
            isset($src->fb) &&
            is_object($src->fb) &&
            isset($src->fb->enabled) &&
            !empty($src->fb->enabled) &&
            ((isset($src->fb->id) && !empty($src->fb->id)) || ($embed && isset($src->fb->useGlobal) && !empty($src->fb->useGlobal)))
        ) {
            $useGlobal = $embed && isset($src->fb->useGlobal) && $src->fb->useGlobal;
            $fbTracker = "function() {";

            if (!$useGlobal) {
                $fbTracker .=
                    "!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');";
                $fbTracker .= "fbq('init', '" . esc_attr($src->fb->id) . "');";
                $fbTracker .= "fbq('track', 'PageView');";
            }

            $fbTracker .= "return function(event, form, block) {";
            $fbTracker .= "switch(event){";

            if (isset($src->fb->trackStart) && $src->fb->trackStart) {
                $fbTracker .=
                    "case 'start': fbq('trackCustom', 'TripettoStart', { description: 'Form is started.'" . $formProps . " }); break;";
            }

            if (isset($src->fb->trackStart) && $src->fb->trackStage) {
                $fbTracker .=
                    "case 'stage': fbq('trackCustom', 'TripettoStage', { description: 'Form block becomes available.'" .
                    $formProps .
                    $blockProps .
                    " }); break;";
            }

            if (isset($src->fb->trackStart) && $src->fb->trackUnstage) {
                $fbTracker .=
                    "case 'unstage': fbq('trackCustom', 'TripettoUnstage', { description: 'Form block becomes unavailable.'" .
                    $formProps .
                    $blockProps .
                    " }); break;";
            }

            if (isset($src->fb->trackStart) && $src->fb->trackFocus) {
                $fbTracker .=
                    "case 'focus': fbq('trackCustom', 'TripettoFocus', { description: 'Form input element gained focus.'" .
                    $formProps .
                    $blockProps .
                    " }); break;";
            }

            if (isset($src->fb->trackStart) && $src->fb->trackBlur) {
                $fbTracker .=
                    "case 'blur': fbq('trackCustom', 'TripettoBlur', { description: 'Form input element lost focus.'" .
                    $formProps .
                    $blockProps .
                    " }); break;";
            }

            if (isset($src->fb->trackStart) && $src->fb->trackPause) {
                $fbTracker .=
                    "case 'pause': fbq('trackCustom', 'TripettoPause', { description: 'Form is paused.'" . $formProps . " }); break;";
            }

            if (isset($src->fb->trackStart) && $src->fb->trackComplete) {
                $fbTracker .=
                    "case 'complete': fbq('trackCustom', 'TripettoComplete', { description: 'Form is completed.'" .
                    $formProps .
                    " }); break;";
            }

            $fbTracker .= "}";
            $fbTracker .= "}";
            $fbTracker .= "}";

            array_push($trackers, $fbTracker);
        }

        if (
            isset($src->custom) &&
            is_object($src->custom) &&
            isset($src->custom->enabled) &&
            !empty($src->custom->enabled) &&
            isset($src->custom->code) &&
            !empty($src->custom->code) &&
            is_string($src->custom->code)
        ) {
            array_push($trackers, $src->custom->code);
        }

        return $trackers;
    }

    static function script($props, $trackers, $placeholder = "", $closure = false)
    {
        $tracking = "";

        if (is_array($trackers) && License::isInProMode()) {
            foreach ($trackers as $tracker) {
                if (!empty($tracker)) {
                    $verify = str_replace("\t", "", str_replace("\r", "", str_replace("\n", "", str_replace(" ", "", $tracker))));

                    if (strpos($verify, "function(){") === 0 && substr($verify, -1) === "}") {
                        if (!empty($tracking)) {
                            $tracking .= ",";
                        }

                        $tracking .= $tracker;
                    }
                }
            }

            if (!empty($tracking)) {
                $tracking = ",[" . $tracking . "]";
            }
        }

        $script = "<div id=\"" . $props->element . "\">" . $placeholder . "</div>";
        $script .= "<script>";

        if ($closure) {
            $script .= "function " . str_replace("-", "_", $props->element) . "(){";
        }

        $script .= "(function(t,r,i,p){";
        $script .= "var a=i.getElementById(t.element),";
        $script .= 'b=t.bundles.map(function(s){return t.uri+s+".js?ver=' . $GLOBALS["TRIPETTO_PLUGIN_VERSION"] . '"}),';
        $script .= "c=function(d){";
        $script .= "d.forEach(function(e){";
        $script .= "if(!r[e]){";
        $script .= "var b=i.createElement(p);";
        $script .= "r[e]=true;";
        $script .= "b.src=e;";
        $script .= "a.parentNode.insertBefore(b,a)";
        $script .= "}";
        $script .= "})";
        $script .= "};";
        $script .=
            "(function(f){f(f)})(function(f){c([b[0]]);typeof r[t.namespaces[0]]!==\"undefined\"&&t.namespaces.filter(function(s){c(b);return typeof r[s]===\"undefined\"}).length===0?WPTripettoRunner.run(t" .
            $tracking .
            "):setTimeout(function(){f(f)},1)});";
        $script .= "})(" . Helpers::JSONToString($props) . ',window,document,"script");';

        if ($closure) {
            $script .= "}";
        }

        $script .= "</script>";
        $script .=
            "<noscript>" .
            __(
                "Normally you should see a Tripetto form over here, but it needs JavaScript to run properly and it seems that is disabled in your browser. Please enable JavaScript to see and use the form.",
                "tripetto"
            ) .
            "</noscript>";

        return $script;
    }

    static function shortcode($atts)
    {
        extract(
            $atts = shortcode_atts(
                [
                    "id" => "0",
                    "mode" => "inline",
                    "pausable" => "no",
                    "persistent" => "no",
                    "css" => "",
                    "width" => "",
                    "height" => "",
                    "async" => "",
                    "placeholder" => "",
                    "message" => "",
                ],
                $atts,
                "tripetto"
            )
        );

        $id = !empty($atts["id"]) ? intval($atts["id"]) : 0;
        $async = !empty($atts["async"]) && $atts["async"] === "no" ? false : true;
        $fullPage = !empty($atts["mode"]) && ($atts["mode"] === "page" || $atts["mode"] === "overlay") ? true : false;
        $pausable = !empty($atts["pausable"]) && ($atts["pausable"] === "yes" || $atts["pausable"] === "true") ? true : false;
        $persistent = !empty($atts["persistent"]) && ($atts["persistent"] === "yes" || $atts["persistent"] === "true") ? true : false;
        $css = !empty($atts["css"]) ? urldecode($atts["css"]) : "";
        $width = !empty($atts["width"]) ? $atts["width"] : "";
        $height = !empty($atts["height"]) ? $atts["height"] : "";
        $placeholder = !empty($atts["placeholder"])
            ? urldecode($atts["placeholder"])
            : (!empty($atts["message"])
                ? urldecode($atts["message"])
                : "");

        if (!empty($id)) {
            global $wpdb;

            $form = $wpdb->get_row($wpdb->prepare("SELECT * from {$wpdb->prefix}tripetto_forms where id=%d", $id));

            if (!is_null($form)) {
                return Runner::script(
                    Runner::props($form, $async, $fullPage, $pausable, $persistent, $css, $width, $height),
                    Runner::trackers($form),
                    $placeholder
                );
            }
        }

        return is_user_logged_in() ? "Invalid shortcode!" : "";
    }

    static function isDate($field)
    {
        return $field->type == "@tripetto/block-date" || $field->type == "tripetto-block-date";
    }

    static function parseFieldValue($field, $origin)
    {
        if (empty($field->string) && $field->string != "0") {
            return "";
        }

        if (PHP_INT_SIZE >= 8 && Runner::isDate($field)) {
            if (strpos($field->string, " ") !== false) {
                return date_i18n(get_option("date_format") . " " . get_option("time_format"), intval($field->value) / 1000);
            }

            return date_i18n(get_option("date_format"), intval($field->value) / 1000);
        }

        if (Attachments::isAttachment($field)) {
            if (empty($field->reference)) {
                return "";
            }

            return admin_url(
                "admin.php?action=tripetto-attachment&reference=" .
                    urlencode($field->reference) .
                    "&origin=" .
                    urlencode($origin) .
                    "&filename=" .
                    urlencode($field->string)
            );
        }

        return $field->string;
    }

    static function parseFieldsToContent($dataset, $origin)
    {
        $content = "";

        foreach ($dataset->fields as $field) {
            if (!empty($field->name)) {
                $value = Runner::parseFieldValue($field, $origin);

                if (!empty($value) || $value == "0") {
                    if (Attachments::isAttachment($field)) {
                        $content .= sprintf(
                            '<p><b>%s</b><br><a href="%s" target="_blank">%s</a></p>',
                            nl2br(esc_html($field->name)),
                            esc_html($value),
                            esc_html($field->string)
                        );
                    } else {
                        $content .= sprintf("<p><b>%s</b><br>%s</p>", nl2br(esc_html($field->name)), nl2br(esc_html($value)));
                    }
                }
            }
        }

        return $content;
    }

    static function exportablesStencil($exportables)
    {
        $hash = hash("sha256", "stencil:exportables");

        foreach ($exportables->fields as $field) {
            $hash = hash("sha256", $hash . $field->node->id . $field->datatype . $field->slot . $field->node->context);
        }

        return $hash == $exportables->stencil ? $hash : hash("sha256", $hash . $exportables->stencil);
    }

    static function actionablesStencil($actionables)
    {
        $hash = hash("sha256", "stencil:actionables");

        if (!empty($actionables)) {
            foreach ($actionables->nodes as $node) {
                foreach ($node->data as $data) {
                    $hash = hash("sha256", $hash . $node->node->id . $data->datatype . $data->slot . $node->node->context);
                }
            }

            if ($actionables->stencil != $hash) {
                return hash("sha256", $hash . $actionables->stencil);
            }
        }

        return $hash;
    }

    static function checksum($exportables, $actionables)
    {
        $hash = hash("sha256", $exportables->fingerprint . Runner::exportablesStencil($exportables));

        foreach ($exportables->fields as $field) {
            $hash = hash(
                "sha256",
                $hash .
                    $field->key .
                    (isset($field->time) ? strval($field->time) : "") .
                    Helpers::limitString($field->string, 4096) .
                    (isset($field->reference) && is_string($field->reference) ? Helpers::limitString($field->reference, 4096) : "")
            );
        }

        if (!empty($actionables)) {
            $hash = hash("sha256", $hash . $actionables->fingerprint . Runner::actionablesStencil($actionables));

            foreach ($actionables->nodes as $node) {
                $hash = hash("sha256", $hash . $node->key);

                foreach ($node->data as $data) {
                    $hash = hash(
                        "sha256",
                        $hash .
                            $data->key .
                            (isset($data->time) ? strval($data->time) : "") .
                            Helpers::limitString($data->string, 4096) .
                            (isset($data->reference) && is_string($data->reference) ? Helpers::limitString($data->reference, 4096) : "")
                    );
                }
            }
        }

        return $hash;
    }

    static function dataset($exportables, $id, $index)
    {
        $dataset = Helpers::createJSON();

        $dataset->id = $id;
        $dataset->index = $index;
        $dataset->created = str_replace("+00:00", ".000Z", gmdate(DATE_ATOM));
        $dataset->fingerprint = $exportables->fingerprint;
        $dataset->fields = $exportables->fields;

        return $dataset;
    }

    static function powUInt32LeftShift($a, $b)
    {
        if (PHP_INT_SIZE >= 8) {
            return ($a << $b) & 0xffffffff;
        }

        return $a << $b;
    }

    static function powReadUInt32($buffer, $offset)
    {
        $result =
            Runner::powUInt32LeftShift($buffer[$offset], 24) |
            Runner::powUInt32LeftShift($buffer[$offset + 1], 16) |
            Runner::powUInt32LeftShift($buffer[$offset + 2], 8) |
            $buffer[$offset + 3];

        if (PHP_INT_SIZE >= 8) {
            return $result & 0xffffffff;
        }

        return $result;
    }

    static function powVerifyDifficulty($hash, $difficulty)
    {
        $buffer = [];
        $size = strlen($hash) / 2;
        $offset = 0;
        $i = 0;

        if ($difficulty >= $size * 8) {
            return false;
        }

        for (; $i < $size; $i++) {
            array_push($buffer, intval($hash[$i * 2] . $hash[$i * 2 + 1], 16));
        }

        for ($i = 0; $i <= $difficulty - 8; $i += 8, $offset++) {
            if ($offset >= $size || $buffer[$offset] !== 0) {
                return false;
            }
        }

        return $offset < $size && ($buffer[$offset] & Runner::powUInt32LeftShift(0xff, 8 + $i - $difficulty)) == 0;
    }

    static function powVerify($nonce, $difficulty, $validity, $checksum, $id)
    {
        if (strlen($nonce) < 16 || strlen($nonce) > 64 || strlen($nonce) % 16) {
            return false;
        }

        $buffer = [];
        $size = strlen($nonce) / 2;

        for ($i = 0; $i < $size; $i++) {
            array_push($buffer, intval($nonce[$i * 2] . $nonce[$i * 2 + 1], 16));
        }

        $age = time() + 1 - ((PHP_INT_SIZE >= 8 ? Runner::powReadUInt32($buffer, 0) * 0x100000000 : 0) + Runner::powReadUInt32($buffer, 4));

        if ($validity > 0 && ($age < 0 || $age > $validity)) {
            return false;
        }

        if (Runner::powVerifyDifficulty(hash("sha256", $checksum . $id . $nonce), $difficulty)) {
            return true;
        }

        return false;
    }

    static function announce()
    {
        $reference = !empty($_POST["reference"]) ? $_POST["reference"] : "";
        $nonce = !empty($_POST["nonce"]) ? $_POST["nonce"] : "";
        $fingerprint = !empty($_POST["fingerprint"]) ? $_POST["fingerprint"] : "";
        $checksum = !empty($_POST["checksum"]) ? $_POST["checksum"] : "";

        if (Helpers::isValidSHA256($reference) && Helpers::isValidSHA256($fingerprint) && Helpers::isValidSHA256($checksum)) {
            global $wpdb;

            $form = $wpdb->get_row(
                $wpdb->prepare("SELECT id,fingerprint from {$wpdb->prefix}tripetto_forms where reference=%s", $reference)
            );

            if (
                !is_null($form) &&
                (empty($nonce) || wp_verify_nonce($nonce, Runner::runnerNonce($form->id))) &&
                (empty($form->fingerprint) || $form->fingerprint == $fingerprint)
            ) {
                $mode = Runner::getProtectionMode();
                $signature = Runner::getSignature($fingerprint);
                $timestamp = time();
                $difficulty = 10;

                switch ($mode) {
                    case "maximal":
                        $difficulty = 16;
                        break;
                    case "minimal":
                        $difficulty = 6;
                        break;
                    case "off":
                        $difficulty = 1;
                        break;
                }

                if ($mode != "off" && $mode != "minimal") {
                    $difficulty += intval(
                        $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT COUNT(id) FROM {$wpdb->prefix}tripetto_entries WHERE form_id=%d AND signature=%s AND created > (NOW() - INTERVAL 10 MINUTE)",
                                $form->id,
                                $signature
                            )
                        )
                    );
                }

                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}tripetto_announcements WHERE created<%d", $timestamp - 60 * 60));

                if ($mode != "off" && $mode != "minimal") {
                    $pending = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT difficulty FROM {$wpdb->prefix}tripetto_announcements WHERE signature=%s AND created>%d",
                            $signature,
                            $timestamp - 10 * 60
                        )
                    );

                    if (count($pending) > 0) {
                        foreach ($pending as $pendingAnnouncement) {
                            $difficulty = max($difficulty + 2, $pendingAnnouncement->difficulty + 2);
                        }
                    }
                }

                $wpdb->insert($wpdb->prefix . "tripetto_announcements", [
                    "form_id" => $form->id,
                    "fingerprint" => $fingerprint,
                    "checksum" => $checksum,
                    "difficulty" => $difficulty,
                    "signature" => $signature,
                    "created" => $timestamp,
                ]);

                if (!empty($wpdb->insert_id)) {
                    $nonce = wp_create_nonce("tripetto:runner:submit:" . $wpdb->insert_id);

                    if (!empty($nonce)) {
                        $wpdb->update($wpdb->prefix . "tripetto_announcements", ["nonce" => $nonce], ["id" => $wpdb->insert_id]);

                        header("Content-Type: application/json");

                        $announcement = Helpers::createJSON();
                        $announcement->id = $nonce;
                        $announcement->difficulty = $difficulty;
                        $announcement->timestamp = $timestamp;

                        http_response_code(200);

                        echo Helpers::JSONToString($announcement);

                        return die();
                    }
                }
            } else {
                http_response_code(409);

                die();
            }
        }

        http_response_code(400);

        die();
    }

    static function submit()
    {
        $announcementId = !empty($_POST["id"]) ? $_POST["id"] : "";
        $nonce = !empty($_POST["nonce"]) ? $_POST["nonce"] : "";
        $language = !empty($_POST["language"]) ? $_POST["language"] : "";
        $locale = !empty($_POST["locale"]) ? $_POST["locale"] : "";
        $exportables = !empty($_POST["exportables"]) ? wp_unslash($_POST["exportables"]) : "";
        $actionables = !empty($_POST["actionables"]) ? wp_unslash($_POST["actionables"]) : "";

        if (
            !empty($announcementId) &&
            !empty($nonce) &&
            Helpers::isValidJSON($exportables) &&
            (empty($actionables) || Helpers::isValidJSON($actionables))
        ) {
            global $wpdb;

            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}tripetto_announcements WHERE created<%d", time() - 60 * 60));

            $announcement = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$wpdb->prefix}tripetto_announcements WHERE nonce=%s", $announcementId)
            );

            if (!is_null($announcement)) {
                $form = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}tripetto_forms WHERE id={$announcement->form_id}");

                if (!is_null($form)) {
                    $mode = Runner::getProtectionMode();
                    $exportables = Helpers::stringToJSON($exportables);
                    $actionables = !empty($actionables) ? Helpers::stringToJSON($actionables) : null;
                    $checksum = Runner::checksum($exportables, $actionables);

                    if (
                        Helpers::isValidSHA256($announcement->fingerprint) &&
                        $announcement->fingerprint == $exportables->fingerprint &&
                        Helpers::isValidSHA256($exportables->stencil) &&
                        (empty($form->stencil) || $exportables->stencil == $form->stencil) &&
                        (!$actionables ||
                            ($actionables->fingerprint == $announcement->fingerprint &&
                                Helpers::isValidSHA256($actionables->stencil) &&
                                (empty($form->actionables) || $actionables->stencil == $form->actionables))) &&
                        $checksum == $announcement->checksum &&
                        ($mode == "off" || Runner::powVerify($nonce, $announcement->difficulty, 60 * 15, $checksum, $announcementId))
                    ) {
                        $index = intval($form->indx) + 1;

                        if ($index <= 1) {
                            $count = intval(
                                $wpdb->get_var(
                                    $wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}tripetto_entries WHERE form_id=%d", $form->id)
                                )
                            );

                            $last = intval(
                                $wpdb->get_var(
                                    $wpdb->prepare(
                                        "SELECT indx FROM {$wpdb->prefix}tripetto_entries WHERE form_id=%d AND indx>0 ORDER BY indx DESC LIMIT 1",
                                        $form->id
                                    )
                                )
                            );

                            $index = max($count, $last) + 1;
                        }

                        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}tripetto_announcements WHERE nonce=%s", $announcementId));

                        $dataset = Runner::dataset($exportables, $announcementId, $index);
                        $datastring = Helpers::JSONToString($dataset);

                        if (strlen($datastring) < 4294967295) {
                            if (empty($form->fingerprint)) {
                                $wpdb->update(
                                    $wpdb->prefix . "tripetto_forms",
                                    [
                                        "fingerprint" => $exportables->fingerprint,
                                        "stencil" => Runner::exportablesStencil($exportables),
                                        "actionables" => Runner::actionablesStencil($actionables),
                                    ],
                                    ["id" => $form->id]
                                );
                            }

                            $migrateEntries = $wpdb->get_row(
                                $wpdb->prepare(
                                    "SELECT fingerprint FROM {$wpdb->prefix}tripetto_entries WHERE stencil=%s AND form_id=%d AND created>%d ORDER BY created DESC",
                                    "",
                                    $form->id,
                                    date_create($form->modified)->getTimestamp()
                                )
                            );

                            if (!is_null($migrateEntries) && !empty($migrateEntries->fingerprint)) {
                                $wpdb->update(
                                    $wpdb->prefix . "tripetto_entries",
                                    [
                                        "fingerprint" => $exportables->fingerprint,
                                        "stencil" => $exportables->stencil,
                                    ],
                                    [
                                        "form_id" => $form->id,
                                        "fingerprint" => $migrateEntries->fingerprint,
                                        "stencil" => "",
                                    ]
                                );
                            }

                            $wpdb->insert($wpdb->prefix . "tripetto_entries", [
                                "form_id" => $form->id,
                                "reference" => $announcementId,
                                "indx" => $index,
                                "entry" => $datastring,
                                "fingerprint" => $exportables->fingerprint,
                                "stencil" => $exportables->stencil,
                                "signature" => $announcement->signature,
                                "lang" => substr($language, 0, 5),
                                "locale" => substr($locale, 0, 5),
                                "created" => date("Y-m-d H:i:s"),
                            ]);

                            $id = intval($wpdb->insert_id);

                            if (!empty($id)) {
                                $wpdb->update($wpdb->prefix . "tripetto_forms", ["indx" => $index], ["id" => $form->id]);

                                if (!empty($_POST["snapshot"])) {
                                    $wpdb->query(
                                        $wpdb->prepare(
                                            "DELETE FROM {$wpdb->prefix}tripetto_snapshots WHERE reference=%s",
                                            $_POST["snapshot"]
                                        )
                                    );
                                }

                                Attachments::validate($exportables, $id);
                                Runner::actions($form, $actionables, $id, $dataset);

                                if (!Runner::hooks($form, $id, $dataset)) {
                                    do_action("tripetto_submit", $dataset, $form);
                                }

                                Helpers::cleanOutputBuffer();

                                header("Content-Type: application/json");

                                $response = Helpers::createJSON();
                                $response->id = $announcementId;

                                http_response_code(200);

                                echo Helpers::JSONToString($response);

                                return die();
                            }
                        }
                    } else {
                        http_response_code(403);

                        die();
                    }
                }
            }
        }

        http_response_code(400);

        die();
    }

    static function handlebars($content, $dataset)
    {
        $content = str_replace("{{tripetto.index}}", strval($dataset->index), $content);
        $content = str_replace("{{tripetto.id}}", $dataset->id, $content);

        return $content;
    }

    static function actions($form, $actionables, $id, $dataset)
    {
        if (!empty($actionables)) {
            foreach ($actionables->nodes as $node) {
                switch ($node->type) {
                    case "@tripetto/block-mailer":
                    case "tripetto-block-mailer":
                        $recipientField = array_filter($node->data, function ($data) {
                            return $data->slot == "recipient";
                        });
                        $recipient = count($recipientField) == 1 ? reset($recipientField)->string : "";

                        $subjectField = array_filter($node->data, function ($data) {
                            return $data->slot == "subject";
                        });
                        $subject = count($subjectField) == 1 ? Runner::handlebars(reset($subjectField)->string, $dataset) : "";
                        $messageField = array_filter($node->data, function ($data) {
                            return $data->slot == "message";
                        });
                        $message = count($messageField) == 1 ? Runner::handlebars(reset($messageField)->string, $dataset) : "";

                        $senderField = array_filter($node->data, function ($data) {
                            return $data->slot == "sender";
                        });
                        $sender = count($senderField) == 1 ? reset($senderField)->string : "";

                        $includeFields = array_filter($node->data, function ($data) {
                            return $data->slot == "data";
                        });
                        $includeFields = count($includeFields) == 1 && !empty(reset($includeFields)->value);

                        if (!empty($recipient) && !empty($subject)) {
                            Mailer::send(
                                $recipient,
                                $subject,
                                Template::render("mailer.php", [
                                    "message" => nl2br(esc_html($message)),
                                    "fields" => !empty($includeFields) ? Runner::parseFieldsToContent($dataset, "block") : "",
                                    "footer" => License::hasProFeatures($form->id)
                                        ? ""
                                        : Template::render("footer.php", [
                                            "recipient" => esc_html($recipient),
                                            "url" => Helpers::pluginUrl(),
                                        ]),
                                ]),
                                $sender
                            );
                        }
                        break;
                }
            }
        }
    }

    static function hooks($form, $id, $dataset)
    {
        $hooks = Helpers::stringToJSON(Helpers::get($form, "hooks"), Migration::hooks($form));

        if (is_object($hooks)) {
            $formName = !empty($form->name) ? $form->name : __("Unnamed form", "tripetto");

            if (
                isset($hooks->email) &&
                is_object($hooks->email) &&
                isset($hooks->email->enabled) &&
                !empty($hooks->email->enabled) &&
                isset($hooks->email->recipient) &&
                !empty($hooks->email->recipient)
            ) {
                $url = Helpers::pluginUrl();

                Mailer::send(
                    $hooks->email->recipient,
                    /* translators: %1$s is replaced with the form name and %2$d is replaced with the form index */
                    sprintf(__('New submission from %1$s (#%2$d)', "tripetto"), $formName, $dataset->index),
                    Template::render("notification.php", [
                        "name" => esc_html($formName),
                        "recipient" => esc_html($hooks->email->recipient),
                        "url" => $url,
                        "index" => strval($dataset->index),
                        "fields" => !empty($hooks->email->includeFields) ? Runner::parseFieldsToContent($dataset, "email") : "",
                        "footer" => License::hasProFeatures($form->id)
                            ? ""
                            : Template::render("footer.php", [
                                "recipient" => esc_html($hooks->email->recipient),
                                "url" => $url,
                            ]),
                        "viewUrl" => sprintf("%s?page=tripetto-forms&action=view&id=%d", admin_url("admin.php"), $id),
                    ])
                );
            }

            if (License::hasProFeatures($form->id)) {
                if (
                    isset($hooks->slack) &&
                    is_object($hooks->slack) &&
                    isset($hooks->slack->enabled) &&
                    !empty($hooks->slack->enabled) &&
                    isset($hooks->slack->url) &&
                    !empty($hooks->slack->url) &&
                    filter_var($hooks->slack->url, FILTER_VALIDATE_URL)
                ) {
                    $message = Helpers::createJSON();

                    /* translators: %1$d is replaced with the form index and %2$s is replaced with the form name */
                    $message->text = sprintf(__('New submission `#%1$d` for `%2$s`', "tripetto"), $dataset->index, $formName);
                    $message->username = "Tripetto";
                    $message->icon_url = Helpers::pluginUrl() . "/assets/tripetto.png";

                    if (!empty($hooks->slack->includeFields)) {
                        $attachments = [];

                        foreach ($dataset->fields as $field) {
                            $value = Runner::parseFieldValue($field, "slack");

                            if (!empty($value) || $value == "0") {
                                array_push($attachments, [
                                    "title" => $field->name,
                                    "value" => $value,
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

                    wp_safe_remote_post($hooks->slack->url, [
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
                }
            }

            Runner::service("make", $form, $hooks, $dataset, true);
            Runner::service("integromat", $form, $hooks, $dataset, true);
            Runner::service("zapier", $form, $hooks, $dataset, true);
            Runner::service("pabbly", $form, $hooks, $dataset, true);

            return Runner::service("webhook", $form, $hooks, $dataset);
        }

        return false;
    }

    static function service($service, $form, $hooks, $dataset, $nvp = false)
    {
        if (
            isset($hooks->{$service}) &&
            is_object($hooks->{$service}) &&
            isset($hooks->{$service}->enabled) &&
            !empty($hooks->{$service}->enabled) &&
            isset($hooks->{$service}->url) &&
            !empty($hooks->{$service}->url) &&
            filter_var($hooks->{$service}->url, FILTER_VALIDATE_URL)
        ) {
            if ($nvp || !empty($hooks->{$service}->nvp)) {
                $data = Helpers::createJSON();

                foreach ($dataset->fields as $field) {
                    if (!empty($field->name)) {
                        $value = Runner::parseFieldValue($field, $service);

                        if (!empty($value) || $value == "0") {
                            $name = $field->name;
                            $counter = 1;

                            while (!empty($data->{$name})) {
                                $counter++;
                                $name = $field->name . "(" . $counter . ")";
                            }

                            $data->{$name} = $value;
                        }
                    }
                }

                $data->tripettoId = $dataset->id;
                $data->tripettoIndex = $dataset->index;
                $data->tripettoCreateDate = $dataset->created;
                $data->tripettoFingerprint = $dataset->fingerprint;
                $data->tripettoFormReference = $form->reference;

                if (!empty($form->name)) {
                    $data->tripettoFormName = $form->name;
                }
            } else {
                $data = clone $dataset;
            }

            if ($service === "webhook") {
                do_action("tripetto_submit", $dataset, $form);

                $filteredData = apply_filters("tripetto_webhook", $data, $form, clone $dataset);
                $data = is_object($filteredData) ? Helpers::JSONToString($filteredData) : strval($filteredData);
            } else {
                $data = Helpers::JSONToString($data);
            }

            wp_remote_post($hooks->{$service}->url, [
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

            return $service === "webhook";
        }

        return false;
    }

    static function pause()
    {
        $reference = !empty($_POST["reference"]) ? $_POST["reference"] : "";
        $nonce = !empty($_POST["nonce"]) ? $_POST["nonce"] : "";
        $url = !empty($_POST["url"]) ? $_POST["url"] : "";
        $emailAddress = !empty($_POST["emailAddress"]) ? $_POST["emailAddress"] : "";
        $snapshot = !empty($_POST["snapshot"]) ? wp_unslash($_POST["snapshot"]) : "";

        if (
            Helpers::isValidSHA256($reference) &&
            !empty($emailAddress) &&
            filter_var($emailAddress, FILTER_VALIDATE_EMAIL) &&
            strlen($emailAddress) < 65535 &&
            !empty($url) &&
            filter_var($url, FILTER_VALIDATE_URL) &&
            strlen($url) < 65535 &&
            strlen($snapshot) < 4294967295 &&
            Helpers::isValidJSON($snapshot)
        ) {
            global $wpdb;

            $form = $wpdb->get_row(
                $wpdb->prepare("SELECT id,definition from {$wpdb->prefix}tripetto_forms where reference=%s", $reference)
            );

            if (!is_null($form) && (empty($nonce) || wp_verify_nonce($nonce, Runner::runnerNonce($form->id)))) {
                $reference = "";

                if (!empty($_POST["token"])) {
                    $current = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT reference,id from {$wpdb->prefix}tripetto_snapshots where reference=%s AND form_id=%d",
                            $_POST["token"],
                            $form->id
                        )
                    );

                    if (!empty($current)) {
                        $reference = $current->reference;

                        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}tripetto_snapshots WHERE id=%d", $current->id));
                    }
                }

                $wpdb->insert($wpdb->prefix . "tripetto_snapshots", [
                    "form_id" => $form->id,
                    "reference" => $reference,
                    "snapshot" => $snapshot,
                    "url" => "",
                    "email" => $emailAddress,
                    "created" => date("Y-m-d H:i:s"),
                ]);

                $snapshotId = intval($wpdb->insert_id);

                if (!empty($snapshotId)) {
                    if (empty($reference)) {
                        $reference = hash("sha256", wp_create_nonce(Runner::runnerNonce($snapshotId)) . ":" . strval($snapshotId));
                    }

                    $n = strpos($url, "tripetto=");

                    if ($n !== false) {
                        $url = substr($url, 0, $n + 9) . $reference;
                    } else {
                        $n = strpos($url, "tripetto-resume=");

                        if ($n !== false) {
                            $url = substr($url, 0, $n + 16) . $reference;
                        } else {
                            $url .= (strpos($url, "?") !== false ? "&" : "?") . "tripetto-resume={$reference}";
                        }
                    }

                    $wpdb->update($wpdb->prefix . "tripetto_snapshots", ["reference" => $reference, "url" => $url], ["id" => $snapshotId]);

                    Mailer::send(
                        $emailAddress,
                        __("Resume your form with this magic link", "tripetto"),
                        Template::render("snapshot.php", [
                            "url" => $url,
                            "footer" => License::hasProFeatures($form->id)
                                ? ""
                                : Template::render("footer.php", [
                                    "recipient" => esc_html($emailAddress),
                                    "url" => Helpers::pluginUrl(),
                                ]),
                        ])
                    );

                    $dataset = Helpers::createJSON();

                    $dataset->id = $snapshotId;
                    $dataset->form = $form->id;
                    $dataset->reference = $reference;
                    $dataset->url = $url;
                    $dataset->emailAddress = $emailAddress;

                    do_action("tripetto_pause", $dataset);

                    http_response_code(200);

                    return die();
                }
            }
        }

        http_response_code(400);

        die();
    }

    static function standalone()
    {
        if (isset($_REQUEST["tripetto"]) && Helpers::isValidSHA256($_REQUEST["tripetto"])) {
            global $wpdb;

            $form = $wpdb->get_row($wpdb->prepare("SELECT * from {$wpdb->prefix}tripetto_forms where reference=%s", $_REQUEST["tripetto"]));

            header("Cache-Control: no-store, max-age=0");
            header("Pragma: no-cache");

            if (is_null($form)) {
                $snapshot = $wpdb->get_row(
                    $wpdb->prepare("SELECT form_id from {$wpdb->prefix}tripetto_snapshots where reference=%s", $_REQUEST["tripetto"])
                );

                if (!is_null($snapshot)) {
                    $form = $wpdb->get_row($wpdb->prepare("SELECT * from {$wpdb->prefix}tripetto_forms where id=%d", $snapshot->form_id));
                }
            }

            if (!is_null($form)) {
                $props = Runner::props($form);
                $description = "";
                $keywords = "";
                $language = "";
                $url = home_url() . "?tripetto=" . $form->reference;

                if (!empty($props->definition)) {
                    if (!empty($props->definition->description)) {
                        $description = strval($props->definition->description);
                    }

                    if (!empty($props->definition->keywords)) {
                        $keywords = implode(",", $props->definition->keywords);
                    }

                    if (!empty($props->definition->language)) {
                        $language = strval($props->definition->language);
                    }
                }

                echo "<!DOCTYPE html>";
                echo "<html" . (!empty($language) ? ' lang="' . esc_attr($language) . '"' : "") . ">";
                echo "<head>";
                echo '<meta charset="UTF-8" />';
                echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" />';
                echo '<meta http-equiv="X-UA-Compatible" content="IE=edge" />';
                echo '<meta name="robots" content="noindex">';

                if (!empty($description)) {
                    echo '<meta name="description" content="' . esc_attr($description) . '" />';
                }

                if (!empty($keywords)) {
                    echo '<meta name="keywords" content="' . esc_attr($keywords) . '" />';
                }

                echo '<meta property="og:url" content="' . esc_url($url) . '" />';

                if (!empty($form->name)) {
                    echo '<meta property="og:title" content="' . esc_attr($form->name) . '" />';
                }

                if (!empty($description)) {
                    echo '<meta property="og:description" content="' . esc_attr($description) . '" />';
                }

                if (!empty($form->name)) {
                    echo "<title>" . esc_html($form->name) . "</title>";
                }

                echo "</head>";
                echo '<body style="margin:0;overflow:hidden;">';

                echo Runner::script($props, Runner::trackers($form, false));

                echo "</body>";
                echo "</html>";

                die();
            }
        }
    }

    static function register($plugin)
    {
        register_activation_hook($plugin, ["Tripetto\Runner", "activate"]);

        add_action("wp_ajax_tripetto_announce", ["Tripetto\Runner", "announce"]);
        add_action("wp_ajax_nopriv_tripetto_announce", ["Tripetto\Runner", "announce"]);
        add_action("wp_ajax_tripetto_submit", ["Tripetto\Runner", "submit"]);
        add_action("wp_ajax_nopriv_tripetto_submit", ["Tripetto\Runner", "submit"]);
        add_action("wp_ajax_tripetto_pause", ["Tripetto\Runner", "pause"]);
        add_action("wp_ajax_nopriv_tripetto_pause", ["Tripetto\Runner", "pause"]);
        add_action("init", ["Tripetto\Runner", "standalone"]);
        add_action("admin_enqueue_scripts", ["Tripetto\Runner", "scripts"]);

        add_shortcode("tripetto", ["Tripetto\Runner", "shortcode"]);
    }
}
?>
