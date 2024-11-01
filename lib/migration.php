<?php
namespace Tripetto;

class Migration
{
    static function apply($version)
    {
        if (version_compare($version, "3.0.4") < 0) {
            // Fingerprints may have changed in version 3.0.4 due to a change in the underlying algorithm.
            // We reset them here, so they will be regenerated when new submissions are processed.
            global $wpdb;

            $wpdb->query("UPDATE {$wpdb->prefix}tripetto_forms SET fingerprint='' WHERE stencil=''");
        }
    }

    static function definition($form, $definition)
    {
        if (
            empty($definition->builder) &&
            empty($definition->epilogue) &&
            (!empty($form->confirmation_title) ||
                !empty($form->confirmation_subtitle) ||
                !empty($form->confirmation_text) ||
                !empty($form->confirmation_image) ||
                !empty($form->confirmation_button_label) ||
                (!empty($form->confirmation_button_url) && filter_var($form->confirmation_button_url, FILTER_VALIDATE_URL)))
        ) {
            $definition->epilogue = Helpers::createJSON();

            if (!empty($form->confirmation_title)) {
                $definition->epilogue->title = $form->confirmation_title;
            }

            if (!empty($form->confirmation_subtitle) || !empty($form->confirmation_text)) {
                $definition->epilogue->description = "";

                if (!empty($form->confirmation_subtitle)) {
                    $definition->epilogue->description .=
                        (!empty($form->confirmation_text) ? "**" : "") .
                        $form->confirmation_subtitle .
                        (!empty($form->confirmation_text) ? "**\n" : "");
                }

                if (!empty($form->confirmation_text)) {
                    $definition->epilogue->description .= $form->confirmation_text;
                }
            }

            if (!empty($form->confirmation_image)) {
                $definition->epilogue->image = $form->confirmation_image;
            }

            if (
                !empty($form->confirmation_button_label) ||
                (!empty($form->confirmation_button_url) && filter_var($form->confirmation_button_url, FILTER_VALIDATE_URL))
            ) {
                $definition->epilogue->button = Helpers::createJSON();
                $definition->epilogue->button->label = !empty($form->confirmation_button_label) ? $form->confirmation_button_label : "";
                $definition->epilogue->button->url =
                    !empty($form->confirmation_button_url) && filter_var($form->confirmation_button_url, FILTER_VALIDATE_URL)
                        ? $form->confirmation_button_url
                        : "";
                $definition->epilogue->button->target = "self";
            }
        }

        $mailerMigration = Helpers::JSONToString($definition);

        if (
            strpos($mailerMigration, '"type":"tripetto-block-mailer"') !== false &&
            strpos($mailerMigration, '"actionable":true') === false
        ) {
            $mailerMigration = str_replace('"reference":"recipient",', '"reference":"recipient","actionable":true,', $mailerMigration);
            $mailerMigration = str_replace('"reference":"subject",', '"reference":"subject","actionable":true,', $mailerMigration);
            $mailerMigration = str_replace('"reference":"message",', '"reference":"message","actionable":true,', $mailerMigration);

            $definition = Helpers::stringToJSON($mailerMigration);
        }

        return $definition;
    }

