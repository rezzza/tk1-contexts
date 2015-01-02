<?php

namespace Rezzza\Tk1\Benchmark;

use Guzzle\Http\Exception\RequestException;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Client as HttpClient;
use mageekguy\atoum\asserter;

class HttpResponseTimeBenchmark
{
    /** @var Request */
    private $request;

    /** @var int */
    private $nbCalls;

    /** @var HttpTimeDataCollector */
    private $httpTimeDataCollector;

    public function __construct(Request $request, $nbCalls)
    {
        $this->request = $request;
        $this->nbCalls = $nbCalls;
    }

    public function start(HttpClient $httpClient, HttpTimeDataCollector $httpTimeDataCollector, asserter\generator $asserter, $expectedResponseContentType)
    {
        for ($i = 0; $i <= $this->nbCalls; $i++) {
            $response = $this->performCall($httpClient);

            $this->checkForHttpError($response, $asserter, $expectedResponseContentType);

            if ($i > 0) {
                // We don't count the first result as we want to be sure to collect time on cached requests
                $httpTimeDataCollector->collect($response->getInfo('total_time'));
            }
        }

        $httpTimeDataCollector->computeResults();
        $this->httpTimeDataCollector = $httpTimeDataCollector;
    }

    /**
     * @param float $averageTimeMaxRequired In milliseconds
     *
     * @return bool
     */
    public function isAverageTimeLessThan($averageTimeMaxRequired)
    {
        $averageTime = $this->httpTimeDataCollector->getAverageTime();

        return $averageTime <= $averageTimeMaxRequired;
    }

    /**
     * @return float in milliseconds
     */
    public function getAverageTime()
    {
        return $this->httpTimeDataCollector->getAverageTime();
    }

    public function printResult()
    {
        echo sprintf('%s %s : %s calls', strtoupper($this->request->getMethod()), $this->request->getUrl(), $this->nbCalls) . PHP_EOL;

        foreach ($this->httpTimeDataCollector->getIterator() as $httpTimeResult) {
            echo sprintf('total time = %s ms', $httpTimeResult) . PHP_EOL;
        }

        echo '--- statistics ---' . PHP_EOL;
        echo sprintf('results filter on %s percentile', $this->httpTimeDataCollector->getPercentile()) . PHP_EOL;
        echo sprintf(
            'min/avg/max = %s/%s/%s ms',
            $this->httpTimeDataCollector->getMinTime(),
            $this->httpTimeDataCollector->getAverageTime(),
            $this->httpTimeDataCollector->getMaxTime()
        ).PHP_EOL;
    }

    /**
     * @return array|\Guzzle\Http\Message\Response|null
     */
    private function performCall(HttpClient $httpClient)
    {
        try {
            return $httpClient->send($this->request);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if (null === $response) {
                throw $e;
            }

            return $response;
        }
    }

    /**
     * @param Response           $response
     * @param asserter\generator $asserter
     * @param string             $expectedResponseContentType application/json|application/xml
     */
    private function checkForHttpError(Response $response, asserter\generator $asserter, $expectedResponseContentType)
    {
        if ($response->isError() || $this->isHttpErrorFatal($response, $expectedResponseContentType)) {
            $asserter->boolean(true)
                ->isTrue('Benchmarking encountered a Fatal Error');
        }
    }

    /**
     * @return bool
     */
    private function isHttpErrorFatal(Response $response, $expectedResponseContentType)
    {
        return $response->isSuccessful() && $response->getContentType() != $expectedResponseContentType;
    }
}
