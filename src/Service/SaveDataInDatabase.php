<?php

namespace App\Service;

use App\Entity\{Cac, LastHigh, Lvc, Position, User};
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class SaveDataInDatabase
{
    private $entityManager;
    private $userRepository;
    private $security;
    private $requestStack;

    // pour accéder à Doctrine hors du controller, je dois injecter l'EntityManager
    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        Security $security,
        RequestStack $requestStack)
    {
        $this->entityManager    = $entityManager;
        $this->userRepository   = $userRepository;
        $this->security         = $security;
        $this->requestStack     = $requestStack;
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
     *
     * @param array $data
     * @param $entity
     * @return array
     */
    public function appendData(array $data, $entity): array
    {
        // je précise le Repository que je veux utiliser à mon EntityManager
        $entityRepository = $this->entityManager->getRepository($entity);

        // puis je récupère lastDate en BDD (ou null si aucune valeur n'est présente)
        $lastDate = $entityRepository->findOneBy([], ["id" => "DESC"]);

        /* les données scrapées ($data) ayant des formats de date différents, je dois reformater celles reçues
        de la BDD pour qu'elles leur correspondent */
        if ($lastDate instanceof Cac) {
            // si $data représente les données du Cac, le format de date est "23/05/2022"
            $lastDate = (!empty($lastDate)) ? $lastDate->getCreatedAt()->format("d/m/Y") : null;
        } else if ($lastDate instanceof Lvc) {
            // si $data représente les données du Lvc, le format de date est "May 23, 2022"
            $lastDate = (!empty($lastDate)) ? $lastDate->getCreatedAt()->format("M d, Y") : null;
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
        return $entityRepository->saveData(array_reverse($newData));
    }

    /**
     * J'actualise la table LastHigh du User connecté si un nouveau plus haut a été réalisé
     *
     * @param array $newData qui représente un tableau d'objets Cac
     * @return void
     */
    public function checkLastHigh(array $newData): void
    {
        // je récupère le plus haut de l'utilisateur en session
        $lastHighInDatabase = $this->getLastHigher();

        // si le résultat est 'null', je crée un 'last_high' en lui affectant par défaut le dernier plus haut du Cac, puis je le récupère
        if (is_null($lastHighInDatabase)) {
            $lastHighInDatabase = $this->setHigher($newData[0]);
        }
        // je boucle sur les nouvelles données du CAC et vérifie si lastHigh.buyLimit ou lastHigh.higher ont été touchés
        foreach ($newData as $row) {
            // si lastHigh a été dépassé, je l'actualise
            if ($row->getHigher() > $lastHighInDatabase->getHigher()) {
                $this->updateHigher($row, $lastHighInDatabase);
            }
        }
    }

    /**
     * @param array $lvcData
     * @return void
     */
    public function checkLvcData(array $lvcData): void
    {
        // récupère les positions isWaiting du User
        $positions = $this->getPositionsOfCurrentUser("isWaiting");

        // je boucle sur les données du LVC et vérifie, pour chacune des positions en cours, si lvc.lower < position.LvcBuyTarget
        foreach ($lvcData as $lvc) {
            foreach ($positions as $position) {
                /** @var Lvc $lvc */
                /** @var Position $position */
                if ($lvc->getLower() < $position->getLvcBuyTarget()) {
                    $this->updatePosition($lvc, $position);

                    // si la position mise à jour est la première de sa série, on génère un nouveau point haut
                    if ($this->checkisFirst($position)) {
                        $cac = $position->getBuyLimit()->getDailyCac();
                        $this->setHigher($cac);
                    }

                    // Si des positions en attente ont un LastHigh antérieur au plus haut courant, on les supprime
                    $positions = $this->checkIsWaitingPositions($position);
                    if (count($positions) > 1) $this->removeIsWaitingPositions($positions);
                }
                //TODO : faire de même avec les positions à clôturer
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
     */
    public function setHigher(Cac $cac): LastHigh
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

        // J'assigne ce plus haut à l'utilisateur courant
        $user->setHigher($lastHighEntity);

        // à partir de l'entity Cac, je récupère l'objet LVC contemporain
        $lvc = $lvcRepository->findOneBy(["createdAt" => $cac->getCreatedAt()]);
        $lvcHigher = $lvc->getHigher();

        // j'hydrate l'instance LastHigh avec les données de l'objet Lvc récupéré
        $lastHighEntity->setLvcHigher($lvcHigher);

        // lvcBuyLimit fixée au double du SPREAD en raison d'un levier x2
        $lvcBuyLimit = $lvcHigher - ($lvcHigher * (Position::SPREAD * 2));
        $lastHighEntity->setLvcBuyLimit(round($lvcBuyLimit, 2));
        $lastHighEntity->setDailyLvc($lvc);

        // je persiste les données et je les insère en base
        $lastHighRepository->add($lastHighEntity, true);

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

        // je récupère le lvc contemporain à $cac
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
     * met à jour les positions en attente d'un utilisateur
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
        $delta = [[0, 2, 4], [0, 4, 8]];

        // je boucle sur le tableau des positions s'il n'est pas vide, sinon j'en crée de nouvelles
        for ($i=0; $i < 3; $i++) {
            $position = (count($positions) === 3) ? $positions[$i] : new Position();
            $position->setBuyLimit($entity);
            $buyLimit = $entity->getBuyLimit();
            $positionDeltaCac = $buyLimit - ($buyLimit * $delta[0][$i] /100);  // les positions sont prises à 0, -2 et -4 %
            $position->setBuyTarget(round($positionDeltaCac, 2));
            $position->setIsWaiting(true);
            $position->setUser($user);
            $lvcBuyLimit = $entity->getLvcBuyLimit();
            $positionDeltaLvc = $lvcBuyLimit - ($lvcBuyLimit * $delta[1][$i] /100);  // les positions sont prises à 0, -4 et -8 %
            $position->setLvcBuyTarget(round($positionDeltaLvc, 2));
            $position->setQuantity(round(Position::LINE_VALUE / $positionDeltaLvc));
            $position->setLvcSellTarget(round($positionDeltaLvc * 1.12, 2));    // revente d'une position à +12 %

            $this->entityManager->persist($position);
        }
        $this->entityManager->flush();
    }

    /**
     * Change le status d'une position dont la limite d'achat a été atteinte
     * @param Lvc $lvc
     * @param Position $position
     * @return void
     */
    public function updatePosition(Lvc $lvc, Position $position)
    {
        // INFO : doit afficher 14/06/23 -23 LVC @ 32.15 PX=7400
        $position->setIsWaiting(false);
        $position->setIsRunning(true);
        $position->setBuyDate($lvc->getCreatedAt());
    }

    /**
     * Clôture une position dont l'objectif de vente a été atteint
     * @param Position $position
     * @return void
     */
    public function closePosition(Position $position)
    {
        $position->setIsRunning(false);
        $position->setIsClosed(true);
    }

    /**
     * Retourne les positions 'isWaiting' dont la buyLimit_id est inférieure à celle de la position courante
     * @param Position $position
     * @return array|null
     */
    public function checkIsWaitingPositions(Position $position): ?array
    {;
        return $this
            ->entityManager
            ->getRepository(Position::class)
            ->getIsWaitingPositionsByBuyLimitID($position);
    }

    /**
     * @param array $positions
     * @return void
     */
    public function removeIsWaitingPositions(array $positions)
    {
        $em = $this->entityManager;

        foreach ($positions as $row) {
            $em->remove($row);
            $em->flush();
        }
    }

    /**
     * Vérifie si une seule position en cours existe relativement à sa buyLimit
     * @param Position $position
     * @return bool
     */
    public function checkisFirst(Position $position): bool
    {
        $positions = $this
            ->entityManager
            ->getRepository(Position::class)
            ->findBy(["isRunning" => true, "buyLimit" => $position->getBuyLimit()]);

        return $positions == 1;
    }
}
