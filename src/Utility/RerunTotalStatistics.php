<?php

namespace SilverStripe\BehatExtension\Utility;

use Behat\Behat\Tester\Result\StepResult;
use Behat\Testwork\Counter\Memory;
use Behat\Testwork\Counter\Timer;
use Behat\Testwork\Tester\Result\TestResult;
use Behat\Testwork\Tester\Result\TestResults;
use Behat\Behat\Output\Statistics\Statistics;
use Behat\Behat\Output\Statistics\ScenarioStat;
use Behat\Behat\Output\Statistics\StepStat;
use Behat\Behat\Output\Statistics\HookStat;

/**
 * Copy paste of Behat\Behat\Output\Statistics\TotalStatistics which is a final class
 *
 * Modified to remove duplicated stats from reruns
 */
class RerunTotalStatistics implements Statistics
{
    /**
     * @var Timer
     */
    private $timer;
    /**
     * @var Memory
     */
    private $memory;
    /**
     * @var array
     */
    private $scenarioCounters = array();
    /**
     * @var array
     */
    private $stepCounters = array();
    /**
     * @var ScenarioStat[]
     */
    private $failedScenarioStats = array();
    /**
     * @var ScenarioStat[]
     */
    private $skippedScenarioStats = array();
    /**
     * @var StepStat[]
     */
    private $failedStepStats = array();
    /**
     * @var StepStat[]
     */
    private $pendingStepStats = array();
    /**
     * @var HookStat[]
     */
    private $failedHookStats = array();
    // start modifications here
    /**
     * @var StepStat[]
     */
    private $passedStepStats = array();
    // end modifications here

    /**
     * Initializes statistics.
     */
    public function __construct()
    {
        $this->resetAllCounters();

        $this->timer = new Timer();
        $this->memory = new Memory();
    }

    public function resetAllCounters()
    {
        $this->scenarioCounters = $this->stepCounters = array(
            TestResult::PASSED    => 0,
            TestResult::FAILED    => 0,
            StepResult::UNDEFINED => 0,
            TestResult::PENDING   => 0,
            TestResult::SKIPPED   => 0
        );
    }

    /**
     * Starts timer.
     */
    public function startTimer()
    {
        $this->timer->start();
    }

    /**
     * Stops timer.
     */
    public function stopTimer()
    {
        $this->timer->stop();
    }

    /**
     * Returns timer object.
     *
     * @return Timer
     */
    public function getTimer()
    {
        return $this->timer;
    }

    /**
     * Returns memory usage object.
     *
     * @return Memory
     */
    public function getMemory()
    {
        return $this->memory;
    }

    /**
     * Registers scenario stat.
     *
     * @param ScenarioStat $stat
     */
    public function registerScenarioStat(ScenarioStat $stat)
    {
        if (TestResults::NO_TESTS === $stat->getResultCode()) {
            return;
        }

        $this->scenarioCounters[$stat->getResultCode()]++;

        // start modifications here
        if (TestResult::FAILED === $stat->getResultCode()) {
            // Ensure that any scenario reruns aren't counted as additional failures
            $alreadyHasFailure = false;
            foreach ($this->failedScenarioStats as $failedStat) {
                if ($failedStat->getPath() === $stat->getPath()) {
                    $alreadyHasFailure = true;
                    break;
                }
            }
            if (!$alreadyHasFailure) {
                $this->failedScenarioStats[] = $stat;
            } else {
                $this->scenarioCounters[TestResult::FAILED]--;
            }
        }

        if (TestResult::PASSED == $stat->getResultCode()) {
            // Remove the scenario from the failed scenarios list if it passes on rerun
            $newFailedScenarioStats = [];
            foreach ($this->failedScenarioStats as $failedStat) {
                if ($failedStat->getPath() !== $stat->getPath()) {
                    $newFailedScenarioStats[] = $failedStat;
                } else {
                    $this->scenarioCounters[TestResult::FAILED]--;
                }
            }
            $this->failedScenarioStats = $newFailedScenarioStats;
        }
        // end modifications here

        if (TestResult::SKIPPED === $stat->getResultCode()) {
            $this->skippedScenarioStats[] = $stat;
        }
    }

    /**
     * Registers step stat.
     *
     * @param StepStat $stat
     */
    public function registerStepStat(StepStat $stat)
    {
        $this->stepCounters[$stat->getResultCode()]++;

        // start modifications here
        if (TestResult::FAILED === $stat->getResultCode()) {
            // Ensure that any scenario reruns don't double count step failures
            $alreadyHasFailure = false;
            foreach ($this->failedStepStats as $failedStat) {
                if ($failedStat->getPath() === $stat->getPath()) {
                    $alreadyHasFailure = true;
                    break;
                }
            }
            if (!$alreadyHasFailure) {
                $this->failedStepStats[] = $stat;
            } else {
                $this->stepCounters[TestResult::FAILED]--;
            }
        }

        if (TestResult::PASSED == $stat->getResultCode()) {
            // Remove any duplicate passes on scenario rerun
            $alreadyHasSuccess = false;
            foreach ($this->passedStepStats as $passedStat) {
                if ($passedStat->getPath() === $stat->getPath()) {
                    $alreadyHasSuccess = true;
                    break;
                }
            }
            if (!$alreadyHasSuccess) {
                $this->passedStepStats[] = $stat;
            } else {
                $this->stepCounters[TestResult::PASSED]--;
            }

            // Remove the step from the failed steps list if it passes on scenario rerun
            $newFailedStepStats = [];
            foreach ($this->failedStepStats as $failedStat) {
                if ($failedStat->getPath() !== $stat->getPath()) {
                    $newFailedStepStats[] = $failedStat;
                } else {
                    $this->stepCounters[TestResult::FAILED]--;
                }
            }
            $this->failedStepStats = $newFailedStepStats;
        }
        // end modifications here

        if (TestResult::PENDING === $stat->getResultCode()) {
            $this->pendingStepStats[] = $stat;
        }
    }

    /**
     * Registers hook stat.
     *
     * @param HookStat $stat
     */
    public function registerHookStat(HookStat $stat)
    {
        if ($stat->isSuccessful()) {
            return;
        }

        $this->failedHookStats[] = $stat;
    }

    /**
     * Returns counters for different scenario result codes.
     *
     * @return array[]
     */
    public function getScenarioStatCounts()
    {
        return $this->scenarioCounters;
    }

    /**
     * Returns skipped scenario stats.
     *
     * @return ScenarioStat[]
     */
    public function getSkippedScenarios()
    {
        return $this->skippedScenarioStats;
    }

    /**
     * Returns failed scenario stats.
     *
     * @return ScenarioStat[]
     */
    public function getFailedScenarios()
    {
        return $this->failedScenarioStats;
    }

    /**
     * Returns counters for different step result codes.
     *
     * @return array[]
     */
    public function getStepStatCounts()
    {
        return $this->stepCounters;
    }

    /**
     * Returns failed step stats.
     *
     * @return StepStat[]
     */
    public function getFailedSteps()
    {
        return $this->failedStepStats;
    }

    /**
     * Returns pending step stats.
     *
     * @return StepStat[]
     */
    public function getPendingSteps()
    {
        return $this->pendingStepStats;
    }

    /**
     * Returns failed hook stats.
     *
     * @return HookStat[]
     */
    public function getFailedHookStats()
    {
        return $this->failedHookStats;
    }
}
