<?php

declare(strict_types=1);

namespace App\Tests\Factory\Publication\Dossier\Type\ComplaintJudgement;

use App\Domain\Publication\Attachment\AttachmentLanguage;
use App\Domain\Publication\Dossier\Type\ComplaintJudgement\ComplaintJudgementMainDocument;
use App\Tests\Factory\FileInfoFactory;
use App\Tests\Factory\Publication\Dossier\Type\Covenant\CovenantFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ComplaintJudgementMainDocument>
 */
final class ComplaintJudgementMainDocumentFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'dossier' => CovenantFactory::new(),
            'fileInfo' => FileInfoFactory::new(),
            'formalDate' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'grounds' => self::faker()->optional(default: [])->words(),
            'internalReference' => self::faker()->optional(default: '')->words(asText: true),
            'language' => self::faker()->randomElement(AttachmentLanguage::cases()),
            'type' => self::faker()->randomElement(ComplaintJudgementMainDocument::getAllowedTypes()),
            'updatedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
    }

    public static function class(): string
    {
        return ComplaintJudgementMainDocument::class;
    }
}