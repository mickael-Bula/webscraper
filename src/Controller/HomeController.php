<?php

namespace App\Controller;

use App\Entity\Cac;
use App\Service\Utils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\DataScraper;
use App\Service\SaveDataInDatabase;
use Doctrine\Persistence\ManagerRegistry;
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
    public function index(SaveDataInDatabase $saveDataInDatabase, ManagerRegistry $managerRegistry): Response
    {
        // on commence par vérifier en session la présence des données du CAC, sinon on y charge celles-ci
        $session = $this->requestStack->getSession();
        if (!$session->has("cac")) {
            $cac = $managerRegistry->getRepository(Cac::class)->findBy([], ['id' => 'DESC'], 10);
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
            // et j'enregistre les 10 données les plus récentes en session
            $cac = array_slice($data, 0, 10);
            $session->set("cac", $cac);

            // j'externalise l'insertion en BDD dans un service dédié
            $lastDate = $saveDataInDatabase->appendData($cac);
        }

        // je crée une portion de tableau pour affichage
        $displayData = array_slice($cac, 0, 10);

        return $this->render('home/index.html.twig', compact('displayData', 'lastDate'));
    }
}
