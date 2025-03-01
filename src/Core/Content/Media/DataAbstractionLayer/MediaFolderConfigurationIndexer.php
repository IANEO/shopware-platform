<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationDefinition;
use Shopware\Core\Content\Media\Event\MediaFolderConfigurationIndexerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package content
 */
class MediaFolderConfigurationIndexer extends EntityIndexer
{
    private IteratorFactory $iteratorFactory;

    private EntityRepository $repository;

    private Connection $connection;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @internal
     */
    public function __construct(
        IteratorFactory $iteratorFactory,
        EntityRepository $repository,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getName(): string
    {
        return 'media_folder_configuration.indexer';
    }

    /**
     * @param array|null $offset
     *
     * @deprecated tag:v6.5.0 The parameter $offset will be native typed
     */
    public function iterate(/*?array */$offset): ?EntityIndexingMessage
    {
        if ($offset !== null && !\is_array($offset)) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Parameter `$offset` of method "iterate()" in class "MediaFolderConfigurationIndexer" will be natively typed to `?array` in v6.5.0.0.'
            );
        }

        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new MediaIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(MediaFolderConfigurationDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        return new MediaIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        $ids = array_unique(array_filter($ids));
        if (empty($ids)) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addAssociation('mediaThumbnailSizes');
        $criteria->setIds($ids);

        $context = $message->getContext();

        /** @var MediaFolderConfigurationCollection $configs */
        $configs = $this->repository->search($criteria, $context);

        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE media_folder_configuration SET media_thumbnail_sizes_ro = :media_thumbnail_sizes_ro WHERE id = :id')
        );

        foreach ($configs as $config) {
            $update->execute([
                'media_thumbnail_sizes_ro' => serialize($config->getMediaThumbnailSizes()),
                'id' => Uuid::fromHexToBytes($config->getId()),
            ]);
        }

        $this->eventDispatcher->dispatch(new MediaFolderConfigurationIndexerEvent($ids, $context, $message->getSkip()));
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }
}
