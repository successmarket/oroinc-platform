<?php

namespace Oro\Bundle\NotificationBundle\Tests\Unit\Provider;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\NotificationBundle\Entity\EmailNotification;
use Oro\Bundle\NotificationBundle\Event\Handler\EventHandlerInterface;
use Oro\Bundle\NotificationBundle\Event\NotificationEvent;
use Oro\Bundle\NotificationBundle\Provider\NotificationManager;

class NotificationManagerTest extends \PHPUnit\Framework\TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject|Cache */
    private $cache;

    /** @var \PHPUnit\Framework\MockObject\MockObject|EntityManagerInterface */
    private $em;

    /** @var \PHPUnit\Framework\MockObject\MockObject|ManagerRegistry */
    private $doctrine;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(Cache::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->doctrine->expects(self::any())
            ->method('getManagerForClass')
            ->with(EmailNotification::class)
            ->willReturn($this->em);
    }

    private function expectLoadRules(array $rules)
    {
        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        $this->em->expects(self::any())
            ->method('createQueryBuilder')
            ->willReturn($qb);
        $qb->expects(self::once())
            ->method('from')
            ->with(EmailNotification::class, 'e')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('select')
            ->with('e')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('getQuery')
            ->willReturn($query);
        $query->expects(self::once())
            ->method('getResult')
            ->willReturn($rules);
    }

    /**
     * @param string $eventName
     * @param object $entity
     */
    private function expectFetchRulesCache($eventName, $entity)
    {
        $this->cache->expects(self::once())
            ->method('fetch')
            ->with('rules')
            ->willReturn([get_class($entity) => [$eventName]]);
        $this->cache->expects(self::never())
            ->method('save');
    }

    /**
     * @param string $eventName
     * @param object $entity
     *
     * @return EmailNotification
     */
    private function createRule($eventName, $entity)
    {
        $rule = new EmailNotification();
        $rule->setEntityName(get_class($entity));
        $rule->setEventName($eventName);

        return $rule;
    }

    public function testProcess()
    {
        $eventName = 'test_event';
        $entity = $this->createMock(\stdClass::class);

        $matchedRule = $this->createRule($eventName, $entity);
        $this->expectFetchRulesCache($eventName, $entity);
        $this->expectLoadRules([
            $matchedRule,
            $this->createRule('another_event', $entity),
            $this->createRule($eventName, new \stdClass())
        ]);

        $handler1 = $this->createMock(EventHandlerInterface::class);
        $handler2 = $this->createMock(EventHandlerInterface::class);

        $notificationEvent = new NotificationEvent($entity);
        $handler1->expects(self::once())
            ->method('handle')
            ->with(self::identicalTo($notificationEvent), [$matchedRule]);
        $handler2->expects(self::once())
            ->method('handle')
            ->with(self::identicalTo($notificationEvent), [$matchedRule]);

        $manager = new NotificationManager([$handler1, $handler2], $this->cache, $this->doctrine);
        $manager->process($notificationEvent, $eventName);
        self::assertFalse($notificationEvent->isPropagationStopped());
    }

    public function testProcessWhenSomeHandlerStopsPropagation()
    {
        $eventName = 'test_event';
        $entity = $this->createMock(\stdClass::class);

        $this->expectFetchRulesCache($eventName, $entity);
        $this->expectLoadRules([$this->createRule($eventName, $entity)]);

        $handler1 = $this->createMock(EventHandlerInterface::class);
        $handler2 = $this->createMock(EventHandlerInterface::class);
        $handler1->expects(self::once())
            ->method('handle')
            ->willReturnCallback(function (NotificationEvent $event) {
                $event->stopPropagation();
            });
        $handler2->expects(self::never())
            ->method('handle');

        $notificationEvent = new NotificationEvent($entity);
        $manager = new NotificationManager([$handler1, $handler2], $this->cache, $this->doctrine);
        $manager->process($notificationEvent, $eventName);
        self::assertTrue($notificationEvent->isPropagationStopped());
    }

    public function testProcessNoRulesCache()
    {
        $eventName = 'test_event';
        $entity = $this->createMock(\stdClass::class);

        $this->cache->expects(self::once())
            ->method('fetch')
            ->with('rules')
            ->willReturn(false);
        $this->cache->expects(self::once())
            ->method('save')
            ->with(
                'rules',
                [
                    get_class($entity)   => ['some_event', 'another_event'],
                    'Test\AnotherEntity' => [$eventName]
                ]
            );

        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        $this->em->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);
        $qb->expects(self::once())
            ->method('from')
            ->with(EmailNotification::class, 'e')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('distinct')
            ->with(true)
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('select')
            ->with('e.entityName, e.eventName')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('getQuery')
            ->willReturn($query);
        $query->expects(self::once())
            ->method('getArrayResult')
            ->willReturn([
                ['entityName' => get_class($entity), 'eventName' => 'some_event'],
                ['entityName' => get_class($entity), 'eventName' => 'another_event'],
                ['entityName' => 'Test\AnotherEntity', 'eventName' => $eventName]
            ]);

        $handler1 = $this->createMock(EventHandlerInterface::class);
        $handler1->expects(self::never())
            ->method('handle');

        $notificationEvent = new NotificationEvent($entity);
        $manager = new NotificationManager([$handler1], $this->cache, $this->doctrine);
        $manager->process($notificationEvent, $eventName);
    }
}
