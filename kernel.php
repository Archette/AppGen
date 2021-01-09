<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Archette\AppGen\Command\Initial\InitialCommand;
use Archette\AppGen\Command\Model\CreateModelCommand;
use Archette\AppGen\Generator\EntityDataFactoryGenerator;
use Archette\AppGen\Generator\EntityDataGenerator;
use Archette\AppGen\Generator\EntityEventGenerator;
use Archette\AppGen\Generator\EntityFacadeGenerator;
use Archette\AppGen\Generator\EntityFactoryGenerator;
use Archette\AppGen\Generator\EntityGenerator;
use Archette\AppGen\Generator\EntityNotFoundExceptionGenerator;
use Archette\AppGen\Generator\EntityRepositoryGenerator;
use Archette\AppGen\Config\AppGenConfig;
use Archette\AppGen\Helper\ClassHelper;
use Nette\Neon\Neon;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Symfony\Component\Console\Application;

define('APPGEN_VERSION', '1.1');

$application = new Application();

if (!file_exists(getcwd() . '/' . ($configFile = 'appgen.neon'))) {
	$application->add(new InitialCommand());
	$application->setDefaultCommand(InitialCommand::getDefaultName());
} else {
	$processor = new Processor();

	try {
		$config = $processor->process(Expect::from(new AppGenConfig()), Neon::decode(file_get_contents($configFile)));
	} catch (Nette\Schema\ValidationException $e) {
		exit(PHP_EOL . 'Config is invalid!' . PHP_EOL . $e->getMessage() . PHP_EOL);
	}

	$application->add(new CreateModelCommand(
		$config,
		new ClassHelper($config),
		new EntityGenerator($config),
		new EntityDataGenerator($config),
		new EntityDataFactoryGenerator($config),
		new EntityFactoryGenerator($config),
		new EntityRepositoryGenerator($config),
		new EntityFacadeGenerator($config),
		new EntityNotFoundExceptionGenerator($config),
		new EntityEventGenerator($config)
	));

	$application->setDefaultCommand(CreateModelCommand::getDefaultName());
}

exit($application->run());
