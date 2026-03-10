<?php

namespace Lle\EntityFileBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Lle\EntityFileBundle\Entity\EntityFile;

/**
 * @method EntityFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method EntityFile|null findOneBy(array $criteria, array $orderBy = null)
 * @method EntityFile[]    findAll()
 * @method EntityFile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EntityFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EntityFile::class);
    }
}
