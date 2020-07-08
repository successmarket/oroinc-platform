<?php
namespace Oro\Bundle\MessageQueueBundle\Tests\Functional\Consumption\Dbal\Extension;

use Doctrine\DBAL\Types\Types;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\MessageQueue\Consumption\Context;
use Oro\Component\MessageQueue\Consumption\Dbal\DbalCliProcessManager;
use Oro\Component\MessageQueue\Consumption\Dbal\DbalPidFileManager;
use Oro\Component\MessageQueue\Consumption\Dbal\Extension\RedeliverOrphanMessagesDbalExtension;
use Oro\Component\MessageQueue\Test\DbalSchemaExtensionTrait;
use Oro\Component\MessageQueue\Transport\Dbal\DbalConnection;
use Oro\Component\MessageQueue\Transport\Dbal\DbalMessageConsumer;
use Oro\Component\Testing\TempDirExtension;
use Psr\Log\NullLogger;

class RedeliverOrphanMessagesDbalExtensionTest extends WebTestCase
{
    use DbalSchemaExtensionTrait;
    use TempDirExtension;

    protected function setUp(): void
    {
        $this->initClient();

        $this->ensureTableExists('message_queue');
    }

    protected function tearDown(): void
    {
        $this->dropTable('message_queue');
    }


    public function testShouldRedeliverOrphanMessages()
    {
        $connection = $this->createConnection();
        $dbal = $connection->getDBALConnection();

        $pidDir = $this->getTempDir('message-queue');
        file_put_contents($pidDir.'/consumer-id.pid', '123456');

        $dbal->insert('message_queue', [
            'consumer_id' => 'consumer-id',
            'redelivered' => false,
            'queue' => 'queue',
            'priority' => 1,
        ], ['redelivered' => Types::BOOLEAN]);
        $id = (int) $dbal->lastInsertId('message_queue_id_seq');

        $consumer = $this->createMessageConsumerMock();
        $consumer
            ->expects($this->once())
            ->method('getConsumerId')
            ->will($this->returnValue('any-other-consumer-id'))
        ;

        //guard
        $this->assertGreaterThan(0, $id);

        // test
        $context = new Context($connection->createSession());
        $context->setLogger(new NullLogger());
        $context->setMessageConsumer($consumer);

        $extension = new RedeliverOrphanMessagesDbalExtension(
            new DbalPidFileManager($pidDir),
            new DbalCliProcessManager(),
            ':console'
        );
        $extension->onBeforeReceive($context);

        $messages = $dbal->executeQuery('SELECT * FROM message_queue WHERE id = ?', [$id])->fetchAll();

        $this->assertCount(1, $messages);
        $this->assertEquals($id, $messages[0]['id']);
        $this->assertNull($messages[0]['consumer_id']);
        $this->assertTrue((bool) $messages[0]['redelivered']);
    }

    /**
     * @return DbalConnection
     */
    private function createConnection()
    {
        $dbal = $this->getContainer()->get('doctrine.dbal.default_connection');

        return new DbalConnection($dbal, 'message_queue');
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|DbalMessageConsumer
     */
    private function createMessageConsumerMock()
    {
        return $this->createMock(DbalMessageConsumer::class, [], [], '', false);
    }
}
