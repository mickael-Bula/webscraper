<?php

namespace App\Tests\UnitTests\Service;

use App\Service\DataScraper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DataScraperTest extends TestCase
{
    /**
     * test vérifiant les données scrapées
     */
    public function testGetData()
    {
        // je crée un objet de la classe dataScraper pour lancer le scraping en lui injectant un double du logger
        $logger = $this->createMock(LoggerInterface::class);
        $dataScraper = new DataScraper($logger);

        $data = $dataScraper->getData('https://fr.investing.com/indices/france-40-historical-data');

        // je fais une série de tests sur les données récupérées
        $this->assertNotCount(0, $data);
        $this->assertCount(23, $data);
    }
}