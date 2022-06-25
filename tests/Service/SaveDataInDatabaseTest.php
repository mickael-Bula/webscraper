<?php

namespace App\Tests\Service;

use App\Entity\Cac;
use App\Service\DataScraper;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SaveDataInDatabaseTest extends KernelTestCase
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    /**
     * L'idée est de comparer les Datetime plutôt que des string pour éviter des chargements erronées.
     * Mais cette utilisation a aussi un inconvénient car tient compte des heures...
     *
     * doc concernant les tests incluant un repository : https://symfony.com/doc/current/testing/database.html
     *
     * @return void
     */
    public function testAppendData()
    {
        // je crée un objet de la classe dataScraper pour lancer le scraping
        $dataScraper = new DataScraper();
        $data = $dataScraper->getData('https://fr.investing.com/indices/france-40-historical-data');

        $cac = $this->entityManager->getRepository(Cac::class)->findOneBy(["id" => "10"]);
        $lastDate = (!empty($cac)) ? $cac->getCreatedAt() : null;

        $newData = [];
        foreach ($data as $row) {
            $formatedData = \DateTime::createFromFormat("d/m/Y", $row[0]);
            if ($formatedData > $lastDate) {
                $newData[] = $row;
            } else {
                break;
            }
        }
        // pb : l'utilisation de DateTime tient compte des heures et échoue à filtrer correctement la dernière date
        dump($newData);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // doing this is recommended to avoid memory leaks
        $this->entityManager->close();
        $this->entityManager = null;
    }
}