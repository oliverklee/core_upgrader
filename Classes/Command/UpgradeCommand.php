<?php

declare(strict_types=1);

namespace IchHabRecht\Upgrader\Command;

use Helhum\Typo3Console\Mvc\Cli\CommandDispatcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpgradeCommand extends Command
{
    /**
     * @var CommandDispatcher
     */
    private $commandDispatcher;

    protected function configure()
    {
        $this->setDescription('Run necessary upgrade wizards');
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force wizards to run, despite being marked as executed before.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->commandDispatcher = CommandDispatcher::createFromCommandRun();

        $io = new SymfonyStyle($input, $output);

        $this->runUpgradeWizards($io, $input->isInteractive());

        return 0;
    }

    private function runUpgradeWizards(OutputStyle $io, bool $isInteractive = truee)
    {
        $io->title('Running TYPO3 upgrade wizards');
        $io->newLine();
        $upgradeWizards = require __DIR__ . '/../../Configuration/Upgrades.php';

        $arguments = [
            '--no-interaction',
        ];

        foreach ($upgradeWizards as $version => $versionUpgrades) {
            $output = [];
            $io->section('Running upgrade to TYPO3 ' . $version);
            if (!$isInteractive) {
                $identifier = array_filter($versionUpgrades, function ($upgradeArray) {
                    return class_exists(current($upgradeArray));
                });
                $output[$version] = $this->commandDispatcher->executeCommand(
                    'upgrade:run',
                    array_merge(
                        array_keys($identifier),
                        $arguments
                    )
                );
            } else {
                $io->progressStart(count($versionUpgrades));
                foreach ($versionUpgrades as $key => $upgradeArray) {
                    foreach ($upgradeArray as $identifier => $class) {
                        if (class_exists($class)) {
                            $output[$class] = $this->commandDispatcher->executeCommand(
                                'upgrade:run',
                                array_merge(
                                    [$identifier],
                                    $arguments
                                )
                            );
                        }
                    }
                    $io->progressAdvance(1);
                }
                $io->progressFinish();
            }

            $io->success('All wizards were executed successfully.');

            if ($io->isVerbose()) {
                foreach ($output as $class => $wizardOutput) {
                    $io->note(
                        [
                            $class . ':',
                            $this->formatOutput($wizardOutput),
                        ]
                    );
                }
            }
            $io->newLine();
        }
        $io->newLine();
    }

    private function formatOutput(string $output): string
    {
        return implode(PHP_EOL, preg_split("/\r\n|\n|\r/", strip_tags($output)));
    }
}
