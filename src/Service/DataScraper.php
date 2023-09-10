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
            ->filter('table > tbody > tr > td')
            ->each(function ($node) {
                return $node->text('rien à afficher');
            });
        
        // la fonction array_chunk() divise le tableau passé en paramètre avec une taille fixée par le second
        $splitData = array_chunk($rawData, 7);

        // on trie $splitData en vérifiant que le premier indice est une date au format jj/mm/aaaa
        $splitData = array_filter($splitData, fn($row) => preg_match("/^\d{2}\/\d{2}\/\d{4}$/", $row[0]));

        // si l'on est un jour de semaine (lundi=1...) ET qu'il est moins de 18h, on considère le marché ouvert
        $isOpen = in_array(date('w'), range(1, 5)) && date("G") <= "18";

        // si le marché est ouvert, je supprime la valeur du jour courant du tableau de résultats
        if ($isOpen) {
            array_splice($splitData, 0, 1);
        }

        // je filtre le tableau de résultats pour ne récupérer que les données utiles (date, closing, opening, higher, lower)
        return array_map(fn($chunk) => array_slice($chunk, 0, 5), $splitData);
    }
}