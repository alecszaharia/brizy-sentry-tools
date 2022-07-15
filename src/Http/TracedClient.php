<?php

namespace Brizy\SentryToolsBundle\Http;

use Http\Client\HttpClient;
use Sentry\Breadcrumb;
use Sentry\State\HubInterface;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class TracedClient implements HttpClientInterface
{
    private ?HttpClientInterface $decoratedClient;
    private HubInterface $hub;

    public function __construct(HttpClientInterface $decoratedClient, HubInterface $hub)
    {
        $this->decoratedClient = $decoratedClient ?? HttpClient::create();
        $this->hub             = $hub;
    }

    public function withOptions(array $options) {
        $clone = clone  $this->decoratedClient;
        return $clone->withOptions($options);
    }


    /**
     * @param string $method
     * @param string $url
     * @param array $options
     *
     * @return ResponseInterface
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        /**
         * @var ResponseInterface $response ;
         */
        return $this->traceFunction(
            [$this->decoratedClient, 'request'],
            $method,
            $url,
            $options
        );
    }

    /**
     * @param $responses
     * @param float|null $timeout
     *
     * @return ResponseStreamInterface
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->stream($responses, $timeout);
    }

    /**
     * Calls the given callback by passing to it the specified arguments and
     * wrapping its execution into a child {@see Span} of the current one.
     *
     * @param callable $callback The function to call
     * @param mixed ...$args The arguments to pass to the callback
     *
     * @phpstan-template T
     *
     * @phpstan-param callable(mixed...): T $callback
     *
     * @phpstan-return T
     */
    protected function traceFunction(callable $callback, ...$args)
    {
        $method = (string)$args[0];
        $url    = (string)$args[1];

        $span = $this->hub->getSpan();

        if (null !== $span) {
            $spanContext = new SpanContext();
            $spanContext->setOp('http.client');
            $spanContext->setDescription($method.' '.$url);
            $span = $span->startChild($spanContext);
        }

        try {
            $response = $callback(...$args);

            $breadcrumbData = [
                'url'    => $url,
                'method' => $method,
            ];

            if (null !== $response) {
                $span->setStatus(SpanStatus::createFromHttpStatusCode($response->getStatusCode()));

                $breadcrumbData['status_code']        = $response->getStatusCode();
                $breadcrumbData['response_body_size'] = strlen($response->getContent());
            } else {
                $span->setStatus(SpanStatus::internalError());
            }

            $this->hub->addBreadcrumb(
                new Breadcrumb(
                    Breadcrumb::LEVEL_INFO,
                    Breadcrumb::TYPE_HTTP,
                    'http',
                    null,
                    $breadcrumbData
                )
            );

            $this->hub->addBreadcrumb(
                new Breadcrumb(
                    Breadcrumb::LEVEL_INFO,
                    Breadcrumb::TYPE_DEFAULT,
                    'php',
                    null,
                    [
                        'memory_get_peak_usage'=>memory_get_peak_usage(true),
                        'memory_get_usage'=>memory_get_usage(true)
                    ]
                )
            );

            return $response;
        } finally {
            if (null !== $span) {
                $span->finish();
            }
        }
    }
}