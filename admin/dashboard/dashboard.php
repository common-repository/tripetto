<?php
namespace Tripetto;

class Dashboard
{
    static function page()
    {
        if (Onboarding::assert()) {
            return;
        }

        wp_enqueue_style("wp-tripetto");
        wp_enqueue_script("wp-tripetto");

        $total = Forms::total();

        // prettier-ignore
        echo '
            <div id="wp-tripetto-dashboard" style="display: none;">
                <div class="wp-tripetto-container">
                    <div class="wp-tripetto-header">
                        <div class="wp-tripetto-header-logo"><span>' .
                        /* translators: %s is replaced with the active version (free or pro) */
                        sprintf(__('You are using the %s version', 'tripetto'), '<u>' . (tripetto_fs()->is_not_paying() ? '<a href="' . tripetto_fs()->get_upgrade_url() . '">' . __('Free', 'tripetto') . '</a>' : '<a href="' . tripetto_fs()->get_account_url() . '">' . __('Pro', 'tripetto') . '</a>') . '</u>') . '</span></div>
                        <div class="wp-tripetto-header-buttons">
                            <a href="?page=tripetto-onboarding" class="wp-tripetto-link-header">' . __('Onboarding', 'tripetto') . '</a>' .
                            (current_user_can("manage_options") ? '<a href="options-general.php?page=tripetto-settings" class="wp-tripetto-link-header">' . __('Settings', 'tripetto') . '</a>' : '') .
                            (tripetto_fs()->is_not_paying() ? '<a href="' . tripetto_fs()->get_upgrade_url() . '" class="wp-tripetto-button wp-tripetto-button-large">' . __('Upgrade', 'tripetto') . '</a>' : '') . '
                        </div>
                    </div>
                    <div class="wp-tripetto-hero">
                        <h1>' . __('Welcome to your Tripetto dashboard.', 'tripetto') . '</h1>
                        <p>' . __('Your fullblown form building and data collection solution. All inside of WordPress.', 'tripetto') . '</p>
                        <ul class="wp-tripetto-buttons">
                            ' . (Capabilities::createForms() ? '<li><a href="?page=tripetto-create" class="wp-tripetto-button">' . ($total > 0 ? __('Build Form', 'tripetto') : __('Build Your First Form', 'tripetto')) . '</a></li>' : '') . '
                            ' . ($total > 0 ? '<li><a href="?page=tripetto-forms" class="wp-tripetto-button' . (Capabilities::createForms() ? ' wp-tripetto-button-outline' : '') . '">' .
                            /* translators: %d is replaced with the number of available forms */
                            sprintf(__('All Forms (%d)', 'tripetto'), $total) . '</a></li>' : '') . '
                        </ul>
                        <small>' .
                        /* translators: %1$s is replaced with `onboarding wizard` and %2$s is replaced with `help center` */
                        sprintf(__('Open the %1$s for settings. Visit the %2$s for tips & tricks.', 'tripetto'), '<a href="?page=tripetto-onboarding">' . __('onboarding wizard', 'tripetto') . '</a>', '<a href="https://tripetto.com/wordpress/help/" target="_blank">' . __('help center', 'tripetto') . '</a>') . '</small>
                    </div>
                    <a href="https://www.youtube.com/watch?v=DWdLEmpt4Os" class="wp-tripetto-video" target="_blank">
                        <span>' . __('Kickstart your form building', 'tripetto') . '</span>
                        <h2>' . __('How to build a simple form', 'tripetto') . '</h2>
                        <div class="wp-tripetto-button wp-tripetto-button-red wp-tripetto-button-play" target="_blank">' . __('Watch Tutorial', 'tripetto') . '</div>
                    </a>
                    ' . (tripetto_fs()->is_not_paying() ? ('
                    <div class="wp-tripetto-pro">
                        <div class="wp-tripetto-pro-left">
                            <h2>' . __('Get Pro', 'tripetto') . '</h2>
                            <small>' . __('From $99 per year', 'tripetto') . '</small>
                            <p class="wp-tripetto-paragraph">' . __('Upgrade to Tripetto Pro for more advanced features and take your forms and surveys to another level.', 'tripetto') . '</p>
                            <a href="' . tripetto_fs()->get_upgrade_url() . '" class="wp-tripetto-button wp-tripetto-button-yellow">' . __('Upgrade to Pro', 'tripetto') . '</a>
                            <div><a href="https://tripetto.com/wordpress/pricing/?utm_source=wp_plugin&utm_medium=tripetto_platforms&utm_campaign=pro_upgrade&utm_content=dashboard" target="_blank" class="wp-tripetto-link-pricing">' . __('View pricing', 'tripetto') . '</a></div>
                        </div>
                        <div class="wp-tripetto-pro-right">
                            <h3>' . __('Tripetto Pro completes you.', 'tripetto') . '</h3>
                            <small>' . __('Pro unlocks the following additional features in Tripetto.', 'tripetto') . '</small>
                            <div class="wp-tripetto-pro-features">
                                <div>
                                    <h4>' . __('Action blocks', 'tripetto') . '</h4>
                                    <ul>
                                        <li>' . __('Calculator', 'tripetto') . '</li>
                                        <li>' . __('Custom variable', 'tripetto') . '</li>
                                        <li>' . __('Force stop', 'tripetto') . '</li>
                                        <li>' . __('Hidden field', 'tripetto') . '</li>
                                        <li>' . __('Raise error', 'tripetto') . '</li>
                                        <li>' . __('Send email', 'tripetto') . '</li>
                                        <li>' . __('Set value', 'tripetto') . '</li>
                                    </ul>
                                    <h4>' . __('Automations', 'tripetto') . '</h4>
                                    <ul>
                                        <li>' . __('Notifications (Slack etc.)', 'tripetto') . '</li>
                                        <li>' . __('Connect 1.000+ services', 'tripetto') . '</li>
                                        <li>' . __('Form activity tracking', 'tripetto') . '</li>
                                    </ul>
                                </div>
                                <div>
                                    <h4>' . __('Unbranding', 'tripetto') . '</h4>
                                    <ul>
                                        <li>' . __('No Tripetto branding', 'tripetto') . '</li>
                                    </ul>
                                    <h4>' . __('License options', 'tripetto') . '</h4>
                                    <ul>
                                        <li>' . __('Single-site', 'tripetto') . '</li>
                                        <li>' . __('5-Sites', 'tripetto') . '</li>
                                        <li>' . __('Unlimited sites', 'tripetto') . '</li>
                                    </ul>
                                    <h4>' . __('Service', 'tripetto') . '</h4>
                                    <ul>
                                        <li>' . __('Access to help center', 'tripetto') . '</li>
                                        <li>' . __('Priority support', 'tripetto') . '</li>
                                        <li>' . __('Updates and upgrades', 'tripetto') . '</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>') : '') .'
                    <div class="wp-tripetto-help">
                        <div class="wp-tripetto-help-search">
                            <h2>' . __('Help Center', 'tripetto') . '</h2>
                            <p class="wp-tripetto-paragraph">' . __('Need a helping hand? The help center offers how-to’s and video tutorials. Find answers directly or navigate through specific subjects.', 'tripetto') . '</p>
                            <form action="https://tripetto.com/wordpress/help/search/" method="get" target="_blank">
                                <input type="text" name="q" placeholder="' . __('Search help articles', 'tripetto') . '" />
                                <button type="submit" class="wp-tripetto-button">' . __('Search', 'tripetto') . '</button>
                            </form>
                        </div>
                        <div class="wp-tripetto-help-howtos">
                            <h3>' . __('How-To’s', 'tripetto') . '</h3>
                            <p>' . __('Browse help articles by subject to find everything about every feature.', 'tripetto') . '</p>
                            <div class="wp-tripetto-help-chapters">
                                <a href="https://tripetto.com/wordpress/help/building-forms-and-surveys/" target="_blank" class="wp-tripetto-help-chapter-build"><h4>' . __('Building forms and surveys', 'tripetto') . '</h4></a>
                                <a href="https://tripetto.com/wordpress/help/using-logic-features/" target="_blank" class="wp-tripetto-help-chapter-logic"><h4>' . __('Using logic features', 'tripetto') . '</h4></a>
                                <a href="https://tripetto.com/wordpress/help/styling-and-customizing/" target="_blank" class="wp-tripetto-help-chapter-customization"><h4>' . __('Styling and customizing', 'tripetto') . '</h4></a>
                                <a href="https://tripetto.com/wordpress/help/automating-things/" target="_blank" class="wp-tripetto-help-chapter-automations"><h4>' . __('Automating things', 'tripetto') . '</h4></a>
                                <a href="https://tripetto.com/wordpress/help/sharing-forms-and-surveys/" target="_blank" class="wp-tripetto-help-chapter-sharing"><h4>' . __('Sharing forms and surveys', 'tripetto') . '</h4></a>
                                <a href="https://tripetto.com/wordpress/help/managing-data-and-results/" target="_blank" class="wp-tripetto-help-chapter-hosting"><h4>' . __('Managing data and results', 'tripetto') . '</h4></a>
                            </div>
                            <div><a href="https://tripetto.com/wordpress/help/" target="_blank" class="wp-tripetto-link-help">' . __('Visit help center', 'tripetto') . '</a></div>
                        </div>
                        <div class="wp-tripetto-help-videos">
                            <h3>' . __('Video Tutorials', 'tripetto') . '</h3>
                            <p>' .
                            /* translators: %s is replaced with `Tripetto YouTube channel` */
                            sprintf(__('Watch these recommended tutorials to get started smartly. Visit the %s for many more video tutorials.', 'tripetto'), ('<a href="https://www.youtube.com/c/tripetto" target="_blank">' . __('Tripetto YouTube channel', 'tripetto') . '</a>')) . '</p>
                            <div class="wp-tripetto-help-thumbnails">
                                <a href="https://www.youtube.com/watch?v=DWdLEmpt4Os" target="_blank" class="wp-tripetto-help-thumbnail-build"></a>
                                <a href="https://www.youtube.com/watch?v=f9c7QG7x31w" target="_blank" class="wp-tripetto-help-thumbnail-logic"></a>
                                <a href="https://www.youtube.com/watch?v=FHhtFcQIXGA" target="_blank" class="wp-tripetto-help-thumbnail-customization"></a>
                                <a href="https://www.youtube.com/watch?v=WE5llYadunk" target="_blank" class="wp-tripetto-help-thumbnail-automations"></a>
                                <a href="https://www.youtube.com/watch?v=pRtYDMSUAfI" target="_blank" class="wp-tripetto-help-thumbnail-sharing"></a>
                                <a href="https://www.youtube.com/watch?v=uNpWG40ksWU" target="_blank" class="wp-tripetto-help-thumbnail-hosting"></a>
                            </div>
                            <div><a href="https://tripetto.com/wordpress/help/video-tutorials/" target="_blank" class="wp-tripetto-link-help">' . __('All video tutorials', 'tripetto') . '</a></div>
                        </div>
                    </div>
                </div>
            </div>
        ';

        wp_add_inline_script("wp-tripetto", "WPTripetto.dashboard();");
    }
}
?>
