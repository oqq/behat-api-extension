<?php
namespace Imbo\BehatApiExtension\Context;

use PHPUnit_Framework_TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use GuzzleHttp\Exception\RequestException;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use RuntimeException;
use stdClass;

/**
 * Namespaced version of file_exists that returns true for a fixed filename. All other paths are
 * checked by the global file_exists function.
 *
 * @param string $path
 * @return boolean
 */
function file_exists($path) {
    if ($path === '/non/readable/file') {
        return true;
    }

    return \file_exists($path);
}

/**
 * Namespaced version of is_readable that returns false for a fixed filename. All other paths are
 * checked by the global is_readable function.
 *
 * @param string $path
 * @return boolean
 */
function is_readable($path) {
    if ($path === '/none/readable/file') {
        return false;
    }

    return \is_readable($path);
}

/**
 * @coversDefaultClass Imbo\BehatApiExtension\Context\ApiContext
 */
class ApiContextText extends PHPUnit_Framework_TestCase {
    private $mockHandler;
    private $handlerStack;
    private $historyContainer = [];
    private $context;
    private $client;
    private $baseUri = 'http://localhost:9876';

    public function setUp() {
        $this->historyContainer = [];

        $this->mockHandler = new MockHandler();
        $this->handlerStack = HandlerStack::create($this->mockHandler);
        $this->handlerStack->push(Middleware::history($this->historyContainer));
        $this->client = new Client([
            'handler' => $this->handlerStack,
            'base_uri' => $this->baseUri,
        ]);

        $this->context = new ApiContext();
        $this->context->setClient($this->client);
    }

    /**
     * Data provider: Get HTTP methods
     *
     * @return array[]
     */
    public function getHttpMethods() {
        return [
            ['method' => 'GET'],
            ['method' => 'PUT'],
            ['method' => 'POST'],
            ['method' => 'HEAD'],
            ['method' => 'OPTIONS'],
            ['method' => 'DELETE'],
        ];
    }

    /**
     * Data provider: Get files and mime types
     *
     * @return array[]
     */
    public function getFilesAndMimeTypes() {
        return [
            [
                'filePath'         => __FILE__,
                'method'           => 'POST',
                'expectedMimeType' => 'text/x-php',
            ],
            [
                'filePath'         => __FILE__,
                'method'           => 'POST',
                'expectedMimeType' => 'foo/bar',
                'mimeType'         => 'foo/bar',
            ],
        ];
    }

    /**
     * Data provider: Get a a response code, along with an array of other response codes that the
     *                first parameter does not match
     *
     * @return array[]
     */
    public function getResponseCodes() {
        return [
            [
                'code' => 200,
                'others' => [300, 400, 500],
            ],
            [
                'code' => 300,
                'others' => [200, 400, 500],
            ],
            [
                'code' => 400,
                'others' => [200, 300, 500],
            ],
            [
                'code' => 500,
                'others' => [200, 300, 400],
            ],
        ];
    }

    /**
     * Data provider: Get response codes and groups
     *
     * @return array[]
     */
    public function getGroupAndResponseCodes() {
        return [
            [
                'group' => 'informational',
                'codes' => [100, 199],
            ],
            [
                'group' => 'success',
                'codes' => [200, 299],
            ],
            [
                'group' => 'redirection',
                'codes' => [300, 399],
            ],
            [
                'group' => 'client error',
                'codes' => [400, 499],
            ],
            [
                'group' => 'server error',
                'codes' => [500, 599],
            ],
        ];
    }

    /**
     * Data provider: Return invalid HTTP response codes
     *
     * @return array[]
     */
    public function getInvalidHttpResponseCodes() {
        return [
            [99],
            [600],
        ];
    }

    /**
     * Data provider: Get response body arrays, the length to use in the check, and whether or not
     *                the test should fail
     *
     * @return array[]
     */
    public function getResponseBodyArrays() {
        return [
            [
                'body' => [1, 2, 3],
                'lengthToUse' => 3,
                'willFail' => false,
            ],
            [
                'body' => [1, 2, 3],
                'lengthToUse' => 2,
                'willFail' => true,
            ],
            [
                'body' => [],
                'lengthToUse' => 0,
                'willFail' => false,
            ],
            [
                'body' => [],
                'lengthToUse' => 1,
                'willFail' => true,
            ],
        ];
    }

    /**
     * Data provider: Get response body arrays that will be used for testing that the length is at
     *                least a given length
     *
     * @return array[]
     */
    public function getResponseBodyArraysForAtLeast() {
        return [
            [
                'body' => [1, 2, 3],
                'lengthToUse' => 3,
                'willFail' => false,
            ],
            [
                'body' => [1, 2, 3],
                'lengthToUse' => 4,
                'willFail' => true,
            ],
            [
                'body' => [],
                'lengthToUse' => 2,
                'willFail' => true,
            ],
        ];
    }

    /**
     * Data provider: Get response body arrays that will be used for testing that the length is at
     *                most a given length
     *
     * @return array[]
     */
    public function getResponseBodyArraysForAtMost() {
        return [
            [
                'body' => [1, 2, 3],
                'lengthToUse' => 3,
                'willFail' => false,
            ],
            [
                'body' => [1, 2, 3],
                'lengthToUse' => 4,
                'willFail' => false,
            ],
            [
                'body' => [],
                'lengthToUse' => 4,
                'willFail' => false,
            ],
            [
                'body' => [1, 2, 3, 4],
                'lengthToUse' => 3,
                'willFail' => true,
            ],
        ];
    }

