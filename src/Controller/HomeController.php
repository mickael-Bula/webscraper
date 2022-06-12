<?php

namespace App\Controller;

use App\Entity\Cac;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\DataScraper;
use App\Service\SaveDataInDatabase;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;

class HomeController extends AbstractController
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @Route("/", name="app_home")
     */
    public function index(SaveDataInDatabase $saveDataInDatabase, ManagerRegistry $managerRegistry): Response
    {
        // on commence par vérifier en session la présence des données du CAC, sinon on y charge celles-ci
        $session = $this->requestStack->getSession();
        if (!$session->has("cac")) {
            $cac = $managerRegistry->getRepository(Cac::class)->findBy([], ['id' => 'DESC'], 10);
            $session->set("cac", $cac);
        }
        $cac = $session->get("cac");

        // je récupère l'heure au format 24h depuis la timezone de Paris
        date_default_timezone_set('Europe/Paris');

        // je récupère le numéro du jour courant
        $day = date('w');

        // Avant 18:00 on considère que le marché est ouvert : le dernier jour complet est donc celui de la veille.
        // On tient également compte des jours de week end (non ouvrés)
        if (date("G") >= "18") {
            // $eves = ["numéro du jour dans la semaine" => "nombre de jours à retrancher pour obtenir la veille ouvrée"]
            $eves = ["0" => "2", "6" => "1"];
            $default = "0";
        } else {
            $eves = ["0" => "2", "1" => "3"];
            $default = "1";
        }
        $lastDay = array_key_exists($day, $eves) ? $eves[$day] : $default;

        // je recalcule la date pour tenir compte des contraintes précédentes
        $currentDate = new \DateTime();
        $lastDate = $currentDate->sub(new \DateInterval("P{$lastDay}D"))->format("d/m/Y");

        // ici on peut ajouter un test : si dimanche, doit retourner j-2 ; si lundi 16h => j-3 ; si mardi 18h => j

        // je compare $lastDate avec la date la plus récente en session (et donc en BDD)
        $lastDateInSession = $session->get("cac")[0]->getCreatedAt()->format("d/m/Y");

        // si les dates ne correspondent pas, je lance le scraping pour récupérer les données manquantes
        if ($lastDate !== $lastDateInSession) {
            $data = DataScraper::getData();
            // et j'enregistre les 10 données les plus récentes en session
            $cac = array_slice($data, 0, 10);
            $session->set("cac", $cac);

            // j'externalise l'insertion en BDD dans un service dédié
            $lastDate = $saveDataInDatabase->appendData($cac);
        }

        // je crée une portion de tableau pour affichage
        $displayData = array_slice($cac, 0, 10);

        return $this->render('home/index.html.twig', compact('displayData', 'lastDate'));
    }
}
