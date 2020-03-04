<?php

declare(strict_types=1);

namespace Archette\AppGen\Helper;

use Archette\AppGen\Config\AppGenConfig;
use Archette\AppGen\Helper\Exception\TypeNotFoundException;
use Nette\Utils\Finder;
use SplFileInfo;

class ClassHelper
{
	private AppGenConfig $config;

	public function __construct(AppGenConfig $config)
	{
		$this->config = $config;
	}

	public function resolveNamespace(string $className): ?string
	{
		/** @var SplFileInfo $file */
		foreach (Finder::findFiles(sprintf('%s.php', $className))->in(getcwd() . DIRECTORY_SEPARATOR . $this->config->appDir)->limitDepth(100) as $key => $file) {
			if (preg_match('/namespace\s(.*);/', file_get_contents($key), $m)) {
				return $m[1];
			}
		}
		
		/** @var SplFileInfo $file */
		foreach (Finder::findFiles(sprintf('%s.php', $className))->in(getcwd() . DIRECTORY_SEPARATOR . 'vendor')->limitDepth(100) as $key => $file) {
			if (preg_match('/namespace\s(.*);/', file_get_contents($key), $m)) {
				return $m[1];
			}
		}

		return null;
	}

	/**
	 * @throws TypeNotFoundException
	 */
	public function formatPhpType(string $type): ?string
	{
		$type = strtolower(explode(':', explode('|', trim(explode(' ', $type)[0], '?'))[0])[0]);

		if (in_array($type, ['int', 'integer', 'smallint', 'bigint'])) {
			return 'int';
		}

		if (in_array($type, ['float', 'decimal'])) {
			return 'float';
		}

		if (in_array($type, ['string', 'binary', 'blob', 'text'])) {
			return 'string';
		}

		if (in_array($type, ['bool', 'boolean'])) {
			return 'bool';
		}

		if (in_array($type, ['array', 'json_array'])) {
			return 'array';
		}

		if (in_array($type, ['time', 'date', 'datetime', 'datetimez'])) {
			return '\DateTime';
		}

		if (in_array($type, ['uuid', 'uuid_binary'])) {
			return '\Ramsey\Uuid\Uuid';
		}

		throw new TypeNotFoundException(sprintf('Type %s is not valid PHP type', $type));
	}

	/**
	 * @throws TypeNotFoundException
	 */
	public function formatDoctrineType(string $type): ?string
	{
		$type = strtolower(explode(':', explode('|', trim(explode(' ', $type)[0], '?'))[0])[0]);

		if ($type === 'bool') {
			$type = 'boolean';
		}

		if ($type === 'int') {
			$type = 'integer';
		}

		if (!in_array($type, [
			'integer',
			'smallint',
			'bigint',
			'float',
			'decimal',
			'string',
			'binary',
			'blob',
			'text',
			'boolean',
			'array',
			'json_array',
			'time',
			'date',
			'datetime',
			'datetimez',
			'uuid',
			'uuid_binary'
		])) {
			throw new TypeNotFoundException(sprintf('Type %s is not valid Doctrine ORM type', $type));
		}

		return $type;
	}
}