    static function styles($form, $runner)
    {
        $styles = Helpers::createJSON();

        if (isset($form->collector_style) && Helpers::isValidJSON($form->collector_style)) {
            $old_styles = Helpers::stringToJSON($form->collector_style);

            $styles->contract = Helpers::createJSON();
            $styles->contract->name = $runner;
            $styles->contract->version = "0.0.0";

            if ($runner == "classic") {
                $styles->mode = "paginated";

                if (!empty($old_styles->mode)) {
                    $styles->mode = $old_styles->mode;
                }

                if (isset($old_styles->showPageIndicators) && is_bool($old_styles->showPageIndicators)) {
                    $styles->showPageIndicators = $old_styles->showPageIndicators ? "true" : "false";
                }
            }

            if (!empty($old_styles->textColor)) {
                $styles->color = $old_styles->textColor;
            }

            if (!empty($old_styles->textFont)) {
                $styles->font = Helpers::createJSON();
                $styles->font->family = Migration::convertFont($old_styles->textFont);
            }

            if (!empty($old_styles->backgroundColor) || (isset($old_styles->backgroundImage) && is_object($old_styles->backgroundImage))) {
                $styles->background = Helpers::createJSON();
                $styles->background->color = $old_styles->backgroundColor;

                if (isset($old_styles->backgroundImage) && is_object($old_styles->backgroundImage)) {
                    $styles->background->url = $old_styles->backgroundImage->url;
                    $styles->background->positioning = $old_styles->backgroundImage->size;
                }
            }

            if (isset($old_styles->showNavigation) && is_bool($old_styles->showNavigation)) {
                $styles->showNavigation = $old_styles->showNavigation ? "auto" : "never";
            }

            if (isset($old_styles->showProgressbar) && is_bool($old_styles->showProgressbar)) {
                $styles->showProgressbar = $old_styles->showProgressbar ? "true" : "false";
            }

            if (isset($old_styles->showEnumerators) && is_bool($old_styles->showEnumerators)) {
                $styles->showEnumerators = $old_styles->showEnumerators ? "true" : "false";
            }

            if (isset($old_styles->showScrollbar) && is_bool($old_styles->showScrollbar)) {
                $styles->showScrollbar = $old_styles->showScrollbar ? "true" : "false";
            }

            if (isset($old_styles->autoFocus) && is_bool($old_styles->autoFocus)) {
                $styles->autoFocus = $old_styles->autoFocus ? "true" : "false";
            }

            if (isset($old_styles->centerActiveBlock) && is_bool($old_styles->centerActiveBlock)) {
                $styles->verticalAlignment = $old_styles->centerActiveBlock ? "middle" : "top";
            }

            if (isset($old_styles->form) && is_object($old_styles->form)) {
                $styles->inputs = Helpers::createJSON();
                $styles->inputs->backgroundColor = "#fff";
                $styles->inputs->borderColor = !empty($old_styles->form->inputStyle)
                    ? Migration::convertBootstrapStyles($old_styles->form->inputStyle, true)
                    : "";
                $styles->inputs->borderSize = 1;
                $styles->inputs->textColor = "#000";
                $styles->inputs->errorColor = "#f00";
                $styles->inputs->agreeColor = !empty($old_styles->form->positiveStyle)
                    ? Migration::convertBootstrapStyles($old_styles->form->positiveStyle)
                    : "";
                $styles->inputs->declineColor = !empty($old_styles->form->negativeStyle)
                    ? Migration::convertBootstrapStyles($old_styles->form->negativeStyle)
                    : "";
                $styles->inputs->selectionColor = !empty($old_styles->form->selectedStyle)
                    ? Migration::convertBootstrapStyles($old_styles->form->selectedStyle)
                    : "";
            }

            if (isset($old_styles->buttons) && is_object($old_styles->buttons)) {
                $styles->buttons = Helpers::createJSON();
                $styles->buttons->baseColor = !empty($old_styles->buttons->okStyle)
                    ? Migration::convertBootstrapStyles($old_styles->buttons->okStyle)
                    : "";
                $styles->buttons->mode =
                    isset($old_styles->buttons->okStyle) &&
                    is_string($old_styles->buttons->okStyle) &&
                    strpos($old_styles->buttons->okStyle, "outline-") !== false
                        ? "outline"
                        : "fill";
                $styles->buttons->finishColor = !empty($old_styles->buttons->completeStyle)
                    ? Migration::convertBootstrapStyles($old_styles->buttons->completeStyle)
                    : "";
            }

            if (isset($old_styles->footer) && is_object($old_styles->footer)) {
                $styles->navigation = Helpers::createJSON();
                $styles->navigation->backgroundColor =
                    isset($old_styles->footer->backgroundColor) && is_string($old_styles->footer->backgroundColor)
                        ? $old_styles->footer->backgroundColor
                        : "";
                $styles->navigation->textColor =
                    isset($old_styles->footer->textColor) && is_string($old_styles->footer->textColor)
                        ? $old_styles->footer->textColor
                        : "";
                $styles->navigation->progressbarColor = !empty($old_styles->footer->progressbarStyle)
                    ? Migration::convertBootstrapStyles($old_styles->footer->progressbarStyle)
                    : "";
            }
        }

        if (isset($form->collector_remove_branding) && $form->collector_remove_branding >= 0) {
            $styles->noBranding = true;
        }

        return $styles;
    }

