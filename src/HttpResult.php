<?php

namespace Swoft\Http;

use Psr\Http\Message\ResponseInterface;
use Swoft\App;
use Swoft\Core\AbstractResult;
use Swoft\Http\Adapter\AdapterInterface;
use Swoft\Http\Adapter\CoroutineAdapter;
use Swoft\Http\Adapter\ResponseTrait;
use Swoft\Http\Message\Stream\SwooleStream;

/**
 * Http Result
 *
 * @property \Swoole\Http\Client|resource $client
 */
class HttpResult extends AbstractResult
{
    use ResponseTrait;

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * Return result
     *
     * @return ResponseInterface
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function getResult(): ResponseInterface
    {
        $client = $this->client;
        if ($this->getAdapter() instanceof CoroutineAdapter) {
            $this->recv();
            $this->sendResult = $client->body;
            $client->close();
            $headers = value(function () {
                $headers = [];
                foreach ($this->client->headers as $key => $value) {
                    $exploded = explode('-', $key);
                    foreach ($exploded as &$str) {
                        $str = ucfirst($str);
                    }
                    $ucKey = implode('-', $exploded);
                    $headers[$ucKey] = $value;
                }
                unset($str);
                return $headers;
            });
            $response = $this->createResponse()
                             ->withBody(new SwooleStream($this->sendResult ?? ''))
                             ->withHeaders($headers ?? [])
                             ->withStatus($this->deduceStatusCode($client));
        } else {
            $status = curl_getinfo($client, CURLINFO_HTTP_CODE);
            $headers = curl_getinfo($client);
            curl_close($client);
            $response = $this->createResponse()
                             ->withBody(new SwooleStream($this->sendResult ?? ''))
                             ->withStatus($status)
                             ->withHeaders($headers);
        }
        App::debug('HTTP request result = ' . json_encode($this->sendResult));
        return $response;
    }

    /**
     * @alias getResult()
     * @return ResponseInterface
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function getResponse(): ResponseInterface
    {
        return $this->getResult();
    }

    /**
     * Transfer sockets error code to HTTP status code.
     * TODO transfer more error code
     *
     * @param \Swoole\Http\Client $client
     * @return int
     */
    private function deduceStatusCode($client): int
    {
        if ($client->errCode === 110) {
            $status = 404;
        } else {
            $status = $client->statusCode;
        }
        return $status > 0 ? $status : 500;
    }

    /**
     * @return AdapterInterface
     */
    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * @param AdapterInterface $adapter
     * @return HttpResult
     */
    public function setAdapter($adapter): self
    {
        $this->adapter = $adapter;
        return $this;
    }
}
