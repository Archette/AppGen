<?php

declare(strict_types=1);

namespace Test\Article;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Test\TimestampableTrait;

/**
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="article")
 */
class Article
{
	use TimestampableTrait;

	/**
	 * @ORM\Id
	 * @ORM\Column(type="uuid_binary", unique=true)
	 */
	private UuidInterface $id;

	/** @ORM\Column(type="string", length=63, unique=true) */
	private string $slug;

	/** @ORM\Column(type="string", length=127) */
	private string $title;

	/** @ORM\Column(type="text") */
	private string $content = '';

	public function __construct(UuidInterface $id, ArticleData $data)
	{
		$this->id = $id;
		$this->edit($data);
	}

	public function edit(ArticleData $data): void
	{
		$this->slug = $data->slug;
		$this->title = $data->title;
		$this->content = $data->content;
	}

	public function getData(): ArticleData
	{
		$data = new ArticleData();
		$data->slug = $this->slug;
		$data->title = $this->title;
		$data->content = $this->content;

		return $data;
	}

	public function getId(): UuidInterface
	{
		return $this->id;
	}

	public function getSlug(): string
	{
		return $this->slug;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function getContent(): string
	{
		return $this->content;
	}
}
