<?php

namespace App\Service;

use App\Entity\Cac;
use Doctrine\ORM\EntityManagerInterface;

class SaveDataInDatabase
{
    private $entityManager;

    // pour accéder à Doctrine hors du controller, je dois injecter l'EntityManager
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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
        $em = $this->entityManager;
        $cacRepository = $em->getRepository(Cac::class);

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
}
