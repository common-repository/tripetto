<?php
namespace Tripetto;

class Polyfill
{
    static function mb_substr($str, $start, $length = null)
    {
        if (function_exists("mb_substr")) {
            return mb_substr($str, $start, $length);
        }

        $chars = preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);

        return $chars !== false ? implode("", array_slice($chars, $start, $length)) : "";
    }

    static function mb_strpos($haystack, $needle, $offset = 0)
    {
        if (function_exists("mb_strpos")) {
            return mb_strpos($haystack, $needle, $offset);
        }

        if ($offset < 0) {
            $offset = self::mb_strlen($haystack) - $offset;
        }

        if ($offset < 0) {
            return false;
        }

        $haystack = preg_split("//u", $haystack, -1, PREG_SPLIT_NO_EMPTY);
        $needle = preg_split("//u", $needle, -1, PREG_SPLIT_NO_EMPTY);

        if ($haystack !== false && $needle !== false) {
            $haystackCount = count($haystack);
            $needleCount = count($needle);

            if ($needleCount <= $haystackCount) {
                for ($i = $offset; $i <= $haystackCount - $needleCount; $i++) {
                    $match = 0;

                    for ($j = 0; $j < $needleCount; $j++) {
                        if ($needle[$j] == $haystack[$i + $j]) {
                            $match++;

                            if ($match == $needleCount) {
                                return $i;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    static function mb_strlen($str)
    {
        if (function_exists("mb_strlen")) {
            return mb_strlen($str);
        }

        $chars = preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);

        return $chars !== false ? count($chars) : 0;
    }
}
?>
