<?php

namespace TrackMage\WordPress;

use Psr\Http\Message\RequestInterface;

class RemoveTransientOnErrorMiddleware
{
    /** @var callable */
    private $nextHandler;

    private $clientId;
    private $clientSecret;

    /**
     * @param string|null $clientId
     * @param string|null $clientSecret
     */
    public function __construct(callable $nextHandler, $clientId, $clientSecret)
    {
        $this->nextHandler = $nextHandler;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * @param string|null $clientId
     * @param string|null $clientSecret
     * @return \Closure
     */
    public static function get($clientId, $clientSecret)
    {
        return static function (callable $handler) use ($clientId, $clientSecret) {
            return new RemoveTransientOnErrorMiddleware($handler, $clientId, $clientSecret);
        };
    }


    /**
     * @param RequestInterface $request
     * @param array $options
     * @return mixed
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        $fn = $this->nextHandler;
        try {
            return $fn($request, $options);
        } catch (\Exception $e) {
            $key = '_trackmage_credentials_valid_' . md5($this->clientId . $this->clientSecret);
            delete_transient($key);
            throw $e;
        }
    }
}
