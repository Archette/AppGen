<?php

declare(strict_types=1);

namespace Archette\AppGen\Command\Model;

use Archette\AppGen\Command\BaseCommand;
use Archette\AppGen\Generator\EntityDataFactoryGenerator;
use Archette\AppGen\Generator\EntityDataGenerator;
use Archette\AppGen\Generator\EntityEventGenerator;
use Archette\AppGen\Generator\EntityFacadeGenerator;
use Archette\AppGen\Generator\EntityFactoryGenerator;
use Archette\AppGen\Generator\EntityGenerator;
use Archette\AppGen\Generator\EntityNotFoundExceptionGenerator;
use Archette\AppGen\Generator\EntityRepositoryGenerator;
use Archette\AppGen\Config\AppGenConfig;
use Archette\AppGen\Generator\Property\DoctrineEntityProperty;
use Archette\AppGen\Generator\Property\Relation\RelationData;
use Archette\AppGen\Helper\ClassHelper;
use Archette\AppGen\Helper\Exception\TypeNotFoundException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class CreateModelCommand extends BaseCommand
{
	private AppGenConfig $config;
	private ClassHelper $classHelper;
	private EntityGenerator $entityGenerator;
	private EntityDataGenerator $entityDataGenerator;
	private EntityDataFactoryGenerator $entityDataFactoryGenerator;
	private EntityFactoryGenerator $entityFactoryGenerator;
	private EntityRepositoryGenerator $entityRepositoryGenerator;
	private EntityFacadeGenerator $entityFacadeGenerator;
	private EntityNotFoundExceptionGenerator $entityNotFoundExceptionGenerator;
	private EntityEventGenerator $entityEventGenerator;

	protected static $defaultName = 'appgen:model';

	public function __construct(
		AppGenConfig $config,
		ClassHelper $classHelper,
		EntityGenerator $entityGenerator,
		EntityDataGenerator $entityDataGenerator,
		EntityDataFactoryGenerator $entityDataFactoryGenerator,
		EntityFactoryGenerator $entityFactoryGenerator,
		EntityRepositoryGenerator $entityRepositoryGenerator,
		EntityFacadeGenerator $entityFacadeGenerator,
		EntityNotFoundExceptionGenerator $entityNotFoundExceptionGenerator,
		EntityEventGenerator $entityEventGenerator
	) {
		parent::__construct();
		$this->config = $config;
		$this->classHelper = $classHelper;
		$this->entityGenerator = $entityGenerator;
		$this->entityDataGenerator = $entityDataGenerator;
		$this->entityDataFactoryGenerator = $entityDataFactoryGenerator;
		$this->entityFactoryGenerator = $entityFactoryGenerator;
		$this->entityRepositoryGenerator = $entityRepositoryGenerator;
		$this->entityFacadeGenerator = $entityFacadeGenerator;
		$this->entityNotFoundExceptionGenerator = $entityNotFoundExceptionGenerator;
		$this->entityEventGenerator = $entityEventGenerator;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		parent::execute($input, $output);

		/** @var QuestionHelper $questionHelper */
		$questionHelper = $this->getHelper('question');

		$entityName = $questionHelper->ask($input, $output, new Question('# <blue>Entity Name</blue>: '));
		$namespace = trim($questionHelper->ask($input, $output, new Question('# <blue>Namespace</blue>: ')), '\\');
		$output->writeln('');

		/** @var DoctrineEntityProperty[] $properties */
		$properties = [];

		$type = $phpType = $doctrineType = $relation = '';
		if ($questionHelper->ask($input, $output, new ConfirmationQuestion('# <blue>Define Entity Properties</blue>? [<info>yes</info>] ', true))) {
			$lazyName = null;
			while (true) {
				$relationData = null;

				$output->writeln('');
				if ($lazyName !== null) {
					$name = $lazyName;
					$output->writeln(sprintf('# <yellow>Property Name</yellow>: %s', $name));
				} else {
					$name = $questionHelper->ask($input, $output, new Question('# <yellow>Property Name</yellow>: '));
				}

				while (true) {
					$type = $questionHelper->ask($input, $output, new Question('# <yellow>Type</yellow> (e.g. "<blue>?string|31 --unique</blue>") [<info>string</info>]: ', 'string'));

					try {
						$phpType = $this->classHelper->formatPhpType($type);
						$doctrineType = $this->classHelper->formatDoctrineType($type);

					} catch (TypeNotFoundException $e) {
						$cleanType = trim($type, '?');
						$phpType = $doctrineType = $this->classHelper->resolveNamespace($cleanType) . '\\' . $cleanType;
						
						if ($phpType !== null) {
							while (true) {
								$relation = strtolower($questionHelper->ask($input, $output, new Question('# <yellow>Relation Type</yellow> (<blue>1:1</blue>/<blue>M:1</blue>/<blue>1:M</blue>/<blue>N:M</blue>) [<info>M:1</info>]: ', 'M:1')));

								if ($relation === '1:1') {
									$relation = RelationData::RELATION_ONE_TO_ONE;
								} elseif ($relation === '1:m') {
									$relation = RelationData::RELATION_ONE_TO_MANY;
								} elseif ($relation === 'n:m') {
									$relation = RelationData::RELATION_MANY_TO_MANY;
								} elseif ($relation === 'm:1') {
									$relation = RelationData::RELATION_MANY_TO_ONE;
								} else {
									$output->writeln('');
									$output->writeln(sprintf('<error>Error! Invalid Relation!</error>'));
									$output->writeln('');
									continue;
								}

								break;
							}

							$bidirectional = (bool) $questionHelper->ask($input, $output, new ConfirmationQuestion('# <yellow>Bidirectional</yellow> (add mappedBy/inversedBy)? [<info>no</info>] ', false));
							$cascadeAttributes = $questionHelper->ask($input, $output, new Question('# <yellow>Define Cascade Attributes</yellow> (<blue>persist</blue>/<blue>remove</blue>/<blue>all</blue>): ', 'no'));
							if (!in_array($cascadeAttributes, ['persist', 'remove', 'all'])) {
								$cascadeAttributes = null;
							}

							$onDeleteCascade = false;
							if ($relation === RelationData::RELATION_ONE_TO_ONE || $relation === RelationData::RELATION_MANY_TO_ONE) {
								$onDeleteCascade = (bool) $questionHelper->ask($input, $output, new ConfirmationQuestion('# <yellow>Add Cascade Delete on Database Level</yellow>? [<info>no</info>] ', false));
							}

							$relationData = new RelationData($relation, $phpType, $cleanType, $bidirectional, $cascadeAttributes, $onDeleteCascade);

						} else {
							$output->writeln('');
							$output->writeln(sprintf('<error>Error! Invalid Type!</error>'));
							$output->writeln('');
							continue;
						}
					}

					break;
				}

				if ($relationData === null) {
					$value = $questionHelper->ask($input, $output, new Question('# <yellow>Default Value</yellow>: '));
				} else {
					$value = null;
				}
				$output->writeln('');

				$properties[$name] = new DoctrineEntityProperty((string) $name, $type, $phpType, $doctrineType, $value, $relationData);

				$defineAnother = $questionHelper->ask($input, $output, new Question('# <blue>Define Another Property</blue>? [<info>yes</info>] '));
				if ($defineAnother === null || strtolower($defineAnother) === 'yes' || strtolower($defineAnother) === 'y') {
					$lazyName = null;
					continue;
				} else if (strtolower($defineAnother) !== 'no' && strtolower($defineAnother) !== 'n') {
					$lazyName = $defineAnother;
					continue;
				}

				break;
			}
		}

		$output->writeln('');
		$createDataFactory = $questionHelper->ask($input, $output, new ConfirmationQuestion('# <blue>Create <yellow>DataFactory</yellow> Class for Form Handling</blue>? [<info>yes</info>] ', true));
		$createEditMethod = $questionHelper->ask($input, $output, new ConfirmationQuestion('# <blue>Create <yellow>edit</yellow> and <yellow>getData</yellow> Method</blue>? [<info>yes</info>] ', true));
		$createGetAllMethod = $questionHelper->ask($input, $output, new ConfirmationQuestion('# <blue>Create <yellow>getAll</yellow> Method</blue>? [<info>yes</info>] ', true));
		$createDeleteMethod = $questionHelper->ask($input, $output, new ConfirmationQuestion('# <blue>Create <yellow>delete</yellow> Method</blue>? [<info>yes</info>] ', true));
		$output->writeln('');

		$getByMethods = [];
		while (true) {
			$getByMethods = $questionHelper->ask($input, $output, new Question('# <blue>Define Fields for <yellow>getBy<Field></yellow> Methods (e.g. "<yellow>email, slug</yellow>")</blue>: ', []));
			if (is_string($getByMethods)) {
				$getByMethods = explode(',', str_replace(' ', '', $getByMethods));
			}

			foreach ($getByMethods as $getByMethod) {
				if (!in_array($getByMethod, array_keys($properties))) {
					$output->writeln('');
					$output->writeln(sprintf('<error>Error! Property "%s" does not exist!</error>', $getByMethod));
					$output->writeln('');
					continue 2;
				}
			}

			break;
		}

		$getAllByMethods = [];
		while (true) {
			$getAllByMethods = $questionHelper->ask($input, $output, new Question('# <blue>Define Fields for <yellow>getAllBy<Field></yellow> Methods (e.g. "<yellow>author, type</yellow>")</blue>: ', []));
			if (is_string($getAllByMethods)) {
				$getAllByMethods = explode(',', str_replace(' ', '', $getAllByMethods));
			}

			foreach ($getAllByMethods as $getAllByMethod) {
				if (!in_array($getAllByMethod, array_keys($properties))) {
					$output->writeln('');
					$output->writeln(sprintf('<error>Error! Property "%s" does not exist!</error>', $getAllByMethod));
					$output->writeln('');
					continue 2;
				}
			}

			break;
		}

		$events = $questionHelper->ask($input, $output, new Question('# <blue>Define Events (for "<yellow>created, updated, deleted</yellow>" type "<yellow>all</yellow>")</blue>: ', []));
		if (is_string($events)) {
			if ($events === 'all') {
				$events = 'created, updated, deleted';
			}
			$events = explode(',', str_replace(' ', '', $events));
		}
		$output->writeln('');

		$askTraits = [];
		foreach ($this->config->model->entity->defaultTraits as $name => $class) {
			if ($class === null) {
				continue;
			}
			$askTraits[$name] = $class;
		}

		$traits = [];
		foreach ($askTraits as $name => $class) {
			if ($class === null) {
				continue;
			}
			if ($questionHelper->ask($input, $output, new ConfirmationQuestion(sprintf('# <blue>Use <yellow>%s</yellow> Trait</blue>? [<info>yes</info>] ', $name), true))) {
				$traits[$name] = $class;
			}
		}

		if (!empty($askTraits)) {
			$output->writeln('');
		}

		$input = new CreateModelResult(
			$namespace,
			$entityName,
			array_values($properties),
			$createGetAllMethod,
			$createEditMethod,
			$createDeleteMethod,
			false,
			$getByMethods,
			$getAllByMethods,
			$events,
			$traits
		);

		$filePath = function (string $namespace): string {
			$path = str_replace('\\', '/', $namespace);
			$path = substr($path, strlen(explode('/', $path)[0]));
			$path = $this->config->appDir . $path . '.php';

			if (!file_exists($directory = dirname($path))) {
				@mkdir($directory, 0777, true);
			}

			return $path;
		};

		$eventMap = [];
		foreach ($input->getEvents() as $event) {
			$eventMap[$filePath($input->getEventClass($event, true))] = $this->entityEventGenerator->create($input, $event);
		}

		$classMap = array_merge([
			$filePath($input->getEntityClass(true)) => $this->entityGenerator->create($input),
			$filePath($input->getDataClass(true)) => $this->entityDataGenerator->create($input),
			$filePath($input->getFactoryClass(true)) => $this->entityFactoryGenerator->create($input),
			$filePath($input->getRepositoryClass(true)) => $this->entityRepositoryGenerator->create($input),
			$filePath($input->getFacadeClass(true)) => $this->entityFacadeGenerator->create($input),
			$filePath($input->getNotFoundExceptionClass(true)) => $this->entityNotFoundExceptionGenerator->create($input)
		], $eventMap);

		if ($createDataFactory) {
			$classMap = $classMap + [$filePath($input->getDataFactoryClass(true)) => $this->entityDataFactoryGenerator->create($input)];
		}

		$classMap = array_map(fn($content) => preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\r\n\r\n", $content), $classMap);

		foreach ($classMap as $location => $content) {
			file_put_contents($location, $content);
		}

		$output->writeln('Files created:');
		foreach ($classMap as $file => $class) {
			$output->writeln(sprintf('<info>%s</info>', $file));
		}

		return 1;
	}
}
