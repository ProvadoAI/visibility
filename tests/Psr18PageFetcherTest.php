<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use VisibilityDetector\Adapters\Http\Psr18PageFetcher;
use VisibilityDetector\Core\Page\PageFetcher;

/**
 * In-memory PSR-18 client. Maps a request URL to a queued response or a
 * configured transport failure. Never performs a real network call.
 */
final class InMemoryPsr18Client implements ClientInterface
{
    /** @var array<string, ResponseInterface> */
    private array $responses = [];

    /** @var array<string, array{type: string, message: string}> */
    private array $failures = [];

    /** @var array<int, string> */
    public array $requestedUrls = [];

    public function on(string $url, ResponseInterface $response): void
    {
        $this->responses[$url] = $response;
    }

    public function failWith(string $url, string $type, string $message): void
    {
        $this->failures[$url] = ['type' => $type, 'message' => $message];
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $url = (string) $request->getUri();
        $this->requestedUrls[] = $url;

        if (isset($this->failures[$url])) {
            $failure = $this->failures[$url];

            throw match ($failure['type']) {
                'network' => new MockNetworkException($request, $failure['message']),
                'request' => new MockRequestException($request, $failure['message']),
                default => new MockClientException($failure['message']),
            };
        }

        if (isset($this->responses[$url])) {
            return $this->responses[$url];
        }

        throw new MockClientException('No mock response configured for ' . $url);
    }
}

final class MockClientException extends RuntimeException implements ClientExceptionInterface
{
}

final class MockNetworkException extends RuntimeException implements NetworkExceptionInterface
{
    public function __construct(private readonly RequestInterface $request, string $message)
    {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}

final class MockRequestException extends RuntimeException implements RequestExceptionInterface
{
    public function __construct(private readonly RequestInterface $request, string $message)
    {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}

final class Psr18PageFetcherTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function test_implements_page_fetcher_interface(): void
    {
        $fetcher = new Psr18PageFetcher(new InMemoryPsr18Client(), $this->factory, $this->factory);

        self::assertInstanceOf(PageFetcher::class, $fetcher);
    }

    public function test_successful_html_response_populates_snapshot(): void
    {
        $url = 'https://merchant.test/products/widget';
        $client = new InMemoryPsr18Client();
        $client->on($url, $this->htmlResponse(200, '<html><body>Widget</body></html>'));

        $snapshot = $this->fetcher($client)->fetch($url);

        self::assertSame($url, $snapshot->requestedUrl);
        self::assertSame($url, $snapshot->finalUrl);
        self::assertSame(200, $snapshot->statusCode);
        self::assertSame('text/html; charset=UTF-8', $snapshot->contentType);
        self::assertSame('<html><body>Widget</body></html>', $snapshot->body);
        self::assertSame('none', $snapshot->failureType);
        self::assertSame([], $snapshot->redirects);
        self::assertSame([], $snapshot->warnings);
        self::assertIsInt($snapshot->durationMs);
        self::assertGreaterThanOrEqual(0, $snapshot->durationMs);
        self::assertArrayHasKey('content-type', $snapshot->headers);
        self::assertSame(['text/html; charset=UTF-8'], $snapshot->headers['content-type']);
    }

    public function test_non_2xx_response_keeps_status_and_no_transport_failure(): void
    {
        $url = 'https://merchant.test/products/missing';
        $client = new InMemoryPsr18Client();
        $client->on($url, $this->htmlResponse(404, '<html><body>Not found</body></html>'));

        $snapshot = $this->fetcher($client)->fetch($url);

        self::assertSame(404, $snapshot->statusCode);
        self::assertSame('none', $snapshot->failureType);
        self::assertSame('<html><body>Not found</body></html>', $snapshot->body);
    }

    public function test_redirect_chain_is_captured_and_final_url_is_last(): void
    {
        $first = 'https://merchant.test/old';
        $second = 'https://merchant.test/mid';
        $third = 'https://merchant.test/products/widget';

        $client = new InMemoryPsr18Client();
        $client->on($first, $this->redirectResponse(301, $second));
        $client->on($second, $this->redirectResponse(302, $third));
        $client->on($third, $this->htmlResponse(200, '<html><body>Widget</body></html>'));

        $snapshot = $this->fetcher($client)->fetch($first);

        self::assertSame($first, $snapshot->requestedUrl);
        self::assertSame($third, $snapshot->finalUrl);
        self::assertSame(200, $snapshot->statusCode);
        self::assertSame([
            ['from' => $first, 'to' => $second, 'statusCode' => 301],
            ['from' => $second, 'to' => $third, 'statusCode' => 302],
        ], $snapshot->redirects);
    }

