<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Zepto\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Zepto\Transport\ZeptoApiTransport;
use Symfony\Component\Mailer\Bridge\Zepto\Transport\ZeptoTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class ZeptoTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new ZeptoTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('zepto', 'default'),
            true,
        ];

        yield [
            new Dsn('zepto+api', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        yield [
            new Dsn('zepto', 'default', self::USER, self::PASSWORD),
            new ZeptoApiTransport(self::PASSWORD, self::USER, false, false, new MockHttpClient(), null, new NullLogger()),
        ];
        yield [
            new Dsn('zepto', 'HOST', self::USER, self::PASSWORD),
            (new ZeptoApiTransport(self::PASSWORD, self::USER, false, false, new MockHttpClient(), null, new NullLogger()))->setHost('HOST'),
        ];
        yield [
            new Dsn('zepto+api', 'default', self::USER, self::PASSWORD),
            new ZeptoApiTransport(self::PASSWORD, self::USER, false, false, new MockHttpClient(), null, new NullLogger()),
        ];
        yield [
            new Dsn('zepto+api', 'HOST', self::USER, self::PASSWORD),
            (new ZeptoApiTransport(self::PASSWORD, self::USER, false, false, new MockHttpClient(), null, new NullLogger()))->setHost('HOST'),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('zepto+foo', 'default', self::USER, self::PASSWORD),
            'The "zepto+foo" scheme is not supported; supported schemes for mailer "zepto" are: "zepto", "zepto+api".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('zepto', 'default')];
        yield [new Dsn('zepto', 'default', self::USER)];
        yield [new Dsn('zepto', 'default', null, self::PASSWORD)];
        yield [new Dsn('zepto+api', 'default')];
        yield [new Dsn('zepto+api', 'default', self::USER)];
        yield [new Dsn('zepto+api', 'default', null, self::PASSWORD)];
    }
}
