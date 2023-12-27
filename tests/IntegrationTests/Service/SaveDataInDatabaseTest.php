<?php

namespace App\Tests\IntegrationTests\Service;

use App\Entity\Cac;
use App\Entity\LastHigh;
use App\Entity\User;
use App\Service\DataScraper;
use App\Service\MailerService;
use App\Repository\UserRepository;
use App\Service\SaveDataInDatabase;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class SaveDataInDatabaseTest extends KernelTestCase
{
    /** @var EntityManager|object|null */
    private $entityManager;

    protected function setUp(): void
    {
        // je lance le kernel qui charge le service container
        self::bootKernel();

        //  j'utilise static::getContainer() pour accéder au service container
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    /**
     * L'idée est de comparer les Datetime plutôt que des string pour éviter des chargements erronées.
     * Mais cette utilisation a aussi un inconvénient car tient compte des heures...
     *
     * doc concernant les tests incluant un repository : https://symfony.com/doc/current/testing/database.html
     *
     * @return void
     */
    public function testAppendData(): void
    {
        // je crée un objet de la classe dataScraper pour lancer le scraping
        $logger = $this->createMock(LoggerInterface::class);
        $dataScraper = new DataScraper($logger);
        $data = $dataScraper->getData('https://fr.investing.com/indices/france-40-historical-data');

        $cac = $this->entityManager->getRepository(Cac::class)->findOneBy(["id" => "10"]);
        $lastDate = !empty($cac) ? $cac->getCreatedAt() : null;

        $newData = [];
        foreach ($data as $row) {
            $formatedData = \DateTime::createFromFormat("d/m/Y", $row[0]);
            if ($formatedData > $lastDate) {
                $newData[] = $row;
            } else {
                break;
            }
        }
        $this->assertNotEmpty($newData);
        $this->assertCount(21, $newData);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // doing this is recommended to avoid memory leaks
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
