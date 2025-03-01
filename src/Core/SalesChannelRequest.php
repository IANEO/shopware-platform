<?php declare(strict_types=1);

namespace Shopware\Core;

/**
 * @package core
 */
final class SalesChannelRequest
{
    public const ATTRIBUTE_IS_SALES_CHANNEL_REQUEST = '_is_sales_channel';

    public const ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE = 'allow_maintenance';

    public const ATTRIBUTE_THEME_ID = 'theme-id';
    public const ATTRIBUTE_THEME_NAME = 'theme-name';
    public const ATTRIBUTE_THEME_BASE_NAME = 'theme-base-name';

    public const ATTRIBUTE_SALES_CHANNEL_MAINTENANCE = 'sw-maintenance';

    public const ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST = 'sw-maintenance-ip-whitelist';

    /**
     * domain-resolved attributes
     */
    public const ATTRIBUTE_DOMAIN_ID = 'sw-domain-id';
    public const ATTRIBUTE_DOMAIN_LOCALE = '_locale';
    public const ATTRIBUTE_DOMAIN_SNIPPET_SET_ID = 'sw-snippet-set-id';
    public const ATTRIBUTE_DOMAIN_CURRENCY_ID = 'sw-currency-id';

    public const ATTRIBUTE_CANONICAL_LINK = 'sw-canonical-link';

    public const ATTRIBUTE_STOREFRONT_URL = 'sw-storefront-url';

    /**
     * @deprecated tag:v6.5.0 - will be removed as the csrf system will be removed in favor for the samesite approach
     */
    public const ATTRIBUTE_CSRF_PROTECTED = 'csrf_protected';

    /**
     * @deprecated tag:v6.5.0 - will be removed as the proxy will be removed
     */
    public const ATTRIBUTE_STORE_API_PROXY = 'sw-store-api-proxy';

    private function __construct()
    {
    }
}
