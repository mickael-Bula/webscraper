<?php

namespace App\Service;

class Utils
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

    /**
     * calculate the date which should be the most recent in the database 
     */
    public function getMostRecentDate()
    {
        // je récupère l'heure au format 24h depuis la timezone de Paris
        date_default_timezone_set('Europe/Paris');

        // je récupère le numéro du jour courant
        $day = date('w');

        // Avant 18:00 on considère que le marché est ouvert : le dernier jour complet est donc celui de la veille.
        // On tient également compte des jours de week end (non ouvrés)
        if (date("G") >= "18") {
            // $eves = ["numéro du jour dans la semaine" => "nombre de jours à retrancher pour obtenir la veille ouvrée"]
            $eves = ["0" => "2", "6" => "1"];
            $default = "0";
        } else {
            $eves = ["0" => "2", "1" => "3"];
            $default = "1";
        }
        $lastDay = array_key_exists($day, $eves) ? $eves[$day] : $default;

        // je recalcule la date pour tenir compte des contraintes précédentes
        $currentDate = new \DateTime();
        return $currentDate->sub(new \DateInterval("P{$lastDay}D"))->format("d/m/Y");
    }
}
