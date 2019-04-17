<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\Test\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\MaintenanceEvent;
use Mautic\CoreBundle\EventListener\MaintenanceSubscriber;
use Mautic\UserBundle\Entity\UserTokenRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Translation\TranslatorInterface;

class MaintenanceSubscriberTest extends \PHPUnit_Framework_TestCase
{
    /** @var MockObject */
    private $connection;

    /** @var MockObject */
    private $userTokenRepository;

    /** @var MockObject */
    private $translator;

    public function setUp()
    {
        if (!defined('MAUTIC_TABLE_PREFIX')) {
            define('MAUTIC_TABLE_PREFIX', 'mautic');
        }

        $this->connection = $this->createMock(Connection::class);
        $this->connection->expects($this->any())
            ->method('getExpressionBuilder')
            ->willReturn(new ExpressionBuilder($this->connection));

        $this->userTokenRepository = $this->createMock(UserTokenRepositoryInterface::class);
        $this->userTokenRepository->expects($this->any())
            ->method('deleteExpired')
            ->willReturn(3);

        $this->translator          = $this->createMock(TranslatorInterface::class);
        $this->translator->expects($this->any())
            ->method('trans')
            ->willReturn('tested');
    }

    public function testGetSubscribedEvents()
    {
        $subscriber = new MaintenanceSubscriber($this->connection, $this->userTokenRepository);

        $this->assertEquals(
            [CoreEvents::MAINTENANCE_CLEANUP_DATA => ['onDataCleanup', -50]],
            $subscriber->getSubscribedEvents()
        );
    }

    /**
     * This test will bypass the queries made by UserTokenRepository
     * But will fake the ones made by MaintenanceSubscriber.
     */
    public function testCleanupDataDryRun()
    {
        $eventObserver = $this->createMock(MaintenanceEvent::class);
        $eventObserver->expects($this->exactly(3))
            ->method('isDryRun')
            ->willReturn(true);
        $eventObserver->expects($this->exactly(3))
            ->method('setStat')
            ->with($this->matches('tested'), 3, $this->anything(), $this->anything());

        $eventObserver->expects($this->exactly(2))
            ->method('getDate')
            ->willReturn(new \DateTime());

        $this->userTokenRepository->expects($this->once())
            ->method('deleteExpired')
            ->with(true)
            ->willReturn(3);

        $mockStmnt = $this->createMock(Statement::class);
        $mockStmnt->expects($this->exactly(2))
            ->method('fetchColumn')
            ->willReturn(3);

        $mockQb = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$this->connection])
            ->setMethods(['execute'])
            ->getMock();
        $mockQb->expects($this->exactly(2))
            ->method('execute')
            ->willReturn($mockStmnt);

        $this->connection->expects($this->exactly(2))
            ->method('createQueryBuilder')
            ->willReturn($mockQb);

        $subscriber = new MaintenanceSubscriber($this->connection, $this->userTokenRepository);
        $subscriber->setTranslator($this->translator);

        $this->assertNull($subscriber->onDataCleanup($eventObserver), 'Unexpected number of calls to $event->setStt()');
    }

    public function testCleanupDataNotDryRun()
    {
        $eventObserver = $this->createMock(MaintenanceEvent::class);
        $eventObserver->expects($this->exactly(3))
            ->method('isDryRun')
            ->willReturn(false);
        $eventObserver->expects($this->exactly(3))
            ->method('setStat')
            ->with('tested', 3, $this->anything(), $this->anything());

        $eventObserver->expects($this->exactly(2))
            ->method('getDate')
            ->willReturn(new \DateTime());

        $mockStmnt = $this->createMock(Statement::class);
        $mockStmnt->expects($this->exactly(4))
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls(
                [['id'=> 1], ['id'=>2], ['id'=>3]],
                [],
                [['id'=> 1], ['id'=>2], ['id'=>3]],
                []
            );

        $mockQb = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$this->connection])
            ->setMethods(['execute'])
            ->getMock();
        $mockQb->expects($this->exactly(6))
            ->method('execute')
            ->willReturnOnConsecutiveCalls($mockStmnt, 3, $mockStmnt, $mockStmnt, 3, $mockStmnt);

        $this->connection->expects($this->exactly(4))
            ->method('createQueryBuilder')
            ->willReturn($mockQb);

        $subscriber = new MaintenanceSubscriber($this->connection, $this->userTokenRepository);
        $subscriber->setTranslator($this->translator);

        $this->assertNull($subscriber->onDataCleanup($eventObserver), 'Unexpected number of calls to $event->setStt()');
    }
}
