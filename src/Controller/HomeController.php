<?php

namespace App\Controller;

use App\Entity\Cac;
use App\Entity\Lvc;
use App\Entity\Position;
use App\Service\Utils;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\DataScraper;
use App\Service\SaveDataInDatabase;
use Symfony\Component\HttpFoundation\RequestStack;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

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
     * @IsGranted("ROLE_USER")
     *
     * @Route("/dashboard", name="app_dashboard")
     *
     * @param SaveDataInDatabase $saveDataInDatabase
     * @param ManagerRegistry $doctrine
     * @return Response
     */
    public function dashboard(
        SaveDataInDatabase $saveDataInDatabase,
        ManagerRegistry $doctrine): Response
    {
        // on passe par le ManagerRegistry() pour récupérer le repository du Cac
        $cacRepository = $doctrine->getRepository(Cac::class);

        // on commence par vérifier en session la présence des données du CAC, sinon on y charge celles-ci
        $session = $this->requestStack->getSession();
        if (!$session->has("cac")) {
            $cac = $cacRepository->findBy([], ['id' => 'DESC'], 10);
            $session->set("cac", $cac);
        }
        $cac = $session->get("cac");
        // je demande à un Service de calculer la date la plus récente attendue en base de données
        $lastDate = (new Utils())->getMostRecentDate();

        // je compare $lastDate avec la date la plus récente en session (si la base est vide j'affecte 'null')
        $lastDateInSession = (!empty($cac)) ? $cac[0]->getCreatedAt()->format("d/m/Y") : null;

        // si les dates ne correspondent pas, je lance le scraping pour récupérer les données manquantes
        if ($lastDate !== $lastDateInSession) {
            // je lance la récupération des données du cac
            $data = DataScraper::getData('https://fr.investing.com/indices/france-40-historical-data');

            // j'externalise l'insertion des données du Cac en BDD dans un service dédié
            $newData = $saveDataInDatabase->appendData($data, Cac::class);

            // je récupère ensuite les données du LVC
            $lvcData = DataScraper::getData('https://www.investing.com/etfs/lyxor-leverage-cac-40-historical-data');

            // puis je sauvegarde en BDD
            $saveDataInDatabase->appendData($lvcData, Lvc::class);

            // j'externalise ensuite la vérification d'un nouveau plus haut et les modifications en BDD qui en résulte
            $saveDataInDatabase->checkNewData($newData);

            // je récupère les 10 données les plus récentes en BDD et je les enregistre en session
            $cac = $cacRepository->findBy([], ['id' => 'DESC'], 10);
            $session->set("cac", $cac);
        }

        // je récupère l'utilisateur en session (je passe par une méthode personnalisée car j'aurai besoin de son id)
        $user = $saveDataInDatabase->getCurrentUser();

        // je récupère toutes les positions en attente pour affichage
        $positionRepository = $doctrine->getRepository(Position::class);
        $waitingPositions = $positionRepository->findBy(["User" => $user->getId(), "isWaiting" => true]);

        return $this->render('home/dashboard.html.twig', compact('cac', 'waitingPositions'));
    }
}
