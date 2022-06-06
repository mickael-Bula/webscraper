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
        // je lance le scraper à la récupération des données
        $scraper = new DataScraper();

        // le tableau récupéré est inversé pour faciliter les insertions suivantes
        $data = $scraper->getData();

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

        // enregistrement en BDD des données triées précédemment
        $cacRepository->saveNewData($newData);

        return $this->render('home/index.html.twig', [
            'data' => array_reverse($data), // j'inverse à nouveau le tableau pour une présentation dans la vue par actualité
            'lastDate' => $lastDate,
        ]);
    }
}
