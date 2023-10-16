<?php

namespace App\Repository;

use App\Entity\Cac;
use Doctrine\DBAL\Exception;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Service\Utils;

/**
 * @extends ServiceEntityRepository<Cac>
 *
 * @method Cac|null find($id, $lockMode = null, $lockVersion = null)
 * @method Cac|null findOneBy(array $criteria, array $orderBy = null)
 * @method Cac[]    findAll()
 * @method Cac[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CacRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cac::class);
    }

    public function add(Cac $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Cac $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * méthode qui enregistre en BDD des objets Cac hydratés avec les données issues du 'scraping'
     * un tableau des objets insérés est retourné
     *
     * @param $data
     */public function saveData($data): void
    {
        foreach ($data as $item) {
            $entity = new Cac();
            // reformat date to conform with expected DataTime format (d-m-Y)
            $date = str_replace('/', '-', $item[0]);
            $entity->setCreatedAt(\DateTime::createFromFormat('d-m-Y', $date));
            $entity->setClosing(Utils::fromString($item[1]));
            $entity->setOpening(Utils::fromString($item[2]));
            $entity->setHigher(Utils::fromString($item[3]));
            $entity->setLower(Utils::fromString($item[4]));

            // ### excerpt from Doctrine documentation : https://www.doctrine-project.org/projects/doctrine-orm/en/2.11/reference/security.html ###/
            // "You can consider all values on Objects inserted and updated through Doctrine\ORM\EntityManager#persist() to be safe from SQL injection"
            $this->getEntityManager()->persist($entity);
        }
        $this->getEntityManager()->flush();
    }

    /**
     * récupère toutes les entités cac qui ont une date supérieure à celle de $lastCacUpdated, triées par ancienneté
     * @param Cac $lastCacUpdated
     * @return array
     */
    public function getDataToUpdateFromUser(Cac $lastCacUpdated): array
    {
        return $this->createQueryBuilder('cac')
            ->where('cac.createdAt > :date')
            ->setParameter('date', $lastCacUpdated->getCreatedAt())
            ->getQuery()
            ->getResult();
    }

    /**
     * @throws Exception
     */
    public function displayCacAndLvcData(): array
    {
        $sql = "SELECT c.closing, c.higher, c.lower, c.opening, c.created_at, l.closing as lvc_closing
                FROM cac c
                JOIN lvc l ON c.created_at = l.created_at
                ORDER BY c.created_at DESC
                LIMIT 10";

        return $this->getEntityManager()
            ->getConnection()
            ->executeQuery($sql)
            ->fetchAllAssociative();
    }
}
