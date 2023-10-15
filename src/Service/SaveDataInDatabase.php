<?php

namespace App\Service;

use App\Entity\{ Cac, LastHigh, Lvc, Position, User };
use App\Repository\UserRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Security\Core\Security;

class SaveDataInDatabase
{
    private EntityManagerInterface $entityManager;
    private UserRepository          $userRepository;
    private Security                $security;
    private MailerService           $mailer;
    private LoggerInterface         $logger;

    public function __construct(
        EntityManagerInterface $entityManager,  // pour accéder à Doctrine hors du controller, je dois injecter l'EntityManager
        UserRepository         $userRepository,
        Security               $security,
        MailerService          $mailer,
        LoggerInterface        $myAppLogger
    )
    {
        $this->entityManager    = $entityManager;
        $this->userRepository   = $userRepository;
        $this->security         = $security;
        $this->mailer           = $mailer;
        $this->logger           = $myAppLogger;
    }

    /**
     * Retourne le plus haut de l'utilisateur
     * @return LastHigh|null
     */
    public function getLastHigher(): ?LastHigh
    {
        return $this->getCurrentUser()->getHigher();
    }

    /**
     * Cette méthode insère dans la base les données postérieures à la dernière entrée disponible
     * Les données postérieures à la date disponible sont retournées de la plus ancienne à la plus récente
     *
     * @param array $data
     * @param $entity
     */
    public function appendData(array $data, $entity): void
    {
        // je précise le Repository que je veux utiliser à mon EntityManager
        $entityRepository = $this->entityManager->getRepository($entity);

        // puis je récupère lastDate en BDD (ou null si aucune valeur n'est présente)
        $lastDate = $entityRepository->findOneBy([], ["id" => "DESC"]);
        if ($lastDate) {
            // les dates scrapées ayant des formats différents, je reformate celles reçues de la BDD pour qu'elles correspondent
            if ($lastDate->getCreatedAt() instanceof Cac) {
                // si $data représente les données du Cac, le format de date est "23/05/2022"
                $lastDate = (!empty($lastDate)) ? $lastDate->getCreatedAt()->format("d/m/Y") : null;
            } else if ($lastDate->getCreatedAt() instanceof Lvc) {
                // si $data représente les données du Lvc, le format de date est "06/23/2022"
                $lastDate = (!empty($lastDate)) ? $lastDate->getCreatedAt()->format("m/d/Y") : null;
            }
        } else {
            // récupère le nom de l'entité
            $className = $this->entityManager->getClassMetadata($entity)->getName();
            $this->logger->error(sprintf("Pas de dernier plus haut trouvé pour l'entité %s", $className));
        }

        // tri des entrées postérieures à lastDate
        $newData = [];
        foreach ($data as $row) {
            if ($lastDate !== $row[0]) {
                $newData[] = $row;
            } else {
                break;
            }
        }

        // inversion du tableau pour que les nouvelles entrées soient ordonnées chronologiquement et insertion en BDD
        $entityRepository->saveData(array_reverse($newData));
    }

    /**
     * Actualise le plus haut local et les positions d'une liste de données Cac
     * @param array $cacData
     * @return void
     */
    public function updateCacData(array $cacData): void
    {
        $lvcRepository = $this->entityManager->getRepository(Lvc::class);

        foreach ($cacData as $cac) {
            $this->checkLastHigh($cac);
            // récupération du lvc contemporain au cac
            $lvcData = $lvcRepository->findOneBy(["createdAt" => $cac->getCreatedAt()]);
            if ($lvcData) {
                // mise à jour des positions...
                $this->checkLvcData($lvcData);
            } else {
                $this->logger->error("Pas de LVC correpondant pour le CAC fournit en date du %s", $cac->getCreatedAt());
            }
            // ...puis de la date de la dernière visite de l'utilisateur
            $this->updateLastCac($cac);
        }
    }

