<?php

namespace App\Service;

use Symfony\Component\BrowserKit\HttpBrowser;

class DataScraper
{
    public static function getData(): array
    {
        $client = new HttpBrowser();    
        $crawler = $client->request('GET', 'https://fr.investing.com/indices/france-40-historical-data');
    
        $rawData = $crawler
            // je filtre le document pour ne récupérer que le contenu du tableau qui m'intéresse
            ->filter('#curr_table > tbody > tr > td')
            ->each(function ($node) {
                return $node->text('rien à afficher');
            });
    
        // la fonction array_chunk() divise le tableau passé en paramètre avec une taille fixée par le second
        $splittedData = array_chunk($rawData, 7);
    
        // je boucle sur les résultats pour ne récupérer que les données utiles
        foreach($splittedData as $chunck) {
            $data[] = array_slice($chunck, 0, 5);
        }
        // on retourne le tableau inversé car les mises à jour viendront compléter les enregistrements, donc à la fin
        return array_reverse($data);
    }
}