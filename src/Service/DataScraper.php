<?php

namespace App\Service;

use Symfony\Component\BrowserKit\HttpBrowser;

class DataScraper
{
    public static function getData(): array
    {
        $client = new HttpBrowser();    
        $crawler = $client->request('GET', 'https://fr.investing.com/indices/france-40-historical-data');

        // on vérifie si le marché est fermé pour s'assurer de la pertinence de données à enregistrer
        $greenClockIcon = $crawler
            ->filter('.greenClockBigIcon')  // l'icône est présente quand le marché est ouvert
            ->each(function($node) { return $node; });   // je récupère un tableau de nodes

        // si le tableau est vide le marché est fermé (false), sinon il est ouvert (true)
        $isOpen = count($greenClockIcon) > 0;

        // je filtre le document pour ne récupérer que le contenu du tableau qui m'intéresse
        $rawData = $crawler
            ->filter('#curr_table > tbody > tr > td')
            ->each(function ($node) {
                return $node->text('rien à afficher');
            });
    
        // la fonction array_chunk() divise le tableau passé en paramètre avec une taille fixée par le second
        $splitData = array_chunk($rawData, 7);

        // en fonction de l'état du marché, j'inclus ou non les données de la valeur du jour
        $splitData = $isOpen ? array_splice($splitData, 0, 1) : $splitData;

        // je boucle sur les résultats pour ne récupérer que les données utiles
        $data = [];
        foreach($splitData as $chunck) {
            $data[] = array_slice($chunck, 0, 5);
        }
        // on retourne le tableau
        return $data;
    }
}