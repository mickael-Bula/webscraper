<?php

namespace App\Service;

class ReformatNumber
{
    /**
     * conversion des données scrapées sous forme de chaîne vers le format float
     */
    public static function fromString(string $stringNumber): float
    {
        $flatNumber = str_replace('.', '', $stringNumber);
        $flatNumber = str_replace(',', '.', $flatNumber);
        return $flatNumber;
    }

    /**
     * conversion des données de la base en string
     */
    public static function fromNumber($flatNumber): string
    {
        return number_format($flatNumber, 2, ',', ' ');
    }
}