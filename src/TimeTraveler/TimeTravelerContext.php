<?php

namespace Rezzza\Tk1\TimeTraveler;

use Behat\Behat\Context\BehatContext;
use Behat\Behat\Context\Step;
use Behat\Symfony2Extension\Context\KernelAwareInterface;
use Symfony\Component\HttpKernel\KernelInterface;

use Rezzza\TimeTraveler;

class TimeTravelerContext extends BehatContext
{
    public function __construct()
    {
        if (!class_exists('Rezzza\TimeTraveler')) {
            throw new \RuntimeException('The TimeTraveler package seems to not be installed.', 1);
        }
    }

    /**
     * @Given /^The current time is "([^"]*)"$/
     */
    public function theCurrentTime($date)
    {
        TimeTraveler::enable();
        TimeTraveler::moveTo($date);
    }

    /**
     * @Given /^The current time is "([^"]*)" with "([^"]*)" timezone$/
     */
    public function theCurrentTimeWithTimezone($date, $newTimezone)
    {
        $currentTimezone = ini_get('date.timezone');
        $dateTime = new \DateTime($date, new \DateTimeZone($newTimezone));
        $dateTime->setTimezone(new \DateTimeZone($currentTimezone));

        return array(
            new Step\Given(sprintf('The current time is "%s"', $dateTime->format('Y-m-d H:i:s'))),
        );
    }

    /**
     * @AfterScenario
     */
    public function cleanTimeTraveler(\Behat\Behat\Event\ScenarioEvent $event)
    {
        TimeTraveler::comeBack();
    }
}
