<?php

namespace Rezzza\Tk1\Benchmark;

use Guzzle\Http\Message\Response;

/**
 * @author Guillaume MOREL <guillaume.morel@verylastroom.com>
 */
class HttpErrorCollector implements \IteratorAggregate
{
    /** @var int[] */
    private $collectedErrors = array();

    /** @var int */
    private $nbResponse = 0;

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->collectedErrors);
    }

    /**
     * @param Response $response
     */
    public function collect(Response $response)
    {
        if ($response->getStatusCode() == 500
        || $response->getStatusCode() == 200 && $response->getContentType() != 'application/json') { // @todo make it guess content-type
            $this->collectedErrors[] = 500;
        }

        $this->nbResponse++;
    }

    public function computeResults()
    {
        $this->guardAtLeast2Results();
    }

    /**
     * @return int
     */
    public function getNbResponse()
    {
        return $this->nbResponse;
    }

    /**
     * @return int[]
     */
    public function getErrors()
    {
        return $this->collectedErrors;
    }

    private function guardAtLeast2Results()
    {
        if ($this->nbResponse < 2) {
            throw new \LogicException('DataCollector should have at least 2 results to compute results');
        }
    }
}
