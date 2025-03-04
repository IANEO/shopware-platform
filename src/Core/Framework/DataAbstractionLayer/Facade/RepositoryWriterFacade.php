<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Facade;

use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Feature;

/**
 * The `writer` service allows you to write data, that is stored inside shopware.
 * Keep in mind that your app needs to have the correct permissions for the data it writes through this service.
 *
 * @script-service custom_endpoint
 */
class RepositoryWriterFacade
{
    private DefinitionInstanceRegistry $registry;

    private SyncService $syncService;

    private Context $context;

    /**
     * @internal
     */
    public function __construct(
        DefinitionInstanceRegistry $registry,
        SyncService $syncService,
        Context $context
    ) {
        $this->registry = $registry;
        $this->context = $context;
        $this->syncService = $syncService;
    }

    /**
     * The `upsert()` method allows you to create or update entities inside the database.
     * If you pass an `id` in the payload it will do an update if an entity with that `id` already exists, otherwise it will be a create.
     *
     * @param string $entityName The name of the entity you want to upsert, e.g. `product` or `media`.
     * @param array<int, mixed> $payload The payload you want to upsert, as a list of associative arrays, where each associative array represents the payload for one entity.
     *
     * @return EntityWrittenContainerEvent The WriteEvents that were generated by executing the `upsert()`.
     *
     * @example writer-create/script.twig Create a new entity.
     * @example writer-update/script.twig Update an existing entity.
     */
    public function upsert(string $entityName, array $payload): EntityWrittenContainerEvent
    {
        $repository = $this->registry->getRepository($entityName);

        return $repository->upsert($payload, $this->context);
    }

    /**
     * The `delete()` method allows you to delete entities from the database.
     *
     * @param string $entityName The name of the entity you want to delete, e.g. `product` or `media`.
     * @param array<int, mixed> $payload The primary keys of the entities you want to delete, as a list of associative arrays, associative array represents the primary keys for one entity.
     *
     * @return EntityWrittenContainerEvent The WriteEvents that were generated by executing the `delete()`.
     *
     * @example writer-delete/script.twig Delete an entity.
     */
    public function delete(string $entityName, array $payload): EntityWrittenContainerEvent
    {
        $repository = $this->registry->getRepository($entityName);

        return $repository->delete($payload, $this->context);
    }

    /**
     * The `sync()` method allows you to execute updates and deletes to multiple entities in one method call.
     *
     * @param array<int, mixed> $payload All operations that should be executed.
     *
     * @return SyncResult The result of the `sync()`.
     *
     * @example writer-sync/script.twig Update an entity and delete another one with one `sync()` call.
     */
    public function sync(array $payload): SyncResult
    {
        if (Feature::isActive('FEATURE_NEXT_15815')) {
            $behavior = new SyncBehavior();
        } else {
            $behavior = new SyncBehavior(true, true);
        }

        $operations = [];
        foreach ($payload as $key => $operation) {
            if (isset($operation['key'])) {
                $key = $operation['key'];
            }
            $operations[] = new SyncOperation((string) $key, (string) $operation['entity'], (string) $operation['action'], $operation['payload']);
        }

        return $this->syncService->sync($operations, $this->context, $behavior);
    }
}
