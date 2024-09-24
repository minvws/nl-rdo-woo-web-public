<?php

declare(strict_types=1);

namespace App\Tests\Integration\Domain\Publication\Dossier\Type\Disposition;

use App\Domain\Publication\Dossier\Type\Disposition\DispositionAttachmentRepository;
use App\Tests\Factory\Publication\Dossier\Type\Disposition\DispositionAttachmentFactory;
use App\Tests\Factory\Publication\Dossier\Type\Disposition\DispositionFactory;
use App\Tests\Integration\IntegrationTestTrait;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DispositionAttachmentRepositoryTest extends KernelTestCase
{
    use IntegrationTestTrait;

    private function getRepository(): DispositionAttachmentRepository
    {
        /** @var DispositionAttachmentRepository */
        return self::getContainer()->get(DispositionAttachmentRepository::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
    }

    public function testRemove(): void
    {
        $dossier = DispositionFactory::createOne();
        $attachment = DispositionAttachmentFactory::createOne([
            'dossier' => $dossier,
        ]);

        $repository = $this->getRepository();

        $result = $this->getRepository()->findForDossierByPrefixAndNr(
            $dossier->getDocumentPrefix(),
            $dossier->getDossierNr(),
            $attachment->getId()->toRfc4122(),
        );
        self::assertNotNull($result);

        $repository->remove($result, true);

        $result = $this->getRepository()->findForDossierByPrefixAndNr(
            $dossier->getDocumentPrefix(),
            $dossier->getDossierNr(),
            $attachment->getId()->toRfc4122(),
        );
        self::assertNull($result);
    }

    public function testFindForDossierByPrefixAndNrFindsMatch(): void
    {
        $dossier = DispositionFactory::createOne();
        $attachment = DispositionAttachmentFactory::createOne([
            'dossier' => $dossier,
        ]);

        $result = $this->getRepository()->findForDossierByPrefixAndNr(
            $dossier->getDocumentPrefix(),
            $dossier->getDossierNr(),
            $attachment->getId()->toRfc4122(),
        );

        self::assertNotNull($result);
        self::assertEquals($attachment->getId(), $result->getId());
    }

    public function testFindForDossierByPrefixAndNrMismatch(): void
    {
        $result = $this->getRepository()->findForDossierByPrefixAndNr(
            'a non-existing document prefix',
            'a non-existing dossier number',
            $this->getFaker()->uuid(),
        );

        self::assertNull($result);
    }

    public function testFindAllForDossier(): void
    {
        $dossier = DispositionFactory::createOne();
        DispositionAttachmentFactory::createOne([
            'dossier' => $dossier,
        ]);
        DispositionAttachmentFactory::createOne([
            'dossier' => $dossier,
        ]);

        $result = $this->getRepository()->findAllForDossier(
            $dossier->getId(),
        );

        self::assertCount(2, $result);
    }

    public function testFindOneForDossier(): void
    {
        $dossier = DispositionFactory::createOne();
        $attachment = DispositionAttachmentFactory::createOne([
            'dossier' => $dossier,
        ]);

        $result = $this->getRepository()->findOneForDossier(
            $dossier->getId(),
            $attachment->getId(),
        );

        self::assertEquals($attachment->getId(), $result->getId());

        $this->expectException(NoResultException::class);
        $this->getRepository()->findOneForDossier(
            $dossier->getId(),
            Uuid::v6(),
        );
    }

    public function testFindOneOrNullForDossier(): void
    {
        $dossier = DispositionFactory::createOne();
        $attachment = DispositionAttachmentFactory::createOne([
            'dossier' => $dossier,
        ]);

        $result = $this->getRepository()->findOneOrNullForDossier(
            $dossier->getId(),
            $attachment->getId(),
        );

        self::assertNotNull($result);
        self::assertEquals($attachment->getId(), $result->getId());

        self::assertNull(
            $this->getRepository()->findOneOrNullForDossier(
                $dossier->getId(),
                Uuid::v6(),
            )
        );
    }
}
