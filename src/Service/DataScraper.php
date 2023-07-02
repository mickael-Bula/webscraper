<?php

namespace App\Service;

use Symfony\Component\BrowserKit\HttpBrowser;

class DataScraper
{
    public static function getData($stock): array
    {
        $client = new HttpBrowser();    
        $crawler = $client->request('GET', $stock);

        // on vérifie si le marché est fermé pour s'assurer de la pertinence de données à enregistrer
        $open = $crawler
            ->filterXPath('//*[@id="__next"]/div[2]/div/div/div[2]/main/div/div[1]/div[2]/div[2]/div[2]/span[2]')
            ->text("rien à afficher");

        // On évalue $open (true ou false) et on l'affecte à $isOpen
        $isOpen = $open == "Ouvert";

        // je filtre le document pour ne récupérer que le contenu du tableau qui m'intéresse
        $rawData = $crawler
            ->filter('table[data-test="historical-data-table"] > tbody > tr > td')
            ->each(function ($node) {
                return $node->text('rien à afficher');
            });
        
        // je sélectionne la portion de 'tbody.datatable_body__3EPFZ' qui m'intéresse (NOTE : il faut creuser pour sélectionner un enfant en particulier)
        // $rawData = array_slice($rawData, 10, 147); // Cette étape n'est plus utile à la date du 02/07/2023
        
        // la fonction array_chunk() divise le tableau passé en paramètre avec une taille fixée par le second
        $splitData = array_chunk($rawData, 7);

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