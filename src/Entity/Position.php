<?php

namespace App\Entity;

use App\Repository\PositionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PositionRepository::class)
 */
class Position
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
    private $buyTarget;

    /**
     * @ORM\Column(type="float")
     */
    private $sellTarget;

    /**
     * @ORM\Column(type="date")
     */
    private $buyDate;

    /**
     * @ORM\Column(type="boolean", options={"default" : "0"})
     */
    private $isActive = false;

    /**
     * @ORM\Column(type="boolean", options={"default" : "0"})
     */
    private $isClosed = false;

    /**
     * @ORM\Column(type="boolean", options={"default" : "0"})
     */
    private $isWaiting = false;

    /**
     * @ORM\Column(type="boolean", options={"default" : "0"})
     */
    private $isRunning = false;

    /**
     * @ORM\ManyToOne(targetEntity=LastHigh::class, inversedBy="positions")
     */
    private $buyLimit;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="positions")
     */
    private $User;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBuyTarget(): ?float
    {
        return $this->buyTarget;
    }

    public function setBuyTarget(float $buyTarget): self
    {
        $this->buyTarget = $buyTarget;

        return $this;
    }

    public function getSellTarget(): ?float
    {
        return $this->sellTarget;
    }

    public function setSellTarget(float $sellTarget): self
    {
        $this->sellTarget = $sellTarget;

        return $this;
    }

    public function getBuyDate(): ?\DateTimeInterface
    {
        return $this->buyDate;
    }

    public function setBuyDate(\DateTimeInterface $buyDate): self
    {
        $this->buyDate = $buyDate;

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isIsClosed(): ?bool
    {
        return $this->isClosed;
    }

    public function setIsClosed(bool $isClosed): self
    {
        $this->isClosed = $isClosed;

        return $this;
    }

    public function isIsWaiting(): ?bool
    {
        return $this->isWaiting;
    }

    public function setIsWaiting(bool $isWaiting): self
    {
        $this->isWaiting = $isWaiting;

        return $this;
    }

    public function isIsRunning(): ?bool
    {
        return $this->isRunning;
    }

    public function setIsRunning(bool $isRunning): self
    {
        $this->isRunning = $isRunning;

        return $this;
    }

    public function getBuyLimit(): ?LastHigh
    {
        return $this->buyLimit;
    }

    public function setBuyLimit(?LastHigh $buyLimit): self
    {
        $this->buyLimit = $buyLimit;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->User;
    }

    public function setUser(?User $User): self
    {
        $this->User = $User;

        return $this;
    }
}
