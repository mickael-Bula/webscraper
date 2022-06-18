<?php

namespace App\Controller;

use App\Entity\Cac;
use App\Repository\UserRepository;
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
        // on passe par le ManagerRegistry() pour récupérer les repository du Cac
        $cacRepository = $doctrine->getRepository(Cac::class);

        // on commence par vérifier en session la présence des données du CAC, sinon on y charge celles-ci
        $session = $this->requestStack->getSession();
        if (!$session->has("cac")) {
            $cac = $cacRepository->findBy([], ['id' => 'DESC'], 10);
            $session->set("cac", $cac);
        }
        $cac = $session->get("cac");

        // je demande à un Service de calculer la date la plus récente attendue et conservée en base de données
        $recentDate = new Utils();
        $lastDate = $recentDate->getMostRecentDate();

        // je compare $lastDate avec la date la plus récente en session (et donc en BDD : si la base est vide j'affecte 'null')
        $lastDateInSession = (!empty($cac)) ? $cac[0]->getCreatedAt()->format("d/m/Y") : null;

        // si les dates ne correspondent pas, je lance le scraping pour récupérer les données manquantes
        if ($lastDate !== $lastDateInSession) {
            $data = DataScraper::getData();

            // j'externalise l'insertion en BDD dans un service dédié
            $newData = $saveDataInDatabase->appendData($data);

            // j'externalise également la vérification d'un nouveau plus haut et la modification en BDD qui en résulte
            $saveDataInDatabase->checkNewHigher($newData);

            // je récupère les 10 données les plus récentes en BDD et je les enregistre en session
            $cac = $cacRepository->findBy([], ['id' => 'DESC'], 10);
            $session->set("cac", $cac);
        }

        // j'actualise $lastDate pour affichage
        $lastDate = $cac[0]->getCreatedAt()->format("d/m/Y");

        // si lastHigh n'existe pas en session, je l'y ajoute en passant par un Service dédié
        if (!$session->has("lastHigh")) {
            // j'externalise dans un Service la vérification du dernier plus haut du User en session
            $saveDataInDatabase->setHigher();
        }

        return $this->render('home/dashboard.html.twig', compact('cac', 'lastDate'));
    }
}
