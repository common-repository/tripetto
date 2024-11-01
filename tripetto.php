<?php
namespace Tripetto;

switch (!empty($_GET["t"]) ? $_GET["t"] : "") {
    case "locale":
        require_once __DIR__ . "/lib/locale.php";

        Locale::process();
        break;
    case "translation":
        require_once __DIR__ . "/lib/translation.php";

        Translation::process();
        break;
    default:
        require_once __DIR__ . "/lib/loader.php";

        Loader::process();
        break;
}
?>
