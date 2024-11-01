<?php
namespace Tripetto;

class Mailer
{
    static function send($to, $subject, $message, $replyTo = "")
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $headers = ["Content-Type: text/html; charset=UTF-8"];
        $name = "";
        $address = "";

        switch (get_option("tripetto_sender")) {
            case "admin":
                $name = get_bloginfo("name");
                $address = get_bloginfo("admin_email");
                break;
            case "custom":
                $name = get_option("tripetto_sender_name");
                $address = get_option("tripetto_sender_address");
                break;
        }

        if (!empty($address) && filter_var($address, FILTER_VALIDATE_EMAIL)) {
            if (!empty($name)) {
                array_push($headers, "From: " . $name . " <" . $address . ">");
            } else {
                array_push($headers, "From: " . $address);
            }
        }

        if (!empty($replyTo) && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            array_push($headers, "Reply-To: " . $replyTo);
        }

        return wp_mail($to, $subject, $message, $headers);
    }
}
?>
