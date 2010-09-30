<?php
class Cli
{
    public static function catchUserInput($message = "Input: ", $default = null)
    {
        echo $message;
        $input = str_replace(PHP_EOL, '', fgets(STDIN));
        if ($default != null && empty($input)) {
            return $default;
        }
        return $input;
    }

    public static function renderLine($content)
    {
        echo $content."\n";
    }
}