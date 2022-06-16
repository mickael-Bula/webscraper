<?php

namespace App\Service;

use App\Entity\{ Cac, LastHigh, Position };
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class SaveDataInDatabase
{
    private $entityManager;
    private $userRepository;
    private $security;

    // pour accéder à Doctrine hors du controller, je dois injecter l'EntityManager
    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        Security $security
    )
    {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->security = $security;
    }

    /**
     * Cette méthode insère dans la base les données postérieures à la dernière entrée disponible
     *
     * @param array
     * @return array|null
     */
    public function appendData($data): ?array
    {
        // je précise le Repository que je veux utiliser à mon EntityManager
        $cacRepository = $this->entityManager->getRepository(Cac::class);

        // puis je récupère lastDate en BDD (ou null si aucune valeur n'est présente)
        $lastDate = $cacRepository->findOneBy([], ["id" => "DESC"]);
        $lastDate = (!empty($lastDate)) ? $lastDate->getCreatedAt()->format("d/m/Y") : null;
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
        $cacRepository->saveData(array_reverse($newData));

        // on retourne les données insérées en base pour la mise à jour de lastHigh
        return $newData;
    }

    /**
     * J'actualise la table LastHigh si un nouveau plus haut a été réalisé
     *
     * @param $newData
     * @return void
     */
    public function checkNewHigher($newData)
    {
        // je récupère le plus haut des dernières données ajoutées en BDD
        $newDataHigher = max(array_map(fn($item) => $item->getHigher(), $newData));

        // je récupère l'objet Cac qui a fait le nouveau plus haut
        $newDataDailyCacHigher = array_filter($newData, fn(Cac $item) => $item->getHigher() === $newDataHigher)[0];

        // je récupère le dernier plus haut de la table LastHigh
        $lastHighRepository = $this->entityManager->getRepository(LastHigh::class);
        $lastHighInDatabase = $lastHighRepository->findOneBy([], ["id" => "DESC"]);

        // si le résultat est 'null', je dois créer une nouvelle entrée, sinon j'actualise celle présente
        if (is_null($lastHighInDatabase)) {

            // je crée une nouvelle instance de LastHigh et je l'hydrate
            $lastHighEntity = new LastHigh();
            $lastHighEntity->setHigher($newDataHigher);
            $lastHighEntity->setBuyLimit($newDataHigher);
            $lastHighEntity->setDailyCac($newDataDailyCacHigher);

            // je persiste les données et je les insère en base
            $lastHighRepository->add($lastHighEntity, true);
        } else {
            // si un nouveau plus haut a été réalisé, j'actualise la table LastHigh
            if ($newDataHigher > $lastHighInDatabase) {

                // j'hydrate le dernier plus haut de la table LastHigh avec les données mises à jour
                $lastHighInDatabase->setHigher($newDataHigher);
                $lastHighInDatabase->setBuyLimit($newDataHigher - ($newDataHigher * 0.1));
                $lastHighInDatabase->setDailyCac($newDataDailyCacHigher);

                $lastHighRepository->add($lastHighInDatabase, true);
            }
        }
    }

    public function updatePositions($buyLimit)
    {
        // je récupère l'id de l'utilisateur en session
        $userId = $this->security->getUser()->getId();
        $user = $this->userRepository->find($userId);

        // je récupère les positions en attente liées à l'utilisateur identifié
        $positionRepository = $this->entityManager->getRepository(Position::class);
        $positions = $positionRepository->findBy(["User" => $userId, "isWaiting" => false]);

        // si le résultat est vide, on crée les trois positions liées à buyLimit
        if (count($positions) === 0) {
            $delta = [1, 2, 4];                                         // représente les % d'écart entre les lignes
            for ($i=0; $i < 3 ;$i++) {
                $position = new Position();
                $position->setBuyLimit($buyLimit);
                $positionDelta = $buyLimit - ($buyLimit * $delta[$i]);  // les positions sont prises à -1, -2 et -4 %
                $position->setBuyTarget($positionDelta);
                $position->setIsWaiting(true);
                $position->setSellTarget($positionDelta * 1.1); // objectif fixé à +10 %
                $position->setUser($user);
                // TODO : mettre des prePersist ici pour les sellTarget toujours fixés à buyTarget +10 %
                // TODO : vérifier la présence des valeurs par défaut (comme isRunning = false)
            }
        } else {
            $i = 0;
            foreach ($positions as $position) {
                $delta = [1, 2, 4];
                // TODO : faire un update des positions en ajustant les buyLimit et buyTarget
                // TODO : essayer de factoriser cette partie de code en partie redondante
                $position->setBuyLimit($buyLimit);
                $positionDelta = $buyLimit - ($buyLimit * $delta[$i]);  // les positions sont prises à -1, -2 et -4 %
                $position->setBuyTarget($positionDelta);
                $position->setIsWaiting(true);
                $position->setSellTarget($positionDelta * 1.1); // objectif fixé à +10 %
                $position->setUser($user);
                $i++;
            }
        }
    }
}
