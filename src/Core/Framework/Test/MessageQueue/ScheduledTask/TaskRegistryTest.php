<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\FooMessage;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\TestTask;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class TaskRegistryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $scheduledTaskRepo;

    private TaskRegistry $registry;

    public function setUp(): void
    {
        $this->scheduledTaskRepo = $this->getContainer()->get('scheduled_task.repository');

        $this->registry = new TaskRegistry(
            [
                new TestTask(),
            ],
            $this->scheduledTaskRepo
        );
    }

    public function testOnNonRegisteredTask(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM scheduled_task');

        $this->registry->registerTasks();

        $tasks = $this->scheduledTaskRepo->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(1, $tasks);
        /** @var ScheduledTaskEntity $task */
        $task = $tasks->first();
        static::assertInstanceOf(ScheduledTaskEntity::class, $task);
        static::assertEquals(TestTask::class, $task->getScheduledTaskClass());
        static::assertEquals(TestTask::getDefaultInterval(), $task->getRunInterval());
        static::assertEquals(TestTask::getTaskName(), $task->getName());
        static::assertEquals(ScheduledTaskDefinition::STATUS_SCHEDULED, $task->getStatus());
    }

    public function testOnAlreadyRegisteredTask(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM scheduled_task');

        $this->scheduledTaskRepo->create([
            [
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => 5,
                'status' => ScheduledTaskDefinition::STATUS_FAILED,
            ],
        ], Context::createDefaultContext());

        $this->registry->registerTasks();

        $tasks = $this->scheduledTaskRepo->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(1, $tasks);
        /** @var ScheduledTaskEntity $task */
        $task = $tasks->first();
        static::assertInstanceOf(ScheduledTaskEntity::class, $task);
        static::assertEquals(TestTask::class, $task->getScheduledTaskClass());
        static::assertEquals(5, $task->getRunInterval());
        static::assertEquals('test', $task->getName());
        static::assertEquals(ScheduledTaskDefinition::STATUS_FAILED, $task->getStatus());
    }

    public function testWithWrongClass(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'Tried to register "%s" as scheduled task, but class does not extend ScheduledTask',
            FooMessage::class
        ));
        $registry = new TaskRegistry(
            /** @phpstan-ignore-next-line we want to test the exception that phpstan also reports */
            [
                new FooMessage(),
            ],
            $this->scheduledTaskRepo
        );

        $registry->registerTasks();
    }

    public function testItDeletesNotAvailableTasks(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM scheduled_task');

        $this->scheduledTaskRepo->create([
            [
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => 5,
                'status' => ScheduledTaskDefinition::STATUS_FAILED,
            ],
        ], Context::createDefaultContext());

        $registry = new TaskRegistry([], $this->scheduledTaskRepo);
        $registry->registerTasks();

        $tasks = $this->scheduledTaskRepo->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(0, $tasks);
    }
}
