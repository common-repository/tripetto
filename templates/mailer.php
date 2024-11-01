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
    <p style="font-size: 16px; line-height: 24px;">
      {{message}}
    </p>
    {{fields}}
    {{footer}}
  </body>
</html>
