<?php

namespace App\Controller;

use App\Entity\Cac;
use App\Entity\Lvc;
use App\Entity\Position;
use App\Entity\User;
use App\Service\MailerService;
use App\Service\Utils;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
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
     * @param ManagerRegistry $doctrine
     * @param SaveDataInDatabase $saveDataInDatabase
     * @param Utils $utils
     * @param MailerService $mailer
     * @return Response
     * @throws TransportExceptionInterface
     */
    public function dashboard(
        ManagerRegistry $doctrine,
        SaveDataInDatabase $saveDataInDatabase, // injection de mes services
        Utils $utils,
        MailerService $mailer
    ): Response
    {
        /**
         * Récupère l'utilisateur en session. Je précise le type User pour accéder à son id
         * @var User $user
         */
        $user = $this->getUser();

        // TODO : test de l'envoi de mail
        $mailer->sendEmail();

        $cacRepository = $doctrine->getRepository(Cac::class);
        $session = $this->requestStack->getSession();

        // on commence par vérifier en session la présence des données du CAC, sinon on les y insère
        if (!$session->has("cac")) {
            $cac = $cacRepository->findBy([], ['id' => 'DESC'], 10);
            $session->set("cac", $cac);
        }
        $cac = $session->get("cac");

        // je demande à un Service de calculer la date la plus récente attendue en base de données
        $lastDate = $utils->getMostRecentDate();

        // je compare $lastDate avec la date la plus récente en session (si la base est vide j'affecte 'null')
        $lastDateInSession = (!empty($cac)) ? $cac[0]->getCreatedAt()->format("d/m/Y") : null;

        // si les dates ne correspondent pas, je lance le scraping pour récupérer les données manquantes
        if ($lastDate !== $lastDateInSession) {
            // je lance la récupération des données du CAC et du LVC
            $data = DataScraper::getData('https://fr.investing.com/indices/france-40-historical-data');
            $lvcData = DataScraper::getData('https://www.investing.com/etfs/lyxor-leverage-cac-40-historical-data');

            // j'externalise l'insertion des données du CAC et du LVC en BDD dans un service dédié
            $newData = $saveDataInDatabase->appendData($data, Cac::class);
            $lvcData = $saveDataInDatabase->appendData($lvcData, Lvc::class);

            // je récupère les 10 données les plus récentes en BDD et je les enregistre en session
            $cac = $cacRepository->findBy([], ['id' => 'DESC'], 10);
            $session->set("cac", $cac);

            // Pour finir, je fais une mise à jour de LastHigh...
            $saveDataInDatabase->checkLastHigh($newData);

            // ...puis de celles du lvc et de chacune des positions
            $saveDataInDatabase->checkLvcData($lvcData);
        }
        // A la création d'un user, si les données sont à jour ($lastDate === $lastDateInSession), aucun plus haut ne lui a été affecté...
        if (is_null($user->getHigher())) {
            // ...on le fait ici avec le dernier plus haut du Cac en BDD
            $saveDataInDatabase->setHigher($cacRepository->findOneBy([], ['id' => 'DESC']));
        };

        // je récupère toutes les positions pour affichage
        $positionRepository = $doctrine->getRepository(Position::class);
        $waitingPositions   = $positionRepository->findBy(["User" => $user->getId(), "isWaiting"    => true]);
        $runningPositions   = $positionRepository->findBy(["User" => $user->getId(), "isRunning"    => true]);
        $closedPositions    = $positionRepository->findBy(["User" => $user->getId(), "isClosed"     => true]);

        return $this->render(
            'home/dashboard.html.twig',
            compact('cac', 'waitingPositions', 'runningPositions', 'closedPositions')
        );

        //TODO Il faut ajouter sur le dashboard la buyLimit et lastHigh du user courant
        // il faut créer un menu de configuration pour que l'utilisateur puisse paramétrer les données précédentes
        // La buyLimt doit être paramétrable : SPREAD achat et revente
        // Un plus haut doit pouvoir être déclaré, soit par la date, soit par la valeur la plus proche en valeur
        // Pour ce dernier point, il faut ajouter un formulaire
        // Il faut développer tout ce qui concerne la vue en Vue.js => permet de monter en compétence, de s'exercer en 'real'
        // A terme, il faut externaliser le scraping dans un micro-service.
    }
}
