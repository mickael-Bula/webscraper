<?php

namespace App\Tests\IntegrationTests\Service;

use App\Entity\Cac;
use App\Service\Utils;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;

class UtilsFonctionnalTest extends KernelTestCase
{
    /** @var EntityManager|object|null */
    private $entityManager;

    private LoggerInterface $logger;
    protected function setUp(): void
    {
        // je lance le kernel qui charge le service container
        self::bootKernel();

        //  j'utilise static::getContainer() pour accéder au service container
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testSetEntityInSession(): void
    {
        // Je crée le répertoire destiné à accueillir les données de la session mockée
        $path = realpath('.' . '/tests');
        $fileSystem = new Filesystem();
        if (!$fileSystem->exists($path . '/mockFileSessionStorage')) {
            $fileSystem->mkdir($path . '/mockFileSessionStorage');
            $path .= '/mockFileSessionStorage';
        }
        // j'utilise la classe MockFileSessionStorage qui simule le comportement d'une session par enregistrement dans un fichier
        $storage = new MockFileSessionStorage($path);
        $session = new Session($storage);

        // je passe la session créée en paramètre de ma classe Utils afin que les données y soient enregistrées
        $utils = new Utils($this->entityManager, $this->logger, $session);
        $results = $utils->setEntityInSession(Cac::class);

        $this->assertNotEmpty($results);
        $this->assertIsArray($results);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // doing this is recommended to avoid memory leaks
        $this->entityManager->close();
        $this->entityManager = null;
    }
}