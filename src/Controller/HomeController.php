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
        $data = $scraper->getData();

        // récupération de lastDate en BDD
        $lastDate = $cacRepository->findLastDate();

        // tri des entrées postérieures à lastDate
        foreach ($data as $row) {
            if ($lastDate !== $row[0]) {
                $newData[] = $row;
            }
            else {
                break;
            }
        }
        //! il faut encore que convertir les dates au même format pour comparaison
        //! il faut ajouter les chiffres derrière la virgule 

        // enregistrement en BDD des données triées précédemment
        $cacRepository->saveNewData($newData);

        return $this->render('home/index.html.twig', [
            'data' => $data,
            'lastDate' => $lastDate,
            'newData' => $newData,
        ]);
    }
}
