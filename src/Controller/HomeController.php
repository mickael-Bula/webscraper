<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\DataScraper;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="app_home")
     */
    public function index(): Response
    {
        // je lance le scraper à la récupération des données
        $scraper = new DataScraper();
        $data = $scraper->getData();
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'data' => $data,
        ]);
    }
}
