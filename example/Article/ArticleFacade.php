<?php

declare(strict_types=1);

namespace Test\Article;

use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\Article\Event\ArticleCreatedEvent;
use Test\Article\Event\ArticleDeletedEvent;
use Test\Article\Event\ArticleUpdatedEvent;
use Test\Article\Exception\ArticleNotFoundException;

final class ArticleFacade extends ArticleRepository
{
	public function __construct(
		private ArticleFactory $articleFactory,
		private EntityManagerInterface $entityManager,
		private EventDispatcherInterface $eventDispatcher
	) {
		parent::__construct($entityManager);
	}

	public function create(ArticleData $data): Article
	{
		$article = $this->articleFactory->create($data);

		$this->entityManager->persist($article);
		$this->entityManager->flush();

		$this->eventDispatcher->dispatch(new ArticleCreatedEvent($article));

		return $article;
	}

	/**
	 * @throws ArticleNotFoundException
	 */
	public function edit(UuidInterface $id, ArticleData $data): Article
	{
		$article = $this->get($id);

		$article->edit($data);
		$this->entityManager->flush();

		$this->eventDispatcher->dispatch(new ArticleUpdatedEvent($article));

		return $article;
	}

	/**
	 * @throws ArticleNotFoundException
	 */
	public function delete(UuidInterface $id): void
	{
		$article = $this->get($id);

		$this->eventDispatcher->dispatch(new ArticleDeletedEvent($article));

		$this->entityManager->remove($article);
		$this->entityManager->flush();
	}
}
