<?php

declare(strict_types=1);

namespace Jaspersoft\Tool;

use CurlHandle;
use Exception;
use InvalidArgumentException;
use Jaspersoft\Exception\RESTRequestException;

class RESTRequest
{
    protected RESTRequest|string|null $url;
    protected ?string $verb;
    protected ?array $requestBody;
    protected int $requestLength;
    protected ?string $username;
    protected ?string $password;
    protected ?string $acceptType;
    protected string $contentType;
    protected bool|string|null $responseBody;
    protected mixed $responseInfo;
    protected ?array $headers;

    protected int $curlTimeout;
    private array $responseHeaders;

    public function __construct(RESTRequest|string|null $url = null, ?string $verb = 'GET', ?array $requestBody = null)
    {
        $this->url = $url;
        $this->verb = $verb;
        $this->requestBody = $requestBody;
        $this->requestLength = 0;
        $this->username = null;
        $this->password = null;
        $this->acceptType = null;
        $this->contentType = 'application/json';
        $this->responseBody = null;
        $this->responseInfo = null;
        $this->curlTimeout = 30;

        if (null !== $this->requestBody) {
            $this->buildPostBody();
        }
    }

    /** This function will convert an indexed array of headers into an associative array where the key matches
     * the key of the headers, and the value matches the value of the header.
     *
     * This is useful to access headers by name that may be returned in the response from makeRequest.
     *
     * @param $array array Indexed header array returned by makeRequest
     *
     * @return array
     */
    public array $errorCode;

    public static function splitHeaderArray($array): array
    {
        $result = [];
        foreach ($array as $value) {
            $pair = explode(':', $value, 2);
            if (count($pair) > 1) {
                $result[$pair[0]] = ltrim($pair[1]);
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    public function flush(): void
    {
        $this->requestBody = null;
        $this->requestLength = 0;
        $this->verb = 'GET';
        $this->responseBody = null;
        $this->responseInfo = null;
        $this->contentType = 'application/json';
        $this->acceptType = 'application/json';
        $this->headers = null;
    }

    public function execute(): void
    {
        $ch = curl_init();
        $this->setAuth($ch);
        $this->setTimeout($ch);
        try {
            switch (mb_strtoupper($this->verb)) {
                case 'GET':
                    $this->executeGet($ch);
                    break;
                case 'POST':
                    $this->executePost($ch);
                    break;
                case 'PUT':
                    $this->executePut($ch);
                    break;
                case 'DELETE':
                    $this->executeDelete($ch);
                    break;
                case 'PUT_MP':
                    $this->verb = 'PUT';
                    $this->executePutMultipart($ch);
                    break;
                case 'POST_MP':
                    $this->verb = 'POST';
                    $this->executePostMultipart($ch);
                    break;
                case 'POST_BIN':
                    $this->verb = 'POST';
                    $this->executeBinarySend($ch);
                    break;
                case 'PUT_BIN':
                    $this->verb = 'PUT';
                    $this->executeBinarySend($ch);
                    break;
                default:
                    throw new InvalidArgumentException('Current verb ('.$this->verb.') is an invalid REST verb.');
            }
        } catch (InvalidArgumentException|Exception $e) {
            curl_close($ch);
            throw $e;
        }
    }

    public function buildPostBody(?array $data = null): void
    {
        $data = (null !== $data) ? $data : $this->requestBody;
        $this->requestBody = $data;
    }

    protected function executeGet(CurlHandle $ch): void
    {
        $this->doExecute($ch);
    }

    protected function executePost(CurlHandle $ch): void
    {
        if (!is_string($this->requestBody)) {
            $this->buildPostBody();
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->requestBody);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

        $this->doExecute($ch);
    }

    protected function executeBinarySend(CurlHandle $ch): void
    {
        $post = $this->requestBody;

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->verb);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

        $this->responseBody = curl_exec($ch);
        $this->responseInfo = curl_getinfo($ch);

        curl_close($ch);
    }

    // Set verb to PUT_MP to use this function
    protected function executePutMultipart(CurlHandle $ch): void
    {
        $post = $this->requestBody;

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $this->responseBody = curl_exec($ch);
        $this->responseInfo = curl_getinfo($ch);

        curl_close($ch);
    }

    // Set verb to POST_MP to use this function
    protected function executePostMultipart(CurlHandle $ch): void
    {
        $post = $this->requestBody;

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $this->responseBody = curl_exec($ch);
        $this->responseInfo = curl_getinfo($ch);

        curl_close($ch);
    }

    protected function executePut(CurlHandle $ch): void
    {
        if (!is_string($this->requestBody)) {
            $this->buildPostBody();
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->requestBody);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');

        $this->doExecute($ch);
    }

    protected function executeDelete(CurlHandle $ch): void
    {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');

        $this->doExecute($ch);
    }

    protected function doExecute(CurlHandle $curlHandle): void
    {
        $this->setCurlOpts($curlHandle);
        $response = curl_exec($curlHandle);
        $this->responseInfo = curl_getinfo($curlHandle);

        if ($response === false)
        {
            $this->responseBody = false;
        }
        else
        {
            $response = preg_replace("/^(?:HTTP\/1.1 100.*?\\r\\n\\r\\n)+/ms", '', $response);

            //  100-continue chunks are returned on multipart communications
            $headerBlock = mb_strstr($response, "\r\n\r\n", true, '8bit');

            // strstr returns the matched characters and following characters, but we want to discard of "\r\n\r\n", so
            // we delete the first 4 bytes of the returned string.
            $responseHeaderBlock = mb_strstr($response, "\r\n\r\n", encoding: '8bit');
            $this->responseBody = mb_substr($responseHeaderBlock, 4, encoding: '8bit');

            // headers are always separated by \n until the end of the header block which is separated by \r\n\r\n.
            $this->responseHeaders = explode("\r\n", $headerBlock);
        }

        curl_close($curlHandle);
    }

    protected function setCurlOpts(CurlHandle $curlHandle): void
    {
        curl_setopt($curlHandle, CURLOPT_URL, $this->url);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_COOKIEFILE, '/dev/null');
        curl_setopt($curlHandle, CURLOPT_HEADER, true);

        if (!empty($this->contentType)) {
            $this->headers[] = 'Content-Type: '.$this->contentType;
        }
        if (!empty($this->acceptType)) {
            $this->headers[] = 'Accept: '.$this->acceptType;
        }
        if (!empty($this->headers)) {
            curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $this->headers);
        }
    }

