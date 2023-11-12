<?php

namespace App\Controller;

use App\Entity\{ Cac, Lvc, User };
use App\Entity\Position;
use App\Service\MailerService;
use App\Service\Utils;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
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
    private RequestStack $requestStack;
    private LoggerInterface $logger;

    public function __construct(RequestStack $requestStack, LoggerInterface $myAppLogger)
    {
        $this->requestStack = $requestStack;
        $this->logger = $myAppLogger;
    }

    /**
     * @Route("/", name="app_home")
     */
    public function index(): Response
    {
        // récupération du thème enregistré en session
        $session = $this->requestStack->getSession();
        $theme = $session->get('theme', 'light');

        return $this->render('home/index.html.twig', compact('theme'));
    }

    /**
     * @IsGranted("ROLE_USER")
     *
     * @Route("/dashboard", name="app_dashboard")
     *
     * @param ManagerRegistry $doctrine
     * @param SaveDataInDatabase $saveDataInDatabase
     * @param Utils $utils
     * @return Response
     * @throws \Exception
     */
    public function dashboard(
        ManagerRegistry $doctrine,
        SaveDataInDatabase $saveDataInDatabase, // injection de mes services
        Utils $utils
    ): Response
    {
        /**
         * Récupère l'utilisateur en session. Je précise le type User pour accéder à son id
         * @var User $user
         */
        $user = $this->getUser();

        $cacRepository = $doctrine->getRepository(Cac::class);
        $session = $this->requestStack->getSession();

        // récupération du thème enregistré en session
        $theme = $session->get('theme', 'light');

        //FIX Ajouter une nouvelle table pour récupérer le statut d'une position (isWaiting, isRunning, isClosed)
        // une fois fait, mettre à jour le mailer pour afficher le changement de statut de la position
        // Ajouter des index pour accélérer les requêtes, notamment sur cac et lvc (https://zestedesavoir.com/tutoriels/730/administrez-vos-bases-de-donnees-avec-mysql/949_index-jointures-et-sous-requetes/3935_index/)

        // on commence par vérifier en session la présence des données du CAC, sinon on les y insère
        $cac = $session->has('cac') ? $session->get('cac') : $utils->setEntityInSession(Cac::class);
        $lvc = $session->has('lvc') ? $session->get('lvc') : $utils->setEntityInSession(Lvc::class);

        // je demande à un Service de calculer la date la plus récente attendue en base de données
        $lastDate = $utils->getMostRecentDate();

        // je compare $lastDate avec la date la plus récente en session (si la base est vide j'affecte 'null')
        $lastDateInSession = (!empty($cac)) ? $cac[0]->getCreatedAt()->format("d/m/Y") : null;

        // si les dates ne correspondent pas, je lance le scraping pour récupérer les données manquantes
        if ($lastDate !== $lastDateInSession) {
            $scraper = new DataScraper($this->logger);
            $cacData = $scraper->getData($_ENV['CAC_DATA']);
            $lvcData = $scraper->getData($_ENV['LVC_DATA']);

            // si aucune données n'est récupérée, on affiche un message dans le template
            if (is_null($cacData)) {
                $this->addFlash('error', 'Aucune donnée récupérée');
            }

            // j'externalise l'insertion des données du CAC et du LVC en BDD à l'aide d'un service dédié
            $saveDataInDatabase->appendData($cacData, Cac::class);
            $saveDataInDatabase->appendData($lvcData, Lvc::class);

            // je récupère les 10 données les plus récentes en BDD pour les enregistrer en session. Idem pour les clôtures du Lvc
            $cac = $utils->setEntityInSession(Cac::class);
            $lvc = $utils->setEntityInSession(Lvc::class);
        }
        // A la création d'un user, si les données sont à jour ($lastDate === $lastDateInSession), aucun plus haut n'a encore été affecté...
        if (is_null($user->getHigher())) {
            // ...on le fait donc ici, en utilisant le plus haut de la cotation du Cac la plus récente en BDD
            $saveDataInDatabase->setHigher($cacRepository->findOneBy([], ['id' => 'DESC']));

            //FIX Ligne utilisée pour les tests que je peux reproduire à partir d'une copie de la base webtrader_save_structure
//            $saveDataInDatabase->setHigher($cacRepository->findOneBy(['id' => 14]));
        }

        // récupération de la liste des données à mettre à jour, puis enregistrement en base
        $cacList = $saveDataInDatabase->dataToCheck();
        $saveDataInDatabase->updateCacData($cacList);

        // je récupère toutes les positions pour affichage
        $positionRepository = $doctrine->getRepository(Position::class);
        $waitingPositions   = $positionRepository->findBy(["User" => $user->getId(), "isWaiting"    => true]);
        $runningPositions   = $positionRepository->findBy(["User" => $user->getId(), "isRunning"    => true]);
        $closedPositions    = $positionRepository->findBy(["User" => $user->getId(), "isClosed"     => true]);

        // récupération des cotations pour affichage du graphique
        $chartData = $utils->getChartData();

        return $this->render(
            'home/dashboard.html.twig',
            compact('theme', 'cac', 'lvc', 'waitingPositions', 'runningPositions', 'closedPositions', 'chartData')
        );

        //TODO Il faut ajouter sur le dashboard la buyLimit et lastHigh du user courant
        // il faut créer un menu de configuration pour que l'utilisateur puisse paramétrer les données précédentes
        // La buyLimt doit être paramétrable : SPREAD achat et revente
        // Un plus haut doit pouvoir être déclaré, soit par la date, soit par la valeur la plus proche en valeur
        // Pour ce dernier point, il faut ajouter un formulaire
        // Il faut développer tout ce qui concerne la vue en Vue.js => permet de monter en compétence, de s'exercer en 'real'
        // A terme, il faut externaliser le scraping dans un micro-service.
        // Présenter les positions en cours sous forme de tableau pouvant contenir jusqu'à 5 lignes différentes
    }
}
