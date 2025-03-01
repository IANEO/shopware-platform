<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @package core
 */
class IterateEntityIndexerMessage implements AsyncMessageInterface
{
    /**
     * @var string
     */
    protected $indexer;

    /**
     * @var mixed|null
     */
    protected $offset;

    protected array $skip = [];

    /**
     * @internal
     */
    public function __construct(string $indexer, $offset, array $skip = [])
    {
        $this->indexer = $indexer;
        $this->offset = $offset;
        $this->skip = $skip;
    }

    public function getIndexer(): string
    {
        return $this->indexer;
    }

    public function getOffset()
    {
        return $this->offset;
    }

    public function setOffset($offset): void
    {
        $this->offset = $offset;
    }

    public function getSkip(): array
    {
        return $this->skip;
    }
}
