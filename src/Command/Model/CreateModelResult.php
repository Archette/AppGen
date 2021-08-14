<?php

declare(strict_types=1);

namespace Archette\AppGen\Command\Model;

use Archette\AppGen\Generator\Property\DoctrineEntityProperty;
use Nette\Utils\Strings;

class CreateModelResult
{
	private string $namespace;
	private string $entityClass;
	private bool $createGetAllMethod;
	private bool $createEditMethod;
	private bool $createDeleteMethod;
	private bool $createSoftDeleteMethod;
	private array $events;
	private array $traits;

	/** @var DoctrineEntityProperty[] */
	private array $getByMethods = [];

	/** @var DoctrineEntityProperty[] */
	private array $getAllByMethods = [];

	/** @var DoctrineEntityProperty[] */
	private array $entityProperties;

	public function __construct(
		string $namespace,
		string $entity,
		array $entityProperties,
		bool $createGetAllMethod,
		bool $createEditMethod,
		bool $createDeleteMethod,
		bool $createSoftDeleteMethod,
		array $getByMethods = [],
		array $getAllByMethods = [],
		array $events = [],
		array $traits = []
	) {
		$this->namespace = $namespace;
		$this->entityClass = $entity;
		$this->createGetAllMethod = $createGetAllMethod;
		$this->createEditMethod = $createEditMethod;
		$this->createDeleteMethod = $createDeleteMethod;
		$this->createSoftDeleteMethod = $createSoftDeleteMethod;
		$this->events = $events;
		$this->traits = $traits;
		$this->entityProperties = $entityProperties;

		foreach ($this->entityProperties as $property) {
			if (in_array($property->getName(), $getByMethods, true)) {
				$this->getByMethods[] = $property;
			}
			if (in_array($property->getName(), $getAllByMethods, true)) {
				$this->getAllByMethods[] = $property;
			}
		}
	}

	public function getNamespace(): string
	{
		return $this->namespace;
	}

	public function getEventNamespace(): string
	{
		return $this->namespace . '\\Event';
	}

	public function getExceptionNamespace(): string
	{
		return $this->namespace . '\\Exception';
	}

	public function getEntityClass(bool $withNamespace = false): string
	{
		return $withNamespace ? $this->namespace . '\\' . $this->entityClass : $this->entityClass;
	}

	public function getDataClass(bool $withNamespace = false): string
	{
		return $this->getEntityClass($withNamespace) . 'Data';
	}

	public function getDataFactoryClass(bool $withNamespace = false): string
	{
		return $this->getDataClass($withNamespace) . 'Factory';
	}

	public function getFactoryClass(bool $withNamespace = false): string
	{
		return $this->getEntityClass($withNamespace) . 'Factory';
	}

	public function getRepositoryClass(bool $withNamespace = false): string
	{
		return $this->getEntityClass($withNamespace) . 'Repository';
	}

	public function getFacadeClass(bool $withNamespace = false): string
	{
		return $this->getEntityClass($withNamespace) . 'Facade';
	}

	public function getNotFoundExceptionClass(bool $withNamespace = false): string
	{
		return ($withNamespace ? $this->getExceptionNamespace() . '\\' . $this->entityClass : $this->entityClass) . 'NotFoundException';
	}

	public function getEventClass(string $eventName, bool $withNamespace = false): ?string
	{
		return in_array($eventName, $this->events) ? (($withNamespace ? $this->getEventNamespace() . '\\' . $this->entityClass : $this->entityClass) . Strings::firstUpper($eventName) . 'Event') : null;
	}

	public function isCreateGetAllMethod(): bool
	{
		return $this->createGetAllMethod;
	}

	/** @return DoctrineEntityProperty[] */
	public function getGetByMethods(): array
	{
		return $this->getByMethods;
	}

	/** @return DoctrineEntityProperty[] */
	public function getGetAllByMethods(): array
	{
		return $this->getAllByMethods;
	}

	public function getEvents(): array
	{
		return $this->events;
	}

	public function hasEvents(): bool
	{
		return !empty($this->events);
	}

	public function createDeleteMethod(): bool
	{
		return $this->createDeleteMethod;
	}

	public function createEditMethod(): bool
	{
		return $this->createEditMethod;
	}

	public function createSoftDeleteMethod(): bool
	{
		return $this->createSoftDeleteMethod;
	}

	/** @return DoctrineEntityProperty[] */
	public function getEntityProperties(): array
	{
		return $this->entityProperties;
	}

	public function hasProperty(string $name): bool
	{
		foreach ($this->entityProperties as $property) {
			if ($property->getName() === $name) {
				return true;
			}
		}

		return false;
	}

	public function getTraits(): array
	{
		return $this->traits;
	}
}
