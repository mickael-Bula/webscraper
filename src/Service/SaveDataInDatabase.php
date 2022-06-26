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
     * Cette méthode insère dans la base les données postérieures à la dernière entrée disponible
     *
     * @param array $data
     * @param $entity
     * @return array|null
     */
    public function appendData(array $data, $entity): ?array
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
    public function checkNewData(array $newData): void
    {
        // je récupère le dernier plus haut de la table LastHigh
        $lastHighRepository = $this->entityManager->getRepository(LastHigh::class);
        $lastHighInDatabase = $lastHighRepository->findOneBy([], ["id" => "DESC"]);

        // si le résultat est 'null', je crée un 'last_high' et je le récupère
        if (is_null($lastHighInDatabase)) {
            // par défaut j'affecte la plus récente donnée du CAC comme dernier plus haut
            $lastHighInDatabase = $this->setHigher($newData[0]);
        }

        // je boucle sur les nouvelles données du CAC et vérifie si lastHigh.buyLimit ou lastHigh.higher ont été touchés
        foreach ($newData as $row) {

            // si buyLimit a été touchée, je passe les positions 'en attente' à 'en cours' et je crée un nouveau lastHigh
            if ($row->getLower() < $lastHighInDatabase->getBuyLimit()) {
                // je change l'état des positions en isRunning
                $this->updatePositions();
                // j'appelle la méthode setHigher en lui fournissant l'itération courante de l'objet Cac
                $lastHighInDatabase = $this->setHigher($row);
            }

            // si lastHigh a été dépassé, je l'actualise
            if ($row->getHigher() > $lastHighInDatabase->getHigher()) {
                $this->updateHigher($row, $lastHighInDatabase);
            }
        }
    }

    /**
     * méthode pour créer un nouveau plus haut en BDD
     *
     * @param Cac $cac l'objet cac qui a fait le plus haut
     * @return LastHigh
     */
    public function setHigher(Cac $cac): LastHigh
    {
        // je récupère le User en session
        $user = $this->getCurrentUser();

        // je récupère les repositories nécessaires
        $lvcRepository = $this->entityManager->getRepository(Lvc::class);
        $lastHighRepository = $this->entityManager->getRepository(LastHigh::class);

        // je récupère le plus haut de l'objet Cac transmis en paramètre
        $lastHigher = $cac->getHigher();

        // je crée une nouvelle instance de LastHigh et je l'hydrate
        $lastHighEntity = new LastHigh();
        $lastHighEntity->setHigher($lastHigher);
        $buyLimit = $lastHigher - ($lastHigher * 0.1);    // buyLimit est 10% sous higher
        $lastHighEntity->setBuyLimit($buyLimit);
        $lastHighEntity->setDailyCac($cac);
        $lastHighEntity->addUser($user);

        // à partir de l'entity Cac, je récupère l'objet LVC contemporain
        $lvc = $lvcRepository->findOneBy(["createdAt" => $cac->getCreatedAt()]);
        $lvcHigher = $lvc->getHigher();

        // j'hydrate l'instance LastHigh avec les données de l'objet Lvc récupéré
        $lastHighEntity->setLvcHigher($lvcHigher);
        $lvcBuyLimit = $lvcHigher - ($lvcHigher * 0.2);     // lvcBuyLimit fixée à 20% en raison d'un levier x2
        $lastHighEntity->setLvcBuyLimit($lvcBuyLimit);
        $lastHighEntity->setDailyLvc($lvc);

        // je persiste les données et je les insère en base
        $lastHighInDatabase = $lastHighRepository->add($lastHighEntity, true);

        // je crée également les positions en rapport avec la nouvelle buyLimit
        $this->setPositions($lastHighEntity);

        return $lastHighInDatabase;
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
        $lastHigh->setBuyLimit($newHigher - ($newHigher * 0.1));
        $lastHigh->setDailyCac($cac);

        // je récupère le lvc contemporain à $cac
        $lvc = $lvcRepository->findOneBy(["createdAt" => $cac->getCreatedAt()]);
        $lvcHigher = $lvc->getHigher();

        // j'hydrate également les données du lvc correspondant
        $lastHigh->setLvcHigher($lvcHigher);
        $lastHigh->setLvcBuyLimit($lvcHigher - ($lvcHigher * 0.2));
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
        return $this->userRepository->find( $user->getId());
    }

    public function setPositions(LastHigh $entity)
    {
        // je récupère l'utilisateur en session
        $user = $this->getCurrentUser();
        $userId = $user->getId();
        $user = $this->userRepository->find($userId);

        // je récupère les positions en attente liées à l'utilisateur identifié
        $positionRepository = $this->entityManager->getRepository(Position::class);
        $positions = $positionRepository->findBy(["User" => $userId, "isWaiting" => true]);

        // je fixe les % d'écart entre les lignes pour le cac et pour le lvc (qui a un levier x2)
        $delta = [[0, 2, 4], [0, 4, 8]];

        // je boucle sur le tableau des positions s'il n'est pas vide, sinon j'en crée de nouvelles
        for ($i=0; $i < 3; $i++) {
            /*
            à chaque tour de boucle je vérifie le tableau positions :
            s'il n'est pas vide je récupère l'indice courant $i, sinon je crée une nouvelle position
            */
            $position = (count($positions) === 3) ? $positions[$i] : new Position();
            $position->setBuyLimit($entity);
            $buyLimit = $entity->getBuyLimit();
            $positionDeltaCac = $buyLimit - ($buyLimit * $delta[0][$i] /100);  // les positions sont prises à 0, -2 et -4 %
            $position->setBuyTarget($positionDeltaCac);
            $position->setIsWaiting(true);
            $position->setUser($user);
            $lvcBuyLimit = $entity->getLvcBuyLimit();
            $positionDeltaLvc = $lvcBuyLimit - ($lvcBuyLimit * $delta[1][$i] /100);  // les positions sont prises à 0, -4 et -8 %
            $position->setLvcBuyTarget($positionDeltaLvc);
            $position->setLvcSellTarget($positionDeltaLvc * 1.2);

            $this->entityManager->persist($position);
        }
        $this->entityManager->flush();
    }

    public function updatePositions()
    {
        // je récupère l'utilisateur en session
        $user = $this->getCurrentUser();
        $userId = $user->getId();

        // je récupère les positions en attente liées à l'utilisateur identifié
        $positionRepository = $this->entityManager->getRepository(Position::class);
        $positions = $positionRepository->findBy(["User" => $userId, "isWaiting" => true]);

        // je passe l'état de ces positions de isWaiting à isRunning
        foreach ($positions as $position) {
            $position->setIsWaiting(false);
            $position->setIsRunning(true);
        }
    }
}
