<?php

declare(strict_types=1);

namespace App\Domain\Publication\Dossier;

use App\Doctrine\TimestampableTrait;
use App\Domain\Publication\Dossier\Step\StepName;
use App\Domain\Publication\Dossier\Type\Covenant\Covenant;
use App\Domain\Publication\Dossier\Type\DossierType;
use App\Domain\Publication\Dossier\Type\WooDecision\WooDecision;
use App\Entity\Department;
use App\Entity\Organisation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This is the base class for dossier type entities. It contains only the common properties and relationships.
 */
#[ORM\Entity(repositoryClass: AbstractDossierRepository::class)]
#[ORM\Table(name: 'dossier')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap([
    DossierType::WOO_DECISION->value => WooDecision::class,
    DossierType::COVENANT->value => Covenant::class,
])]
#[ORM\UniqueConstraint(name: 'dossier_unique_index', columns: ['dossier_nr', 'document_prefix'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['dossierNr', 'documentPrefix'], groups: [StepName::DETAILS->value])]
abstract class AbstractDossier
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    protected Uuid $id;

    #[ORM\Column(length: 255)]
    #[Assert\Length(min: 3, max: 50, groups: [StepName::DETAILS->value])]
    #[Assert\Regex(
        pattern: '/^[a-z0-9-]+$/i',
        message: 'use_only_letters_numbers_and_dashes',
        groups: [StepName::DETAILS->value]
    )]
    protected string $dossierNr = '';

    #[ORM\Column(length: 500)]
    #[Assert\Length(min: 2, max: 500, groups: [StepName::DETAILS->value])]
    protected string $title = '';

    #[ORM\Column(length: 255, enumType: DossierStatus::class)]
    protected DossierStatus $status;

    /** @var Collection<array-key,Department> */
    #[ORM\ManyToMany(targetEntity: Department::class)]
    #[ORM\JoinTable(name: 'dossier_department')]
    #[ORM\JoinColumn(name: 'dossier_id', onDelete: 'cascade')]
    #[Assert\Count(min: 1, groups: [StepName::DETAILS->value])]
    protected Collection $departments;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual(
        value: 'today -10 years',
        message: 'date_max_10_year_old',
        groups: [StepName::DETAILS->value]
    )]
    protected ?\DateTimeImmutable $dateFrom = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual(
        propertyPath: 'dateFrom',
        message: 'date_to_before_date_from',
        groups: [StepName::DETAILS->value]
    )]
    #[Assert\LessThanOrEqual(
        value: 'today +5 years',
        message: 'date_max_5_year_in_future',
        groups: [StepName::DETAILS->value]
    )]
    protected ?\DateTimeImmutable $dateTo = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(groups: [StepName::DECISION->value, StepName::CONTENT->value])]
    protected string $summary = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(groups: [StepName::DETAILS->value])]
    protected string $documentPrefix = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Assert\NotBlank(groups: [StepName::PUBLICATION->value])]
    protected ?\DateTimeImmutable $publicationDate = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    protected bool $completed = false;

    #[ORM\ManyToOne(inversedBy: 'dossiers')]
    #[ORM\JoinColumn(nullable: false)]
    protected Organisation $organisation;

    #[ORM\Column(length: 255)]
    protected string $internalReference = '';

    public function __construct()
    {
        $this->id = Uuid::v6();
        $this->status = DossierStatus::NEW;

        $this->departments = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDossierNr(): string
    {
        return $this->dossierNr;
    }

    public function setDossierNr(string $dossierNr): self
    {
        $this->dossierNr = strtolower($dossierNr);

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getStatus(): DossierStatus
    {
        return $this->status;
    }

    public function setStatus(DossierStatus $status): self
    {
        $this->status = $status;

        if ($this->publicationDate === null && $status->isPublished()) {
            $this->setPublicationDate(new \DateTimeImmutable());
        }

        return $this;
    }

    /**
     * @return Collection<array-key,Department>
     */
    public function getDepartments(): Collection
    {
        return $this->departments;
    }

    public function addDepartment(Department $department): self
    {
        if (! $this->departments->contains($department)) {
            $this->departments->add($department);
        }

        return $this;
    }

    public function removeDepartment(Department $department): self
    {
        $this->departments->removeElement($department);

        return $this;
    }

    /**
     * @param Department[] $departments
     *
     * @return $this
     */
    public function setDepartments(array $departments): static
    {
        $this->departments->clear();
        $this->departments->add(...$departments);

        return $this;
    }

    public function getDateFrom(): ?\DateTimeImmutable
    {
        return $this->dateFrom;
    }

    public function setDateFrom(?\DateTimeImmutable $dateFrom): static
    {
        $this->dateFrom = $dateFrom;

        return $this;
    }

    public function getDateTo(): ?\DateTimeImmutable
    {
        return $this->dateTo;
    }

    public function setDateTo(?\DateTimeImmutable $dateTo): static
    {
        $this->dateTo = $dateTo;

        return $this;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function getDocumentPrefix(): string
    {
        return $this->documentPrefix;
    }

    public function setDocumentPrefix(string $documentPrefix): self
    {
        $this->documentPrefix = $documentPrefix;

        return $this;
    }

    public function getPublicationDate(): ?\DateTimeImmutable
    {
        return $this->publicationDate;
    }

    public function setPublicationDate(?\DateTimeImmutable $publicationDate): void
    {
        $this->publicationDate = $publicationDate;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function setCompleted(bool $completed): static
    {
        $this->completed = $completed;

        return $this;
    }

    public function hasFuturePublicationDate(): bool
    {
        return $this->publicationDate >= new \DateTimeImmutable('today midnight');
    }

    public function getOrganisation(): Organisation
    {
        return $this->organisation;
    }

    public function setOrganisation(Organisation $organisation): static
    {
        $this->organisation = $organisation;

        return $this;
    }

    public function getInternalReference(): string
    {
        return $this->internalReference;
    }

    public function setInternalReference(string $internalReference): void
    {
        $this->internalReference = $internalReference;
    }

    abstract public function getType(): DossierType;
}
