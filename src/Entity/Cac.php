<?php

namespace App\Entity;

use App\Repository\CacRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CacRepository::class)
 */
class Cac
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="date")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="float")
     */
    private $closing;

    /**
     * @ORM\Column(type="float")
     */
    private $opening;

    /**
     * @ORM\Column(type="float")
     */
    private $higher;

    /**
     * @ORM\Column(type="float")
     */
    private $lower;

    /**
     * @ORM\OneToMany(targetEntity=lastHigh::class, mappedBy="dailyCac")
     */
    private $lastHigher;

    public function __construct()
    {
        $this->lastHigher = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getClosing(): ?float
    {
        return $this->closing;
    }

    public function setClosing(float $closing): self
    {
        $this->closing = $closing;

        return $this;
    }

    public function getOpening(): ?float
    {
        return $this->opening;
    }

    public function setOpening(float $opening): self
    {
        $this->opening = $opening;

        return $this;
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

    public function getLower(): ?float
    {
        return $this->lower;
    }

    public function setLower(float $lower): self
    {
        $this->lower = $lower;

        return $this;
    }

    /**
     * @return Collection<int, lastHigh>
     */
    public function getLastHigher(): Collection
    {
        return $this->lastHigher;
    }

    public function addLastHigher(lastHigh $lastHigher): self
    {
        if (!$this->lastHigher->contains($lastHigher)) {
            $this->lastHigher[] = $lastHigher;
            $lastHigher->setDailyCac($this);
        }

        return $this;
    }

    public function removeLastHigher(lastHigh $lastHigher): self
    {
        if ($this->lastHigher->removeElement($lastHigher)) {
            // set the owning side to null (unless already changed)
            if ($lastHigher->getDailyCac() === $this) {
                $lastHigher->setDailyCac(null);
            }
        }

        return $this;
    }
}
