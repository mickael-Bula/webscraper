<?php

namespace App\Tests\Service;

use App\Entity\Cac;
use App\Entity\LastHigh;
use App\Entity\User;
use App\Service\DataScraper;
use App\Service\MailerService;
use App\Service\SaveDataInDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class SaveDataInDatabaseTest extends KernelTestCase
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;
    private $userRepository;

    protected function setUp(): void
    {
        // je lance le kernel qui charge le service container
        self::bootKernel();

        //  j'utilise static::getContainer() pour accéder au service container
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->userRepository = $this->entityManager->getRepository(User::class);
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
        $this->assertNotEmpty($newData);
        $this->assertCount(22, $newData);
    }

    public function testSetPositions()
    {
        // je crée des doubles des dépendances requises
        $security = $this->createMock(Security::class);
        $requestStack = $this->createMock(RequestStack::class);
        $mailer = $this->createMock(MailerService::class);

        $entity = $this->entityManager->getRepository(LastHigh::class)->findAll();
        $data = new SaveDataInDatabase(
            $this->entityManager,
            $this->userRepository,
            $security,
            $requestStack,
            $mailer
        );

        $this->assertInstanceOf(Security::class, $security);
        $this->assertInstanceOf(MailerService::class, $mailer);
        $this->assertInstanceOf(RequestStack::class, $requestStack);
        $this->assertInstanceOf(LastHigh::class, $entity[0]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // doing this is recommended to avoid memory leaks
        $this->entityManager->close();
        $this->entityManager = null;
    }
}