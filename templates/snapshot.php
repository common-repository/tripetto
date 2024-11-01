<?php
namespace Tripetto;

if (!defined("WPINC")) {
    die();
}

if (!defined("ABSPATH")) {
    die();
}
?>
<html>
  <body>
  <h1 style="font-size: 24px; color: #2c405a; line-height: 32px; margin: 0;">
    <?php esc_html_e("Form paused", "tripetto"); ?>
  </h1>
    <p style="font-size: 16px; color: #3f536e; line-height: 24px;">
      <?php esc_html_e("You took a break while filling out the form. Sweet!", "tripetto"); ?>
      <br />
      <?php esc_html_e("Click the link below at any time to continue right where you left off.", "tripetto"); ?>
    </p>
    <p>
      <a href="{{url}}" style="font-size: 16px; color: #0093ee; text-transform: underline;">
        <?php esc_html_e("Resume your form", "tripetto"); ?>
      </a>
    </p>
    {{footer}}
  </body>
</html>