    protected function setAuth(CurlHandle $curlHandle): void
    {
        if (null !== $this->username && null !== $this->password) {
            curl_setopt($curlHandle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curlHandle, CURLOPT_USERPWD, $this->username.':'.$this->password);
        }
    }

    protected function setTimeout(CurlHandle $curlHandle): void
    {
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, $this->curlTimeout);
    }

    public function defineTimeout(int $seconds): void
    {
        $this->curlTimeout = $seconds;
    }

    public function getAcceptType(): ?string
    {
        return $this->acceptType;
    }

    public function setAcceptType(?string $acceptType): void
    {
        $this->acceptType = $acceptType;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function getResponseInfo(): mixed
    {
        return $this->responseInfo;
    }

    public function getUrl(): RESTRequest|string|null
    {
        return $this->url;
    }

    public function setUrl(RESTRequest|string|null $url): void
    {
        $this->url = $url;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getVerb(): ?string
    {
        return $this->verb;
    }

    public function setVerb(?string $verb): void
    {
        $this->verb = $verb;
    }

    /**
     * @throws RESTRequestException
     */
    public function handleError(int $statusCode, array $expectedCodes, ?string $responseBody)
    {
        if (!empty($responseBody)) {
            $errorData = json_decode($responseBody);
            $exception = new RESTRequestException(
                (empty($errorData->message)) ? RESTRequestException::UNEXPECTED_CODE_MSG : $errorData->message
            );
            $exception->expectedStatusCodes = $expectedCodes;
            $exception->statusCode = $statusCode;
            if (!empty($errorData->errorCode)) {
                $exception->errorCode = $errorData->errorCode;
            }
            if (!empty($errorData->parameters)) {
                $exception->parameters = $errorData->parameters;
            }
        } else {
            $exception = new RESTRequestException(RESTRequestException::UNEXPECTED_CODE_MSG);
            $exception->expectedStatusCodes = $expectedCodes;
            $exception->statusCode = $statusCode;
        }
        throw $exception;
    }

    /**
     * @throws RESTRequestException
     */
    public function makeRequest(
        RESTRequest|string|null $url,
        array $expectedCodes = [200],
        ?string $verb = null,
        ?string $reqBody = null,
        bool $returnData = false,
        string $contentType = 'application/json',
        string $acceptType = 'application/json',
        array $headers = []
    ): array {
        $this->performReset($url, $verb, $reqBody);
        $info = $this->getResponseInfo();
        $statusCode = $info['http_code'];
        $body = $this->getResponseBody();

        $headers = $this->responseHeaders;

        // An exception is thrown here if the expected code does not match the status code in the response
        if (!in_array($statusCode, $expectedCodes, true)) {
            $this->handleError($statusCode, $expectedCodes, $body);
        }

        return compact('body', 'statusCode', 'headers');
    }

    /**
     * @throws RESTRequestException
     */
    public function prepAndSend(
        RESTRequest|string|null $url,
        array $expectedCodes = [200],
        ?string $verb = null,
        ?string $reqBody = null,
        bool $returnData = false,
        string $contentType = 'application/json',
        string $acceptType = 'application/json',
        array $headers = []
    ): bool|string|null {
        $this->performReset($url, $verb, $reqBody);
        $statusCode = $this->getResponseInfo();
        $responseBody = $this->getResponseBody();
        $statusCode = $statusCode['http_code'];

        if (!in_array($statusCode, $expectedCodes, true)) {
            $this->handleError($statusCode, $expectedCodes, $responseBody);
        }

        if ($returnData) {
            return $this->getResponseBody();
        }

        return true;
    }

    /**
     * This function creates a multipart/form-data request and sends it to the server.
     * this function should only be used when a file is to be sent with a request (PUT/POST).
     *
     * @param RESTRequest|string|null $url          - URL to send request to
     * @param int|string              $expectedCode - HTTP Status Code you expect to receive on success
     * @param string                  $verb         - HTTP Verb to send with request
     * @param string|null             $reqBody      - The body of the request if necessary
     * @param array|null              $file         - An array with the URI string representing the image, and the filepath to the image. (i.e: array('/images/JRLogo', '/home/user/jasper.jpg') )
     * @param bool                    $returnData   - whether you wish to receive the data returned by the server or not
     *
     * @return array - Returns an array with the response info and the response body, since the server sends a 100 request, it is hard to validate the success of the request
     */
    public function multipartRequestSend(
        RESTRequest|string|null $url,
        int|string $expectedCode = 200,
        string $verb = 'PUT_MP',
        ?string $reqBody = null,
        ?array $file = null,
        bool $returnData = false
    ): array {
        $this->performReset($url, $verb, $reqBody);
        $response = $this->getResponseInfo();
        $responseBody = $this->getResponseBody();
        $statusCode = $response['http_code'];

        return [$statusCode, $responseBody];
    }

    /**
     * @throws RESTRequestException
     */
    public function sendBinary(RESTRequest|string|null $url, array $expectedCodes, array $body, string $contentType, string $contentDisposition, string $contentDescription, string $verb = 'POST'): ?string
    {
        $this->flush();
        $this->setUrl($url);
        $this->setVerb($verb.'_BIN');
        $this->buildPostBody($body);
        $this->setContentType($contentType);
        $this->headers = [
            'Content-Type: '.$contentType,
            'Content-Disposition: '.$contentDisposition,
            'Content-Description: '.$contentDescription,
            'Accept: application/json',
        ];

        $this->execute();

        $statusCode = $this->getResponseInfo();
        $responseBody = $this->getResponseBody();
        $statusCode = $statusCode['http_code'];

        if (!in_array($statusCode, $expectedCodes, true)) {
            $this->handleError($statusCode, $expectedCodes, $responseBody);
        }

        return $this->getResponseBody();
    }

    private function performReset(RESTRequest|string|null $url, ?string $verb, ?array $reqBody): void
    {
        $this->flush();
        $this->setUrl($url);
        if (null !== $verb) {
            $this->setVerb($verb);
        }
        if (null !== $reqBody) {
            $this->buildPostBody($reqBody);
        }

        $this->execute();
    }
}
