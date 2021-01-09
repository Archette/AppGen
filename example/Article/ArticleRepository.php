<?php

declare(strict_types=1);

namespace Test\Article;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;
use Test\Article\Exception\ArticleNotFoundException;

abstract class ArticleRepository
{
	public function __construct(
		private EntityManagerInterface $entityManager
	) {}

	/**
	 * @return EntityRepository|ObjectRepository
	 */
	private function getRepository()
	{
		return $this->entityManager->getRepository(Article::class);
	}

	/**
	 * @throws ArticleNotFoundException
	 */
	public function get(UuidInterface $id): Article
	{
		/** @var Article $article */
		$article = $this->getRepository()->findOneBy([
			'id' => $id
		]);

		if ($article === null) {
			throw new ArticleNotFoundException(sprintf('Article with id "%s" not found.', $id));
		}

		return $article;
	}

	/**
	 * @throws ArticleNotFoundException
	 */
	public function getBySlug(string $slug): Article
	{
		/** @var Article $article */
		$article = $this->getRepository()->findOneBy([
			'slug' => $slug
		]);

		if ($article === null) {
			throw new ArticleNotFoundException(sprintf('Article with slug "%s" not found.', $slug));
		}

		return $article;
	}

	/**
	 * @return Article[]
	 */
	public function getAll(): array
	{
		return $this->getQueryBuilderForAll()->getQuery()->execute();
	}

	private function getQueryBuilderForAll(): QueryBuilder
	{
		return $this->getRepository()->createQueryBuilder('e');
	}
}
