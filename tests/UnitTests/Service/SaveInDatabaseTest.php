<?php

namespace App\Tests\UnitTests\Service;

use App\Entity\Cac;
use App\Entity\Lvc;
use App\Entity\User;
use App\Service\Utils;
use Psr\Log\LoggerInterface;
use App\Service\MailerService;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManager;
use App\Repository\CacRepository;
use App\Repository\UserRepository;
use App\Service\SaveDataInDatabase;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Security\Core\Security;

class SaveInDatabaseTest extends TestCase
{
    /** @var EntityManager|object|null */
    private $entityManager;

    /** @var Security */
    private $security;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManager::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->security = $this->createMock(Security::class);
        $this->mailer = $this->createMock(MailerService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }
    public function testAppendDataWithNullParam(): void
    {
        // instanciation de la classe SaveDataInDatabase avec des doubles passés en paramètres
        $saveDataInDB = new SaveDataInDatabase(
            $this->entityManager,
            $this->userRepository,
            $this->security,
            $this->mailer,
            $this->logger
        );

        // double des appels à doctrine
        $stub = $this->createStub(CacRepository::class);
        $stub->method('findOneBy')->willReturn(null);
        $this->entityManager
            ->method('getRepository')
            ->willReturn($stub);

        // double de la classe ClassMetadata pour forcé le retour de sa méthode getName()
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getName')->willReturn('cac');
        $this->entityManager
            ->method('getClassMetadata')
            ->willReturn($metadata);

        // liste des paramètres fournis à l'appel de la méthode testée
        $data = [];
        $cac = null;

        // récupère le retour de la méthode testée
        $result = $saveDataInDB->appendData($data, $cac);

        // vérifie l'assertion
        $this->assertNull($result);
    }

    public function testAppendDataWithNullValueForCacGetCreatedAt(): void
    {
        // instanciation de la classe SaveDataInDatabase avec des doubles passés en paramètres
        $saveDataInDB = new SaveDataInDatabase(
            $this->entityManager,
            $this->userRepository,
            $this->security,
            $this->mailer,
            $this->logger
        );
        // liste des paramètres fournis à l'appel de la méthode testée
        $data = [];
        $cac = new Cac();

        // double des appels à doctrine
        $stub = $this->createStub(CacRepository::class);
        $stub->method('findOneBy')->willReturn($cac);
        $this->entityManager
            ->method('getRepository')
            ->willReturn($stub);

        // double de la classe ClassMetadata pour forcé le retour de sa méthode getName()
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getName')->willReturn('cac');
        $this->entityManager
            ->method('getClassMetadata')
            ->willReturn($metadata);

        // récupère le retour de la méthode testée
        $result = $saveDataInDB->appendData($data, $cac);

        // vérifie l'assertion
        $this->assertNull($result);
    }

    public function testAppendDataReturnsCurrentDateInEuropeanFormat(): void
    {
        // instanciation de la classe SaveDataInDatabase avec des doubles passés en paramètres
        $saveDataInDB = new SaveDataInDatabase(
            $this->entityManager,
            $this->userRepository,
            $this->security,
            $this->mailer,
            $this->logger
        );
        // liste des paramètres fournis à l'appel de la méthode testée
        $data = [];
        $cac = new Cac();
        $cac->setCreatedAt(new \DateTime());

        // double des appels à doctrine
        $stub = $this->createStub(CacRepository::class);
        $stub->method('findOneBy')->willReturn($cac);
        $this->entityManager
            ->method('getRepository')
            ->willReturn($stub);

        // double de la classe ClassMetadata pour forcé le retour de sa méthode getName()
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getName')->willReturn('cac');
        $this->entityManager
            ->method('getClassMetadata')
            ->willReturn($metadata);

        // récupère le retour de la méthode testée
        $result = $saveDataInDB->appendData($data, $cac);

        // vérifie l'assertion
        $now = new \DateTime();
        $this->assertEquals($now->format('d/m/Y'), $result);
    }

    public function testAppendDataReturnsCurrentDateInAmericanFormat(): void
    {
        // instanciation de la classe SaveDataInDatabase avec des doubles passés en paramètres
        $saveDataInDB = new SaveDataInDatabase(
            $this->entityManager,
            $this->userRepository,
            $this->security,
            $this->mailer,
            $this->logger
        );
        // liste des paramètres fournis à l'appel de la méthode testée
        $data = [];
        $lvc = new Lvc();
        $lvc->setCreatedAt(new \DateTime());

        // double des appels à doctrine
        $stub = $this->createStub(CacRepository::class);
        $stub->method('findOneBy')->willReturn($lvc);
        $this->entityManager
            ->method('getRepository')
            ->willReturn($stub);

        // double de la classe ClassMetadata pour forcé le retour de sa méthode getName()
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getName')->willReturn('lvc');
        $this->entityManager
            ->method('getClassMetadata')
            ->willReturn($metadata);

        // récupère le retour de la méthode testée
        $result = $saveDataInDB->appendData($data, $lvc);

        // vérifie l'assertion
        $now = new \DateTime();
        $this->assertEquals($now->format('m/d/Y'), $result);
    }
}