    /**
     * Data provider
     *
     * @return []
     */
    public function getResponseCodesAndReasonPhrases() {
        return [
            200 => [
                'code' => 200,
                'phrase' => 'OK',
            ],
            300 => [
                'code' => 300,
                'phrase' => 'Multiple Choices',
            ],
            400 => [
                'code' => 400,
                'phrase' => 'Bad Request',
            ],
            500 => [
                'code' => 500,
                'phrase' => 'Internal Server Error',
            ],
        ];
    }

    /**
     * Data provider
     *
     * @return array[]
     */
    public function getUris() {
        return [
            // The first six sets are from http://docs.guzzlephp.org/en/latest/quickstart.html (2016-12-12)
            [
                'baseUri' => 'http://foo.com',
                'path' => '/bar',
                'fullUri' => 'http://foo.com/bar',
            ],
            [
                'baseUri' => 'http://foo.com/foo',
                'path' => '/bar',
                'fullUri' => 'http://foo.com/bar',
            ],
            [
                'baseUri' => 'http://foo.com/foo',
                'path' => 'bar',
                'fullUri' => 'http://foo.com/bar',
            ],
            [
                'baseUri' => 'http://foo.com/foo/',
                'path' => 'bar',
                'fullUri' => 'http://foo.com/foo/bar',
            ],
            [
                'baseUri' => 'http://foo.com',
                'path' => 'http://baz.com',
                'fullUri' => 'http://baz.com',
            ],
            [
                'baseUri' => 'http://foo.com/?bar',
                'path' => 'bar',
                'fullUri' => 'http://foo.com/bar',
            ],

            [
                'baseUri' => 'http://foo.com',
                'path' => '/bar?foo=bar',
                'fullUri' => 'http://foo.com/bar?foo=bar',
            ],

            // https://github.com/imbo/behat-api-extension/issues/20
            [
                'baseUri' => 'http://localhost:8080/app_dev.php',
                'path' => '/api/authenticate',
                'fullUri' => 'http://localhost:8080/api/authenticate',
            ],
            [
                'baseUri' => 'http://localhost:8080/app_dev.php/',
                'path' => 'api/authenticate',
                'fullUri' => 'http://localhost:8080/app_dev.php/api/authenticate',
            ],
            [
                'baseUri' => 'http://localhost:8080',
                'path' => '/app_dev.php/api/authenticate',
                'fullUri' => 'http://localhost:8080/app_dev.php/api/authenticate',
            ],
        ];
    }

