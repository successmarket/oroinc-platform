<?php

namespace Oro\Bundle\IntegrationBundle\Tests\Functional\Command;

use Doctrine\ORM\EntityRepository;
use Oro\Bundle\IntegrationBundle\Async\Topics;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Tests\Functional\DataFixtures\LoadChannelData;
use Oro\Bundle\MessageQueueBundle\Entity\Job;
use Oro\Bundle\MessageQueueBundle\Entity\Repository\JobRepository;
use Oro\Bundle\MessageQueueBundle\Test\Functional\MessageQueueExtension;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\MessageQueue\Client\MessagePriority;
use Oro\Component\Testing\Unit\EntityTrait;

/**
 * @dbIsolationPerTest
 */
class SyncCommandTest extends WebTestCase
{
    use MessageQueueExtension, EntityTrait;

    protected function setUp(): void
    {
        $this->initClient();
        $this->loadFixtures([LoadChannelData::class]);
    }

    public function testShouldOutputHelpForTheCommand()
    {
        $result = $this->runCommand('oro:cron:integration:sync', ['--help']);

        static::assertStringContainsString('Usage: oro:cron:integration:sync [options]', $result);
    }

    public function testShouldSendSyncIntegrationWithoutAnyAdditionalOptions()
    {
        /** @var Channel $integration */
        $integration = $this->getReference('oro_integration:foo_integration');

        $result = $this->runCommand('oro:cron:integration:sync', ['--integration='.$integration->getId()]);

        static::assertStringContainsString('Schedule sync for "Foo Integration" integration.', $result);

        $traces = self::getMessageCollector()->getTopicSentMessages(Topics::SYNC_INTEGRATION);

        $this->assertCount(1, $traces);

        $this->assertEquals([
            'integration_id' => $integration->getId(),
            'connector_parameters' => [],
            'connector' => null,
            'transport_batch_size' => 100,
        ], $traces[0]['message']->getBody());
        $this->assertEquals(MessagePriority::VERY_LOW, $traces[0]['message']->getPriority());
    }

    public function testShouldSendSyncIntegrationWithCustomConnectorAndOptions()
    {
        /** @var Channel $integration */
        $integration = $this->getReference('oro_integration:foo_integration');

        $result = $this->runCommand('oro:cron:integration:sync', [
            '--integration='.$integration->getId(),
            '--connector' => 'theConnector',
            'fooConnectorOption=fooValue',
            'barConnectorOption=barValue',
        ]);

        static::assertStringContainsString('Schedule sync for "Foo Integration" integration.', $result);

        $traces = self::getMessageCollector()->getTopicSentMessages(Topics::SYNC_INTEGRATION);

        $this->assertCount(1, $traces);

        $this->assertEquals([
            'integration_id' => $integration->getId(),
            'connector_parameters' => [
                'fooConnectorOption' => 'fooValue',
                'barConnectorOption' => 'barValue',
            ],
            'connector' => 'theConnector',
            'transport_batch_size' => 100,
        ], $traces[0]['message']->getBody());
        $this->assertEquals(MessagePriority::VERY_LOW, $traces[0]['message']->getPriority());
    }

    public function testShouldSendSyncIntegrationWithStaleJob()
    {
        /** @var Channel $integration */
        $integration = $this->getReference('oro_integration:foo_integration');

        $jobHandler = $this->getContainer()->get('oro_message_queue.job.manager');
        $data = [
            'name' => 'oro_integration:sync_integration:'.$integration->getId(),
            'owner_id' => 'owner-id-1',
            'created_at' => new \DateTime('now', new \DateTimeZone('UTC')),
            'unique' => true,
            'status' => Job::STATUS_RUNNING
        ];
        /** @var Job $entity */
        $entity = $this->getEntity(Job::class, $data);
        $jobHandler->saveJob($entity);

        $this->assertNull($this->getJobRepository()->findRootJobByJobNameAndStatuses(
            'oro_integration:sync_integration:'.$integration->getId(),
            [Job::STATUS_STALE]
        ));

        $result = $this->runCommand('oro:cron:integration:sync', ['--integration='.$integration->getId()]);

        static::assertStringContainsString('Schedule sync for "Foo Integration" integration.', $result);

        $traces = self::getMessageCollector()->getTopicSentMessages(Topics::SYNC_INTEGRATION);
        $this->assertCount(1, $traces);
        $this->assertEquals(
            [
                'integration_id' => $integration->getId(),
                'connector_parameters' => [],
                'connector' => null,
                'transport_batch_size' => 100,
            ],
            $traces[0]['message']->getBody()
        );

        $this->assertNotEmpty($this->getJobRepository()->findRootJobByJobNameAndStatuses(
            'oro_integration:sync_integration:'.$integration->getId(),
            [Job::STATUS_STALE]
        ));
    }

    /**
     * @return JobRepository|EntityRepository
     */
    private function getJobRepository(): JobRepository
    {
        $doctrineHelper = self::getContainer()->get('oro_entity.doctrine_helper');

        return $doctrineHelper->getEntityRepository(Job::class);
    }
}
