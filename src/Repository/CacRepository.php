<?php

namespace App\Repository;

use App\Entity\Cac;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Service\ReformatNumber;

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

    public function findLastDate(): array
    {
        return $this
            ->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
        ;
    }

    public function saveData($item): void
    {
        foreach ($item as $eachItem) {
            $entity = new Cac();
            // reformat date (d/m/Y) to conform with expected DataTime format (d-m-Y)
            $date = str_replace('/', '-', $eachItem[0]);
            $entity->setCreatedAt(\DateTime::createFromFormat('d-m-Y', $date));
            $entity->setClosing(ReformatNumber::fromString($eachItem[1]));
            $entity->setOpening(ReformatNumber::fromString($eachItem[2]));
            $entity->setHigher(ReformatNumber::fromString($eachItem[3]));
            $entity->setLower(ReformatNumber::fromString($eachItem[4]));
            
            // ### excerpt from Doctrine documentation : https://www.doctrine-project.org/projects/doctrine-orm/en/2.11/reference/security.html ###/
            // "You can consider all values on Objects inserted and updated through Doctrine\ORM\EntityManager#persist() to be safe from SQL injection"
            $this->getEntityManager()->persist($entity);
        }
        $this->getEntityManager()->flush();
    }

//    /**
//     * @return Cac[] Returns an array of Cac objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Cac
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
