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
      <?php esc_html_e("New submission", "tripetto"); ?>
    </h1>
    <p>
      <?php esc_html_e("A new submission (#{{index}}) was received for form", "tripetto"); ?> <b>{{name}}</b>.<br />
      <a href="{{viewUrl}}"><?php esc_html_e("View in WordPress", "tripetto"); ?></a>
    </p>
    {{fields}}
    {{footer}}
  </body>
</html>