    /**
     * J'actualise la table LastHigh du User connecté si un nouveau plus haut a été réalisé
     *
     * @param Cac $cac
     * @return void
     */
    public function checkLastHigh(Cac $cac): void
    {
        // je récupère le plus haut de l'utilisateur en session
        $lastHighInDatabase = $this->getLastHigher();

        // si le résultat est 'null', je crée un 'last_high' en affectant par défaut le dernier plus haut du Cac et je crée les positions
        if (is_null($lastHighInDatabase)) {
            $lastHighInDatabase = $this->setHigher($cac);
        }
        // si lastHigh a été dépassé, je l'actualise
        if ($cac->getHigher() > $lastHighInDatabase->getHigher()) {
            $this->updateHigher($cac, $lastHighInDatabase);
        }
    }

    /**
     * Mise à jour des positions en attente et en cours à partir des données LVC récupérées
     * @param Lvc $lvc
     * @return void
     */
    public function checkLvcData(Lvc $lvc): void
    {
        $this->updateIsWaitingPositions($lvc);
        $this->updateIsRunningPositions($lvc);
    }

    /**
     * @param Lvc $lvc
     * @return void
     */
    public function updateIsWaitingPositions(Lvc $lvc): void
    {
        // récupère les positions isWaiting du User
        $positions = $this->getPositionsOfCurrentUser("isWaiting");

        // Pour chacune des positions en cours, je vérifie si lvc.lower < position.LvcBuyTarget
        foreach ($positions as $position) {
            /** @var Position $position */
            if ($lvc->getLower() <= $position->getLvcBuyTarget()) {
                // on passe le statut de la position à isRunning
                $this->openPosition($lvc, $position);
                // si la position mise à jour est la première de sa série...
                if ($this->checkIsFirst($position)) {
                    // ...on crée et on récupère le nouveau point haut en passant l'objet cac contemporain du lvc courant
                    $cac = $this->entityManager->getRepository(Cac::class)->findOneBy(['createdAt' => $lvc->getCreatedAt()]);
                    if (!$cac) {
                        $this->logger->error(sprintf(
                            "Impossible de récupérer le CAC corrrspondant au LVC en date du %s",
                            $lvc->getCreatedAt()));
                    }
                    // On récupère toutes les positions en attente qui ont un point haut différent...
                    $isWaitingPositions = $this->getIsWaitingPositions($position);
                    // ...pour vérifier celles qui sont toujours au nombre de 3 pour une même buyLimit (pas de position isRunning)
                    $isWaitingpositionsChecked = $this->checkIsWaitingPositions($isWaitingPositions);
                    // Si elles existent, on les met à jour, sinon on crée trois nouvelles positions
                    $this->setHigher($cac, $isWaitingpositionsChecked);
                }
            }
        }
    }

    /**
     * @param Lvc $lvc
     * @return void
     */
    public function updateIsRunningPositions(Lvc $lvc): void
    {
        // TODO : il reste à traiter le solde des positions clôturées pour l'afficher sur le dashboard
        // récupère les positions isRunning du User
        $positions = $this->getPositionsOfCurrentUser("isRunning");

        // Pour chacune des positions en cours, je vérifie si lvc.higher > position.sellTarget
        foreach ($positions as $position) {
            /** @var Position $position */
            if ($lvc->getHigher() > $position->getLvcSellTarget()) {
                // on passe le statut de la position à isClosed
                $this->closePosition($lvc, $position);
            }
        }
    }

    /**
     * Retourne les positions en attente liées à l'utilisateur identifié
     * @param $status L'état de la position (isWaiting, isRunning ou isClosed)
     * @return array
     */
    private function getPositionsOfCurrentUser($status): array
    {
        return $this->entityManager->getRepository(Position::class)
            ->findBy(["User" => $this->getCurrentUser()->getId(), $status => true]);
    }

    /**
     * Récupère les positions en attente liées à un lastHigh de l'utilisateur connecté
     * @param User $user
     * @param LastHigh $lastHigh
     * @return array
     */
    public function getIsWaitingPositionsByLashHighId(User $user, LastHigh $lastHigh): array
    {
        return $this->entityManager->getRepository(Position::class)
            ->findBy(["User" => $user->getId(), "isWaiting" => true, "buyLimit" => $lastHigh->getId()]);
    }

