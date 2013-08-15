<?php
/**
 * Created by JetBrains PhpStorm.
 * User: christian.manalansan
 * Date: 8/15/13
 * Time: 8:02 AM
 * To change this template use File | Settings | File Templates.
 */

/**
 * take a string and convert it to a corresponding integer
 */
function integer_hash($in, $start = FALSE, $end = FALSE) {
    $range = $end - $start + 1;
    $out = md5($in);
    $out = base_convert($out, 16, 10);
    $out = substr($out, 0, 10); // make sure the number is not too big
    if ($start === FALSE && $end === FALSE) {
        return $out;
    }
    $out = $out % $range;
    return $out + $start;
}