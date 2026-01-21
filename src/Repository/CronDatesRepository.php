<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Lingoda\CronBundle\Entity\CronDates;

/**
 * @extends EntityRepository<CronDates>
 */
class CronDatesRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, $em->getClassMetadata(CronDates::class));
    }

    public function save(CronDates $cronDates): void
    {
        $this->getEntityManager()->persist($cronDates);
        $this->getEntityManager()->flush();
    }
}