    /**
     * Data provider
     *
     * @return array[]
     */
    public function getDataForResponseGroupFailures() {
        return [
            'informational' => [
                'responseCode' => 100,
                'actualGroup' => 'informational',
                'expectedGroup' => 'success',
            ],
            'success' => [
                'responseCode' => 200,
                'actualGroup' => 'success',
                'expectedGroup' => 'informational',
            ],
            'redirection' => [
                'responseCode' => 300,
                'actualGroup' => 'redirection',
                'expectedGroup' => 'success',
            ],
            'client error' => [
                'responseCode' => 400,
                'actualGroup' => 'client error',
                'expectedGroup' => 'success',
            ],
            'server error' => [
                'responseCode' => 500,
                'actualGroup' => 'server error',
                'expectedGroup' => 'success',
            ],
        ];
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage File does not exist: "/foo/bar"
     * @covers ::addMultipartFileToRequest
     */
    public function testGivenIAttachAFileToTheRequestThatDoesNotExist() {
        $this->context->addMultipartFileToRequest('/foo/bar', 'foo');
    }

    /**
     * @covers ::addMultipartFileToRequest
     */
    public function testCanAddMultipartFileToRequest() {
        $this->mockHandler->append(new Response(200));
        $files = [
            'file1' => __FILE__,
            'file2' => __DIR__ . '/../../README.md'
        ];

        foreach ($files as $name => $path) {
            $this->assertSame($this->context, $this->context->addMultipartFileToRequest($path, $name));
        }

        $this->context->requestPath('/some/path', 'POST');

        $this->assertSame(1, count($this->historyContainer));

        $request = $this->historyContainer[0]['request'];
        $boundary = $request->getBody()->getBoundary();

        $this->assertSame(sprintf('multipart/form-data; boundary=%s', $boundary), $request->getHeaderLine('Content-Type'));
        $contents = $request->getBody()->getContents();

        foreach ($files as $path) {
            $this->assertContains(
                file_get_contents($path),
                $contents
            );
        }
    }

    /**
     * @covers ::setBasicAuth
     */
    public function testGivenIAuthenticateAs() {
        $this->mockHandler->append(new Response(200));

        $username = 'user';
        $password = 'pass';

        $this->assertSame($this->context, $this->context->setBasicAuth($username, $password));
        $this->context->requestPath('/some/path', 'POST');

        $this->assertSame(1, count($this->historyContainer));

        $request = $this->historyContainer[0]['request'];
        $this->assertSame('Basic dXNlcjpwYXNz', $request->getHeaderLine('authorization'));
    }

    /**
     * @covers ::addRequestHeader
     */
    public function testAddRequestHeader() {
        $this->mockHandler->append(new Response(200));

        $this->assertSame($this->context, $this->context
            ->addRequestHeader('foo', 'foo')
            ->addRequestHeader('bar', 'foo')
            ->addRequestHeader('bar', 'bar')
        );
        $this->context->requestPath('/some/path', 'POST');
        $this->assertSame(1, count($this->historyContainer));

        $request = $this->historyContainer[0]['request'];
        $this->assertSame('foo', $request->getHeaderLine('foo'));
        $this->assertSame('foo, bar', $request->getHeaderLine('bar'));
    }

    /**
     * @covers ::setRequestHeader
     */
    public function testSetRequestHeader() {
        $this->mockHandler->append(new Response(200));

        $this->assertSame($this->context, $this->context
            ->setRequestHeader('foo', 'foo')
            ->setRequestHeader('bar', 'foo')
            ->setRequestHeader('bar', 'bar')
        );
        $this->context->requestPath('/some/path', 'POST');
        $this->assertSame(1, count($this->historyContainer));

        $request = $this->historyContainer[0]['request'];
        $this->assertSame('foo', $request->getHeaderLine('foo'));
        $this->assertSame('bar', $request->getHeaderLine('bar'));
    }

    /**
     * @dataProvider getHttpMethods
     * @covers ::requestPath
     * @covers ::setRequestPath
     * @covers ::setRequestMethod
     * @covers ::sendRequest
     *
     * @param string $method
     */
    public function testWhenIRequestPathUsesTheCorrectHTTPMethod($method) {
        $this->mockHandler->append(new Response(200));
        $this->assertSame($this->context, $this->context->requestPath('/some/path', $method));

        $this->assertSame(1, count($this->historyContainer));
        $this->assertSame($method, $this->historyContainer[0]['request']->getMethod());
    }

    /**
     * @covers ::requestPath
     * @covers ::setRequestMethod
     * @covers ::setRequestPath
     * @covers ::setRequestBody
     * @covers ::sendRequest
     */
    public function testWhenIRequestPathWithQueryParameters() {
        $this->mockHandler->append(new Response(200));
        $this->assertSame(
            $this->context,
            $this->context->requestPath('/some/path?foo=bar&bar=foo&a[]=1&a[]=2')
        );

        $this->assertSame(1, count($this->historyContainer));

        $request = $this->historyContainer[0]['request'];

        $this->assertSame('foo=bar&bar=foo&a%5B%5D=1&a%5B%5D=2', $request->getUri()->getQuery());
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseCodeIs
     * @covers ::requireResponse
     */
    public function testAssertResponseCodeIsWithMissingResponseInstance() {
        $this->context->assertResponseCodeIs(200);
    }

    /**
     * @dataProvider getResponseCodes
     * @covers ::assertResponseCodeIs
     * @covers ::validateResponseCode
     *
     * @param int $code
     */
    public function testAssertResponseCodeIs($code) {
        $this->mockHandler->append(new Response($code));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseCodeIs($code);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseCodeIsNot
     * @covers ::requireResponse
     */
    public function testAssertResponseCodeIsNotWithMissingResponseInstance() {
        $this->context->assertResponseCodeIsNot(200);
    }

    /**
     * @dataProvider getResponseCodes
     * @covers ::assertResponseCodeIsNot
     * @covers ::validateResponseCode
     *
     * @param int $code
     * @param int[] $otherCodes
     */
    public function testAssertResponseCodeIsNot($code, array $otherCodes) {
        $this->mockHandler->append(new Response($code));
        $this->context->requestPath('/some/path');

        foreach ($otherCodes as $otherCode) {
            $this->context->assertResponseCodeIsNot($otherCode);
        }

        $this->expectException('Imbo\BehatApiExtension\Exception\AssertionFailedException');
        $this->expectExceptionMessage('Did not expect response code ' . $code);

        $this->context->assertResponseCodeIsNot($code);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseIs
     * @covers ::requireResponse
     */
    public function testAssertResponseIsWhenThereIsNoResponseInstance() {
        $this->context->assertResponseIs('success');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseIsNot
     * @covers ::requireResponse
     */
    public function testAssertResponseIsNotWhenThereIsNoResponseInstance() {
        $this->context->assertResponseIsNot('success');
    }

    /**
     * @dataProvider getGroupAndResponseCodes
     * @covers ::assertResponseIs
     * @covers ::requireResponse
     * @covers ::getResponseCodeGroupRange
     *
     * @param string $group
     * @param int[] $codes
     */
    public function testAssertResponseIs($group, array $codes) {
        foreach ($codes as $code) {
            $this->mockHandler->append(new Response($code, [], 'response body'));
            $this->context->requestPath('/some/path');
            $this->context->assertResponseIs($group);
        }
    }

    /**
     * @dataProvider getGroupAndResponseCodes
     * @covers ::assertResponseIsNot
     * @covers ::assertResponseIs
     *
     * @param string $group
     * @param int[] $codes
     */
    public function testAssertResponseIsNot($group, array $codes) {
        $groups = [
            'informational',
            'success',
            'redirection',
            'client error',
            'server error',
        ];

        foreach ($codes as $code) {
            $this->mockHandler->append(new Response($code));
            $this->context->requestPath('/some/path');

            foreach (array_filter($groups, function($g) use ($group) { return $g !== $group; }) as $g) {
                // Assert that the response is not in any of the other groups
                $this->context->assertResponseIsNot($g);
            }
        }
    }

    /**
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Did not expect response to be in the "success" group (response code: 200).
     * @covers ::assertResponseIsNot
     * @covers ::assertResponseIs
     */
    public function testAssertResponseIsNotWhenResponseIsInGroup() {
        $this->mockHandler->append(new Response(200));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseIsNot('success');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid response code group: foobar
     * @covers ::assertResponseIsNot
     * @covers ::getResponseCodeGroupRange
     */
    public function testAssertResponseIsInInvalidGroup() {
        $this->mockHandler->append(new Response(200));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseIsNot('foobar');
    }

    /**
     * @dataProvider getDataForResponseGroupFailures
     * @covers ::assertResponseIs
     * @covers ::getResponseCodeGroupRange
     * @covers ::getResponseGroup
     *
     * @param int $responseCode
     * @param string $actualGroup
     * @param string $expectedGroup
     */
    public function testAssertResponseIsFailure($responseCode, $actualGroup, $expectedGroup) {
        $this->mockHandler->append(new Response($responseCode));
        $this->context->requestPath('/some/path');

        $this->expectException('Imbo\BehatApiExtension\Exception\AssertionFailedException');
        $this->expectExceptionMessage(sprintf(
            'Expected response group "%s", got "%s" (response code: %d).',
            $expectedGroup,
            $actualGroup,
            $responseCode
        ));
        $this->context->assertResponseIs($expectedGroup);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseBodyIs
     * @covers ::requireResponse
     */
    public function testAssertResponseBodyIsWhenNoResponseExists() {
        $this->context->assertResponseBodyIs(new PyStringNode(['some body'], 1));
    }

    /**
     * @covers ::assertResponseBodyIs
     */
    public function testAssertResponseBodyIsWithMatchingBody() {
        $this->mockHandler->append(new Response(200, [], 'response body'));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyIs(new PyStringNode(['response body'], 1));
    }

    /**
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Expected response body "foo", got "response body".
     * @covers ::assertResponseBodyIs
     */
    public function testAssertResponseBodyIsWithNonMatchingBody() {
        $this->mockHandler->append(new Response(200, [], 'response body'));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyIs(new PyStringNode(['foo'], 1));
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseBodyMatches
     * @covers ::requireResponse
     */
    public function testAssertResponseBodyMatchesWhenNoResponseExists() {
        $this->context->assertResponseBodyMatches(new PyStringNode(['/foo/'], 1));
    }

    /**
     * @covers ::assertResponseBodyMatches
     */
    public function testAssertResponseBodyMatches() {
        $this->mockHandler->append(new Response(200, [], '{"foo":"bar"}'));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyMatches(new PyStringNode(['/^{"FOO": ?"BAR"}$/i'], 1));
    }

    /**
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Expected response body to match regular expression "/^{"FOO": "BAR"}$/", got "{"foo":"bar"}".
     * @covers ::assertResponseBodyMatches
     */
    public function testAssertResponseBodyMatchesWithAPatternThatDoesNotMatch() {
        $this->mockHandler->append(new Response(200, [], '{"foo":"bar"}'));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyMatches(new PyStringNode(['/^{"FOO": "BAR"}$/'], 1));
    }

    /**
     * @dataProvider getInvalidHttpResponseCodes
     * @covers ::validateResponseCode
     *
     * @param int $code
     */
    public function testAssertResponseCodeIsUsingAnInvalidCode($code) {
        $this->mockHandler->append(new Response(200));
        $this->context->requestPath('/some/path');

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(sprintf('Response code must be between 100 and 599, got %d.', $code));

        $this->context->assertResponseCodeIs($code);
    }

    /**
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Expected response code 400, got 200
     * @covers ::assertResponseCodeIs
     */
    public function testAssertResponseCodeIsFailure() {
        $this->mockHandler->append(new Response(200));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseCodeIs(400);
    }

    /**
     * @expectedException GuzzleHttp\Exception\RequestException
     * @expectedExceptionMessage error
     * @covers ::sendRequest
     */
    public function testSendRequestWhenThereIsAnErrorCommunicatingWithTheServer() {
        $this->mockHandler->append(new RequestException('error', new Request('GET', 'path')));
        $this->context->requestPath('path');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseHeaderExists
     * @covers ::requireResponse
     */
    public function testAssertResponseHeaderExistsWhenNoResponseExists() {
        $this->context->assertResponseHeaderExists('Connection');
    }

    /**
     * @covers ::assertResponseHeaderExists
     */
    public function testAssertResponseHeaderExists() {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json']));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseHeaderExists('Content-Type');
    }

    /**
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage The "Content-Type" response header does not exist
     * @covers ::assertResponseHeaderExists
     */
    public function testAssertResponseHeaderExistsWhenHeaderDoesNotExist() {
        $this->mockHandler->append(new Response(200));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseHeaderExists('Content-Type');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseHeaderDoesNotExist
     * @covers ::requireResponse
     */
    public function testAssertResponseHeaderDoesNotExistWhenNoResponseExists() {
        $this->context->assertResponseHeaderDoesNotExist('Connection');
    }

    /**
     * @covers ::assertResponseHeaderDoesNotExist
     */
    public function testAssertResponseHeaderDoesNotExist() {
        $this->mockHandler->append(new Response(200));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseHeaderDoesNotExist('Content-Type');
    }

    /**
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage The "Content-Type" response header should not exist
     * @covers ::assertResponseHeaderDoesNotExist
     */
    public function testAssertResponseHeaderDoesNotExistWhenHeaderExists() {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json']));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseHeaderDoesNotExist('Content-Type');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseHeaderIs
     * @covers ::requireResponse
     */
    public function testAssertResponseHeaderIsWhenNoResponseExists() {
        $this->context->assertResponseHeaderIs('Connection', 'close');
    }

    /*
     * @covers ::assertResponseHeaderIs
     */
    public function testAssertResponseHeaderIs() {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json']));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseHeaderIs('Content-Type', 'application/json');
    }

    /**
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Expected the "Content-Type" response header to be "application/xml", got "application/json".
     * @covers ::assertResponseHeaderIs
     */
    public function testAssertResponseHeaderIsWhenValueDoesNotMatch() {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json']));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseHeaderIs('Content-Type', 'application/xml');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseHeaderMatches
     * @covers ::requireResponse
     */
    public function testAssertResponseHeaderMatchesWhenNoResponseExists() {
        $this->context->assertResponseHeaderMatches('Connection', 'close');
    }

    /**
     * @covers ::assertResponseHeaderMatches
     */
    public function testAssertResponseHeaderMatches() {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json']));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseHeaderMatches('Content-Type', '#^application/(json|xml)$#');
    }

    /**
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Expected the "Content-Type" response header to match the regular expression "#^application/xml$#", got "application/json".
     * @covers ::assertResponseHeaderMatches
     */
    public function testAssertResponseHeaderMatchesWhenValueDoesNotMatch() {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json']));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseHeaderMatches('Content-Type', '#^application/xml$#');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseBodyJsonArrayLength
     * @covers ::requireResponse
     */
    public function testAssertResponseBodyJsonArrayLengthWhenNoRequestHasBeenMade() {
        $this->context->assertResponseBodyJsonArrayLength(5);
    }

    /**
     * @dataProvider getResponseBodyArrays
     * @covers ::assertResponseBodyJsonArrayLength
     * @covers ::getResponseBodyArray
     *
     * @param array $body
     * @param int $lenthToUse
     * @param boolean $willFail
     */
    public function testAssertResponseBodyJsonArrayLength(array $body, $lengthToUse, $willFail) {
        $this->mockHandler->append(new Response(200, [], json_encode($body)));
        $this->context->requestPath('/some/path');

        if ($willFail) {
            $this->expectException('Imbo\BehatApiExtension\Exception\AssertionFailedException');
            $this->expectExceptionMessage(sprintf(
                'Expected response body to be a JSON array with %d entr%s, got %d: "[',
                $lengthToUse,
                (int) $lengthToUse === 1 ? 'y' : 'ies',
                count($body)
            ));
        }

        $this->context->assertResponseBodyJsonArrayLength($lengthToUse);
    }

    /**
     * @covers ::assertResponseBodyIsAnEmptyJsonArray
     * @covers ::getResponseBodyArray
     * @covers ::getResponseBody
     */
    public function testAssertResponseBodyIsAnEmptyJsonArray() {
        $this->mockHandler->append(new Response(200, [], json_encode([])));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyIsAnEmptyJsonArray();
    }

    /**
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Expected response body to be an empty JSON array, got "[
     * @covers ::assertResponseBodyIsAnEmptyJsonArray
     * @covers ::getResponseBodyArray
     * @covers ::getResponseBody
     */
    public function testAssertResponseBodyIsAnEmptyJsonArrayWhenItsNot() {
        $this->mockHandler->append(new Response(200, [], json_encode([1, 2, 3])));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyIsAnEmptyJsonArray();
    }

    /**
     * @covers ::assertResponseBodyIsAnEmptyJsonObject
     */
    public function testAssertResponseBodyIsAnEmptyJsonObject() {
        $this->mockHandler->append(new Response(200, [], json_encode(new stdClass())));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyIsAnEmptyJsonObject();
    }

    /**
     * @covers ::assertResponseBodyIsAnEmptyJsonObject
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Expected response body to be a JSON object.
     */
    public function testAssertResponseBodyIsAnEmptyJsonObjectWhenTheResponseBodyIsNotAnObject() {
        $this->mockHandler->append(new Response(200, [], json_encode([])));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyIsAnEmptyJsonObject();
    }

    /**
     * @covers ::assertResponseBodyIsAnEmptyJsonObject
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Expected response body to be an empty JSON object, got "{
     */
    public function testAssertResponseBodyIsAnEmptyJsonObjectWhenItIsNot() {
        $object = new stdClass();
        $object->foo = 'bar';
        $this->mockHandler->append(new Response(200, [], json_encode($object)));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyIsAnEmptyJsonObject();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The response body does not contain a valid JSON array.
     * @covers ::assertResponseBodyJsonArrayLength
     * @covers ::getResponseBodyArray
     */
    public function testAssertResponseBodyJsonArrayLengthWithAnInvalidBody() {
        $this->mockHandler->append(new Response(200, [], json_encode(['foo' => 'bar'])));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyJsonArrayLength(0);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseBodyJsonArrayMinLength
     * @covers ::requireResponse
     */
    public function testAssertResponseBodyJsonArrayMinLengthWhenNoRequestHaveBeenMade() {
        $this->context->assertResponseBodyJsonArrayMinLength(5);
    }

    /**
     * @dataProvider getResponseBodyArraysForAtLeast
     * @covers ::assertResponseBodyJsonArrayMinLength
     * @covers ::getResponseBody
     *
     * @param array $body
     * @param int $lengthToUse
     * @param boolean $willFail
     */
    public function testAssertResponseBodyJsonArrayMinLength(array $body, $lengthToUse, $willFail) {
        $this->mockHandler->append(new Response(200, [], json_encode($body)));
        $this->context->requestPath('/some/path');

        if ($willFail) {
            $this->expectException('Imbo\BehatApiExtension\Exception\AssertionFailedException');
            $this->expectExceptionMessage(sprintf(
                'Expected response body to be a JSON array with at least %d entr%s, got %d: "[',
                $lengthToUse,
                (int) $lengthToUse === 1 ? 'y' : 'ies',
                count($body)
            ));
        }

        $this->context->assertResponseBodyJsonArrayMinLength($lengthToUse);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The response body does not contain a valid JSON array.
     * @covers ::assertResponseBodyJsonArrayMinLength
     * @covers ::getResponseBody
     */
    public function testAssertResponseBodyJsonArrayMinLengthWithAnInvalidBody() {
        $this->mockHandler->append(new Response(200, [], json_encode(['foo' => 'bar'])));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyJsonArrayMinLength(2);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseBodyJsonArrayMaxLength
     * @covers ::requireResponse
     */
    public function testAssertResponseBodyJsonArrayMaxLengthWhenNoRequestHaveBeenMade() {
        $this->context->assertResponseBodyJsonArrayMaxLength(5);
    }

    /**
     * @dataProvider getResponseBodyArraysForAtMost
     * @covers ::assertResponseBodyJsonArrayMaxLength
     * @covers ::getResponseBody
     *
     * @param array $body
     * @param int $lengthToUse
     * @param boolean $willFail
     */
    public function testAssertResponseBodyJsonArrayMaxLength(array $body, $lengthToUse, $willFail) {
        $this->mockHandler->append(new Response(200, [], json_encode($body)));
        $this->context->requestPath('/some/path');

        if ($willFail) {
            $this->expectException('Imbo\BehatApiExtension\Exception\AssertionFailedException');
            $this->expectExceptionMessage(sprintf(
                'Expected response body to be a JSON array with at most %d entr%s, got %d: "[',
                $lengthToUse,
                (int) $lengthToUse === 1 ? 'y' : 'ies',
                count($body)
            ));
        }

        $this->context->assertResponseBodyJsonArrayMaxLength($lengthToUse);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The response body does not contain a valid JSON array.
     * @covers ::assertResponseBodyJsonArrayMaxLength
     * @covers ::getResponseBody
     */
    public function testAssertResponseBodyJsonArrayMaxLengthWithAnInvalidBody() {
        $this->mockHandler->append(new Response(200, [], json_encode(['foo' => 'bar'])));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyJsonArrayMaxLength(2);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseBodyContainsJson
     * @covers ::requireResponse
     */
    public function testAssertResponseBodyContainsJsonWhenNoRequestHaveBeenMade() {
        $this->context->assertResponseBodyContainsJson(new PyStringNode(['{"foo":"bar"}'], 1));
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The response body does not contain valid JSON data.
     * @covers ::assertResponseBodyContainsJson
     * @covers ::getResponseBody
     */
    public function testAssertResponseBodyContainsJsonWithInvalidJsonInBody() {
        $this->mockHandler->append(new Response(200, [], "{'foo':'bar'}"));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyContainsJson(new PyStringNode(['{"foo":"bar"}'], 1));
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The supplied parameter is not a valid JSON object.
     * @covers ::assertResponseBodyContainsJson
     */
    public function testAssertResponseBodyContainsJsonWithInvalidJsonParameter() {
        $this->mockHandler->append(new Response(200, [], '{"foo":"bar"}'));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyContainsJson(new PyStringNode(["{'foo':'bar'}"], 1));
    }

    /**
     * @covers ::assertResponseBodyContainsJson
     * @covers ::getResponseBody
     */
    public function testAssertResponseBodyContainsJson() {
        $this->mockHandler->append(new Response(200, [], '{"foo":"bar","bar":"foo"}'));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyContainsJson(new PyStringNode(['{"bar":"foo","foo":"bar"}'], 1));
    }

    /**
     * @expectedException OutOfRangeException
     * @expectedExceptionMessage Key is missing from the haystack: bar
     * @covers ::assertResponseBodyContainsJson
     * @covers ::getResponseBody
     */
    public function testAssertResponseBodyContainsJsonOnFailure() {
        $this->mockHandler->append(new Response(200, [], '{"foo":"bar"}'));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyContainsJson(new PyStringNode(['{"bar":"foo"}'], 1));
    }

    /**
     * @see https://github.com/imbo/behat-api-extension/issues/7
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage It's not allowed to set a request body when using multipart/form-data or form parameters.
     * @covers ::setRequestBody
     */
    public function testDontAllowRequestBodyWithMultipartFormDataRequests() {
        $this->mockHandler->append(new Response(200));
        $this->context->addMultipartFileToRequest(__FILE__, 'file');
        $this->context->setRequestBody('some body');
    }

    /**
     * @covers ::setRequestFormParams
     * @covers ::sendRequest
     */
    public function testCanSetRequestFormParams() {
        $this->mockHandler->append(new Response(200));
        $this->assertSame($this->context, $this->context->setRequestFormParams(new TableNode([
            ['name', 'value'],
            ['foo', 'bar'],
            ['bar', 'foo'],
            ['bar', 'bar'],
        ])));
        $this->context->requestPath('/some/path');

        $this->assertSame(1, count($this->historyContainer));

        $request = $this->historyContainer[0]['request'];

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        $this->assertSame(37, (int) $request->getHeaderLine('Content-Length'));
        $this->assertSame('foo=bar&bar%5B0%5D=foo&bar%5B1%5D=bar', (string) $request->getBody());
    }

    /**
     * @covers ::sendRequest
     */
    public function testCanSetFormParamsAndAttachAFileInTheSameRequest() {
        $this->mockHandler->append(new Response(200));
        $this->context->setRequestFormParams(new TableNode([
            ['name', 'value'],
            ['foo', 'bar'],
            ['bar', 'foo'],
            ['bar', 'bar'],
        ]));
        $this->context->addMultipartFileToRequest(__FILE__, 'file');
        $this->context->requestPath('/some/path');

        $this->assertSame(1, count($this->historyContainer));

        $request = $this->historyContainer[0]['request'];
        $boundary = $request->getBody()->getBoundary();

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(sprintf('multipart/form-data; boundary=%s', $boundary), $request->getHeaderLine('Content-Type'));

        $contents = $request->getBody()->getContents();

        $this->assertContains('Content-Disposition: form-data; name="file"; filename="ApiContextTest.php"', $contents);
        $this->assertContains(file_get_contents(__FILE__), $contents);

        $foo = <<<FOO
Content-Disposition: form-data; name="foo"
Content-Length: 3

bar
FOO;

        $bar0 = <<<BAR
Content-Disposition: form-data; name="bar[]"
Content-Length: 3

foo
BAR;
        $bar1 = <<<BAR
Content-Disposition: form-data; name="bar[]"
Content-Length: 3

bar
BAR;
        $this->assertContains($foo, $contents);
        $this->assertContains($bar0, $contents);
        $this->assertContains($bar1, $contents);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseReasonPhraseIs
     * @covers ::requireResponse
     */
    public function testCanAssertResponseReasonPhraseIsWhenNoResponseExist() {
        $this->context->assertResponseReasonPhraseIs('OK');
    }

    /**
     * @dataProvider getResponseCodesAndReasonPhrases
     * @covers ::assertResponseReasonPhraseIs
     *
     * @param int $code The HTTP response code
     * @param string $phrase The HTTP response reason phrase
     */
    public function testCanAssertResponseReasonPhraseIs($code, $phrase) {
        $this->mockHandler->append(new Response($code, [], null, 1.1, $phrase));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseReasonPhraseIs($phrase);
    }

    /**
     * @covers ::assertResponseReasonPhraseIs
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Expected response reason phrase "ok", got "OK".
     */
    public function testAssertResponseReasonPhraseIsCanFail() {
        $this->mockHandler->append(new Response());
        $this->context->requestPath('/some/path');
        $this->context->assertResponseReasonPhraseIs('ok');
    }

    /**
     * @covers ::assertResponseReasonPhraseIsNot
     */
    public function testCanAssertResponseReasonPhraseIsNot() {
        $this->mockHandler->append(new Response());
        $this->context->requestPath('/some/path');
        $this->context->assertResponseReasonPhraseIsNot('Not Modified');
    }

    /**
     * @covers ::assertResponseReasonPhraseIsNot
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Did not expect response reason phrase "OK".
     */
    public function testAssertResponseReasonPhraseIsNotFailure() {
        $this->mockHandler->append(new Response());
        $this->context->requestPath('/some/path');
        $this->context->assertResponseReasonPhraseIsNot('OK');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseReasonPhraseIsNot
     * @covers ::requireResponse
     */
    public function testCanAssertResponseReasonPhraseIsNotWhenNoResponseExist() {
        $this->context->assertResponseReasonPhraseIsNot('OK');
    }

    /**
     * @dataProvider getResponseCodesAndReasonPhrases
     * @covers ::assertResponseStatusLineIs
     *
     * @param int $code The HTTP response code
     * @param string $phrase The HTTP response reason phrase
     */
    public function testCanAssertResponseStatusLine($code, $phrase) {
        $this->mockHandler->append(new Response($code, [], null, 1.1, $phrase));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseStatusLineIs(sprintf('%d %s', $code, $phrase));
    }

    /**
     * @covers ::assertResponseStatusLineIs
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Expected response status line "200 Foobar", got "200 OK".
     */
    public function testAssertResponseStatusLineCanFail() {
        $this->mockHandler->append(new Response());
        $this->context->requestPath('/some/path');
        $this->context->assertResponseStatusLineIs('200 Foobar');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseStatusLineIs
     * @covers ::requireResponse
     */
    public function testCanAssertResponseStatusLineIsWhenNoResponseExist() {
        $this->context->assertResponseStatusLineIs('200 OK');
    }

    /**
     * @covers ::assertResponseStatusLineIsNot
     */
    public function testCanAssertResponseStatusLineIsNot() {
        $this->mockHandler->append(new Response());
        $this->context->requestPath('/some/path');
        $this->context->assertResponseStatusLineIsNot('304 Not Modified');
    }

    /**
     * @covers ::assertResponseStatusLineIsNot
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Did not expect response status line "200 OK".
     */
    public function testAssertResponseStatusLineIsNotFailure() {
        $this->mockHandler->append(new Response());
        $this->context->requestPath('/some/path');
        $this->context->assertResponseStatusLineIsNot('200 OK');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseStatusLineIsNot
     * @covers ::requireResponse
     */
    public function testCanAssertResponseStatusLineIsNotWhenNoResponseExist() {
        $this->context->assertResponseStatusLineIsNot('200 OK');
    }

    /**
     * Data provider
     *
     * @return array[]
     */
    public function getRequestBodyValues() {
        return [
            'regular string' => [
                'data' => 'some string',
                'expected' => 'some string',
            ],
            'PyStringNode' => [
                'data' => new PyStringNode(['some string'], 1),
                'expected' => 'some string',
            ],
        ];
    }

    /**
     * @dataProvider getRequestBodyValues
     * @covers ::setRequestBody
     *
     * @param string|PyStringNode $data
     * @param string $expected
     */
    public function testCanSetRequestBodyToAString($data, $expected) {
        $this->mockHandler->append(new Response());
        $this->context->setRequestBody($data);
        $this->context->requestPath('/some/path', 'POST');

        $this->assertSame(1, count($this->historyContainer));
        $request = $this->historyContainer[0]['request'];
        $this->assertSame($expected, (string) $request->getBody());
    }

    /**
     * @covers ::setRequestBodyToFileResource
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage File does not exist: "/foo/bar"
     */
    public function testFailsWhenSettingRequestBodyToAFileThatDoesNotExist() {
        $this->mockHandler->append(new Response());
        $this->context->setRequestBodyToFileResource('/foo/bar');
    }

    /**
     * @covers ::setRequestBodyToFileResource
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage File is not readable: "/non/readable/file"
     */
    public function testFailsWhenSettingRequestBodyToAFileThatIsNotReadable() {
        $this->mockHandler->append(new Response());
        $this->context->setRequestBodyToFileResource('/non/readable/file');
    }

    /**
     * @covers ::setRequestBodyToFileResource
     * @covers ::setRequestBody
     */
    public function testCanSetRequestBodyToAFile() {
        $this->mockHandler->append(new Response());
        $this->assertSame($this->context, $this->context->setRequestBodyToFileResource(__FILE__));
        $this->context->requestPath('/some/path', 'POST');

        $this->assertSame(1, count($this->historyContainer));
        $request = $this->historyContainer[0]['request'];
        $this->assertSame(file_get_contents(__FILE__), (string) $request->getBody());
        $this->assertSame('text/x-php', $request->getHeaderLine('Content-Type'));
    }

    /**
     * @dataProvider getUris
     * @covers ::setClient
     * @covers ::setRequestPath
     *
     * @param string $baseUri
     * @param string $path
     * @param string $fullUri
     */
    public function testResolvesPathsCorrectly($baseUri, $path, $fullUri) {
        // Set a new client with the given base_uri (and not the one used in setUp())
        $this->assertSame($this->context, $this->context->setClient(new Client([
            'handler' => $this->handlerStack,
            'base_uri' => $baseUri,
        ])));

        $this->mockHandler->append(new Response());
        $this->assertSame($this->context, $this->context->setRequestBodyToFileResource(__FILE__));
        $this->context->requestPath($path);

        $this->assertSame(1, count($this->historyContainer));
        $request = $this->historyContainer[0]['request'];
        $this->assertSame($fullUri, (string) $request->getUri());
    }

    /**
     * @covers ::getResponseBody
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The response body does not contain a valid JSON array / object.
     */
    public function testGetResponseBodyThrowsExceptionIfBodyIsNotJSONArrayOrObject() {
        $this->mockHandler->append(new Response(200, [], 123));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseBodyIsAnEmptyJsonObject();
    }

    /**
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Value "<NULL>" is not TRUE.
     * @covers ::assertResponseBodyContainsJson
     */
    public function testAssertResponseBodyContainsJsonWhenComparatorDoesNotReturnTrue() {
        $this->mockHandler->append(new Response(200, [], '{"foo":"bar","bar":"foo"}'));
        $this->context->requestPath('/some/path');
        $comparator = $this->createMock('Imbo\BehatApiExtension\ArrayContainsComparator');
        $comparator->expects($this->once())->method('compare')->will($this->returnValue(null));
        $this->context->assertResponseBodyContainsJson(new PyStringNode(['{"bar":"foo","foo":"bar"}'], 1), $comparator);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseReasonPhraseMatches
     * @covers ::requireResponse
     */
    public function testAssertResponseReasonPhraseMatchesWhenNoResponseExists() {
        $this->context->assertResponseReasonPhraseMatches('/ok/');
    }

    /**
     * @covers ::assertResponseReasonPhraseMatches
     */
    public function testAssertResponseReasonPhraseMatches() {
        $this->mockHandler->append(new Response(200));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseReasonPhraseMatches('/OK/');
    }

    /**
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Expected the response reason phrase to match the regular expression "/ok/", got "OK".
     * @covers ::assertResponseReasonPhraseMatches
     */
    public function testAssertResponseReasonPhraseMatchesWhenValueDoesNotMatch() {
        $this->mockHandler->append(new Response(200));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseReasonPhraseMatches('/ok/');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseStatusLineMatches
     * @covers ::requireResponse
     */
    public function testAssertResponseStatusLineMatchesWhenNoResponseExists() {
        $this->context->assertResponseStatusLineMatches('/200 OK/');
    }

    /**
     * @covers ::assertResponseStatusLineMatches
     */
    public function testAssertResponseStatusLineMatches() {
        $this->mockHandler->append(new Response(200));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseStatusLineMatches('/200 OK/');
    }

    /**
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Expected the response status line to match the regular expression "/200 ok/", got "200 OK".
     * @covers ::assertResponseStatusLineMatches
     */
    public function testAssertResponseStatusLineMatchesWhenValueDoesNotMatch() {
        $this->mockHandler->append(new Response(200));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseStatusLineMatches('/200 ok/');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage The request has not been made yet, so no response object exists.
     * @covers ::assertResponseHeaderIsNot
     * @covers ::requireResponse
     */
    public function testAssertResponseHeaderIsNotWithMissingResponseInstance() {
        $this->context->assertResponseHeaderIsNot('header', 'value');
    }

    /**
     * @covers ::assertResponseHeaderIsNot
     */
    public function testAssertResponseHeaderIsNot() {
        $this->mockHandler->append(new Response(200, ['Content-Length' => '123']));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseHeaderIsNot('Content-Type', '456');
    }

    /**
     * @expectedException Imbo\BehatApiExtension\Exception\AssertionFailedException
     * @expectedExceptionMessage Did not expect the "content-type" response header to be "123".
     * @covers ::assertResponseHeaderIsNot
     */
    public function testAssertResponseHeaderIsNotWithActualValue() {
        $this->mockHandler->append(new Response(200, ['Content-Type' => '123']));
        $this->context->requestPath('/some/path');
        $this->context->assertResponseHeaderIsNot('content-type', '123');
    }
}
