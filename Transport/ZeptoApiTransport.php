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

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ZeptoApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.zeptomail.%s/%s/email';
    private const VERSION = 'v1.1';

    /**
     * @param string $key          The API key for authenticating with the Zepto Mail service.
     * @param string $tld          The top-level domain (TLD) for the Zepto Mail service, e.g., 'com', 'eu', etc.
     * @param bool   $openTracking Indicates whether user engagement tracking should be enabled
     * @param bool   $clickTracking Indicates whether click tracking should be enabled
     * @param HttpClientInterface|null $client The HTTP client to use for sending requests
     * @param EventDispatcherInterface|null $dispatcher The event dispatcher to use for dispatching events
     * @param LoggerInterface|null $logger The logger to use for logging messages
     * @throws \Exception If the TLD ends with a dot or if the key is not provided
     */
    public function __construct(
        #[\SensitiveParameter] private string $key,
        private string $tld,
        private bool $openTracking = false,
        private bool $clickTracking = false,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->tld = ltrim($this->tld,'.');
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('zepto+api://%s', $this->getZeptoEndpoint());
    }

    /**
     * Queues an email message to be sent to one or more recipients.
     */
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $payload = $this->getPayload($email, $envelope);

        $response = $this->client->request('POST', 'https://'.$this->getZeptoEndpoint(), [
            'body' => json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => sprintf('Zoho-enczapikey %s', $this->key),
            ]
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote ZeptoMail server.', $response, 0, $e);
        }

        if (202 !== $statusCode) {
            try {
                $result = $response->toArray(false);
                throw new HttpTransportException('Unable to send an email (.'.$result['error']['code'].'): '.$result['error']['details'][0]['message'] ?? '', $response, $statusCode);
            } catch (DecodingExceptionInterface $e) {
                throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response, 0, $e);
            }
        }

        $sentMessage->setMessageId(json_decode($response->getContent(false), true)['Data']['message']['request_id']);

        return $response;
    }

    /**
     * Get the message request body.
     */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $addressStringifier = function (Address $address) {
            $stringified = ['address' => $address->getAddress()];

            if ($address->getName()) {
                $stringified['name'] = $address->getName();
            }

            return $stringified;
        };

        $recipientIdentifier = function (Address $address) use ($addressStringifier) {
            return ['email_address' => $addressStringifier($address)];
        };

        $data = [
            'from' => $addressStringifier($envelope->getSender()),
            'to' => array_map($recipientIdentifier, $this->getRecipients($email, $envelope)),
            'htmlbody' => $email->getHtmlBody(),
            'textbody' => $email->getTextBody(),
            'subject' => $email->getSubject(),
            'attachments' => $this->getMessageAttachments($email),
            'track_clicks' => $this->clickTracking,
            'track_opens' => $this->openTracking,
            'mime_headers' => $this->getMessageCustomHeaders($email),
            'inline_images' => $this->getInlineImages($email),
        ];

        if ($emails = array_map($addressStringifier, $email->getCc())) {
            $data['cc'] = $emails;
        }

        if ($emails = array_map($addressStringifier, $email->getBcc())) {
            $data['bcc'] = $emails;
        }

        if ($emails = array_map($addressStringifier, $email->getReplyTo())) {
            $data['reply_to'] = $emails;
        }

        return $data;
    }

    /**
     * List of attachments. Please note that the service limits the total size
     * of an email request (which includes attachments) to 10 MB.
     */
    private function getMessageAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
            $disposition = $headers->getHeaderBody('Content-Disposition');
            if ($disposition === 'inline') {
                // Inline images are handled separately
                continue;
            }
            $att = [
                'name' => $filename,
                'content' => base64_encode($attachment->getBody()),
                'mime_type' => $headers->get('Content-Type')->getBody(),
            ];

            $attachments[] = $att;
        }

        return $attachments;
    }

    private function getInlineImages(Email $email): array
    {
        $attachments = [];
        // Inline images are treated as attachments with a 'Content-Disposition' of 'inline'.
        $inlineImages = array_filter($email->getAttachments(), function ($attachment) {
            $headers = $attachment->getPreparedHeaders();
            $disposition = $headers->getHeaderBody('Content-Disposition');
            $contentType = $headers->get('Content-Type')->getBody();
            $isImage = is_string($contentType) && str_starts_with(strtolower($contentType), 'image/');
            return 'inline' === $disposition && $isImage;
        });
        foreach ($inlineImages as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
            $attachments[] = [
                'name' => $filename,
                'content' => base64_encode($attachment->getBody()),
                'mime_type' => $headers->get('Content-Type')->getBody(),
                'cid' => $filename,
            ];
        }
        return $attachments;
    }

    /**
     * Get the Zepto endpoint URL.
     */
    private function getZeptoEndpoint(): string
    {
        return $this->host ?: \sprintf(self::HOST, $this->tld, self::VERSION);
    }

    private function getMessageCustomHeaders(Email $email): array
    {
        $headers = [];

        $headersToBypass = [
            'date', 'x-csa-complaints', 'message-id',
            'domainkey-status', 'received-spf', 'authentication-results', 'received',
            'from', 'sender', 'subject', 'to', 'cc', 'bcc', 'reply-to', 'return-path', 'delivered-to',
            'dkim-signature', 'list-id', 'user-agent', 'x-mailer'
        ];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (\in_array(strtolower($name), $headersToBypass, true)) {
                continue;
            }
            $headers[$header->getName()] = $header->getBodyAsString();
        }

        return $headers;
    }
}
