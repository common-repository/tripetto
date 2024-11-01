<?php
namespace Tripetto;

class Templates
{
    static function getTemplateReference($id)
    {
        return hash("sha256", "template-" . $id);
    }

    static function addTemplate($id, $name, $runner, $isPro)
    {
        $url = Helpers::pluginUrl();
        $description = __("Autoscroll form", "tripetto");
        $thumbnail = 'url(\'' . $url . "/admin/templates/" . $id . '/thumbnail.jpg\')';
        $createURL = "#";

        if (!$isPro || !tripetto_fs()->is_not_paying()) {
            $createURL = "?page=tripetto-forms&action=create&runner=" . $runner . "&template=" . Templates::getTemplateReference($id);
        }

        switch ($runner) {
            case "chat":
                $description = __("Chat form", "tripetto");
                break;
            case "classic":
                $description = __("Classic form", "tripetto");
                break;
        }

        // prettier-ignore
        return '<li class="wp-tripetto-template-tile">' .
            '<div class="wp-tripetto-template-top" style="background-image: ' . $thumbnail . ';">' .
                '<div class="wp-tripetto-template-buttons">' .
                    '<a href="' . $createURL . '"' .
                        ($isPro && tripetto_fs()->is_not_paying()
                            ? ' class="wp-tripetto-template-disabled"'
                            : "") .
                        ">" .
                        __("Use Template", "tripetto") .
                        '</a>' .
                    '<span onclick="WPTripetto.previewTemplate(\'' . $runner . '\',\'' . $url . '\',\'' . $id . '\');">' . __("Preview", "tripetto") . '</span>' .
                '</div>' .
                    ($isPro && tripetto_fs()->is_not_paying()
                        ? '<div class="wp-tripetto-template-pro"><a href="' .
                            tripetto_fs()->get_upgrade_url() .
                            '">' .
                            __("Pro Template", "tripetto") .
                            "</a></div>"
                        : "") .
            '</div>' .
            '<div class="wp-tripetto-template-bottom wp-tripetto-template-' . $runner . '">' .
                '<span>' . $description . '</span>' .
                '<h3>' . $name . '</h3>' .
            '</div>' .
        '</li>';
    }

    static function page()
    {
        if (Onboarding::assert()) {
            return;
        }

        wp_enqueue_style("wp-tripetto");
        wp_enqueue_script("wp-tripetto");

        // prettier-ignore
        echo '
            <div id="wp-tripetto-templates" style="display: none;">
                <div class="wp-tripetto-templates-scratch">
                    <h2>' . __("Start from scratch...", "tripetto") . '</h2>
                    <p>' . __("Choose your desired form face and start building right away. You can always switch form faces instantly while working on your form.", "tripetto") . '</p>
                    <p>' . __("Select your desired form type.", "tripetto") . '</p>
                    <ul>
                        <li>
                            <a href="?page=tripetto-forms&action=create&runner=autoscroll" class="wp-tripetto-templates-autoscroll">
                                <h3>' . __("Autoscroll Form", "tripetto") . '</h3>
                                <span>' . __("Fluently presents one question at a time.", "tripetto") . '</span>
                            </a>
                        </li>
                        <li>
                            <a href="?page=tripetto-forms&action=create&runner=chat" class="wp-tripetto-templates-chat">
                                <h3>' . __("Chat Form", "tripetto") . '</h3>
                                <span>' . __("Presents all questions and answers as a chat.", "tripetto") . '</span>
                            </a>
                        </li>
                        <li>
                            <a href="?page=tripetto-forms&action=create&runner=classic" class="wp-tripetto-templates-classic">
                                <h3>' . __("Classic Form", "tripetto") . '</h3>
                                <span>' . __("Presents question fields in a traditional format.", "tripetto") . '</span>
                            </a>
                        </li>
                    </ul>
                    <small>' .
                    /* translators: %1$s is replaced with `help center` and %2$s is replaced with `form faces` */
                    sprintf(__('Visit the %1$s for tips & tricks about %2$s.', 'tripetto'), '<a href="https://tripetto.com/wordpress/help/styling-and-customizing/" target="_blank">' . __('help center', 'tripetto') . '</a>', '<a href="https://tripetto.com/form-layouts/" target="_blank">' . __('form faces', 'tripetto') . '</a>') . '</small>
                </div>
                <div class="wp-tripetto-templates-raster">
                    <h2>' . __("Or build from a template.", "tripetto") . '</h2>
                    <p>' . __("Choose any of the templates below to build your form even faster. Templates are fully editable and customizable of course.", "tripetto") . '</p>
                    <ul>' .
                        Templates::addTemplate("contact-us", __("Contact Us", "tripetto"), "classic", false) .
                        Templates::addTemplate("support-request", __("Support Request", "tripetto"), "autoscroll", true) .
                        Templates::addTemplate("product-evaluation", __("Product Evaluation", "tripetto"), "chat", false) .
                        Templates::addTemplate("quiz", __("Quiz", "tripetto"), "autoscroll", true) .
                        Templates::addTemplate("quote-request", __("Quote Request", "tripetto"), "autoscroll", true) .
                        Templates::addTemplate("feedback-collection", __("Feedback Collection", "tripetto"), "chat", false) .
                        Templates::addTemplate("job-application", __("Job Application", "tripetto"), "autoscroll", false) .
                        Templates::addTemplate("event-registration", __("Event Registration", "tripetto"), "classic", false) .
                        Templates::addTemplate("customer-satisfaction", __("Customer Satisfaction (NPS)", "tripetto"), "autoscroll", false) .
                        Templates::addTemplate("webshop-order", __("Webshop Order", "tripetto"), "classic", true) .
                        Templates::addTemplate("wedding-rsvp", __("Wedding RSVP", "tripetto"), "chat", false) .
                        Templates::addTemplate("calculation-wizard", __("Calculation Wizard", "tripetto"), "chat", true) .
                    '</ul>
                </div>
            </div>';

        wp_add_inline_script("wp-tripetto", "WPTripetto.templates();");

        Header::generate(
            __("Build New Form", "tripetto"),
            "?page=tripetto-forms",
            null,
            null,
            '<a href="?page=tripetto-forms" class="wp-tripetto-header-button wp-tripetto-header-button-danger">' .
                __("Cancel", "tripetto") .
                "</a>"
        );
    }

    static function retrieve($reference)
    {
        $templates = scandir(__DIR__);

        foreach ($templates as $key => $id) {
            if (!empty($id) && Templates::getTemplateReference($id) == $reference) {
                $definition = @file_get_contents(__DIR__ . "/" . $id . "/definition.json");
                $styles = @file_get_contents(__DIR__ . "/" . $id . "/styles.json");

                if (!empty($definition) && !empty($styles)) {
                    $template = Helpers::createJSON();
                    $template->definition = Helpers::stringToJSON($definition);
                    $template->styles = Helpers::stringToJSON($styles);
                    $template->name = !empty($template->definition->name) ? $template->definition->name : "";

                    return $template;
                }
            }
        }

        return false;
    }
}
?>
