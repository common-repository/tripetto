<?php
namespace Tripetto;

class Template
{
    static function render($file, $replacements)
    {
        $template = dirname(__FILE__) . "/../templates/" . $file;

        if (!file_exists($template)) {
            return false;
        }

        ob_start();
        include $template;
        $body = ob_get_contents();
        ob_end_clean();

        foreach ($replacements as $key => $value) {
            $body = str_replace("{{" . $key . "}}", $value, $body);
        }

        return $body;
    }
}
?>
