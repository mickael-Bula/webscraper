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
     * @param $newData
     * @return void
     */
    public function checkNewHigher($newData): void
    {
        // je récupère le User en session
        $user = $this->getCurrentUser();

        // je récupère le repository du Lvc
        $lvcRepository = $this->entityManager->getRepository(Lvc::class);

        // je récupère le plus haut des dernières données ajoutées en BDD
        $newDataHigher = max(array_map(fn($item) => $item->getHigher(), $newData));

        // Je récupère l'objet Cac qui a fait le nouveau plus haut
        $newDataDailyCacHigher = array_values(array_filter($newData, fn(Cac $item) => $item->getHigher() === $newDataHigher))[0];
        /*
        NOTE : ci-dessus, si j'utilise array_values() c'est pour réindexer le tableau de résultat obtenu avec array_filter().
        En effet, array_filter() conservant par défaut les indices du tableau filtré, je dois procéder à une
        réindexation si je veux pouvoir en récupérer le premier indice.
        */

        // je récupère le dernier plus haut de la table LastHigh
        $lastHighRepository = $this->entityManager->getRepository(LastHigh::class);
        $lastHighInDatabase = $lastHighRepository->findOneBy([], ["id" => "DESC"]);

        // si le résultat est 'null', je dois créer une nouvelle entrée, sinon j'actualise celle présente
        if (is_null($lastHighInDatabase)) {

            // je récupère le plus récent plus haut des données scrapées (le last_High pas défaut)
            $lastHighInNewData = $newData[0];
            $lastHigher = $lastHighInNewData->getHigher();

            // je crée une nouvelle instance de LastHigh et je l'hydrate
            $lastHighEntity = new LastHigh();
            $lastHighEntity->setHigher($lastHigher);
            $buyLimit = $lastHigher - ($lastHigher * 0.1);    // buyLimit est 10% sous higher
            $lastHighEntity->setBuyLimit($buyLimit);
            $lastHighEntity->setDailyCac($lastHighInNewData);
            $lastHighEntity->addUser($user);

            // à partir de l'entity Cac, je récupère l'objet LVC contemporain
            $lvc = $lvcRepository->findOneBy(["createdAt" => $lastHighInNewData->getCreatedAt()]);
            $lvcHigher = $lvc->getHigher();

            // j'hydrate l'instance LastHigh avec les données de l'objet Lvc récupéré
            $lastHighEntity->setLvcHigher($lvcHigher);
            $lvcBuyLimit = $lvcHigher - ($lvcHigher * 0.2);     // lvcBuyLimit fixée à 20% en raison d'un levier x2
            $lastHighEntity->setLvcBuyLimit($lvcBuyLimit);
            $lastHighEntity->setDailyLvc($lvc);

            // je persiste les données et je les insère en base
            $lastHighRepository->add($lastHighEntity, true);

            // je mets également à jour les positions en rapport avec la nouvelle buyLimit
            $this->updatePositions($lastHighEntity);
        }
        else {

            // si higher existe en BDD et qu'un nouveau plus haut a été réalisé, j'actualise la table LastHigh
            $lastHigherInDB = $lastHighInDatabase->getHigher();
            if ($newDataDailyCacHigher->getHigher() > $lastHigherInDB) {

                // j'hydrate le dernier plus haut de la table LastHigh avec les données mises à jour
                $newHigher = $newDataDailyCacHigher->getHigher();
                $lastHighInDatabase->setHigher($newHigher);
                $lastHighInDatabase->setBuyLimit($newHigher - ($newHigher * 0.1));
                $lastHighInDatabase->setDailyCac($newDataDailyCacHigher);

                // je récupère le lvc contemporain à $newDataDailyCacHigher
                $lvc = $lvcRepository->findOneBy(["createdAt" => $newDataDailyCacHigher->getCreatedAt()]);
                $lvcHigher = $lvc->getHigher();

                // j'hydrate également avec les données du lvc correspondant
                $lastHighInDatabase->setLvcHigher($lvcHigher);
                $lastHighInDatabase->setLvcBuyLimit($lvcHigher - ($lvcHigher * 0.2));
                $lastHighInDatabase->setDailyLvc($lvc);

                // je persite et j'enregistre les données
                $lastHighRepository->add($lastHighInDatabase, true);

                // je mets également à jour les positions en rapport avec la nouvelle buyLimit
                $this->updatePositions($lastHighInDatabase);
            }
        }
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

    public function updatePositions(LastHigh $entity)
    {
        // je récupère l'utilisateur en session
        $user = $this->getCurrentUser();
        $userId = $user->getId();
        $user = $this->userRepository->find($userId);

        // je récupère les positions en attente liées à l'utilisateur identifié
        $positionRepository = $this->entityManager->getRepository(Position::class);
        $positions = $positionRepository->findBy(["User" => $userId, "isWaiting" => true]);

        // je fixe les % d'écart entre les lignes
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
}
