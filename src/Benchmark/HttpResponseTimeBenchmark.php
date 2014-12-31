<?php

namespace Rezzza\Tk1\Benchmark;

use Guzzle\Http\Exception\RequestException;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Client as HttpClient;

class HttpResponseTimeBenchmark
{
    /** @var Request */
    private $request;

    /** @var int */
    private $nbCalls;

    /** @var HttpTimeDataCollector */
    private $httpTimeDataCollector;

    /** @var HttpErrorCollector */
    private $httpErrorCollector;

    public function __construct(Request $request, $nbCalls)
    {
        $this->request = $request;
        $this->nbCalls = $nbCalls;
    }

    public function start(HttpClient $httpClient, HttpTimeDataCollector $httpTimeDataCollector, HttpErrorCollector $httpErrorCollector)
    {
        for ($i = 0; $i <= $this->nbCalls; $i++) {
            $response = $this->performCall($httpClient);

            if ($i > 0) {
                // We don't count the first result as we want to be sure to collect time on cached requests
                $httpTimeDataCollector->collect($response->getInfo('total_time'));
                $httpErrorCollector->collect($response);
            }
        }

        $httpTimeDataCollector->computeResults();
        $httpErrorCollector->computeResults();
        $this->httpTimeDataCollector = $httpTimeDataCollector;
        $this->httpErrorCollector = $httpErrorCollector;
    }

    public function isAverageTimeLessThan($averageTimeMaxRequired)
    {
        $averageTime = $this->httpTimeDataCollector->getAverageTime();

        return $averageTime <= $averageTimeMaxRequired;
    }

    /**
     * @return float in milli seconds
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

        echo sprintf('with %s/%s HTTP errors.', count($this->httpErrorCollector->getErrors()), $this->httpErrorCollector->getNbResponse()) . PHP_EOL;
    }

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
}
