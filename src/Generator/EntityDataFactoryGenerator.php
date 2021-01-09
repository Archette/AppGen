<?php

declare(strict_types=1);

namespace Archette\AppGen\Generator;

use Archette\AppGen\Command\Model\CreateModelResult;
use Archette\AppGen\Config\AppGenConfig;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Type;
use Nette\Utils\Strings;

class EntityDataFactoryGenerator
{
	private AppGenConfig $config;

	public function __construct(
		AppGenConfig $config
	) {
		$this->config = $config;
	}

	public function create(CreateModelResult $input): string
	{
		$file = new PhpFile();

		$file->setStrictTypes();

		$namespace = $file->addNamespace($input->getNamespace());

		$class = new ClassType($input->getDataFactoryClass());
		$class->setFinal();

		foreach ($input->getEntityProperties() as $p) {
			if ($p->getRelation() !== null) {
				$constructor = $class->addMethod('__construct');
				if (Strings::contains($this->config->model->entity->idType, 'uuid')) {
					$namespace->addUse('Ramsey\Uuid\Uuid');
				}
				foreach ($input->getEntityProperties() as $property) {
					$relation = $property->getRelation();
					if ($relation !== null) {
						if (!in_array($relation->getTargetClass() . 'Facade', $namespace->getUses())) {
							$namespace->addUse($relation->getTargetClass() . 'Facade');
							$constructor->addPromotedParameter(Strings::firstLower($relation->getTargetClassName()) . 'Facade')
								->setType($relation->getTargetClass() . 'Facade')
								->setPrivate();
						}
					}
				}
				break;
			}
		}

		$create = $class->addMethod('createFromFormData')
			->setReturnType($input->getDataClass(true));
		$create->addParameter('formData')
			->setType(Type::ARRAY);
		$create->addBody(sprintf('$data = new %s();', $input->getDataClass()));

		foreach ($input->getEntityProperties() as $property) {
			if ($property->getRelation() === null) {
				$create->addBody(sprintf('$data->%1$s = $formData[\'%1$s\'];', $property->getName()));
			}
		}

		foreach ($input->getEntityProperties() as $property) {
			$relation = $property->getRelation();
			if ($relation !== null) {
				if ($relation->getType() === $relation::RELATION_MANY_TO_ONE || $relation->getType() === $relation::RELATION_ONE_TO_ONE) {
					if (Strings::contains($this->config->model->entity->idType, 'uuid')) {
						$create->addBody(sprintf('$data->%1$s = $this->%2$sFacade->get(Uuid::fromString($formData[\'%1$s\']));', $property->getName(), Strings::firstLower($relation->getTargetClassName())));
					} else {
						$create->addBody(sprintf('$data->%1$s = $this->%2$sFacade->get($formData[\'%1$s\']);', $property->getName(), Strings::firstLower($relation->getTargetClassName())));
					}
				}
			}
		}

		foreach ($input->getEntityProperties() as $property) {
			$relation = $property->getRelation();
			if ($relation !== null) {
				if ($relation->getType() === $relation::RELATION_ONE_TO_MANY || $relation->getType() === $relation::RELATION_MANY_TO_MANY) {
					$create->addBody('');
					$create->addBody(sprintf('foreach ($formData[\'%1$s\'] as $identifier) {', $property->getName()));
					if (Strings::contains($this->config->model->entity->idType, 'uuid')) {
						$create->addBody(sprintf('	$data->%1$s[] = $this->%2$sFacade->get(Uuid::fromString($identifier));', $property->getName(), Strings::firstLower($relation->getTargetClassName())));
					} else {
						$create->addBody(sprintf('	$data->%1$s[] = $this->%2$sFacade->get($identifier);', $property->getName(), Strings::firstLower($relation->getTargetClassName())));
					}
					$create->addBody('}');
				}
			}
		}

		$create->addBody('');
		$create->addBody('return $data;');

		$namespace->add($class);

		return str_replace("\n\n\tprivate", "\n\tprivate", (string) $file);
	}
}
