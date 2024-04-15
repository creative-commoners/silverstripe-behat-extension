<?php

namespace SilverStripe\BehatExtension\Utility;

use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Specification\SpecificationIterator;
use Behat\Testwork\Tester\Result\IntegerTestResult;
use Behat\Testwork\Tester\Result\TestResult;
use Behat\Testwork\Tester\Result\TestResults;
use Behat\Testwork\Tester\Result\TestWithSetupResult;
use Behat\Testwork\Tester\Setup\SuccessfulSetup;
use Behat\Testwork\Tester\Setup\SuccessfulTeardown;
use Behat\Testwork\Tester\SpecificationTester;
use Behat\Testwork\Tester\SuiteTester;

/**
 * Copy paste of Behat\Testwork\Tester\Runtime\RuntimeSuiteTester which is a final class
 *
 * Modified so that it reruns failed features
 */
class RerunRuntimeSuiteTester implements SuiteTester
{
    /**
     * @var SpecificationTester
     */
    private $specTester;

    /**
     * Initializes tester.
     *
     * @param SpecificationTester $specTester
     */
    public function __construct(SpecificationTester $specTester)
    {
        $this->specTester = $specTester;
    }

    /**
     * {@inheritdoc}
     */
    public function setUp(Environment $env, SpecificationIterator $iterator, $skip)
    {
        return new SuccessfulSetup();
    }

    /**
     * {@inheritdoc}
     */
    public function test(Environment $env, SpecificationIterator $iterator, $skip = false)
    {
        $results = array();
        foreach ($iterator as $specification) {
            $setup = $this->specTester->setUp($env, $specification, $skip);
            $localSkip = !$setup->isSuccessful() || $skip;
            $testResult = $this->specTester->test($env, $specification, $localSkip);
            $teardown = $this->specTester->tearDown($env, $specification, $localSkip, $testResult);

            // start modifications here
            if (!$testResult->isPassed()) {
                file_put_contents('php://stdout', 'Retrying specification' . PHP_EOL);
                $setup = $this->specTester->setUp($env, $specification, $skip);
                $localSkip = !$setup->isSuccessful() || $skip;
                $testResult = $this->specTester->test($env, $specification, $localSkip);
                $teardown = $this->specTester->tearDown($env, $specification, $localSkip, $testResult);
            }
            // end modifications here

            $integerResult = new IntegerTestResult($testResult->getResultCode());
            $results[] = new TestWithSetupResult($setup, $integerResult, $teardown);
        }

        return new TestResults($results);
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown(Environment $env, SpecificationIterator $iterator, $skip, TestResult $result)
    {
        return new SuccessfulTeardown();
    }
}
