<?php
namespace Tripetto;

if (!defined("WPINC")) {
    die();
}

if (!defined("ABSPATH")) {
    die();
}
?>
<table cellspacing="0" cellpadding="0" style="width: 100%; border-top: 1px solid #e5eef5; padding-top: 16px; padding-bottom: 16px;">
  <tr>
    <td style="padding-right: 16px;">
      <a href="https://tripetto.com" target="_blank">
        <img src="{{url}}/assets/tripetto.png" width="24" height="24" style="border: 0;" />
      </a>
    </td>
    <td style="width: 100%;">
      <div style="display: block; font-size: 12px; color: #3f536e; line-height: 18px;">
        <?php esc_html_e("Sent to", "tripetto"); ?>
        <a href="#" style="color: #3f536e; text-decoration: none;font-weight: bold;">{{recipient}}</a>
        <?php esc_html_e("by", "tripetto"); ?>
        <a href="https://tripetto.com" target="_blank" style="color: #3f536e; text-decoration: underline;">Tripetto</a>
      </div>
      <div style="display: block; font-size: 12px; color: #8dabc4; line-height: 18px;">
        <?php esc_html_e("Get conversational!", "tripetto"); ?>
      </div>
    </td>
  </tr>
</table>
