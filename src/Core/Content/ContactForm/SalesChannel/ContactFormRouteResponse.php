<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\SalesChannel;

use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @package content
 */
class ContactFormRouteResponse extends StoreApiResponse
{
    /**
     * @var ContactFormRouteResponseStruct
     */
    protected $object;

    public function __construct(ContactFormRouteResponseStruct $object)
    {
        parent::__construct($object);
    }

    public function getResult(): ContactFormRouteResponseStruct
    {
        return $this->object;
    }
}
