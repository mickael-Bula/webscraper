<?php

namespace App\Service;

use App\Entity\Cac;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Utils
{
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;

    public function __construct(
        EntityManagerInterface $entityManager,
        SessionInterface $session
    )
    {
        $this->entityManager = $entityManager;
        $this->session = $session;
    }

    /**
     *  convert scraped data from string to float
     *
     * @param string $stringNumber
     * @return float
     */
    public static function fromString(string $stringNumber): float
    {
        return str_replace(['.', ','], ['', '.'], $stringNumber);
    }

    /**
     * retourne un nombre décimal avec un point comme séparateur
     *
     * @param string $stringNumber
     * @return float
     */
    public static function stringToNumber(string $stringNumber): float
    {
        return str_replace(',', '.', $stringNumber);
    }

    /**
     * calculate the date which should be the most recent in the database 
     */
    public function getMostRecentDate(): string
    {
        // je récupère l'heure au format 24 h depuis la timezone de Paris
        date_default_timezone_set('Europe/Paris');

        // je récupère le numéro du jour courant
        $day = date('w');

        // Avant 18:00, marché ouvert : le dernier jour en base est celui de la veille (ou -2 jours pour dimanche et -3 pour lundi)
        [$eves, $default] = (int)date("G") >= 18 ? [["0" => "2", "6" => "1"], "0"] : [["0" => "2", "1" => "3"], "1"];
        $lastDay = array_key_exists($day, $eves) ? $eves[$day] : $default;

        // Calcul de la date la plus récente en tenant compte des contraintes de jours ouvrés
        return $this->getCurrentDate($lastDay);
    }

    /**
     * @param string $lastDay
     * @return string
     */
    private function getCurrentDate(string $lastDay): string
    {
        return (new \DateTime)
            ->sub(new \DateInterval("P{$lastDay}D"))
            ->format("d/m/Y");
    }

    /**
     * @return mixed
     */
    public function setCacInSession()
    {
        $cacList = $this->entityManager->getRepository(Cac::class)->findBy([], ['id' => 'DESC'], 10);
        $this->session->set('cac', $cacList);

        return $this->session->get('cac');
    }
}
