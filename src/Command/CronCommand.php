<?php

declare(strict_types=1);

namespace Okvpn\Bundle\CronBundle\Command;

use Okvpn\Bundle\CronBundle\Loader\ScheduleLoaderInterface;
use Okvpn\Bundle\CronBundle\Runner\ScheduleRunnerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'okvpn:cron',
    description: 'Runs currently schedule cron',
)]
class CronCommand extends Command
{
    public function __construct(
        private ScheduleRunnerInterface $scheduleRunner,
        private ScheduleLoaderInterface $loader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('okvpn:cron')
            ->addOption('with', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'StampFqcn to add command stamp to all schedules')
            ->addOption('without', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'StampFqcn to remove command stamp from all schedules.')
            ->addOption('command', null, InputOption::VALUE_OPTIONAL, 'Run only selected command')
            ->addOption('demand', null, InputOption::VALUE_NONE, 'Start cron scheduler every one minute without exit')
            ->addOption('group', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Run schedules for specific groups.')
            ->addOption('time-limit', null, InputOption::VALUE_OPTIONAL, 'Run cron scheduler during this time (sec.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('demand')) {
            $output->writeln('Run scheduler without exit');
            $startTime = time();
            $timeLimit = $input->getOption('time-limit');

            while ($now = time() and (null === $timeLimit || $now - $startTime < $timeLimit)) {
                sleep(60 - ($now % 60));
                $runAt = microtime(true);
                $this->scheduler($input, $output);
                $output->writeln(sprintf('All schedule tasks completed in %.3f seconds', microtime(true) - $runAt), OutputInterface::VERBOSITY_VERBOSE);
            }
        } else {
            $this->scheduler($input, $output);
        }

        return Command::SUCCESS;
    }

    protected function scheduler(InputInterface $input, OutputInterface $output): void
    {
        $options = [];
        $command = $input->getOption('command');
        if ($input->getOption('group')) {
            $options['groups'] = (array) $input->getOption('group');
        }
        if ($input->getOption('with')) {
            $options['with'] = (array) $input->getOption('with');
        }

        foreach ($this->loader->getSchedules($options) as $schedule) {
            if (null !== $command && $schedule->getCommand() !== $command) {
                continue;
            }

            if ($without = $input->getOption('without')) {
                $schedule = $schedule->without(...$without);
            }

            $output->writeln(" > Scheduling run for command {$schedule->getCommand()} ...", OutputInterface::VERBOSITY_VERBOSE);
            $this->scheduleRunner->execute($schedule);
        }
    }
}