    public function test_relative_redirect_location_is_resolved_against_current_url(): void
    {
        $first = 'https://merchant.test/catalog/old-widget';
        $resolved = 'https://merchant.test/products/widget';

        $client = new InMemoryPsr18Client();
        $client->on($first, $this->redirectResponse(301, '/products/widget'));
        $client->on($resolved, $this->htmlResponse(200, '<html></html>'));

        $snapshot = $this->fetcher($client)->fetch($first);

        self::assertSame($resolved, $snapshot->finalUrl);
        self::assertSame([
            ['from' => $first, 'to' => $resolved, 'statusCode' => 301],
        ], $snapshot->redirects);
    }

    public function test_redirect_chain_is_bounded(): void
    {
        $client = new InMemoryPsr18Client();
        // A self-referential redirect loop would be infinite if unbounded.
        for ($i = 0; $i <= 10; ++$i) {
            $from = sprintf('https://merchant.test/hop-%d', $i);
            $to = sprintf('https://merchant.test/hop-%d', $i + 1);
            $client->on($from, $this->redirectResponse(302, $to));
        }

        $snapshot = $this->fetcher($client)->fetch('https://merchant.test/hop-0');

        // Default bound is 5 redirects followed, then it stops at the next hop.
        self::assertCount(5, $snapshot->redirects);
        self::assertNotSame([], $snapshot->warnings);
        self::assertStringContainsString('Maximum redirect count', $snapshot->warnings[0]);
    }

    public function test_timeout_network_failure_maps_to_timeout(): void
    {
        $url = 'https://merchant.test/slow';
        $client = new InMemoryPsr18Client();
        $client->failWith($url, 'network', 'cURL error 28: Operation timed out after 30000 ms');

        $snapshot = $this->fetcher($client)->fetch($url);

        self::assertSame('timeout', $snapshot->failureType);
        self::assertNull($snapshot->statusCode);
        self::assertNull($snapshot->finalUrl);
        self::assertNull($snapshot->body);
        self::assertNotSame([], $snapshot->warnings);
    }

    public function test_dns_network_failure_maps_to_dns_not_found(): void
    {
        $url = 'https://does-not-exist.test/';
        $client = new InMemoryPsr18Client();
        $client->failWith($url, 'network', 'cURL error 6: Could not resolve host: does-not-exist.test');

        $snapshot = $this->fetcher($client)->fetch($url);

        self::assertSame('dns_not_found', $snapshot->failureType);
        self::assertNull($snapshot->statusCode);
    }

    public function test_connection_refused_maps_to_connection_refused(): void
    {
        $url = 'https://merchant.test/';
        $client = new InMemoryPsr18Client();
        $client->failWith($url, 'network', 'cURL error 7: Failed to connect: Connection refused');

        $snapshot = $this->fetcher($client)->fetch($url);

        self::assertSame('connection_refused', $snapshot->failureType);
    }

    public function test_ssl_failure_maps_to_ssl_error(): void
    {
        $url = 'https://merchant.test/';
        $client = new InMemoryPsr18Client();
        $client->failWith($url, 'network', 'cURL error 60: SSL certificate problem: unable to get local issuer certificate');

        $snapshot = $this->fetcher($client)->fetch($url);

        self::assertSame('ssl_error', $snapshot->failureType);
    }

    public function test_request_exception_maps_to_invalid_response(): void
    {
        $url = 'https://merchant.test/';
        $client = new InMemoryPsr18Client();
        $client->failWith($url, 'request', 'The request could not be built.');

        $snapshot = $this->fetcher($client)->fetch($url);

        self::assertSame('invalid_response', $snapshot->failureType);
    }

    public function test_unknown_client_exception_maps_to_unknown(): void
    {
        $url = 'https://merchant.test/';
        $client = new InMemoryPsr18Client();
        $client->failWith($url, 'client', 'Something unexpected happened.');

        $snapshot = $this->fetcher($client)->fetch($url);

        self::assertSame('unknown', $snapshot->failureType);
    }

    public function test_non_html_content_type_is_captured(): void
    {
        $url = 'https://merchant.test/products/widget.json';
        $client = new InMemoryPsr18Client();
        $response = $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"name":"Widget"}'));
        $client->on($url, $response);

        $snapshot = $this->fetcher($client)->fetch($url);

        self::assertSame(200, $snapshot->statusCode);
        self::assertSame('application/json', $snapshot->contentType);
        self::assertSame('{"name":"Widget"}', $snapshot->body);
        self::assertSame('none', $snapshot->failureType);
    }

    public function test_empty_url_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->fetcher(new InMemoryPsr18Client())->fetch('   ');
    }

    private function fetcher(InMemoryPsr18Client $client): Psr18PageFetcher
    {
        return new Psr18PageFetcher($client, $this->factory, $this->factory);
    }

    private function htmlResponse(int $status, string $body): ResponseInterface
    {
        return $this->factory->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody($this->factory->createStream($body));
    }

    private function redirectResponse(int $status, string $location): ResponseInterface
    {
        return $this->factory->createResponse($status)
            ->withHeader('Location', $location);
    }
}
