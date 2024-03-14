<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Lingoda\CronBundle\Repository\CronDatesRepository;

#[ORM\Table(name: 'cron_dates')]
#[ORM\Entity(repositoryClass: CronDatesRepository::class)]
class CronDates
{
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    private string $id;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $lastTriggeredAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $lastStartedAt = null;

    public function __construct(string $id, DateTimeInterface $triggeredAt)
    {
        $this->id = $id;
        $this->lastTriggeredAt = $triggeredAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLastTriggeredAt(): DateTimeInterface
    {
        return $this->lastTriggeredAt;
    }

    public function setLastTriggeredAt(DateTimeInterface $lastTriggeredAt): void
    {
        $this->lastTriggeredAt = $lastTriggeredAt;
    }

    public function getLastStartedAt(): ?DateTimeInterface
    {
        return $this->lastStartedAt;
    }

    public function setLastStartedAt(DateTimeInterface $lastStartedAt): void
    {
        $this->lastStartedAt = $lastStartedAt;
    }
}
