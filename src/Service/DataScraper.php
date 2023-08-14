<?php

namespace App\Service;

use Symfony\Component\BrowserKit\HttpBrowser;

class DataScraper
{
    public static function getData($stock): array
    {
        $client = new HttpBrowser();    
        $crawler = $client->request('GET', $stock);

        // je filtre le document pour ne récupérer que le contenu du tableau qui m'intéresse
        $rawData = $crawler
            ->filter('table[data-test="historical-data-table"] > tbody > tr > td')
            ->each(function ($node) {
                return $node->text('rien à afficher');
            });
        
        // la fonction array_chunk() divise le tableau passé en paramètre avec une taille fixée par le second
        $splitData = array_chunk($rawData, 7);

        // si l'on est un jour de semaine (lundi=1...) ET qu'il est moins de 18h, on considère le marché ouvert
        $isOpen = in_array(date('w'), range(1, 5)) && date("G") <= "18";

        // si le marché est ouvert, je supprime la valeur du jour courant du tableau de résultats
        if ($isOpen) {
            array_splice($splitData, 0, 1);
        }

        // je boucle sur les résultats pour ne récupérer que les données utiles (date, closing, opening, higher, lower)
        $data = [];
        foreach($splitData as $chunk) {
            $data[] = array_slice($chunk, 0, 5);
        }
        // on retourne le tableau
        return $data;
    }
}