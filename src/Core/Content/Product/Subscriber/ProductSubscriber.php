<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\Product\AbstractIsNewDetector;
use Shopware\Core\Content\Product\AbstractProductMaxPurchaseCalculator;
use Shopware\Core\Content\Product\AbstractProductVariationBuilder;
use Shopware\Core\Content\Product\AbstractPropertyGroupSorter;
use Shopware\Core\Content\Product\AbstractSalesChannelProductBuilder;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\Entity\PartialSalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 *
 * @package inventory
 */
class ProductSubscriber implements EventSubscriberInterface
{
    private AbstractSalesChannelProductBuilder $salesChannelProductBuilder;

    private AbstractProductVariationBuilder $productVariationBuilder;

    private AbstractProductPriceCalculator $calculator;

    private AbstractPropertyGroupSorter $propertyGroupSorter;

    private AbstractProductMaxPurchaseCalculator $maxPurchaseCalculator;

    private AbstractIsNewDetector $isNewDetector;

    private SystemConfigService $systemConfigService;

    /**
     * @internal
     */
    public function __construct(
        AbstractSalesChannelProductBuilder $salesChannelProductBuilder,
        AbstractProductVariationBuilder $productVariationBuilder,
        AbstractProductPriceCalculator $calculator,
        AbstractPropertyGroupSorter $propertyGroupSorter,
        AbstractProductMaxPurchaseCalculator $maxPurchaseCalculator,
        AbstractIsNewDetector $isNewDetector,
        SystemConfigService $systemConfigService
    ) {
        $this->salesChannelProductBuilder = $salesChannelProductBuilder;
        $this->productVariationBuilder = $productVariationBuilder;
        $this->calculator = $calculator;
        $this->propertyGroupSorter = $propertyGroupSorter;
        $this->maxPurchaseCalculator = $maxPurchaseCalculator;
        $this->isNewDetector = $isNewDetector;
        $this->systemConfigService = $systemConfigService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_LOADED_EVENT => 'loaded',
            'product.partial_loaded' => 'partialEntityLoaded',
            'sales_channel.' . ProductEvents::PRODUCT_LOADED_EVENT => 'salesChannelLoaded',
            'sales_channel.product.partial_loaded' => 'partialSalesChannelLoaded',
        ];
    }

    public function loaded(EntityLoadedEvent $event): void
    {
        $this->entityLoaded($event->getEntities(), $event->getContext());
    }

    /**
     * @internal
     */
    public function partialEntityLoaded(PartialEntityLoadedEvent $event): void
    {
        $this->entityLoaded($event->getEntities(), $event->getContext());
    }

    public function salesChannelLoaded(SalesChannelEntityLoadedEvent $event): void
    {
        $this->productSalesChannelLoaded($event->getEntities(), $event->getSalesChannelContext());
    }

    /**
     * @internal
     */
    public function partialSalesChannelLoaded(PartialSalesChannelEntityLoadedEvent $event): void
    {
        $this->productSalesChannelLoaded($event->getEntities(), $event->getSalesChannelContext());
    }

    /**
     * @param Entity[] $collection
     */
    private function entityLoaded(array $collection, Context $context): void
    {
        /** @var ProductEntity $product */
        foreach ($collection as $product) {
            // CheapestPrice will only be added to SalesChannelProductEntities in the Future
            if (!Feature::isActive('FEATURE_NEXT_16151')) {
                $price = $product->get('cheapestPrice');

                if ($price instanceof CheapestPriceContainer) {
                    $resolved = $price->resolve($context);
                    $product->assign([
                        'cheapestPrice' => $resolved,
                        'cheapestPriceContainer' => $price,
                    ]);
                }
            }

            $this->setDefaultLayout($product);

            $this->productVariationBuilder->build($product);
        }
    }

    /**
     * @param Entity[] $elements
     */
    private function productSalesChannelLoaded(array $elements, SalesChannelContext $context): void
    {
        /** @var SalesChannelProductEntity $product */
        foreach ($elements as $product) {
            if (Feature::isActive('FEATURE_NEXT_16151')) {
                $price = $product->get('cheapestPrice');

                if ($price instanceof CheapestPriceContainer) {
                    $resolved = $price->resolve($context->getContext());
                    $product->assign([
                        'cheapestPrice' => $resolved,
                        'cheapestPriceContainer' => $price,
                    ]);
                }
            }

            if (Feature::isActive('v6.5.0.0')) {
                $assigns = [];

                if (($properties = $product->get('properties')) !== null && $properties instanceof PropertyGroupOptionCollection) {
                    $assigns['sortedProperties'] = $this->propertyGroupSorter->sort($properties);
                }

                $assigns['calculatedMaxPurchase'] = $this->maxPurchaseCalculator->calculate($product, $context);

                $assigns['isNew'] = $this->isNewDetector->isNew($product, $context);

                $product->assign($assigns);
            } else {
                Feature::callSilentIfInactive('v6.5.0.0', function () use ($product, $context): void {
                    $this->salesChannelProductBuilder->build($product, $context);
                });
            }

            $this->setDefaultLayout($product, $context->getSalesChannelId());
        }

        $this->calculator->calculate($elements, $context);
    }

    /**
     * @param Entity $product - typehint as Entity because it could be a ProductEntity or PartialEntity
     */
    private function setDefaultLayout(Entity $product, ?string $salesChannelId = null): void
    {
        if (!Feature::isActive('v6.5.0.0') || !$product->has('cmsPageId')) {
            return;
        }

        if ($product->get('cmsPageId') !== null) {
            return;
        }

        $cmsPageId = $this->systemConfigService->get(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $salesChannelId);

        if (!$cmsPageId) {
            return;
        }

        $product->assign(['cmsPageId' => $cmsPageId]);
    }
}