    /**
     * méthode pour créer en BDD le nouveau plus haut de l'utilisateur courant
     *
     * @param Cac $cac l'objet cac qui a fait le plus haut
     * @param array $positions
     * @return LastHigh
     */
    public function setHigher(Cac $cac, array $positions = []): LastHigh
    {
        // je récupère le User en session ainsi que les repositories nécessaires
        $user = $this->getCurrentUser();
        $lvcRepository = $this->entityManager->getRepository(Lvc::class);
        $lastHighRepository = $this->entityManager->getRepository(LastHigh::class);

        // je récupère le plus haut de l'objet Cac transmis en paramètre
        $lastHigher = $cac->getHigher();

        // je crée une nouvelle instance de LastHigh et je l'hydrate
        $lastHighEntity = new LastHigh();
        $lastHighEntity->setHigher($lastHigher);
        $buyLimit = $lastHigher - ($lastHigher * Position::SPREAD);    // buyLimit se situe 6 % sous higher
        $lastHighEntity->setBuyLimit(round($buyLimit, 2));
        $lastHighEntity->setDailyCac($cac);

        // à partir de l'entity Cac, je récupère l'objet LVC contemporain
        $lvc = $lvcRepository->findOneBy(["createdAt" => $cac->getCreatedAt()]);
        if (!$lvc) {
            $this->logger->error(sprintf("Pas de LVC correpondant pour le CAC fournit en date du %s", $cac->getCreatedAt()));
        }
        $lvcHigher = $lvc->getHigher();

        // j'hydrate l'instance LastHigh avec les données de l'objet Lvc récupéré
        $lastHighEntity->setLvcHigher($lvcHigher);

        // lvcBuyLimit fixée au double du SPREAD en raison d'un levier x2
        $lvcBuyLimit = $lvcHigher - ($lvcHigher * (Position::SPREAD * 2));
        $lastHighEntity->setLvcBuyLimit(round($lvcBuyLimit, 2));
        $lastHighEntity->setDailyLvc($lvc);

        // je persiste les données et je les insère en base. Je le fais avant de le transmettre au user pour qu'un id soit créé
        $lastHighRepository->add($lastHighEntity, true);

        // J'assigne ce plus haut à l'utilisateur courant et j'enregistre à nouveau en base
        $user->setHigher($lastHighEntity);

        $this->entityManager->flush();

        // je crée également les positions en rapport avec la nouvelle buyLimit
        $this->setPositions($lastHighEntity, $positions);

        return $lastHighEntity;
    }

    /**
     * méthode pour mettre à jour un plus haut existant en BDD
     *
     * @param Cac $cac l'objet cac qui a fait le nouveau plus haut
     * @param LastHigh $lastHigh représente le plus haut à actualiser
     * @return void
     */
    public function updateHigher(Cac $cac, LastHigh $lastHigh): void
    {
        // je récupère les repositories nécessaires
        $lvcRepository = $this->entityManager->getRepository(Lvc::class);
        $lastHighRepository = $this->entityManager->getRepository(LastHigh::class);
        $positionsRepository = $this->entityManager->getRepository(Position::class);

        // j'hydrate le dernier plus haut de la table LastHigh avec les données mises à jour
        $newHigher = $cac->getHigher();
        $lastHigh->setHigher($newHigher);
        $lastHigh->setBuyLimit(round($newHigher - ($newHigher * Position::SPREAD), 2));
        $lastHigh->setDailyCac($cac);

        // je récupère le lvc contemporain de l'objet $cac
        $lvc = $lvcRepository->findOneBy(["createdAt" => $cac->getCreatedAt()]);
        if (!$lvc) {
            $this->logger->error(sprintf("Pas de LVC correpondant pour le CAC fournit en date du %s", $cac->getCreatedAt()));
        }
        $lvcHigher = $lvc->getHigher();

        // j'hydrate également les données du lvc correspondant
        $lastHigh->setLvcHigher($lvcHigher);
        $lastHigh->setLvcBuyLimit(round($lvcHigher - ($lvcHigher * (Position::SPREAD * 2)), 2));
        $lastHigh->setDailyLvc($lvc);

        // je persite et j'enregistre les données
        $lastHighRepository->add($lastHigh, true);

        // je récupère les positions en attente du user liées au lastHigh (via la buyLimit) pour les mettre à jour
        $positions = $positionsRepository->findBy([
            "User" => $this->getCurrentUser(),
            "isWaiting" => true,
            "buyLimit" => $lastHigh->getId()
        ]);
        $this->setPositions($lastHigh, $positions);
    }

