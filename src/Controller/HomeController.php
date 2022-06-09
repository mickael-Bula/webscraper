<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\DataScraper;
use App\Service\SaveDataInDatabase;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="app_home")
     */
    public function index(SaveDataInDatabase $saveDataInDatabase): Response
    {
        // récupération des données par le scraper
        $data = DataScraper::getData();
        
        // j'externalise l'insertion en BDD dans un service dédié
        $lastDate = $saveDataInDatabase->appendData($data);

        // je crée une portion de tableau pour affichage
        $displayData = array_slice($data, 0, 10);

        return $this->render('home/index.html.twig', compact('displayData', 'lastDate'));
    }
}
