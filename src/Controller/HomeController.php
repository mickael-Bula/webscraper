<?php

namespace App\Controller;

use App\Entity\Cac;
use App\Repository\CacRepository;
use App\Service\Utils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\DataScraper;
use App\Service\SaveDataInDatabase;
use Symfony\Component\HttpFoundation\RequestStack;

class HomeController extends AbstractController
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @Route("/", name="app_home")
     */
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    /**
     * @Route("/dashboard", name="app_dashboard")
     *
     * @param SaveDataInDatabase $saveDataInDatabase
     * @param CacRepository $cacRepository
     * @return Response
     */
    public function dashboard(SaveDataInDatabase $saveDataInDatabase, CacRepository $cacRepository): Response
    {
        // on commence par vérifier en session la présence des données du CAC, sinon on y charge celles-ci
        $session = $this->requestStack->getSession();
        // $session->clear();
        // die();
        if (!$session->has("cac")) {
            $cac = $cacRepository->findBy([], ['id' => 'DESC'], 10);
            $session->set("cac", $cac);
        }
        $cac = $session->get("cac");

        // je demande à un Service de calculer la date la plus récente qui devrait se trouver en base de données
        $recentDate = new Utils();
        $lastDate = $recentDate->getMostRecentDate();

        // je compare $lastDate avec la date la plus récente en session (et donc en BDD)
        $lastDateInSession = $cac[0]->getCreatedAt()->format("d/m/Y");

        // si les dates ne correspondent pas, je lance le scraping pour récupérer les données manquantes
        if ($lastDate !== $lastDateInSession) {
            $data = DataScraper::getData();

            // j'externalise l'insertion en BDD dans un service dédié
            $lastDate = $saveDataInDatabase->appendData($data);

            // je récupère les 10 données les plus récentes en BDD et je les enregistre en session
            $cac = $cacRepository->findBy([], ['id' => 'DESC'], 10);
            $session->set("cac", $cac);
        }

        return $this->render('home/dashboard.html.twig', compact('cac', 'lastDate'));
    }
}
