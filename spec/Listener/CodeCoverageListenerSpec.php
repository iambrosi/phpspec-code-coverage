<?php

declare(strict_types=1);

namespace spec\FriendsOfPhpSpec\PhpSpec\CodeCoverage\Listener;

use FriendsOfPhpSpec\PhpSpec\CodeCoverage\Listener\CodeCoverageListener;
use PhpSpec\Console\ConsoleIO;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\Html\Facade;
use SebastianBergmann\CodeCoverage\Version;
use function in_array;

/**
 * @author Henrik Bjornskov
 */
class CodeCoverageListenerSpec extends ObjectBehavior
{
    /**
     * @var CodeCoverage
     */
    private $coverage;

    public function getMatchers(): array
    {
        return [
            'haveIncludedFile' => function ($subject, string $file) {
                $isLegacy = version_compare(Version::id(), '9.0.0', '<');

                $filter = $this->coverage->filter();
                if ($isLegacy && in_array($file, $filter->getWhitelist(), true)) {
                    return true;
                }

                if (!$isLegacy && !$filter->isExcluded($file)) {
                    return true;
                }

                return false;
            },
        ];
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(CodeCoverageListener::class);
    }

    /**
     * Disabled due to tests breaking as php-code-coverage marked their classes
     * final and we cannot mock them. The tests should be converted into proper
     * functional (integration) tests instead. This file is left for reference.
     *
     * @see https://github.com/leanphp/phpspec-code-coverage/issues/19
     *
     * function let(ConsoleIO $io, CodeCoverage $coverage)
     * {
     * $this->beConstructedWith($io, $coverage, array());
     * }
     *
     * function it_is_initializable()
     * {
     * $this->shouldHaveType('LeanPHP\PhpSpec\CodeCoverage\Listener\CodeCoverageListener');
     * }
     *
     * function it_should_run_all_reports(
     * CodeCoverage $coverage,
     * Report\Clover $clover,
     * Report\PHP $php,
     * SuiteEvent $event,
     * ConsoleIO $io
     * ) {
     * $reports = array(
     * 'clover' => $clover,
     * 'php' =>  $php
     * );
     *
     * $io->isVerbose()->willReturn(false);
     *
     * $this->beConstructedWith($io, $coverage, $reports);
     * $this->setOptions(array(
     * 'format' => array('clover', 'php'),
     * 'output' => array(
     * 'clover' => 'coverage.xml',
     * 'php' => 'coverage.php'
     * )
     * ));
     *
     * $clover->process($coverage, 'coverage.xml')->shouldBeCalled();
     * $php->process($coverage, 'coverage.php')->shouldBeCalled();
     *
     * $this->afterSuite($event);
     * }
     *
     * function it_should_color_output_text_report_by_default(
     * CodeCoverage $coverage,
     * Report\Text $text,
     * SuiteEvent $event,
     * ConsoleIO $io
     * ) {
     * $reports = array(
     * 'text' => $text
     * );
     *
     * $this->beConstructedWith($io, $coverage, $reports);
     * $this->setOptions(array(
     * 'format' => 'text'
     * ));
     *
     * $io->isVerbose()->willReturn(false);
     * $io->isDecorated()->willReturn(true);
     *
     * $text->process($coverage, true)->willReturn('report');
     * $io->writeln('report')->shouldBeCalled();
     *
     * $this->afterSuite($event);
     * }
     *
     * function it_should_not_color_output_text_report_unless_specified(
     * CodeCoverage $coverage,
     * Report\Text $text,
     * SuiteEvent $event,
     * ConsoleIO $io
     * ) {
     * $reports = array(
     * 'text' => $text
     * );
     *
     * $this->beConstructedWith($io, $coverage, $reports);
     * $this->setOptions(array(
     * 'format' => 'text'
     * ));
     *
     * $io->isVerbose()->willReturn(false);
     * $io->isDecorated()->willReturn(false);
     *
     * $text->process($coverage, false)->willReturn('report');
     * $io->writeln('report')->shouldBeCalled();
     *
     * $this->afterSuite($event);
     * }
     */
    public function it_should_output_html_report(SuiteEvent $event, ConsoleIO $io): void
    {
        $reports = [
            'html' => new Facade(),
        ];

        $this->beConstructedWith($io, $this->coverage, $reports);
        $this->setOptions([
            'format' => 'html',
            'output' => ['html' => '/tmp/coverage'],
        ]);

        $io->isVerbose()->willReturn(false);
        $io->writeln(Argument::any())->shouldNotBeCalled();

//        $html->process($this->coverage, 'coverage')->willReturn('report');

        $this->afterSuite($event);
    }

    public function it_should_provide_extra_output_in_verbose_mode(
        SuiteEvent $event,
        ConsoleIO $io
    ): void {
        $reports = [
            'html' => new Facade(),
        ];

        $this->beConstructedWith($io, $this->coverage, $reports);
        $this->setOptions([
            'format' => 'html',
            'output' => ['html' => '/tmp/coverage'],
        ]);

        $io->isVerbose()->willReturn(true);
        $io->writeln()->shouldBeCalled();
        $io->writeln('Generating code coverage report in html format ...')->shouldBeCalled();

        $this->afterSuite($event);

        if (!is_dir('/tmp/')) {

        }
    }

    public function it_should_correctly_handle_excluded_files_and_directories(SuiteEvent $event): void
    {
        $this->setOptions([
            'whitelist'       => [__DIR__.'/fixtures/included'],
            'blacklist'       => [__DIR__.'/fixtures/included/dir2'],
            'whitelist_files' => [__DIR__.'/fixtures/file.php'],
            'blacklist_files' => [__DIR__.'/fixtures/included/excluded.php'],
        ]);

        $this->beforeSuite($event);

        $this->shouldHaveIncludedFile(__DIR__.'/fixtures/file.php');
        $this->shouldHaveIncludedFile(__DIR__.'/fixtures/included/dir1/file.php');
        $this->shouldNotHaveIncludedFile(__DIR__.'/fixtures/included/dir2/file.php');
        $this->shouldNotHaveIncludedFile(__DIR__.'/fixtures/included/excluded.php');
    }

    public function let(ConsoleIO $io, Driver $driver)
    {
        $this->coverage = new CodeCoverage($driver->getWrappedObject(), new Filter());
        $this->beConstructedWith($io, $this->coverage, []);
    }
}
