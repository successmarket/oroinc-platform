<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Functional;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\TestFrameworkBundle\Entity\Item;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class EntityPageTest extends WebTestCase
{
    /** @var EntityManager */
    private $entityManager;

    protected function setUp(): void
    {
        $this->initClient([], $this->generateBasicAuthHeader());

        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManagerForClass(Item::class);
    }

    public function testEntityWorkflowsAction()
    {
        $item = new Item();

        $this->entityManager->persist($item);
        $this->entityManager->flush($item);

        $crawler = $this->client->request('GET', $this->getUrl('oro_test_item_view', ['id' => $item->getId()]));

        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 200);
        $this->assertNotEmpty($crawler->html());

        static::assertStringContainsString('Oro Test Workflow', $crawler->html());
        static::assertStringContainsString('To Second Step', $crawler->html());
    }
}
