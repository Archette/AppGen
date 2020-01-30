<?php

declare(strict_types=1);

namespace Archette\AppGen\Generator;

use Archette\AppGen\Command\Model\CreateModelResult;
use Archette\AppGen\Config\AppGenConfig;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Type;
use Nette\Utils\Strings;

class EntityFacadeGenerator
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
		$namespace->addUse($input->getFactoryClass(true));
		$namespace->addUse('\Doctrine\ORM\EntityManagerInterface');
		if ($input->hasEvents()) {
			$namespace->addUse('\Symfony\Component\EventDispatcher\EventDispatcherInterface');
		}
		if (Strings::contains($this->config->model->entity->idType, 'uuid')) {
			$namespace->addUse('Ramsey\Uuid\UuidInterface');
		}
		if ($createdEvent = $input->getEventClass('created', true)) {
			$namespace->addUse($createdEvent);
		}
		if ($updatedEvent = $input->getEventClass('updated', true)) {
			$namespace->addUse($updatedEvent);
		}
		if ($deletedEvent = $input->getEventClass('deleted', true)) {
			$namespace->addUse($deletedEvent);
		}
		if ($input->createEditMethod() || $input->createDeleteMethod()) {
			$namespace->addUse($input->getNotFoundExceptionClass(true));
		}

		$class = new ClassType($input->getFacadeClass());
		$class->setFinal();
		$class->setExtends($input->getRepositoryClass(true));

		$constructor = $class->addMethod('__construct');

		$factoryProperty = $class->addProperty(Strings::firstLower($input->getFactoryClass()))
			->setType($input->getFactoryClass(true))
			->setVisibility(ClassType::VISIBILITY_PRIVATE);

		$constructor->addParameter($factoryProperty->getName())
			->setType($factoryProperty->getType());

		$entityManagerProperty = $class->addProperty('entityManager')
			->setType('\Doctrine\ORM\EntityManagerInterface')
			->setVisibility(ClassType::VISIBILITY_PRIVATE);

		$constructor->addParameter($entityManagerProperty->getName())
			->setType($entityManagerProperty->getType());

		if ($input->hasEvents()) {
			$eventDispatcherProperty = $class->addProperty('eventDispatcher')
				->setType('\Symfony\Component\EventDispatcher\EventDispatcherInterface')
				->setVisibility(ClassType::VISIBILITY_PRIVATE);

			$constructor->addParameter($eventDispatcherProperty->getName())
				->setType($eventDispatcherProperty->getType());
		}

		$constructor->addBody(sprintf('parent::__construct($%s);', $entityManagerProperty->getName()));
		$constructor->addBody(sprintf('$this->%1$s = $%1$s;', $factoryProperty->getName()));
		$constructor->addBody(sprintf('$this->%1$s = $%1$s;', $entityManagerProperty->getName()));
		if (isset($eventDispatcherProperty)) {
			$constructor->addBody(sprintf('$this->%1$s = $%1$s;', $eventDispatcherProperty->getName()));
		}

		$create = $class->addMethod('create')
			->setReturnType($input->getEntityClass(true))
			->setVisibility(ClassType::VISIBILITY_PUBLIC);

		$create->addParameter('data')
			->setType($input->getDataClass(true));

		$create->addBody(sprintf('$%s = $this->%s->create($data);', Strings::firstLower($input->getEntityClass()), $factoryProperty->getName()));
		$create->addBody('');
		$create->addBody(sprintf('$this->%s->persist($%s);', $entityManagerProperty->getName(), Strings::firstLower($input->getEntityClass())));
		$create->addBody(sprintf('$this->%s->flush();', $entityManagerProperty->getName()));
		$create->addBody('');
		if (isset($eventDispatcherProperty) && $createdEvent = $input->getEventClass('created')) {
			$create->addBody(sprintf('$this->%s->dispatch(new %s($%s));', $eventDispatcherProperty->getName(), $createdEvent, Strings::firstLower($input->getEntityClass())));
			$create->addBody('');
		}
		$create->addBody(sprintf('return $%s;', Strings::firstLower($input->getEntityClass())));

		if ($input->createEditMethod()) {
			$edit = $class->addMethod('edit')
				->setReturnType($input->getEntityClass(true))
				->setVisibility(ClassType::VISIBILITY_PUBLIC);

			$edit->addComment(sprintf('@throws %s', $input->getNotFoundExceptionClass()));

			$edit->addParameter('id')
				->setType(Strings::contains($this->config->model->entity->idType, 'uuid') ? 'Ramsey\Uuid\UuidInterface' : 'int');

			$edit->addParameter('data')
				->setType($input->getDataClass(true));

			$edit->addBody(sprintf('$%s = $this->get($id);', Strings::firstLower($input->getEntityClass())));
			$edit->addBody('');
			$edit->addBody(sprintf('$%s->edit($data);', Strings::firstLower($input->getEntityClass())));
			$edit->addBody(sprintf('$this->%s->flush();', $entityManagerProperty->getName()));
			$edit->addBody('');
			if (isset($eventDispatcherProperty) && $updatedEvent = $input->getEventClass('updated')) {
				$edit->addBody(sprintf('$this->%s->dispatch(new %s($%s));', $eventDispatcherProperty->getName(), $updatedEvent, Strings::firstLower($input->getEntityClass())));
				$edit->addBody('');
			}
			$edit->addBody(sprintf('return $%s;', Strings::firstLower($input->getEntityClass())));
		}

		if ($input->createDeleteMethod()) {
			$delete = $class->addMethod('delete')
				->setReturnType(Type::VOID)
				->setVisibility(ClassType::VISIBILITY_PUBLIC);

			$delete->addComment(sprintf('@throws %s', $input->getNotFoundExceptionClass()));

			$delete->addParameter('id')
				->setType(Strings::contains($this->config->model->entity->idType, 'uuid') ? 'Ramsey\Uuid\UuidInterface' : Type::INT);

			$delete->addBody(sprintf('$%s = $this->get($id);', Strings::firstLower($input->getEntityClass())));
			$delete->addBody('');
			if (isset($eventDispatcherProperty) && $deletedEvent = $input->getEventClass('deleted')) {
				$delete->addBody(sprintf('$this->%s->dispatch(new %s($%s));', $eventDispatcherProperty->getName(), $deletedEvent, Strings::firstLower($input->getEntityClass())));
				$delete->addBody('');
			}
			$delete->addBody(sprintf('$this->%s->remove($%s);', $entityManagerProperty->getName(), Strings::firstLower($input->getEntityClass())));
			$delete->addBody(sprintf('$this->%s->flush();', $entityManagerProperty->getName()));
		}

		$namespace->add($class);

		return str_replace("\n\n\tprivate", "\n\tprivate", (string) $file);
	}
}
