<?php

declare(strict_types=1);

namespace Archette\AppGen\Generator;

use Archette\AppGen\Command\Model\CreateModelResult;
use Archette\AppGen\Config\AppGenConfig;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\Utils\Strings;

class EntityDataGenerator
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

		$class = new ClassType($input->getDataClass());
		$class->setFinal();

		foreach ($input->getEntityProperties() as $property) {
			$dataProperty = $class->addProperty($property->getName())
				->setVisibility(ClassType::VISIBILITY_PUBLIC);

			$relation = $property->getRelation();
			if ($relation !== null) {
				$namespace->addUse($relation->getTargetClass());
			}

			if (Strings::startsWith($property->getType(), '\\')) {
				$namespace->addUse($property->getType());
			}

			if ($relation !== null && ($relation->getType() === $relation::RELATION_ONE_TO_MANY || $relation->getType() === $relation::RELATION_MANY_TO_MANY)) {
				$dataProperty->setValue([])
					->addComment(sprintf('@var %s[]', $relation->getTargetClassName()));
			} else {
				$dataProperty->setType($property->getType())
					->setNullable($property->isNullable());

				if ($property->getDefaultValue() !== null || $property->isNullable()) {
					$dataProperty->setValue($property->getDefaultValue());
				}
			}
		}

		$namespace->add($class);

		return str_replace("\n\n\t", "\n\t", (string) $file);
	}
}
