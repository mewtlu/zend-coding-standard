<?php

function (& $var) {
    $x = [];
    $y &=&$x;
    $z =& $y;
    $j = & $z;
    $v = [& $x];

    return [&$j];
}

function ($x, & $y) {
    $z = $x & $y;
}