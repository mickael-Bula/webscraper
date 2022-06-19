<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\DataScraper;

class DataScraperTest extends TestCase
{
    /**
     * test vérifiant les données scrapées
     */
    public function testGetData()
    {
        // je crée un objet de la classe dataScraper pouir lancer le scraping
        $dataScraper = new DataScraper();
        $data = $dataScraper->getData();

        // je fais une série de tests sur les données récupérées
        $this->assertNotCount(0, $data);
        $this->assertCount(23, $data);
    }
}