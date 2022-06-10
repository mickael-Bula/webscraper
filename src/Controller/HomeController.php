<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
// use Symfony\Component\Routing\Annotation\Route;
use App\Service\DataScraper;
use App\Service\SaveDataInDatabase;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="app_home")
     */
    public function index(SaveDataInDatabase $saveDataInDatabase): Response
    {
        // get data from scraper
        $data = DataScraper::getData();
        
        // put database insertion in a dedicated service
        $lastDate = $saveDataInDatabase->appendData($data);

        // split array to display useful data only
        $displayData = array_slice($data, 0, 10);

        return $this->render('home/index.html.twig', compact('displayData', 'lastDate'));
    }
}