    /**
     * Je récupère le User en session ou en BDD à partir de son id
     * (je précise à l'IDE que getId() se réfère à l'Entity User)
     *
     * @return User
     */
    public function getCurrentUser(): User
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return $this->userRepository->find($user->getId());
    }

    /**
     * Met à jour les positions en attente d'un utilisateur dont la buyLimit n'a pas été touchée
     * @param LastHigh $lastHigh
     * @param array $positions
     * @return void
     */
    public function setPositions(LastHigh $lastHigh, array $positions = []): void
    {
        // je récupère le user en session
        $user = $this->getCurrentUser();

        // Je compte les positions passées en paramètre
        $nbPositions = count($positions);

        /* Si la taille du tableau n'est pas égal à 0 ou 3, c'est qu'une position du cycle d'achat
        a été passée en isRunning : les positions isWaiting de la même buyLimit sont alors gelées */
        if (!in_array($nbPositions, [0, 3])) {
            $this->logger->info(sprintf(
                "Pas de mise à jour des positions : au moins une position isRunning existe avec une buyLimit = %s",
                $lastHigh->getBuyLimit()
            ));

            return;
        }

        // je fixe les % d'écart entre les lignes pour le cac et pour le lvc (qui a un levier x2)
        $delta = [
            'cac' => [0, 2, 4],
            'lvc' => [0, 4, 8]
        ];

        // je boucle sur les positions existantes, sinon j'en crée 3 nouvelles
        for ($i = 0; $i < 3; $i++) {
            $position = $nbPositions === 0 ? new Position() : $positions[$i];
            $position->setBuyLimit($lastHigh);
            $buyLimit = $lastHigh->getBuyLimit();
            $positionDeltaCac = $buyLimit - ($buyLimit * $delta['cac'][$i] / 100);  // les positions sont prises à 0, -2 et -4 %
            $position->setBuyTarget(round($positionDeltaCac, 2));
            $position->setIsWaiting(true);
            $position->setUser($user);
            $lvcBuyLimit = $lastHigh->getLvcBuyLimit();
            $positionDeltaLvc = $lvcBuyLimit - ($lvcBuyLimit * $delta['lvc'][$i] / 100);  // les positions sont prises à 0, -4 et -8 %
            $position->setLvcBuyTarget(round($positionDeltaLvc, 2));
            $position->setQuantity(round(Position::LINE_VALUE / $positionDeltaLvc));
            $position->setLvcSellTarget(round($positionDeltaLvc * 1.2, 2));    // revente d'une position à +20 %

            $this->entityManager->persist($position);
        }
        $this->entityManager->flush();

        // NOTE : 100 mails par mois dans le cadre du plan gratuit proposé par Mailtrap
        $this->mailer->sendEmail($positions);
    }

    /**
     * Change le statut d'une position dont la limite d'achat a été atteinte et envoi un mail
     * @param Lvc $lvc
     * @param Position $position
     * @return void
     */
    public function openPosition(Lvc $lvc, Position $position): void
    {
        // NOTE : doit afficher 14/06/23 -23 LVC @ 32.15 PX=7400
        $position->setIsWaiting(false);
        $position->setIsRunning(true);
        $position->setBuyDate($lvc->getCreatedAt());

        $this->entityManager->flush();

        $this->mailer->sendEmail($position);
    }

    /**
     * Clôture une position dont l'objectif de vente a été atteint
     * et supprime le reliquat de position en attente ayant la même buyLimiy
     * @param Lvc $lvc
     * @param Position $position
     * @return void
     */
    public function closePosition(Lvc $lvc, Position $position): void
    {
        $position->setIsRunning(false);
        $position->setIsClosed(true);
        $position->setSellDate($lvc->getCreatedAt());

        $positions = $this->getIsWaitingPositionsByLashHighId($this->getCurrentUser(), $position->getBuyLimit());
        $this->removeIsWaitingPositions($positions);

        $this->entityManager->flush();
    }

    /**
     * Retourne les positions 'isWaiting' dont la buyLimit_id est différente de celle de la position courante
     * @param Position $position
     * @return array|null
     */
    public function getIsWaitingPositions(Position $position): ?array
    {
        return $this->entityManager
            ->getRepository(Position::class)
            ->getIsWaitingPositionsByBuyLimitID($position);
    }

    /**
     * Récupère les positions d'une même buyLimit lorsqu'elles sont au nombre de trois
     * @param array $positions
     * @return array|null
     */
    public function checkIsWaitingPositions(array $positions): ?array
    {
        // on trie les positions en fonction de la propriété buyLimit
        $results = array_reduce($positions, static function ($result, $position) {
            /** @var Position $position */
            // je récupère l'id de la propriété buyLimit. S'il n'existe pas dans le tableau $result, je l'ajoute.
            $buyLimit = $position->getBuyLimit() ? $position->getBuyLimit()->getId() : null;
            if (!isset($result[$buyLimit])) {
                $result[$buyLimit] = [];
            }
            // j'ajoute la position courante en valeur de la clé correspondant à sa buyLimit
            $result[$buyLimit][] = $position;

            return $result;
        }, []);

        // pour chacun des résultats, si on trouve 3 positions, on les ajoute à la liste des positions à traiter
        return array_filter($results, static fn($item) => count($item) === 3);
    }

    /**
     * Suppression d'une liste de positions
     * @param array $positions
     * @return void
     */
    public function removeIsWaitingPositions(array $positions): void
    {
        foreach ($positions as $row) {
            $this->entityManager->remove($row);
        }
        $this->entityManager->flush();
    }

    /**
     * Vérifie si une seule position en cours existe relativement à sa buyLimit
     * @param Position $position
     * @return bool
     */
    public function checkIsFirst(Position $position): bool
    {
        $positions = $this->entityManager
            ->getRepository(Position::class)
            ->findBy(["isRunning" => true, "buyLimit" => $position->getBuyLimit()]);

        return count($positions) === 1;
    }

    /**
     * récupère la liste des entités cac utilisée pour la mise à jour des positions de l'utilisateur courant
     * @return array
     */
    public function dataToCheck(): array
    {
        $cacRepository = $this->entityManager->getRepository(Cac::class);

        // je récupère le user en session
        $user = $this->getCurrentUser();

        // si lastCacUpdated est null, je lui assigne en référence la dernière donnée du cac disponible en BDD
        if (is_null($user->getLastCacUpdated())) {
            $cac = $cacRepository->findOneBy([], ['id' => 'DESC']);

            //FIX Lignes utilisées pour les tests
//            $cac = $cacRepository->findOneBy(['id' => 14]);
            $user->setLastCacUpdated($cac);
            $this->entityManager->flush();
        }

        return $cacRepository->getDataToUpdateFromUser($user->getLastCacUpdated());
    }

    /**
     * Enregistre en base la date de la dernière visite de l'utilisateur courant
     * Permet d'obtenir la liste des données à vérifier pour mettre à jour les positions de l'utilisateur
     * @param Cac $cac
     * @return void
     */
    public function updateLastCac(Cac $cac): void
    {
        $user = $this->getCurrentUser();
        $user->setLastCacUpdated($cac);
        $this->entityManager->flush();
    }
}
