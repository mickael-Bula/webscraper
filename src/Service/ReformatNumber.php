<?php

namespace App\Service;

class ReformatNumber
{
    /**
     * convert scraped data from string to float
     */
    public static function fromString(string $stringNumber): float
    {
        $flatNumber = str_replace('.', '', $stringNumber);
        return str_replace(',', '.', $flatNumber);
    }

    /**
     * convert database's data to string
     */
    public static function fromNumber($flatNumber): string
    {
        return number_format($flatNumber, 2, ',', ' ');
    }
}