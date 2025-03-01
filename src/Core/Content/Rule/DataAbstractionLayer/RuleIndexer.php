<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Content\Rule\Event\RuleIndexerEvent;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Content\Rule\RuleEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package business-ops
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class RuleIndexer extends EntityIndexer implements EventSubscriberInterface
{
    public const PAYLOAD_UPDATER = 'rule.payload';

    public const AREA_UPDATER = 'rule.area';

    private IteratorFactory $iteratorFactory;

    private Connection $connection;

    private EntityRepository $repository;

    private RulePayloadUpdater $payloadUpdater;

    private EventDispatcherInterface $eventDispatcher;

    private CartRuleLoader $cartRuleLoader;

    private RuleAreaUpdater $areaUpdater;

    /**
     * @internal
     */
    public function __construct(
        Connection $connection,
        IteratorFactory $iteratorFactory,
        EntityRepository $repository,
        RulePayloadUpdater $payloadUpdater,
        RuleAreaUpdater $areaUpdater,
        CartRuleLoader $cartRuleLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->connection = $connection;
        $this->payloadUpdater = $payloadUpdater;
        $this->areaUpdater = $areaUpdater;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartRuleLoader = $cartRuleLoader;
    }

    public function getName(): string
    {
        return 'rule.indexer';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostInstallEvent::class => 'refreshPlugin',
            PluginPostActivateEvent::class => 'refreshPlugin',
            PluginPostUpdateEvent::class => 'refreshPlugin',
            PluginPostDeactivateEvent::class => 'refreshPlugin',
            PluginPostUninstallEvent::class => 'refreshPlugin',
            RuleEvents::RULE_WRITTEN_EVENT => 'onRuleWritten',
        ];
    }

    public function refreshPlugin(): void
    {
        // Delete the payload and invalid flag of all rules
        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `rule` SET `payload` = null, `invalid` = 0')
        );
        $update->execute();
    }

    /**
     * @param array{offset: string}|null $offset
     *
     * @deprecated tag:v6.5.0 The parameter $offset will be native typed
     */
    public function iterate(/*?array */$offset): ?EntityIndexingMessage
    {
        if ($offset !== null && !\is_array($offset)) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Parameter `$offset` of method "iterate()" in class "RuleIndexer" will be natively typed to `?array` in v6.5.0.0.'
            );
        }

        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new RuleIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(RuleDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        $this->handle(new RuleIndexingMessage(array_values($updates), null, $event->getContext()));

        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        $ids = array_unique(array_filter($ids));
        if (empty($ids)) {
            return;
        }

        if ($message->allow(self::PAYLOAD_UPDATER)) {
            $this->payloadUpdater->update($ids);
        }

        if ($message->allow(self::AREA_UPDATER)) {
            $this->areaUpdater->update($ids);
        }

        $this->eventDispatcher->dispatch(new RuleIndexerEvent($ids, $message->getContext(), $message->getSkip()));
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    public function onRuleWritten(EntityWrittenEvent $event): void
    {
        $this->cartRuleLoader->invalidate();
    }
}
