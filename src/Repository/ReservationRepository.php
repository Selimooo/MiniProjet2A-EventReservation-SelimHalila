<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findByEvent(Event $event): array
    {
        return $this->findBy(['event' => $event], ['createdAt' => 'DESC']);
    }
}
