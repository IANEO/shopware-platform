<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\Event\CategoryRouteCacheKeyEvent;
use Shopware\Core\Content\Category\Event\CategoryRouteCacheTagsEvent;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductBoxStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package content
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class CachedCategoryRoute extends AbstractCategoryRoute
{
    private AbstractCategoryRoute $decorated;

    private CacheInterface $cache;

    private EntityCacheKeyGenerator $generator;

    /**
     * @var AbstractCacheTracer<CategoryRouteResponse>
     */
    private AbstractCacheTracer $tracer;

    /**
     * @var array<string>
     */
    private array $states;

    private EventDispatcherInterface $dispatcher;

    /**
     * @internal
     *
     * @param AbstractCacheTracer<CategoryRouteResponse> $tracer
     * @param array<string> $states
     */
    public function __construct(
        AbstractCategoryRoute $decorated,
        CacheInterface $cache,
        EntityCacheKeyGenerator $generator,
        AbstractCacheTracer $tracer,
        EventDispatcherInterface $dispatcher,
        array $states
    ) {
        $this->decorated = $decorated;
        $this->cache = $cache;
        $this->generator = $generator;
        $this->tracer = $tracer;
        $this->states = $states;
        $this->dispatcher = $dispatcher;
    }

    public static function buildName(string $id): string
    {
        return 'category-route-' . $id;
    }

    public function getDecorated(): AbstractCategoryRoute
    {
        return $this->decorated;
    }

    /**
     * @Since("6.2.0.0")
     * @Route("/store-api/category/{navigationId}", name="store-api.category.detail", methods={"GET","POST"})
     */
    public function load(string $navigationId, Request $request, SalesChannelContext $context): CategoryRouteResponse
    {
        return Profiler::trace('category-route', function () use ($navigationId, $request, $context) {
            if ($context->hasState(...$this->states)) {
                return $this->getDecorated()->load($navigationId, $request, $context);
            }

            $key = $this->generateKey($navigationId, $request, $context);

            if ($key === null) {
                return $this->getDecorated()->load($navigationId, $request, $context);
            }

            $value = $this->cache->get($key, function (ItemInterface $item) use ($navigationId, $request, $context) {
                $name = self::buildName($navigationId);

                $response = $this->tracer->trace($name, function () use ($navigationId, $request, $context) {
                    return $this->getDecorated()->load($navigationId, $request, $context);
                });

                $item->tag($this->generateTags($navigationId, $response, $request, $context));

                return CacheValueCompressor::compress($response);
            });

            return CacheValueCompressor::uncompress($value);
        });
    }

    private function generateKey(string $navigationId, Request $request, SalesChannelContext $context): ?string
    {
        $parts = array_merge(
            $request->query->all(),
            $request->request->all(),
            [$this->generator->getSalesChannelContextHash($context, [RuleAreas::CATEGORY_AREA, RuleAreas::PRODUCT_AREA])]
        );

        $event = new CategoryRouteCacheKeyEvent($navigationId, $parts, $request, $context, null);
        $this->dispatcher->dispatch($event);

        if (!$event->shouldCache()) {
            return null;
        }

        return self::buildName($navigationId) . '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
    }

    /**
     * @return array<string>
     */
    private function generateTags(string $navigationId, CategoryRouteResponse $response, Request $request, SalesChannelContext $context): array
    {
        $tags = array_merge(
            $this->tracer->get(self::buildName($navigationId)),
            $this->extractProductIds($response),
            [self::buildName($navigationId)]
        );

        $event = new CategoryRouteCacheTagsEvent($navigationId, $tags, $request, $response, $context, null);
        $this->dispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }

    /**
     * @return array<string>
     */
    private function extractProductIds(CategoryRouteResponse $response): array
    {
        $page = $response->getCategory()->getCmsPage();

        if ($page === null) {
            return [];
        }

        $ids = [];

        $slots = $page->getElementsOfType('product-slider');
        /** @var CmsSlotEntity $slot */
        foreach ($slots as $slot) {
            $slider = $slot->getData();

            if (!$slider instanceof ProductSliderStruct) {
                continue;
            }

            if ($slider->getProducts() === null) {
                continue;
            }
            foreach ($slider->getProducts() as $product) {
                $ids[] = $product->getId();
                $ids[] = $product->getParentId();
            }
        }

        $slots = $page->getElementsOfType('product-box');
        /** @var CmsSlotEntity $slot */
        foreach ($slots as $slot) {
            $box = $slot->getData();

            if (!$box instanceof ProductBoxStruct) {
                continue;
            }
            if ($box->getProduct() === null) {
                continue;
            }

            $ids[] = $box->getProduct()->getId();
            $ids[] = $box->getProduct()->getParentId();
        }

        $ids = array_values(array_unique(array_filter($ids)));

        return array_merge(
            array_map([EntityCacheKeyGenerator::class, 'buildProductTag'], $ids),
            [EntityCacheKeyGenerator::buildCmsTag($page->getId())]
        );
    }
}
