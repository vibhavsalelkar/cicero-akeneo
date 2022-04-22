<?php

namespace Webkul\Magento2Bundle\Component;

use Webkul\Magento2Bundle\Traits\ApiEndPointsTrait;

/**
 * REST APIClient class.
 */
class OAuthClient
{
    use ApiEndPointsTrait;
    
    /**
     * access token
     *
     * @var string
     */
    protected $accessToken;

    /**
     * headers.
     *
     * @var array
     */
    protected $headers;

    /**
     * lastResponse of api
     *
     * @var array
     */
    protected $lastResponse;

    /**
     * lastInfo headers of api
     *
     * @var array
     */
    protected $lastInfo;
    
    protected $hostName;
    /**
     * Initialize
     *
     * @param string $accessToken access token.
     */
    public function __construct($accessToken, $hostName = null)
    {
        $this->accessToken = $accessToken;
        if ($hostName) {
            $this->hostName = $hostName;
        }
    }

    /**
     * fetches api  by curl
     *
     */
    public function fetch($url, $payload, $method, $headers)
    {
        $this->headers = [ 'Authorization: Bearer ' . $this->accessToken ];
        
        foreach ($headers as $key => $value) {
            $this->headers[] = $key . ': ' . $value;
        }
        $response = $this->requestByCurl($url, $payload, $method, $this->headers);
        $lastResponse = $response['response'];
        $info = $response['info'];
        
        $this->lastResponse = $lastResponse;
        $this->lastInfo = $info;

        if (isset($info['http_code']) && !in_array($info['http_code'], [200, 201, 202, 203, 204, 206])) {
            throw new \Exception(sprintf('expected 200 got %s, Reponse %s, URL %s', $info['http_code'], $lastResponse, $url));
        }

        if (null === json_decode($lastResponse, true)) {
            throw new \Exception('not valid json response');
        }
        $this->lastResponse = $lastResponse;
        if (gettype($lastResponse) !== 'string') {
            $lastResponse = json_encode($lastResponse);
        }
        
        
        return $lastResponse;
    }

    /**
     * get last response
     *
     */
    public function getLastResponse()
    {
        $lastResponse = $this->lastResponse;
        if (gettype($lastResponse) !== 'string') {
            $lastResponse = json_encode($lastResponse);
        }

        return $lastResponse;
    }

    public function getLastResponseInfo()
    {
        $info = $this->lastInfo;

        return $info;
    }

    public function getApiUrlByEndpoint($endpointName, $store = '')
    {
        $url = null;
        $store = $store ? '/' . $store . '/' : '/';
        if (array_key_exists($endpointName, $this->apiEndpoints)) {
            $url = $this->getHostName() . $this->apiEndpoints[$endpointName];
            $url = str_replace('/{_store}/', $store, $url);
        }
        
        return $url;
    }

    /**
    * returns curl response for given route
    *
    * @param string $url
    * @param string $method like GET, POST
    * @param array headers (optional)
    *
    * @return string $response
    */
    protected function requestByCurl($url, $payload = null, $method = 'GET', $headers = [])
    {
        $ch = curl_init();
        $this->setDefaultCurlSettings($ch);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if (!empty($payload) && 'GET' != $method) {
            if (is_array($payload)) {
                $payload = json_encode($payload);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);

        return ['response' => $response, 'info' => $info];
    }

 
    /**
     * Set default cURL settings.
     */
    protected function setDefaultCurlSettings(&$ch)
    {
        $timeout = 90 ;
        \curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        \curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        \curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);


        \curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        \curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        \curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    }

    public function getHostName()
    {
        if (!$this->hostName) {
            throw new \Exception('Error! hostName not set!');
        }
        $result = rtrim($this->hostName, '/');
        
        return $result;
    }
}
