<?php
require("../src/exceptions.php");
require("../src/fields.php");
require("../src/scheme.php");

// get bytes array represented by $str (forms like '9e a7 05')
function string_to_bytes($str)
{
    return array_map('hexdec', explode(' ', $str));
}

// get binary string represented by byte array $bytes
function bytes_to_binary($bytes)
{
    $params = array_merge(['C*'], $bytes);
    return call_user_func_array('pack', $params);
}

function print_bytes($bytes)
{
    echo implode(" ", array_map(function($v){
        return sprintf("%02x", $v);
    }, $bytes)), "\n";
}

function array_vs_string($bytes, $string)
{
    return $bytes === string_to_bytes($string);
}