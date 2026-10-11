<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PostStatus;
use App\Enum\PostType;
use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_POST_SLUG', columns: ['slug'])]
#[ORM\Index(name: 'idx_post_status_published_at', columns: ['status', 'published_at'])]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false)]
class Post
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    public const EXCERPT_MAX_LENGTH = 500;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $excerpt = null;

    #[ORM\Column(length: 20, enumType: PostType::class)]
    private PostType $type = PostType::Article;

    #[ORM\Column(length: 20, enumType: PostStatus::class, options: ['default' => 'draft'])]
    private PostStatus $status = PostStatus::Draft;

    #[ORM\Column(options: ['default' => false])]
    private bool $isFeatured = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isSticky = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $featuredImage = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $viewCount = 0;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $programmingLanguage = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $publishedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    /** @var Collection<int, Category> */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'posts')]
    private Collection $categories;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'posts')]
    private Collection $tags;

    /** @var Collection<int, PostMeta> */
    #[ORM\OneToMany(targetEntity: PostMeta::class, mappedBy: 'post', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $meta;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'post', orphanRemoval: true)]
    private Collection $comments;

    /** @var Collection<int, PostRevision> */
    #[ORM\OneToMany(targetEntity: PostRevision::class, mappedBy: 'post', orphanRemoval: true, cascade: ['remove'])]
    private Collection $revisions;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->meta = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->revisions = new ArrayCollection();
        $this->publishedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setExcerpt(?string $excerpt): static
    {
        if ($excerpt === null || trim($excerpt) === '') {
            $this->excerpt = null;

            return $this;
        }

        $text = trim(strip_tags($excerpt));
        $this->excerpt = mb_substr($text, 0, self::EXCERPT_MAX_LENGTH);

        return $this;
    }

    public function getType(): PostType
    {
        return $this->type;
    }

    public function setType(PostType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): PostStatus
    {
        return $this->status;
    }

    public function setStatus(PostStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;

        return $this;
    }

    public function isSticky(): bool
    {
        return $this->isSticky;
    }

    public function setIsSticky(bool $isSticky): static
    {
        $this->isSticky = $isSticky;

        return $this;
    }

    public function getFeaturedImage(): ?string
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(?string $featuredImage): static
    {
        $this->featuredImage = $featuredImage;

        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function setViewCount(int $viewCount): static
    {
        $this->viewCount = $viewCount;

        return $this;
    }

    public function getProgrammingLanguage(): ?string
    {
        return $this->programmingLanguage;
    }

    public function setProgrammingLanguage(?string $programmingLanguage): static
    {
        $this->programmingLanguage = $programmingLanguage;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeInterface $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    /** @return Collection<int, Category> */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /** @return Collection<int, PostMeta> */
    public function getMeta(): Collection
    {
        return $this->meta;
    }

    public function addMeta(PostMeta $meta): static
    {
        if (!$this->meta->contains($meta)) {
            $this->meta->add($meta);
            $meta->setPost($this);
        }

        return $this;
    }

    public function removeMeta(PostMeta $meta): static
    {
        if ($this->meta->removeElement($meta) && $meta->getPost() === $this) {
            $meta->setPost(null);
        }

        return $this;
    }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setPost($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment) && $comment->getPost() === $this) {
            $comment->setPost(null);
        }

        return $this;
    }

    /** @return Collection<int, PostRevision> */
    public function getRevisions(): Collection
    {
        return $this->revisions;
    }
}
