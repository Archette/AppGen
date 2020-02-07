<?php

declare(strict_types=1);

namespace Archette\AppGen\Generator;

use Archette\AppGen\Command\Model\CreateModelResult;
use Archette\AppGen\Config\AppGenConfig;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Type;
use Nette\Utils\Strings;

class EntityGenerator
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

		$namespace->addUse('Doctrine\ORM\Mapping', 'ORM');
		$namespace->addUse('Ramsey\Uuid\UuidInterface');
		foreach ($input->getTraits() as $name => $class) {
			$namespace->addUse($class);
		}

		$tableName = ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $input->getEntityClass())), '_');
		$class = new ClassType($input->getEntityClass());

		$class->addComment('@ORM\Entity')
			->addComment('@ORM\HasLifecycleCallbacks')
			->addComment('@ORM\Table(name="' . $tableName . '")');

		$id = $class->addProperty('id');
		$id->setType($this->config->model->entity->idType === 'uuid' || $this->config->model->entity->idType === 'uuid_binary' ? 'Ramsey\Uuid\UuidInterface' : Type::INT)
			->setVisibility(ClassType::VISIBILITY_PRIVATE)
			->addComment('@ORM\Id')
			->addComment('@ORM\Column(type="' . $this->config->model->entity->idType . '", unique=true)');

		if ($this->config->model->entity->idType === 'integer') {
			$id->addComment('@ORM\GeneratedValue(strategy="IDENTITY")');
		}

		foreach ($input->getEntityProperties() as $property) {
			$doctrineProperty = $class->addProperty($property->getName())
				->setVisibility(ClassType::VISIBILITY_PRIVATE);

			if ($relation = $property->getRelation()) {
				if ($relation->getType() === $relation::RELATION_MANY_TO_ONE || $relation->getType() === $relation::RELATION_ONE_TO_ONE) {
					$doctrineProperty->setType($property->getType())
						->setNullable($property->isNullable());

				} else {
					$doctrineProperty->addComment(sprintf('@var %s[]', $relation->getTargetClassName()));
					$namespace->addUse($relation->getTargetClass());
				}

				$doctrineProperty->addComment(sprintf('@ORM\%s(targetEntity="\%s"%s%s%s)',
					$relation->getType(),
					$relation->getTargetClass(),
					$relation->isBiDirectional() && ($relation->getType() === $relation::RELATION_ONE_TO_MANY || $relation->getType() === $relation::RELATION_ONE_TO_ONE) ? ', mappedBy="' . $tableName . '"' : '',
					$relation->isBiDirectional() && ($relation->getType() === $relation::RELATION_MANY_TO_ONE || $relation->getType() === $relation::RELATION_MANY_TO_MANY) ? ', inversedBy="' . $tableName . '"' : '',
					$relation->getCascadeString() !== null ? ', cascade={' . $relation->getCascadeString() .'}' : ''
				));

				if ($relation->isOnDeleteCascade()) {
					$doctrineProperty->addComment('@ORM\JoinColumn(onDelete="CASCADE")');
				}

			} else {
				$doctrineProperty->setType($property->getType())
					->setNullable($property->isNullable());

				$doctrineProperty->addComment(sprintf('@ORM\Column(type="%s"%s%s%s)',
					$property->getDoctrineType(),
					$property->getDoctrineMaxLength() !== null ? ', length=' . $property->getDoctrineMaxLength() : '',
					$property->isNullable() ? ', nullable=true' : '',
					$property->isUnique() ? ', unique=true' : ''
				));
			}

			if ($property->getDefaultValue() !== null || $property->isNullable()) {
				$doctrineProperty->setValue($property->getDefaultValue());
			}
		}

		foreach ($input->getTraits() as $name => $className) {
			$class->addTrait($className);
		}

		$constructor = $class->addMethod('__construct');

		if (Strings::contains($this->config->model->entity->idType, 'uuid')) {
			$constructor->addParameter('id')
				->setType('Ramsey\Uuid\UuidInterface');
			$constructor->addBody('$this->id = $id;');
		}

		foreach ($input->getEntityProperties() as $property) {
			if ($relation = $property->getRelation()) {
				$namespace->addUse($relation->getTargetClass());

				if ($relation->getType() === $relation::RELATION_MANY_TO_ONE || $relation->getType() === $relation::RELATION_ONE_TO_ONE) {
					continue;
				}

				$constructor->addBody(sprintf('$this->%s = new ArrayCollection();', $property->getName()));

				if (!in_array($arrayCollection = 'Doctrine\Common\Collections\ArrayCollection', $namespace->getUses())) {
					$namespace->addUse($arrayCollection);
				}
			}
		}

		$constructor->addParameter('data')
			->setType($input->getDataClass(true));

		if ($input->createEditMethod()) {
			$constructor->addBody('$this->edit($data);');

			$edit = $class->addMethod('edit')
				->setReturnType(Type::VOID);

			$edit->addParameter('data')
				->setType($input->getDataClass(true));

			$getData = $class->addMethod('getData')
				->setReturnType($input->getDataClass(true));

			$getData->addBody(sprintf('$data = new %s();', $input->getDataClass()));

			foreach ($input->getEntityProperties() as $property) {
				if ($property->getName() !== 'updatedAt' && $property->getName() !== 'createdAt') {
					$edit->addBody(sprintf('$this->%1$s = $data->%1$s;', $property->getName()));
					$getData->addBody(sprintf('$data->%1$s = $this->%1$s;', $property->getName()));
				}
			}

			$getData->addBody('');
			$getData->addBody('return $data;');
		}

		$class->addMethod('getId')
			->setReturnType(Strings::contains($this->config->model->entity->idType, 'uuid') ? 'Ramsey\Uuid\UuidInterface' : Type::INT)
			->setBody('return $this->id;');

		foreach ($input->getEntityProperties() as $property) {
			if ($relation = $property->getRelation()) {
				if ($relation->getType() === $relation::RELATION_ONE_TO_MANY || $relation->getType() === $relation::RELATION_MANY_TO_MANY) {
					$class->addMethod('get' . Strings::firstUpper($property->getName()))
						->setBody(sprintf('return $this->%s;', $property->getName()))
						->addComment(sprintf('@return %s[]', $relation->getTargetClassName()));
					continue;
				}
			}
			$class->addMethod(($property->isBoolean() ? 'is' : 'get') . Strings::firstUpper($property->getName()))
				->setReturnType($property->getType())
				->setReturnNullable($property->isNullable())
				->setBody(sprintf('return $this->%s;', $property->getName()));
			if ($this->config->model->entity->createSetters) {
				$class->addMethod('set'. Strings::firstUpper($property->getName()))
					->setReturnType(Type::VOID)
					->setBody(sprintf('$this->%1$s = $%1$s;', $property->getName()))
					->addParameter($property->getName())
					->setType($property->getType());
			}
		}

		$namespace->add($class);

		return (string) $file;
	}
}
