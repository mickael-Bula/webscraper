<?php

namespace App\Service;

use App\Entity\{ Cac, LastHigh, Position };
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
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->security = $security;
        $this->requestStack = $requestStack;
    }

    /**
     * Cette méthode insère dans la base les données postérieures à la dernière entrée disponible
     *
     * @param array
     * @return array|null
     */
    public function appendData(array $data): ?array
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
        return $cacRepository->saveData(array_reverse($newData));

        // on retourne les données insérées en base pour la mise à jour de lastHigh
        // return $newData;
    }

    /**
     * J'actualise la table LastHigh si un nouveau plus haut a été réalisé
     *
     * @param $newData
     * @return void
     */
    public function checkNewHigher($newData): void
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

            // je mets également à jour les positions en rapport avec la nouvelle buyLimit
            // TODO : il faudrait automatiser cette mise à jour dès que buyLimit est modifié
        } else {
            // si un nouveau plus haut a été réalisé, j'actualise la table LastHigh
            $lastHigherInDB = $lastHighInDatabase->getDailyCac()->getHigher();
            if ($newDataDailyCacHigher->getHigher() > $lastHigherInDB) {
                dump($newDataDailyCacHigher->getHigher, $lastHigherInDB);
                // j'hydrate le dernier plus haut de la table LastHigh avec les données mises à jour
                $newHigher = $newDataDailyCacHigher->getHigher();
                $lastHighInDatabase->setHigher($newHigher);
                $lastHighInDatabase->setBuyLimit($newHigher - ($newHigher * 0.1));
                $lastHighInDatabase->setDailyCac($newDataDailyCacHigher);

                $lastHighRepository->add($lastHighInDatabase, true);
            }
        }
    }

    /**
     * méthode qui enregistre en session le plus haut de l'utilisateur connecté
     *
     * @return void
     */
    public function setHigher(): void
    {
        // je récupère la session après injection du service RequestStack dans le constructeur
        $session = $this->requestStack->getSession();

        // je récupère le User en session ou en BDD à partir de son id
        $userId = $this->security->getUser()->getId();
        $user = $this->userRepository->find($userId);
        $lastHigh = $user->getHigher();  // je récupère une instance de LastHigh

        // si $lastHigh->getHigher() est 'null', j'assigne comme nouveau plus haut le dernier cac.higher
        if (is_null($lastHigh->getHigher())) {
            $entity = new LastHigh();
            $cac = $session->get("cac");
            $higher = $cac[0]->getHigher();
            $entity->setHigher($higher);
            $buyLimit = $higher - ($higher * 0.1);  // buyLimit est 10% sous higher
            $entity->setBuyLimit($buyLimit);
            $entity->setDailyCac($cac[0]);
            $entity->addUser($user);

            $this->entityManager->getRepository(LastHigh::class)->add($entity, true);

            // j'appelle la méthode de SaveDataInDatabase qui met à jour les positions liées à une buyLimit
            $this->updatePositions($entity);

            // j'enregistre en session et je quitte
            $session->set("lastHigh", $cac[0]->getHigher());
            return;
        }
        // sinon, si $lastHigh->getHigher() n'est pas 'null', je l'enregistre en session
        $session->set("lastHigh", $lastHigh);
    }

    public function updatePositions(LastHigh $entity)
    {
        // je récupère l'id de l'utilisateur en session
        $userId = $this->security->getUser()->getId();
        $user = $this->userRepository->find($userId);

        // je récupère les positions en attente liées à l'utilisateur identifié
        $positionRepository = $this->entityManager->getRepository(Position::class);
        $positions = $positionRepository->findBy(["User" => $userId, "isWaiting" => true]);

        // je fixe les % d'écart entre les lignes
        $delta = [0, 2, 4];

        // je boucle sur le tableau des positions s'il n'est pas vide, sinon j'en crée de nouvelles
        for ($i=0; $i < 3; $i++) {
            $position = (count($positions) === 3) ? $positions[$i] : new Position();
            $position->setBuyLimit($entity);
            $buyLimit = $entity->getBuyLimit();
            $positionDelta = $buyLimit - ($buyLimit * $delta[$i] /100);  // les positions sont prises à 0, -2 et -4 %
            $position->setBuyTarget($positionDelta);
            $position->setIsWaiting(true);
            $position->setUser($user);

            $this->entityManager->persist($position);
        }
        $this->entityManager->flush();
    }
}
