<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Exception\StoreSessionExpiredException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package merchant-services
 *
 * @internal
 * @covers \Shopware\Core\Framework\Store\Exception\StoreSessionExpiredException
 */
class StoreSessionExpiredExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__STORE_SESSION_EXPIRED',
            (new StoreSessionExpiredException())->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_FORBIDDEN,
            (new StoreSessionExpiredException())->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            'Store session has expired',
            (new StoreSessionExpiredException())->getMessage()
        );
    }
}
