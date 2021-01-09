<?php

declare(strict_types=1);

namespace Archette\AppGen\Command\Initial;

use Archette\AppGen\Command\BaseCommand;
use Archette\AppGen\Config\AppGenConfig;
use Nette\Neon\Neon;
use Nette\Utils\Strings;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class InitialCommand extends BaseCommand
{
	protected static $defaultName = 'appgen:init';

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		parent::execute($input, $output);

		/** @var QuestionHelper $questionHelper */
		$questionHelper = $this->getHelper('question');

		$output->writeln('# <fg=yellow>Configure the AppGen (traits can be added later)</>');
		$output->writeln('');

		$config = new AppGenConfig();

		$config->appDir = $questionHelper->ask($input, $output, new Question('# <fg=blue>Please Specify App Directory</> [<info>app</info>]: ', 'app'));
		$config->model->entity->idType = $questionHelper->ask($input, $output, new Question('# <fg=blue>Please Specify Entity ID Column Type</> [<info>uuid_binary</info>]: ', 'uuid_binary'));
		if (!Strings::contains($config->model->entity->idType, 'uuid')) {
			$config->model->entity->idComment = $questionHelper->ask($input, $output, new Question('# <fg=blue>Please Specify ID Generator Comment</> [<info>@ORM\GeneratedValue</info>]: ', '@ORM\GeneratedValue'));
		}
		$config->model->entity->createSetters = $questionHelper->ask($input, $output, new ConfirmationQuestion('# <fg=blue>Do You Still Use Setters?</> [<info>no, I\'m a big boy</info>]: ', false));
		$config->model->symfonyEvents = $questionHelper->ask($input, $output, new ConfirmationQuestion('# <fg=blue>Are You Using Symfony Events?</> [<info>yes</info>]: ', true));
		$output->writeln('');

		file_put_contents('appgen.neon', str_replace(['    ', "\n\n"], ["\t", "\n"], Neon::encode($config, Neon::BLOCK)));

		$output->writeln('# Configuration was <info>successfully</info> created!');

		return 1;
	}
}
