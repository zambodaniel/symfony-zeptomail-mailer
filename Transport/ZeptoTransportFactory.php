<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Zepto\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class ZeptoTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if (!\in_array($scheme, ['zepto+api', 'zepto'], true)) {
            throw new UnsupportedSchemeException($dsn, 'zepto', $this->getSupportedSchemes());
        }

        $user = $this->getUser($dsn); // resourceName
        $password = $this->getPassword($dsn); // apiKey
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $trackClicks = (bool) $dsn->getOption('track_clicks', false);
        $trackOpens = (bool) $dsn->getOption('track_opens', false);

        return (new ZeptoApiTransport($password, $user, $trackOpens, $trackClicks, $this->client, $this->dispatcher, $this->logger))->setHost($host);
    }

    protected function getSupportedSchemes(): array
    {
        return ['zepto', 'zepto+api'];
    }
}
