<?php

namespace Rezzza\Tk1\Benchmark;

use mageekguy\atoum\asserter;
use Behat\Behat\Context\BehatContext;
use Guzzle\Http\Client as HttpClient;

class BenchmarkContext extends BehatContext
{
    private $httpClient;

    /** @var HttpResponseTimeBenchmark */
    private $benchmark;

    private $asserter;

    private $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->asserter = new asserter\generator;
        $this->httpClient = new HttpClient;
    }

    /**
     * @When /^I send (?P<nbRequest>\d+) (?P<method>[A-Z]+) request to "(?P<url>[^"]*)" with (?P<percentile>[\d\.]+) percentile$/
     */
    public function iSendGetRequestToWithPercentile($nbRequest, $method, $url, $percentile)
    {
        $url = $this->baseUrl.'/'.ltrim($url, '/');
        $request = $this->httpClient->createRequest($method, $url);

        $this->benchmark = new HttpResponseTimeBenchmark($request, $nbRequest);
        $this->benchmark->start($this->httpClient, new HttpTimeDataCollector($percentile), $this->asserter, 'application/json');
    }

    /**
     * @Then /^print benchmark result$/
     */
    public function printBenchmarkResult()
    {
        $this->guardBenchmarkStarted();
        $this->benchmark->printResult();
    }

    /**
     * @Then /^response average time should be inferior to (?P<averageTimeRequired>\d+) ms with (?P<burstTolerancePercent>\d+)% burst tolerance$/
     */
    public function responseAverageTimeShouldBeInferiorToMsWithBurstTolerance($averageTimeRequired, $burstTolerancePercent)
    {
        $this->guardBenchmarkStarted();
        $maxAverageTime = $averageTimeRequired * (1 + ($burstTolerancePercent / 100));

        $this->asserter
            ->boolean($this->benchmark->isAverageTimeLessThan($maxAverageTime))
                ->isTrue(sprintf('Average time is %s ms, should be less than %s ms.', $this->benchmark->getAverageTime(), $maxAverageTime))
        ;
    }

    private function guardBenchmarkStarted()
    {
        if (null === $this->benchmark) {
            throw new \LogicException('You should start a benchmark to print its results');
        }
    }
}
