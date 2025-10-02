<?php

if (!function_exists('fmt_number')) {
    function fmt_number($value): string
    {
        if ($value == null || $value == '') return '0';
        $s = number_format((float)$value, 4, '.', '');
        return rtrim(rtrim($s, '0'),'.');
    }
}

if (!function_exists('fmt_qty')){
    function fmt_qty($value, ?string $unit = null): string
    {
        $n = fmt_number($value);
        return $unit ? "{$n} {$unit}" : $n;
    }
}