    static function l10n($form)
    {
        $l10n = Helpers::createJSON();

        if (isset($form->collector_style) && Helpers::isValidJSON($form->collector_style)) {
            $styles = Helpers::stringToJSON($form->collector_style);

            if (
                isset($styles->buttons) &&
                is_object($styles->buttons) &&
                (!empty($styles->buttons->okLabel) || !empty($styles->buttons->backLabel) || !empty($styles->buttons->completeLabel))
            ) {
                $l10n->language = "en";
                $l10n->translations = Helpers::createJSON();
                $l10n->translations->{"_empty_"} = Helpers::createJSON();
                $l10n->translations->{"_empty_"}->language = "en";

                if (!empty($styles->buttons->okLabel)) {
                    $l10n->translations->{"runner#1|🆗 Buttons\u{0004}Next"} = [null, $styles->buttons->okLabel];
                }

                if (!empty($styles->buttons->backLabel)) {
                    $l10n->translations->{"runner#1|🆗 Buttons\u{0004}Back"} = [null, $styles->buttons->backLabel];
                }

                if (!empty($styles->buttons->completeLabel)) {
                    $l10n->translations->{"runner#1|🆗 Buttons\u{0004}Submit"} = [null, $styles->buttons->completeLabel];
                }
            }
        }

        return $l10n;
    }

    static function hooks($form)
    {
        $hooks = Helpers::createJSON();

        if (isset($form->notification_email) && !empty($form->notification_email)) {
            $hooks->email = Helpers::createJSON();
            $hooks->email->enabled = true;
            $hooks->email->recipient = $form->notification_email;
            $hooks->email->includeFields = !empty($form->notification_email_include_data);
        }

        if (isset($form->notification_slack) && !empty($form->notification_slack)) {
            $hooks->slack = Helpers::createJSON();
            $hooks->slack->enabled = true;
            $hooks->slack->url = $form->notification_slack;
            $hooks->slack->includeFields = false;
        }

        if (isset($form->integration_zapier) && !empty($form->integration_zapier)) {
            $hooks->webhook = Helpers::createJSON();
            $hooks->webhook->enabled = true;
            $hooks->webhook->url = $form->integration_zapier;
            $hooks->webhook->nvp = false;
        }

        return $hooks;
    }

    static function convertFont($font)
    {
        switch ($font) {
            case "Arial, Helvetica, Sans-Serif":
                return "Arial";
            case "Arial Black, Gadget, Sans-Serif":
                return "Arial Black";
            case "Comic Sans MS, Textile, Cursive, Sans-Serif":
                return "Comic Sans MS";
            case "Courier New, Courier, Monospace":
                return "Courier New";
            case "Georgia, Times New Roman, Times, Serif":
                return "Georgia";
            case "Impact, Charcoal, Sans-Serif":
                return "Impact";
            case "Lucida Console, Monaco, Monospace":
                return "Lucida Console";
            case "Lucida Sans Unicode, Lucida Grande, Sans-Serif":
                return "Lucida Sans Unicode";
            case "Palatino Linotype, Book Antiqua, Palatino, Serif":
                return "Palatino";
            case "Tahoma, Geneva, Sans-Serif":
                return "Tahoma";
            case "Times New Roman, Times, Serif":
                return "Times New Roman";
            case "Trebuchet MS, Helvetica, Sans-Serif":
                return "Trebuchet MS";
            case "Verdana, Geneva, Sans-Serif":
                return "Verdana";
        }

        return "";
    }

    static function convertBootstrapStyles($style, $isBorder = false)
    {
        switch ($style) {
            case "primary":
            case "outline-primary":
                return "#007bff";
            case "secondary":
            case "outline-secondary":
                return "#6c757d";
            case "success":
            case "outline-success":
                return "#28a745";
            case "info":
            case "outline-info":
                return "#17a2b8";
            case "warning":
            case "outline-warning":
                return "#ffc107";
            case "danger":
            case "outline-danger":
                return "#dc3545";
            case "light":
            case "outline-light":
                return $isBorder ? "#ffffff" : "#f8f9fa";
            case "dark":
            case "outline-dark":
                return "#343a40";
        }

        return "";
    }
}
?>
