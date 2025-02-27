<?php declare(strict_types=1);

namespace Shopware\Core\Checkout;

use Shopware\Core\Checkout\DependencyInjection\CompilerPass\CartRedisCompilerPass;
use Shopware\Core\Framework\Bundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @package core
 *
 * @internal
 */
class Checkout extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new CartRedisCompilerPass());

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('cart.xml');
        $loader->load('customer.xml');
        $loader->load('document.xml');
        $loader->load('order.xml');
        $loader->load('payment.xml');
        $loader->load('rule.xml');
        $loader->load('promotion.xml');
        $loader->load('shipping.xml');
    }
}
