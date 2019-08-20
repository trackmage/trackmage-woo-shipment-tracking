<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

trait GuzzleMockTrait
{
    /**
     * @param Response[]|null $responses
     * @param Request[]|null $requests
     * @return Client
     */
    private function createClient(array $responses = null, array &$requests = null)
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        if ($requests !== null) {
            $handler->push(Middleware::history($requests));
        }
        $client = new Client([
            'handler' => $handler,
            'http_errors' => false,
        ]);
        return $client;
    }

    /**
     * @param array|array[] $requests
     * @param string[] $expectedList Array<Array<$expectedMethod, $expectedPath, $expectedQuery>>
     */
    private function assertMethodsWereCalled(array $requests, $expectedList)
    {
        if (!isset($requests[0])) {
            $requests = [$requests];
        }
        $actualList = array_map(function (Request $request) {
            parse_str($request->getUri()->getQuery(), $query);
            return [$request->getMethod(), $request->getUri()->getPath(), $query];
        }, array_column($requests, 'request'));
        self::assertEquals(count($expectedList), count($actualList));
        foreach ($expectedList as $key => $expectedItem) {
            list($expectedMethod, $expectedPath) = $expectedItem;
            $expectedQuery = isset($expectedItem[2]) ? $expectedItem[2] : null;
            self::assertEquals($expectedMethod, $actualList[$key][0]);
            self::assertRegExp('@' . $expectedPath . '@', $actualList[$key][1]);
            if (null !== $expectedQuery) {
                self::assertArraySubset($expectedQuery, $actualList[$key][2]);
            }
        }
    }

    private function splitRequest(Request $request)
    {
        $parts = $request->getUri();
        parse_str($parts->getQuery(), $queries);
        return array($parts->getPath(), $queries);
    }

    private function assertSubmittedForm(array $expected, Request $request)
    {
        parse_str($request->getBody()->getContents(), $actual);
        self::assertEquals($expected, $actual);
    }

    private function assertSubmittedJsonEquals(array $expected, Request $request)
    {
        $actual = json_decode($request->getBody()->getContents(), true);
        self::assertEquals($expected, $actual);
    }

    private function assertSubmittedJsonIncludes(array $expected, Request $request)
    {
        $actual = json_decode($request->getBody()->getContents(), true);
        self::assertArraySubset($expected, $actual);
    }

    private function createBadResponse($status = 400, $body = null)
    {
        $request = new Request('GET', '');
        $response = new Response($status, [], $body);
        if ($status >= 500) {
            return new ServerException('error', $request, $response);
        }
        return new ClientException('error', $request, $response);
    }

    private function createRequestException()
    {
        $request = new Request('GET', '');
        return new RequestException('error', $request);
    }

    private function createConnectionTimeoutResponse()
    {
        $request = new Request('GET', '');
        return new ConnectException('Connection timeout', $request);
    }

    private function createJsonResponse($status = 200, array $data = [])
    {
        return new Response($status, [], json_encode($data));
    }

    private function createEmptyResponse($status = 200)
    {
        return new Response($status);
    }

    private function assertRequestContentType(Request $request, $expected)
    {
        $content = $request->getHeaderLine('content-type');
        self::assertEquals($expected, $content);
    }
}
