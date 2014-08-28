<?php

namespace Rezzza\Tk1\Command;

use mageekguy\atoum\asserter;
use Behat\Behat\Context\Context as BehatContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\ApplicationTester;

class CommandContext implements BehatContext, KernelAwareContext
{
    private $kernel;

    private $asserter;

    private $tester;

    public function __construct()
    {
        $this->asserter = new asserter\generator;
    }

    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function iRunWithArrayParameters($name, $params)
    {
        $app = new Application($this->kernel);
        $app->setAutoExit(false);

        $this->tester = new ApplicationTester($app);
        $this->tester->run(array_merge(
            array(
                'command' => $name
            ),
            $params
        ));
    }

    /**
     * Runs symfony command with provided parameters
     *
     * @When /^I run "([^"]*)" command with parameters:$/
     */
    public function iRunWithParameters($name, PyStringNode $params)
    {
        $params = json_decode($params->getRaw(), true);

        if (null === $params) {
            throw new \InvalidArgumentException('Args in command could not be converted in json');
        }

        return $this->iRunWithArrayParameters($name, $params);
    }

    /**
     * Checks whether previously runned command failed|passed.
     *
     * @Then /^the command should (fail|pass)$/
     *
     * @param string $success "fail" or "pass"
     */
    public function itShouldPass($success)
    {
        if ('fail' === $success) {
            if (0 === $this->getExitCode()) {
                echo 'Actual output:' . PHP_EOL . PHP_EOL . $this->getOutput();
            }

            $this->asserter->integer($this->getExitCode())->isNotEqualTo(0);
        } else {
            if (0 !== $this->getExitCode()) {
                echo 'Actual output:' . PHP_EOL . PHP_EOL . $this->getOutput();
            }

            $this->asserter->integer($this->getExitCode())->isEqualTo(0);
        }
    }

    /**
     * Checks whether last command output matches provided string.
     *
     * @Then the output should match:
     *
     * @param PyStringNode $text PyString text instance
     */
    public function theOutputShouldMatch(PyStringNode $text)
    {
        $this->asserter->string($this->getOutput())->match($this->getExpectedOutput($text));
    }


    /**
     * Checks whether last command output contains provided string.
     *
     * @Then the output should contain:
     *
     * @param string $not
     * @param PyStringNode $text PyString text instance
     */
    public function theOutputShouldContain(PyStringNode $text)
    {
        $this->asserter->string($this->getOutput())->contains($this->getExpectedOutput($text));
    }

    /**
     * Checks whether last command output contains provided string.
     *
     * @Then the output should not contain:
     *
     * @param string $not
     * @param PyStringNode $text PyString text instance
     */
    public function theOutputShouldNotContain(PyStringNode $text)
    {
        $this->asserter->string($this->getOutput())->notContains($this->getExpectedOutput($text));
    }

    /**
     * Checks whether previously runned command passes|failes with provided output.
     *
     * @Then /^the command should (fail|pass) with:$/
     *
     * @param string $success "fail" or "pass"
     * @param PyStringNode $text PyString text instance
     */
    public function itShouldPassWith($success, PyStringNode $text)
    {
        $this->itShouldPass($success);
        $this->theOutputShouldContain($text);
    }

    /**
     * @Then /^I should see the last command output$/
     * @Then /^print last command output$/
     */
    public function iShouldSeeTheLastCommandOutput()
    {
        echo str_pad('> Start last command output ', 80, '-') . PHP_EOL;
        echo $this->getOutput();
        echo str_pad('> End last command output ', 80, '-') . PHP_EOL;
    }

    public function getExitCode()
    {
        return $this->getTester()->getStatusCode() !== null ? $this->getTester()->getStatusCode() : 1;
    }

    public function getExpectedOutput(PyStringNode $expectedText)
    {
        return strtr($expectedText, array('\'\'\'' => '"""'));
    }

    public function getOutput()
    {
        return $this->getTester()->getDisplay(true);
    }

    public function getKernel()
    {
        return $this->kernel;
    }

    public function getContainer()
    {
        return $this->kernel->getContainer();
    }

    private function getTester()
    {
        if (null === $this->tester) {
            throw new \LogicException('You should call iRunWithArrayParameters method to get the tester available');
        }

        return $this->tester;
    }
}
