<?php

declare(strict_types=1);

namespace Okvpn\Bundle\CronBundle\Command;

use Okvpn\Bundle\CronBundle\Runner\ScheduleRunnerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'okvpn:cron:execute-job',
    description: 'INTERNAL!!!. Execute cron command from file.',
)]
class CronExecuteCommand extends Command
{
    public function __construct(
        private ScheduleRunnerInterface $scheduleRunner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('filename', InputArgument::REQUIRED, 'PHP serialized cron job')
            ->setHidden(true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fileContent = file_get_contents($input->getArgument('filename'));
        $envelope = unserialize($fileContent);

        try {
            $this->scheduleRunner->execute($envelope);
        } finally {
            @unlink($input->getArgument('filename'));
        }

        return Command::SUCCESS;
    }
}
