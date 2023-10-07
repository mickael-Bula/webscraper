<?php

namespace App\Service;

use App\Entity\{Cac, LastHigh, Lvc, Position, User};
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
    private UserRepository $userRepository;
    private Security $security;
    private RequestStack $requestStack;
    private MailerService $mailer;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,  // pour accéder à Doctrine hors du controller, je dois injecter l'EntityManager
        UserRepository $userRepository,
        Security $security,
        RequestStack $requestStack,
        MailerService $mailer,
        LoggerInterface $myAppLogger
    )
    {
        $this->entityManager    = $entityManager;
        $this->userRepository   = $userRepository;
        $this->security         = $security;
        $this->requestStack     = $requestStack;
        $this->mailer           = $mailer;
        $this->logger           = $myAppLogger;
    }

    /**
     * Retourne le plus haut de l'utilisateur
     * @return LastHigh|null
     */
    public function getLastHigher(): ?LastHigh
    {
        $user = $this->getCurrentUser();

        return $user->getHigher();
    }

    /**
     * Cette méthode insère dans la base les données postérieures à la dernière entrée disponible
     * Les données postérieures à la date disponible sont retournées de la plus ancienne à la plus récente
     *
     * @param array $data
     * @param $entity
     */
    public function appendData(array $data, $entity)
    {
        // je précise le Repository que je veux utiliser à mon EntityManager
        $entityRepository = $this->entityManager->getRepository($entity);

        // puis je récupère lastDate en BDD (ou null si aucune valeur n'est présente)
        $lastDate = $entityRepository->findOneBy([], ["id" => "DESC"]);

        // les dates scrapées ayant des formats différents, je reformate celles reçues de la BDD pour qu'elles correspondent
        if ($lastDate instanceof Cac) {
            // si $data représente les données du Cac, le format de date est "23/05/2022"
            $lastDate = (!empty($lastDate)) ? $lastDate->getCreatedAt()->format("d/m/Y") : null;
        } else if ($lastDate instanceof Lvc) {
            // si $data représente les données du Lvc, le format de date est "06/23/2022"
            $lastDate = (!empty($lastDate)) ? $lastDate->getCreatedAt()->format("m/d/Y") : null;
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
     * J'actualise la table LastHigh du User connecté si un nouveau plus haut a été réalisé
     *
     * @param Cac $cac
     * @return void
     * @throws TransportExceptionInterface
     */
    public function checkLastHigh(Cac $cac): void
    {
        // je récupère le plus haut de l'utilisateur en session
        $lastHighInDatabase = $this->getLastHigher();

        // si le résultat est 'null', je crée un 'last_high' en lui affectant par défaut le dernier plus haut du Cac, puis je le récupère
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
     * @throws TransportExceptionInterface
     */
    public function checkLvcData(Lvc $lvc): void
    {
        $this->updateIsWaitingPositions($lvc);
        $this->updateIsRunningPositions($lvc);
    }

    /**
     * @param Lvc $lvc
     * @return void
     * @throws TransportExceptionInterface
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
                if ($this->checkisFirst($position)) {
                    // ...on génère un nouveau point haut en transmettant l'objet cac contemporain du lvc courant...
                    $cac = $this->entityManager->getRepository(Cac::class)->findOneBy(['createdAt' => $lvc->getCreatedAt()]);
                    $this->setHigher($cac);
                    // ...puis on récupère toutes les positions en attente qui ont un point haut différent...
                    $isWaitingPositions = $this->getIsWaitingPositions($position);
                    // ...pour vérifier celles qui sont toujours au nombre de 3 pour une même buyLimit (pas de position passée à isRunning)...
                    $isWaitingpositionsChecked = $this->checkIsWaitingPositions($isWaitingPositions);
                    // ...et les supprimer
                    if ($isWaitingPositions) $this->removeIsWaitingPositions($isWaitingpositionsChecked);
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
        $user = $this->getCurrentUser();
        $positionRepository = $this->entityManager->getRepository(Position::class);

        return $positionRepository->findBy(["User" => $user->getId(), $status => true]);
    }

    /**
     * méthode pour créer en BDD le nouveau plus haut de l'utilisateur courant
     *
     * @param Cac $cac l'objet cac qui a fait le plus haut
     * @return LastHigh
     * @throws TransportExceptionInterface
     */
    public function setHigher(Cac $cac): LastHigh
    {
        // je récupère le User en session ainsi que les repositories nécessaires
        $user = $this->getCurrentUser();
        $lvcRepository = $this->entityManager->getRepository(Lvc::class);
        $lastHighRepository = $this->entityManager->getRepository(LastHigh::class);

        // je récupère le plus haut de l'objet Cac transmis en paramètre
        $lastHigher = $cac->getHigher();

        // FIXME Il vaudrait mieux faier un update du lastHigh du user plutôt que de créer une nouvelle instance
        // je crée une nouvelle instance de LastHigh et je l'hydrate
        $lastHighEntity = new LastHigh();
        $lastHighEntity->setHigher($lastHigher);
        $buyLimit = $lastHigher - ($lastHigher * Position::SPREAD);    // buyLimit se situe 6 % sous higher
        $lastHighEntity->setBuyLimit(round($buyLimit, 2));
        $lastHighEntity->setDailyCac($cac);

        // à partir de l'entity Cac, je récupère l'objet LVC contemporain
        $lvc = $lvcRepository->findOneBy(["createdAt" => $cac->getCreatedAt()]);
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
        $this->setPositions($lastHighEntity);

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

        // j'hydrate le dernier plus haut de la table LastHigh avec les données mises à jour
        $newHigher = $cac->getHigher();
        $lastHigh->setHigher($newHigher);
        $lastHigh->setBuyLimit(round($newHigher - ($newHigher * Position::SPREAD), 2));
        $lastHigh->setDailyCac($cac);

        // je récupère le lvc contemporain de l'objet $cac
        $lvc = $lvcRepository->findOneBy(["createdAt" => $cac->getCreatedAt()]);
        $lvcHigher = $lvc->getHigher();

        // j'hydrate également les données du lvc correspondant
        $lastHigh->setLvcHigher($lvcHigher);
        $lastHigh->setLvcBuyLimit(round($lvcHigher - ($lvcHigher * (Position::SPREAD * 2)), 2));
        $lastHigh->setDailyLvc($lvc);

        // je persite et j'enregistre les données
        $lastHighRepository->add($lastHigh, true);

        // je mets également à jour les positions en rapport avec la nouvelle buyLimit
        $this->setPositions($lastHigh);
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
     * @param LastHigh $entity
     * @return void
     */
    public function setPositions(LastHigh $entity)
    {
        // je récupère depuis la session l'instance courante de User
        $user = $this->getCurrentUser();

        // Je récupère également les positions en attente du user
        $positions = $this->getPositionsOfCurrentUser("isWaiting");

        // je fixe les % d'écart entre les lignes pour le cac et pour le lvc (qui a un levier x2)
        $delta = [
            'cac' => [0, 2, 4],
            'lvc' => [0, 4, 8]
        ];

        // je récupère le nombre de positions isWaiting actuellement ouvertes.
        $nbPositions = count($positions) === 0 ? 3 : count($positions);

        /* Si la taille du tableau n'est pas égal à 0 ou 3, c'est qu'une position du cycle d'achat
        a été passée en isRunning : les positions isWaiting de la même buyLimit sont alors gelées */
        if (!in_array(count($positions), ["0", "3"])) {
            $this->logger->info(sprintf(
                "Pas de mise à jour des positions : au moins une position isRunning existe avec une buyLimit = %s",
                $entity->getBuyLimit()
            ));

            return;
        }

        // je boucle sur les positions existantes, sinon j'en crée 3 nouvelles
        for ($i = 0; $i < 3; $i++) {
            $position = $nbPositions === 0 ? new Position() : $positions[$i];
            $position->setBuyLimit($entity);
            $buyLimit = $entity->getBuyLimit();
            $positionDeltaCac = $buyLimit - ($buyLimit * $delta['cac'][$i] /100);  // les positions sont prises à 0, -2 et -4 %
            $position->setBuyTarget(round($positionDeltaCac, 2));
            $position->setIsWaiting(true);
            $position->setUser($user);
            $lvcBuyLimit = $entity->getLvcBuyLimit();
            $positionDeltaLvc = $lvcBuyLimit - ($lvcBuyLimit * $delta['lvc'][$i] /100);  // les positions sont prises à 0, -4 et -8 %
            $position->setLvcBuyTarget(round($positionDeltaLvc, 2));
            $position->setQuantity(round(Position::LINE_VALUE / $positionDeltaLvc));
            $position->setLvcSellTarget(round($positionDeltaLvc * 1.12, 2));    // revente d'une position à +12 %

            $this->entityManager->persist($position);
        }
        $this->entityManager->flush();

        // NOTE : 100 mails par mois dans le cadre du plan gratuit proposé par Mailtrap
         $this->mailer->sendEmail($positions);
    }

    /**
     * Change le statut d'une position dont la limite d'achat a été atteinte
     * @param Lvc $lvc
     * @param Position $position
     * @return void
     */
    public function openPosition(Lvc $lvc, Position $position)
    {
        // INFO : doit afficher 14/06/23 -23 LVC @ 32.15 PX=7400
        $position->setIsWaiting(false);
        $position->setIsRunning(true);
        $position->setBuyDate($lvc->getCreatedAt());

        $this->entityManager->flush();
    }

    /**
     * Clôture une position dont l'objectif de vente a été atteint
     * @param Lvc $lvc
     * @param Position $position
     * @return void
     */
    public function closePosition(Lvc $lvc, Position $position)
    {
        //FIXME ajouter un appel vers la vérification des positions avec la même buyLimit pour suppression
        $position->setIsRunning(false);
        $position->setIsClosed(true);
        $position->setSellDate($lvc->getCreatedAt());

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
        $results = array_reduce($positions, function ($result, $position) {
            /** @var Position $position */
            // je récupère l'id de la propriété buyLimit. S'il n'existe pas dans le tableau $result, je l'ajoute.
            $buyLimit  = $position->getBuyLimit()->getId();
            if (!isset($result[$buyLimit])) {
                $result[$buyLimit] = [];
            }
            // j'ajoute la position courante en valeur de la clé correspondant à sa buyLimit
            $result[$buyLimit][] = $position;

            return $result;
        }, []);

        // pour chacun des résultats, si on trouve 3 positions, on les ajoute à la liste des positions à traiter
        return array_filter($results, fn($item) => count($item) === 3);
    }

    /**
     * @param array $positions
     * @return void
     */
    public function removeIsWaitingPositions(array $positions)
    {
        foreach ($positions as $row) {
            $this->entityManager->remove($row);
            $this->entityManager->flush();
        }
    }

    /**
     * Vérifie si une seule position en cours existe relativement à sa buyLimit
     * @param Position $position
     * @return bool
     */
    public function checkisFirst(Position $position): bool
    {
        $positions = $this->entityManager
            ->getRepository(Position::class)
            ->findBy(["isRunning" => true, "buyLimit" => $position->getBuyLimit()]);

        return count($positions) == 1;
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
    public function updateLastCac(Cac $cac)
    {
        $user = $this->getCurrentUser();
        $user->setLastCacUpdated($cac);
        $this->entityManager->flush();
    }
}
