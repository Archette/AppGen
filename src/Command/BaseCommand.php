<?php

declare(strict_types=1);

namespace Archette\AppGen\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->getFormatter()->setStyle('blue', new OutputFormatterStyle('blue'));
		$output->getFormatter()->setStyle('yellow', new OutputFormatterStyle('yellow'));
		$output->getFormatter()->setStyle('success', new OutputFormatterStyle('white'));

		$output->writeln('');
		$output->writeln('<info>#################################################</info>');
		$output->writeln(sprintf('<info>~</info> Welcome to <fg=blue>AppGen v%s</blue> created by <blue>Rick Strafy</> <info>~</info>', APPGEN_VERSION));
		$output->writeln('<info>#################################################</info>');
		$output->writeln('');

		return 1;
	}
}
