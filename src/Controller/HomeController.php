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
    private MailerService $mailer;
    private LoggerInterface $logger;

    public function __construct(RequestStack $requestStack, MailerService $mailer, LoggerInterface $myAppLogger)
    {
        $this->requestStack = $requestStack;
        $this->mailer = $mailer;
        $this->logger = $myAppLogger;
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
     * @return Response
     * @throws TransportExceptionInterface
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
        $lvcRepository = $doctrine->getRepository(Lvc::class);
        $session = $this->requestStack->getSession();

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
        // A la création d'un user, si les données sont à jour ($lastDate === $lastDateInSession), aucun plus haut ne lui a été affecté...
        if (is_null($user->getHigher())) {
            // ...on le fait ici avec le dernier plus haut du Cac en BDD
            $saveDataInDatabase->setHigher($cacRepository->findOneBy([], ['id' => 'DESC']));
        };

        // récupération en base de la liste des données à mettre à jour
        $cacList = $saveDataInDatabase->dataToCheck();
        // pour chacune, on actualise le plus haut local et les positions
        foreach ($cacList as $cacData) {
            $saveDataInDatabase->checkLastHigh($cacData);
            // récupération du lvc contemporain au cac, puis mise à jour de celui-ci
            $lvcData = $lvcRepository->findOneBy(["createdAt" => $cacData->getCreatedAt()]);
            $saveDataInDatabase->checkLvcData($lvcData);
            // met à jour la date de la dernière visite de l'utilisateur
            $saveDataInDatabase->updateLastCac($cacData);
        }

        // je récupère toutes les positions pour affichage
        $positionRepository = $doctrine->getRepository(Position::class);
        $waitingPositions   = $positionRepository->findBy(["User" => $user->getId(), "isWaiting"    => true]);
        $runningPositions   = $positionRepository->findBy(["User" => $user->getId(), "isRunning"    => true]);
        $closedPositions    = $positionRepository->findBy(["User" => $user->getId(), "isClosed"     => true]);

        return $this->render(
            'home/dashboard.html.twig',
            compact('cac', 'lvc', 'waitingPositions', 'runningPositions', 'closedPositions')
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
