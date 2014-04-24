<?php

namespace Rezzza\Tk1\Behatch;

use Behat\Behat\Context\BehatContext;
use Sanpi\Behatch\Json\JsonInspector;

class ExtraJsonContext extends BehatContext
{
    private $asserter;

    private $behatchCtx;

    public function __construct($asserter, $behatchCtx)
    {
        $this->asserter = $asserter;
        $this->behatchCtx = $behatchCtx;
        $evaluationMode = $this->getMainContext()->getSubContext($this->behatchCtx)->getParameter('json', 'evaluation_mode');
        $this->inspector = new JsonInspector($evaluationMode);
    }

     /**
     * @Given /^the JSON node "([^"]*)" should be an url equal to "([^"]*)"$/
     */
    public function theJsonNodeShouldBeAnUrlEqualTo($node, $url)
    {
        $from = parse_url(
            $this->inspector->evaluate($this->getJson(), $node)
        );
        $this->sortQuery($from);

        $to = parse_url($url);
        $this->sortQuery($to);

        $this->asserter
            // uses phpArray when https://github.com/atoum/atoum/issues/326 will be fixed.
            ->variable($from)
            ->isIdenticalTo($to)
        ;
    }

    private function getJson()
    {
        $content = $this->getSession()->getPage()->getContent();

        return new Json($content);
    }

    private function sortQuery($url)
    {
        $sortQuery = function($q) {
            $q = explode('&', $q);
            sort($q);

            return implode('&', $q);
        };

        return isset($url['query']) ? $sortQuery($url['query']) : '';
    }
}
