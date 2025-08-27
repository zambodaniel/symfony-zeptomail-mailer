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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Bridge\Zepto\Transport\ZeptoApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ZeptoApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(ZeptoApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): array
    {
        return [
            [
                new ZeptoApiTransport('KEY', 'eu'),
                'zepto+api://api.zeptomail.eu/v1.1/email',
            ],
        ];
    }

    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new ZeptoApiTransport('KEY', 'eu');
        $method = new \ReflectionMethod(ZeptoApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('mime_headers', $payload);
        $this->assertArrayHasKey('foo', $payload['mime_headers']);
        $this->assertEquals('bar', $payload['mime_headers']['foo']);
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.zeptomail.eu/v1.1/email', $url);

            $body = json_decode($options['body'], true);

            $this->assertSame('fabpot@symfony.com', $body['from']['address']);
            $this->assertSame('Saif Eddin', $body['to'][0]['email_address']['name']);
            $this->assertSame('saif.gmati@symfony.com', $body['to'][0]['email_address']['address']);
            $this->assertSame('Hello!', $body['subject']);
            $this->assertSame('Hello There!', $body['textbody']);
            $this->assertSame('<div>Hello There!</div>', $body['htmlbody']);

            $this->assertSame([
                [
                    'name' => 'Hello There!',
                    'content' => base64_encode('content'),
                    'mime_type' => 'text/plain',
                ],
            ], $body['attachments']);
            $this->assertCount(1, $body['attachments']);

            $this->assertSame([
                [
                    'name' => 'my_inline_image',
                    'content' => base64_encode('base64EncodedImage'),
                    'mime_type' => 'image/jpeg',
                    'cid' => 'my_inline_image',
                ]
            ], $body['inline_images']);
            $this->assertCount(1, $body['inline_images']);
            return new JsonMockResponse([
                'Data' => ['message' => ['request_id' => 'foobar']],
            ], [
                'http_code' => 202,
            ]);
        });

        $transport = new ZeptoApiTransport('KEY', 'eu', false, false, $client);

        $mail = new Email();

        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!')
            ->html('<div>Hello There!</div>')
            ->attach('content', 'Hello There!', 'text/plain')
            ->embed('base64EncodedImage', 'my_inline_image', 'image/jpeg');

        $message = $transport->send($mail);

        $this->assertSame('foobar', $message->getMessageId());
    }

    public function testTagAndMetadataHeaders()
    {
        $email = new Email();
        $email->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'));
        $email->getHeaders()->add(new TagHeader('category-one'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new ZeptoApiTransport('KEY', 'eu');
        $method = new \ReflectionMethod(ZeptoApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('mime_headers', $payload);
        $this->assertArrayHasKey('X-Tag', $payload['mime_headers']);
        $this->assertArrayHasKey('X-Metadata-Color', $payload['mime_headers']);
        $this->assertArrayHasKey('X-Metadata-Client-ID', $payload['mime_headers']);

        $this->assertCount(3, $payload['mime_headers']);

        $this->assertSame('category-one', $payload['mime_headers']['X-Tag']);
        $this->assertSame('blue', $payload['mime_headers']['X-Metadata-Color']);
        $this->assertSame('12345', $payload['mime_headers']['X-Metadata-Client-ID']);
    }
}
