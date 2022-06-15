<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\LastHigh;
use App\Repository\CacRepository;
use App\Repository\LastHighRepository;
use App\Repository\UserRepository;
use App\Service\Utils;
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
     * @param CacRepository $cacRepository
     * @param LastHighRepository $lastHighRepository
     * @return Response
     */
    public function dashboard(
        SaveDataInDatabase $saveDataInDatabase,
        CacRepository $cacRepository,
        LastHighRepository $lastHighRepository,
        UserRepository $userRepository): Response
    {
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

        // je compare $lastDate avec la date la plus récente en session (et donc en BDD)
        $lastDateInSession = $cac[0]->getCreatedAt()->format("d/m/Y");

        // si les dates ne correspondent pas, je lance le scraping pour récupérer les données manquantes
        if ($lastDate !== $lastDateInSession) {
            $data = DataScraper::getData();

            // j'externalise l'insertion en BDD dans un service dédié
            $newData = $saveDataInDatabase->appendData($data);

            // j'externalise également la vérification d'un nouveau plus haut et la modification en BDD qui en résulte
            $saveDataInDatabase->checkNewHigher($lastHighRepository, $newData);

            // je récupère les 10 données les plus récentes en BDD et je les enregistre en session
            $cac = $cacRepository->findBy([], ['id' => 'DESC'], 10);
            $session->set("cac", $cac);
        }

        // j'actualise $lastDate pour affichage
        $lastDate = $cac[0]->getCreatedAt()->format("d/m/Y");

        // TODO : il faudra mettre tout cela dans un service
        // je récupère le dernier plus haut du User en session
        if (!$session->has("lastHigh")) {

            // s'il n'existe pas je le récupère en BDD à partir de l'id de l'utilisateur connecté
            $userId = $this->getUser()->getId();
            $lastHigh = $userRepository->find($userId)->getHigher()->getHigher();

            // si j'ai un résultat autre que null je le mets en session
            if (!is_null($lastHigh)) {
                $session->set("lastHigh", $lastHigh);
            } else {
                // sinon, par défaut, on assigne la dernière valeur disponible comme nouveau plus haut
                $entity = new LastHigh();
                $higher = $cac[0]->getHigher();
                $entity->setHigher($higher);
                $entity->setBuyLimit($higher - ($higher * 0.1));    // buyLimit est 10% sous higher
                $entity->setDailyCac($cac[0]);
                $entity->addUser($userRepository->find($userId));

                $lastHighRepository->add($entity, true);

                $session->set("lastHigh", $cac[0]->getHigher());
            }
        }
        // je récupère la valeur du plus haut
        $lastHigh = $session->get("lastHigh");
        dump($lastHigh);

        return $this->render('home/dashboard.html.twig', compact('cac', 'lastDate'));
    }
}
