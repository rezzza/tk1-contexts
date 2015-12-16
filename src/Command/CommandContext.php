<?php

namespace Rezzza\Tk1\Command;

use Behat\Behat\Context\BehatContext;
use Behat\Behat\Context\Step;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Symfony2Extension\Context\KernelAwareInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use mageekguy\atoum\asserter;

class CommandContext extends BehatContext implements KernelAwareInterface
{
    /** @var KernelInterface */
    private $kernel;

    /** @var asserter\generator  */
    private $asserter;

    /** @var ApplicationTester */
    private $tester;

    /** @var int Terminal width used during output */
    private $terminalWidth;
    /** @var int Terminal height used during output */
    private $terminalHeight;

    /**
     * Constructor
     * @param asserter\generator $asserter       Asserter
     * @param int                $terminalWidth  Terminal width used during output
     * @param int                $terminalHeight Terminal height used during output
     */
    public function __construct($asserter, $terminalWidth = 640, $terminalHeight = 300)
    {
        $this->asserter = $asserter;
        $this->terminalWidth = $terminalWidth;
        $this->terminalHeight = $terminalHeight;
    }

    /**
     * {@inheritdoc}
     */
    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @param string $name
     * @param array $params
     */
    public function iRunWithArrayParameters($name, $params)
    {
        $application = $this->initApplication();

        $this->tester = new ApplicationTester($application);
        $this->tester->run(array_merge(
            array(
                'command' => $name
            ),
            $params
        ));
    }

    /**
     * @param string $name
     * @param array  $params
     * @param string $dialogString
     */
    public function iRunDialogConsoleWithArrayParameters($name, array $params, $dialogString = 'Y')
    {
        $application = $this->initApplication();

        $this->tester = $applicationTester = new ApplicationTester($application);

        // Application needs to be run one time before calling find()
        // Otherwise for some reason CLI services are not injected in DIC
        $applicationTester->run(array());

        $command = $application->find($name);

        $dialog = $command->getHelper('dialog');
        $dialog->setInputStream($this->getInputStream($dialogString));

        $this->tester->run(array_merge(
            array(
                'command' => $command->getName()
            ),
            $params
        ));
    }

    /**
     * Runs symfony command with provided parameters
     *
     * @When /^I run "([^"]*)" command with parameters:$/
     * @param string       $name
     * @param PyStringNode $params
     */
    public function iRunWithParameters($name, PyStringNode $params)
    {
        $params = $this->guardAgainstInvalidCommandParams($params);

        $this->iRunWithArrayParameters($name, $params);
    }

    /**
     * @When /^I run "(?P<commandCliName>[^"]+)" command confirming with "(?P<dialogString>[^"]+)" with parameters:$/
     * @param string       $commandCliName
     * @param string       $dialogString
     * @param PyStringNode $params
     */
    public function iRunDialogConsoleWithParameters($commandCliName, $dialogString, PyStringNode $params)
    {
        $params = $this->guardAgainstInvalidCommandParams($params);

        $this->iRunDialogConsoleWithArrayParameters($commandCliName, $params, $dialogString);
    }

    /**
     * Checks whether previously run command failed|passed.
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
        $this->asserter->phpString($this->getOutput())->match($this->getExpectedOutput($text));
    }

    /**
     * Checks whether last command output matches N times the provided string.
     *
     * @Then /^the output should match (\d+) times:$/
     * @param int          $nb
     * @param PyStringNode $text
     */
    public function theOutputShouldMatchNTimes($nb, PyStringNode $text)
    {
        preg_match_all($this->getExpectedOutput($text), $this->getOutput(), $matches);

        $this->asserter->phpArray($matches[0])->hasSize($nb);
    }

    /**
     * Checks whether last command output contains provided string.
     *
     * @Then the output should contain:
     *
     * @param PyStringNode $text PyString text instance
     */
    public function theOutputShouldContain(PyStringNode $text)
    {
        $this->asserter->phpString($this->getOutput())->contains($this->getExpectedOutput($text));
    }

    /**
     * Checks whether last command output contains provided string.
     *
     * @Then the output should not contain:
     *
     * @param PyStringNode $text PyString text instance
     */
    public function theOutputShouldNotContain(PyStringNode $text)
    {
        $this->asserter->phpString($this->getOutput())->notContains($this->getExpectedOutput($text));
    }

    /**
     * Checks whether previously run command passes|fails with provided output.
     *
     * @Then /^the command should (fail|pass) with:$/
     *
     * @param string $success "fail" or "pass"
     * @param PyStringNode $text PyString text instance
     *
     * @return \Behat\Behat\Context\Step\SubstepInterface[]
     */
    public function itShouldPassWith($success, PyStringNode $text)
    {
        return array(
            new Step\Given(sprintf('the command should %s', $success)),
            new Step\Then('the output should contain:', $text)
        );
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

    /**
     * @return int 0 success otherwise fail
     */
    public function getExitCode()
    {
        return $this->tester->getStatusCode() !== null ? $this->tester->getStatusCode() : 1;
    }

    /**
     * @return string
     */
    public function getExpectedOutput(PyStringNode $expectedText)
    {
        return strtr($expectedText, array('\'\'\'' => '"""'));
    }

    /**
     * @return string
     */
    public function getOutput()
    {
        return $this->tester->getDisplay(true);
    }

    /**
     * @param string $input
     *
     * @return resource
     */
    private function getInputStream($input)
    {
        $stream = fopen('php://memory', 'r+', false);
        fputs($stream, $input);
        rewind($stream);

        return $stream;
    }

    /**
     * @param PyStringNode $params
     *
     * @return array
     */
    private function guardAgainstInvalidCommandParams(PyStringNode $params)
    {
        $params = json_decode($params->getRaw(), true);

        if (null === $params) {
            throw new \InvalidArgumentException('Args in command could not be converted in json');
        }

        return $params;
    }

    /**
     * @return Application
     */
    private function initApplication()
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        return $application->setTerminalDimensions($this->terminalWidth, $this->terminalHeight);
    }
}
