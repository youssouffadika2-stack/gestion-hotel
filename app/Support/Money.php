<?php

namespace App\Support;

class Money
{
    /** 1250000 => "1 250 000 FCFA" (le FCFA n'a pas de décimales). */
    public static function fcfa(int|float|null $amount): string
    {
        return number_format((int) $amount, 0, ',', ' ').' FCFA';
    }
}
