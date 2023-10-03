<?php

namespace App\Entity;

use App\Repository\LastHighRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
     * @ORM\OneToMany(targetEntity=User::class, mappedBy="higher")
     */
    private $users;

    /**
     * @ORM\Column(type="float")
     */
    private $higher;

    /**
     * @ORM\Column(type="float")
     */
    private $buyLimit;

    /**
     * @ORM\OneToMany(targetEntity=Position::class, mappedBy="buyLimit")
     */
    private $positions;

    /**
     * @ORM\Column(type="float")
     */
    private $lvcHigher;

    /**
     * @ORM\Column(type="float")
     */
    private $lvcBuyLimit;

    /**
     * @ORM\ManyToOne(targetEntity=Cac::class, inversedBy="lastHigher")
     */
    private $dailyCac;

    /**
     * @ORM\ManyToOne(targetEntity=Lvc::class, inversedBy="lastHigher")
     */
    private $dailyLvc;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->positions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->setHigher($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getHigher() === $this) {
                $user->setHigher(null);
            }
        }

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

    public function getBuyLimit(): ?float
    {
        return $this->buyLimit;
    }

    public function setBuyLimit(float $buyLimit): self
    {
        $this->buyLimit = $buyLimit;

        return $this;
    }

    /**
     * @return Collection<int, Position>
     */
    public function getPositions(): Collection
    {
        return $this->positions;
    }

    public function addPosition(Position $position): self
    {
        if (!$this->positions->contains($position)) {
            $this->positions[] = $position;
            $position->setBuyLimit($this);
        }

        return $this;
    }

    public function removePosition(Position $position): self
    {
        if ($this->positions->removeElement($position)) {
            // set the owning side to null (unless already changed)
            if ($position->getBuyLimit() === $this) {
                $position->setBuyLimit(null);
            }
        }

        return $this;
    }

    public function getLvcHigher(): ?float
    {
        return $this->lvcHigher;
    }

    public function setLvcHigher(float $lvcHigher): self
    {
        $this->lvcHigher = $lvcHigher;

        return $this;
    }

    public function getLvcBuyLimit(): ?float
    {
        return $this->lvcBuyLimit;
    }

    public function setLvcBuyLimit(float $lvcBuyLimit): self
    {
        $this->lvcBuyLimit = $lvcBuyLimit;

        return $this;
    }

    public function getDailyCac(): ?Cac
    {
        return $this->dailyCac;
    }

    public function setDailyCac(?Cac $dailyCac): self
    {
        $this->dailyCac = $dailyCac;

        return $this;
    }

    public function getDailyLvc(): ?Lvc
    {
        return $this->dailyLvc;
    }

    public function setDailyLvc(?Lvc $dailyLvc): self
    {
        $this->dailyLvc = $dailyLvc;

        return $this;
    }
}
