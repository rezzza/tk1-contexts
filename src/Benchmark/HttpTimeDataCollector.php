<?php

namespace Rezzza\Tk1\Benchmark;

class HttpTimeDataCollector implements \IteratorAggregate
{
    private $percentile;

    private $collectedTimes = array();

    private $consolidatedTimes = array();

    private $averageTime;

    private $maxTime;

    private $minTime;

    public function __construct($percentile = 0.95)
    {
        $this->percentile = (float) $percentile;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->collectedTimes);
    }

    public function collect($totalTime)
    {
        $this->collectedTimes[] = $totalTime * 1000; // we store it in milliseconds
    }

    public function computeResults()
    {
        $this->guardAtLeast2Results();

        // We filter with percentile method to remove occasional bursts
        $this->consolidatedTimes = $this->filterWithPercentile($this->collectedTimes, $this->percentile);

        $this->averageTime = $this->computeAverageTime();
        $this->maxTime = $this->computeMaxTime();
        $this->minTime = $this->computeMinTime();
    }

    /**
     * @return float in milliseconds
     */
    public function getAverageTime()
    {
        return $this->averageTime;
    }

    /**
     * @return float in milliseconds
     */
    public function getMaxTime()
    {
        return $this->maxTime;
    }

    /**
     * @return float in milliseconds
     */
    public function getMinTime()
    {
        return $this->minTime;
    }

    public function getPercentile()
    {
        return $this->percentile;
    }

    private function filterWithPercentile($times, $percentile)
    {
        $percentileThreshold = $this->computePercentileThreshold($times, $percentile);

        return array_filter($times, function ($value) use ($percentileThreshold) {
            return $value < $percentileThreshold;
        });
    }

    private function computeAverageTime()
    {
        return round(array_sum($this->consolidatedTimes) / count($this->consolidatedTimes), 3);
    }

    private function computeMaxTime()
    {
        return max($this->consolidatedTimes);
    }

    private function computeMinTime()
    {
        return min($this->consolidatedTimes);
    }

    private function computePercentileThreshold($arr, $percentile)
    {
        sort($arr);

        return $arr[(int) round($percentile * count($arr) - 1.0 - $percentile)];
    }

    private function guardAtLeast2Results()
    {
        if (count($this->collectedTimes) < 2) {
            throw new \LogicException('DataCollector should have at least 2 results to compute results');
        }
    }
}
