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
    private LoggerInterface $logger;
    private SessionInterface $session;
    private $entityName;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        SessionInterface $session
    )
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
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
        $flatNumber = str_replace('.', '', $stringNumber);

        return str_replace(',', '.', $flatNumber);
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
        [$eves, $default] = intval(date("G")) >= 18 ? [["0" => "2", "6" => "1"], "0"] : [["0" => "2", "1" => "3"], "1"];
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
     * Insère en session les 10 résultats les plus récents de l'entité passée en argument
     * @param $entity
     * @return array[]
     */
    public function setEntityInSession($entity): array
    {
        $this->entityName = $this->getEntityName($entity);
        $entityName = $this->entityName;
        $em = $this->entityManager->getRepository($entity);

        // Si l'entity est Cac, on récupère l'ensemble des 10 dernières cotations, si l'entity est Lvc on récupère uniquement les cours de clôtures
        $entities = $entityName === 'cac' ? $em->findBy([], ['id' => 'DESC'], 10) : $em->findLastTenClosingDesc();

        $this->session->set($entityName, $entities);

        return $this->session->get($entityName);
    }

    /**
     * @param $entity
     * @return string
     */
    private function getEntityName($entity): string
    {
        $entityName = explode('\\', $entity);
        $entityName = strtolower(end($entityName));

        // on logue une erreur si l'entité n'est ni cac ni lvc
        if (!in_array($entityName, ['cac', 'lvc'])) {
            $this->logger->error(sprintf("L'entité %s est inconnue.", $entity));
            $this->session->getFlashBag()->add("error", "Tentative d'enregistrement d'une entité inconnue : vérifiez les logs applicatifs");
        }

        return $entityName;
    }
}
