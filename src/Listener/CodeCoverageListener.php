<?php

/**
 * This file is part of the friends-of-phpspec/phpspec-code-coverage package.
 *
 * @author  ek9 <dev@ek9.co>
 * @license MIT
 *
 * For the full copyright and license information, please see the LICENSE file
 * that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfPhpSpec\PhpSpec\CodeCoverage\Listener;

use PhpSpec\Console\ConsoleIO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report;
use SebastianBergmann\CodeCoverage\Version;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Henrik Bjornskov
 */
class CodeCoverageListener implements EventSubscriberInterface
{
    /**
     * @var CodeCoverage
     */
    private $coverage;

    /**
     * @var ConsoleIO
     */
    private $io;

    /**
     * @var array<string, mixed>
     */
    private $options;

    /**
     * @var array<string, mixed>
     */
    private $reports;

    /**
     * @var bool
     */
    private $skipCoverage;

    /**
     * CodeCoverageListener constructor.
     *
     * @param array<string, mixed> $reports
     */
    public function __construct(ConsoleIO $io, CodeCoverage $coverage, array $reports, bool $skipCoverage = false)
    {
        $this->io = $io;
        $this->coverage = $coverage;
        $this->reports = $reports;
        $this->options = [
            'whitelist' => ['src', 'lib'],
            'blacklist' => ['test', 'vendor', 'spec'],
            'whitelist_files' => [],
            'blacklist_files' => [],
            'output' => ['html' => 'coverage'],
            'format' => ['html'],
        ];

        $this->skipCoverage = $skipCoverage;
    }

    public function afterExample(ExampleEvent $event): void
    {
        if ($this->skipCoverage) {
            return;
        }

        $this->coverage->stop();
    }

    public function afterSuite(SuiteEvent $event): void
    {
        if ($this->skipCoverage) {
            if ($this->io->isVerbose()) {
                $this->io->writeln('Skipping code coverage generation');
            }

            return;
        }

        if ($this->io->isVerbose()) {
            $this->io->writeln();
        }

        foreach ($this->reports as $format => $report) {
            if ($this->io->isVerbose()) {
                $this->io->writeln(sprintf('Generating code coverage report in %s format ...', $format));
            }

            if ($report instanceof Report\Text) {
                $this->io->writeln(
                    $report->process($this->coverage, $this->io->isDecorated())
                );
            } else {
                $report->process($this->coverage, $this->options['output'][$format]);
            }
        }
    }

    public function beforeExample(ExampleEvent $event): void
    {
        if ($this->skipCoverage) {
            return;
        }

        $example = $event->getExample();

        $name = null;

        if (null !== $spec = $example->getSpecification()) {
            $name = $spec->getClassReflection()->getName();
        }

        $name = strtr('%spec%::%example%', [
            '%spec%' => $name,
            '%example%' => $example->getFunctionReflection()->getName(),
        ]);

        $this->coverage->start($name);
    }

    /**
     * Note: We use array_map() instead of array_walk() because the latter expects
     * the callback to take the value as the first and the index as the seconds parameter.
     */
    public function beforeSuite(SuiteEvent $event): void
    {
        if ($this->skipCoverage) {
            return;
        }

        $filter = $this->coverage->filter();

        if (version_compare(Version::id(), '9.0.0', '>=')) {
            $this->setupCoverageFilter($filter);

            return;
        }

        // Legacy Filter

        foreach ($this->options['whitelist'] as $option) {
            $filter->addDirectoryToWhitelist($option);
        }

        foreach ($this->options['blacklist'] as $option) {
            $filter->removeDirectoryFromWhitelist($option);
        }

        foreach ($this->options['whitelist_files'] as $option) {
            $filter->addFileToWhitelist($option);
        }

        foreach ($this->options['blacklist_files'] as $option) {
            $filter->removeFileFromWhitelist($option);
        }
    }

    /**
     * @return array<string, array<int, int|string>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'beforeExample' => ['beforeExample', -10],
            'afterExample' => ['afterExample', -10],
            'beforeSuite' => ['beforeSuite', -10],
            'afterSuite' => ['afterSuite', -10],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options + $this->options;
    }

    private function setupCoverageFilter(Filter $filter): void
    {
        foreach ($this->options['whitelist'] as $option) {
            $filter->includeDirectory($option);
        }

        foreach ($this->options['blacklist'] as $option) {
            $filter->excludeDirectory($option);
        }

        foreach ($this->options['whitelist_files'] as $option) {
            $filter->includeFile($option);
        }

        foreach ($this->options['blacklist_files'] as $option) {
            $filter->excludeFile($option);
        }
    }
}
