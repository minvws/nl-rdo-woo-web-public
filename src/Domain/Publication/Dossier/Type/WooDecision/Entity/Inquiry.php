<?php

declare(strict_types=1);

namespace App\Domain\Publication\Dossier\Type\WooDecision\Entity;

use App\Doctrine\TimestampableTrait;
use App\Domain\Publication\Dossier\Type\WooDecision\Repository\InquiryRepository;
use App\Domain\Publication\EntityWithBatchDownload;
use App\Entity\Organisation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: InquiryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Inquiry implements EntityWithBatchDownload
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $casenr;

    /** @var Collection<array-key,Document> */
    #[ORM\ManyToMany(targetEntity: Document::class, inversedBy: 'inquiries', cascade: ['persist'])]
    private Collection $documents;

    /** @var Collection<array-key,WooDecision> */
    #[ORM\ManyToMany(targetEntity: WooDecision::class, inversedBy: 'inquiries', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'inquiry_dossier')]
    #[ORM\JoinColumn(name: 'inquiry_id', referencedColumnName: 'id')]
    #[ORM\OrderBy(['decisionDate' => 'DESC'])]
    private Collection $dossiers;

    #[ORM\Column(length: 255)]
    private string $token;

    #[ORM\OneToOne(mappedBy: 'inquiry', targetEntity: InquiryInventory::class)]
    private ?InquiryInventory $inventory = null;

    #[ORM\ManyToOne(inversedBy: 'inquiries')]
    #[ORM\JoinColumn(nullable: false)]
    private Organisation $organisation;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->dossiers = new ArrayCollection();

        $this->token = Uuid::v6()->toBase58();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCasenr(): string
    {
        return $this->casenr;
    }

    public function setCasenr(string $casenr): self
    {
        $this->casenr = $casenr;

        return $this;
    }

    /**
     * @return Collection<array-key,Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): self
    {
        if (! $this->documents->contains($document)) {
            $this->documents->add($document);
            $document->addInquiry($this);
        }

        return $this;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function removeDocument(Document $document): self
    {
        // Document removal for inquiries is disabled as part of #2868:
        // $this->documents->removeElement($document);
        // $document->removeInquiry($this);

        return $this;
    }

    /**
     * @return Collection<array-key,WooDecision>
     */
    public function getDossiers(): Collection
    {
        return $this->dossiers;
    }

    public function addDossier(WooDecision $dossier): self
    {
        if (! $this->dossiers->contains($dossier)) {
            $this->dossiers->add($dossier);
        }

        return $this;
    }

    public function removeDossier(WooDecision $dossier): self
    {
        $this->dossiers->removeElement($dossier);

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @return Collection<array-key,WooDecision>
     */
    public function getPubliclyAvailableDossiers(): Collection
    {
        return $this->dossiers
            ->filter(static fn (WooDecision $dossier) => $dossier->getStatus()->isPubliclyAvailable());
    }

    /**
     * @return Collection<array-key,WooDecision>
     */
    public function getScheduledDossiers(): Collection
    {
        return $this->dossiers->filter(
            static fn (WooDecision $dossier) => $dossier->getStatus()->isScheduled()
        );
    }

    public function getInventory(): ?InquiryInventory
    {
        return $this->inventory;
    }

    public function setInventory(?InquiryInventory $inventory): self
    {
        $this->inventory = $inventory;

        return $this;
    }

    public function getBatchFileName(): string
    {
        return $this->casenr;
    }

    public function isAvailableForBatchDownload(): bool
    {
        return true;
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
}