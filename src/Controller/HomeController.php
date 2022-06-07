<?php

namespace App\Controller;

use App\Repository\CacRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\DataScraper;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="app_home")
     */
    public function index(CacRepository $cacRepository): Response
    {
        // récupération des données par le scraper
        $data = DataScraper::getData();

        // récupération de lastDate en BDD
        $lastDate = $cacRepository->findLastDate();
        if ( !empty($lastDate)) {
            $lastDate = $lastDate[0]->getCreatedAt();
            $lastDate = $lastDate->format('d/m/Y');
        } else {
            $lastDate = null;
        }

        // tri des entrées postérieures à lastDate
        $newData = [];
        foreach ($data as $row) {
            if ( $lastDate !== $row[0]) {
                $newData[] = $row;
            }
            else {
                break;
            }
        }

        // inversion du tableau pour que les nouvelles entrées soient ordonnées chronologiquement et insertion en BDD
        $cacRepository->saveData(array_reverse($newData));

        // je crée une portion de tableau pour affichage
        $displayData = array_slice($data, 0, 10);

        return $this->render('home/index.html.twig', compact('displayData', 'lastDate'));
    }
}
