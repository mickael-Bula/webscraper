<?php

namespace App\Repository;

use App\Entity\Position;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Position>
 *
 * @method Position|null find($id, $lockMode = null, $lockVersion = null)
 * @method Position|null findOneBy(array $criteria, array $orderBy = null)
 * @method Position[]    findAll()
 * @method Position[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Position::class);
    }

    public function add(Position $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Position $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Récupère les positions en attente qui ont une buyLimit inférieure à celle de la position courante
     * @param Position $position
     * @return float|int|mixed|string
     */
    public function getIsWaitingPositionsByBuyLimitID(Position $position)
    {
        $qb = $this
            ->createQueryBuilder('p')
            ->where('p.isWaiting = true')
            ->andWhere('p.buyLimit < :id')
            ->setParameter('id', $position->getBuyLimit())
            ->orderBy('p.buyLimit', 'ASC');

        return $qb->getQuery()->execute();
    }
}
