<?php

declare(strict_types=1);

namespace Archette\AppGen\Generator;

use Archette\AppGen\Command\Model\CreateModelResult;
use Archette\AppGen\Config\AppGenConfig;
use Archette\AppGen\Generator\Property\DoctrineEntityProperty;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Type;
use Nette\Utils\Strings;

class EntityRepositoryGenerator
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
		if (in_array('DateTime', array_merge(array_values($input->getGetByMethods()), array_values($input->getGetAllByMethods())))) {
			$namespace->addUse('DateTime');
		}
		$namespace->addUse('Doctrine\ORM\EntityManagerInterface');
		$namespace->addUse('Doctrine\ORM\QueryBuilder');
		if (Strings::contains($this->config->model->entity->idType, 'uuid')) {
			$namespace->addUse('Ramsey\Uuid\UuidInterface');
		}
		$namespace->addUse($input->getNotFoundExceptionClass(true));
		$namespace->addUse('Doctrine\Persistence\ObjectRepository');

		$class = new ClassType($input->getRepositoryClass());
		$class->setAbstract();
		
		$constructor = $class->addMethod('__construct');
		$constructor->addPromotedParameter('entityManager')
			->setType('Doctrine\ORM\EntityManagerInterface')
			->setPrivate();

		$class->addMethod('getRepository')
			->setVisibility(ClassType::VISIBILITY_PRIVATE)
			->setReturnType('Doctrine\Persistence\ObjectRepository')
			->addBody('return $this->entityManager->getRepository(' . $input->getEntityClass() . '::class);');

		$get = $class->addMethod('get');
		$get->addParameter('id')
			->setType(Strings::contains($this->config->model->entity->idType, 'uuid') ? 'Ramsey\Uuid\UuidInterface' : Type::INT);
		$get->setReturnType($input->getEntityClass(true));
		$get->setVisibility(ClassType::VISIBILITY_PUBLIC)
			->addComment('@throws ' . $input->getNotFoundExceptionClass());

		foreach ($this->createGetByBody(Strings::firstLower($input->getEntityClass()), 'id', 'id') as $code) {
			$get->addBody($code);
		}

		$commonGetByMethod = function (DoctrineEntityProperty $property, string $parameterName, bool $all = false) use ($class): Method {
			$method = $class->addMethod('get' . ($all ? 'All' : '') . 'By' . Strings::firstUpper($property->getName()));
			$method->addParameter($parameterName)
				->setType($property->getRelation() === null ? $property->getType() : (Strings::contains($this->config->model->entity->idType, 'uuid') ? 'Ramsey\Uuid\UuidInterface' : Type::INT));

			return $method;
		};

		foreach ($input->getGetByMethods() as $property) {
			$parameterName = Strings::firstLower($property->getName());
			if ($property->getRelation() !== null) {
				$parameterName = $parameterName . 'Id';
			}

			$method = $commonGetByMethod($property, $parameterName);
			$method->setReturnType($input->getEntityClass(true));
			$method->setVisibility(ClassType::VISIBILITY_PUBLIC)
				->addComment('@throws ' . $input->getNotFoundExceptionClass());

			foreach ($this->createGetByBody(Strings::firstLower($input->getEntityClass()), Strings::firstLower($property->getName()), $parameterName) as $code) {
				$method->addBody($code);
			}
		}

		foreach ($input->getGetAllByMethods() as $property) {
			$parameterName = Strings::firstLower($property->getName());
			if ($property->getRelation() !== null) {
				$parameterName = $parameterName . 'Id';
			}

			$method = $commonGetByMethod($property, $parameterName, true);
			$method->setReturnType(Type::ARRAY);
			$method->setVisibility(ClassType::VISIBILITY_PUBLIC)
				->addComment('@return ' . $input->getEntityClass() . '[]');

			foreach ($this->createGetAllByBody(Strings::firstLower($property->getName()), $parameterName) as $code) {
				$method->addBody($code);
			}
		}

		if ($input->isCreateGetAllMethod()) {
			$class->addMethod('getAll')
				->setReturnType(Type::ARRAY)
				->setVisibility(ClassType::VISIBILITY_PUBLIC)
				->addComment('@return ' . $input->getEntityClass() . '[]')
				->addBody('return $this->getQueryBuilderForAll()->getQuery()->execute();');
		}

		$class->addMethod('getQueryBuilderForAll')
			->setReturnType('Doctrine\ORM\QueryBuilder')
			->setVisibility(ClassType::VISIBILITY_PRIVATE)
			->addBody('return $this->getRepository()->createQueryBuilder(\'e\');');

		$class->addMethod('getQueryBuilderForDataGrid')
			->setReturnType('Doctrine\ORM\QueryBuilder')
			->setVisibility(ClassType::VISIBILITY_PUBLIC)
			->addBody('return $this->getQueryBuilderForAll();');

		$namespace->add($class);

		return (string) $file;
	}

	private function createGetByBody(string $entityName, string $columnName, string $fieldName): array
	{
		$code = [];

		$code[] = '/** @var ' . Strings::firstUpper($entityName) . ' $' . $entityName . ' */';
		$code[] = '$' . $entityName . ' = $this->getRepository()->findOneBy([';
		$code[] = '	\'' . $columnName . '\' => $' . $fieldName;
		$code[] = ']);';
		$code[] = '';
		$code[] = 'if ($' . $entityName . ' === null) {';
		$code[] = '	throw new ' . Strings::firstUpper($entityName) . 'NotFoundException();';
		$code[] = '}';
		$code[] = '';
		$code[] = 'return $' . $entityName . ';';

		return $code;
	}

	private function createGetAllByBody(string $columnName, string $fieldName): array
	{
		$code = [];

		$code[] = 'return $this->getRepository()->findBy([';
		$code[] = '	\'' . $columnName . '\' => $' . $fieldName;
		$code[] = ']);';

		return $code;
	}
}
