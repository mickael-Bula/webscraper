<?php

namespace App\Tests\UnitTests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\Utils;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class UtilsTest extends TestCase
{
    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $session = $this->createMock(RequestStack::class)->getSession();
        $this->utils = new Utils($entityManager, $session);
    }

    /**
     * test de la transformation des données du cac en nombre décimal
     * le format 1.234,56 est particulier, la règle US étant 1,234.56 et french 1 234,56
     */
    public function testFromString(): void
    {
        $this->assertEquals(6541.72, $this->utils::fromString("6.541,72"));

        $date1 = "2030-01-12";
        $date2 = "2020-12-14";

        $this->assertGreaterThan($date2, $date1);

        echo $date1 < $date2 ? "La date du $date1 est antérieure à celle du $date2" : "La date du $date1 est postérieure à celle du $date2";
    }

    public function testGetMostRecentDate(): void
    {
        // je récupère le jour de veille ouvré calculé par mon Service
        $date = $this->utils->getMostRecentDate();

        // je crée un timestamp à partir de la date courante
        $now = time();

        // j'en extrait le numéro du jour de la semaine ainsi que l'heure pour comparaison
        $day = date("w", $now);
        $hour = date("G", $now);

        // s'il est moins de 18 h
        if ($hour <= "18") {
            // et que le jour n'est ni dimanche ni lundi
            if (!in_array($day, ["0", "1"], true)) {
                // on enlève un jour pour trouver le dernier jour ouvré
                $x = 1;
                // mais si on est dimanche
            } else if ($day === "0") {
                // on enlève 2 jours pour trouver le dernier jour ouvré
                $x = 2;
                // sinon on est lundi et ce sont 3 jours qu'il faut enlever
            } else {
                $x = 3;
            }
        // mais s'il est plus de 18 h
        } else if (!in_array($day, ["0", "6"], true)) {
            // le dernier jour ouvré est alors le jour en cours
            $x = 0;
        // mais si on est samedi
        } else if ($day === "6") {
            // on enlève un jour pour avoir le dernier jour ouvré
            $x = 1;
        // sinon on est alors dimanche
        } else {
            // et ce sont 2 jours que l'on doit ôter
            $x = 2;
        }
        // je calcule un timestamp en soustrayant le résultat des conditions précédentes
        $eve = strtotime("-{$x} day");
        // je convertis le timestamp en chaîne
        $eve = date("d/m/Y", $eve);
        
        // je teste enfin par comparaison cette chaîne avec celle récupérée du Service
        $this->assertEquals($eve, $date);
    }
}
