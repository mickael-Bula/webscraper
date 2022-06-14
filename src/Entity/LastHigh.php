<?php

namespace App\Entity;

use App\Repository\LastHighRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=LastHighRepository::class)
 */
class LastHigh
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="float")
     */
    private $higher;

    /**
     * @ORM\Column(type="float")
     */
    private $buyLimit;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="lastHighs")
     */
    private $user;

    /**
     * @ORM\OneToOne(targetEntity=Cac::class, cascade={"persist", "remove"})
     */
    private $dailyHigher;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHigher(): ?float
    {
        return $this->higher;
    }

    public function setHigher(float $higher): self
    {
        $this->higher = $higher;

        return $this;
    }

    public function getBuyLimit(): ?float
    {
        return $this->buyLimit;
    }

    public function setBuyLimit(float $buyLimit): self
    {
        $this->buyLimit = $buyLimit;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getDailyHigher(): ?Cac
    {
        return $this->dailyHigher;
    }

    public function setDailyHigher(?Cac $dailyHigher): self
    {
        $this->dailyHigher = $dailyHigher;

        return $this;
    }
}
