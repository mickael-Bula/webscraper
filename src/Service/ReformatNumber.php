<?php

namespace App\Service;

class ReformatNumber
{
    public static function fromString(string $stringNumber): float
    {
        $flatNumber = str_replace('.', '', $stringNumber);
        return str_replace(',', '.', $flatNumber);
    }
    
    public static function fromNumber(float $flatNumber): string
    {
        return number_format($flatNumber, 2, ',', ' ');
    }
}