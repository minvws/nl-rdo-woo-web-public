<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Upload\Postprocessor\Strategy;

use App\Domain\Ingest\Process\IngestProcessOptions;
use App\Domain\Ingest\Process\SubType\SubTypeIngester;
use App\Domain\Publication\Dossier\Type\WooDecision\Entity\Document;
use App\Domain\Publication\Dossier\Type\WooDecision\Entity\WooDecision;
use App\Domain\Publication\Dossier\Type\WooDecision\Repository\DocumentRepository;
use App\Domain\Publication\FileInfo;
use App\Domain\Upload\FileType\FileType;
use App\Domain\Upload\FileType\FileTypeHelper;
use App\Domain\Upload\Postprocessor\Strategy\FileStrategy;
use App\Domain\Upload\Process\FileStorer;
use App\Domain\Upload\UploadedFile;
use App\Service\HistoryService;
use App\Tests\Unit\UnitTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final class FileStrategyTest extends UnitTestCase
{
    private EntityManagerInterface&MockInterface $doctrine;
    private LoggerInterface&MockInterface $logger;
    private SubTypeIngester&MockInterface $ingestService;
    private HistoryService&MockInterface $historyService;
    private FileStorer&MockInterface $fileStorer;
    private FileTypeHelper&MockInterface $fileTypeHelper;
    private UploadedFile&MockInterface $file;
    private WooDecision&MockInterface $dossier;
    private DocumentRepository&MockInterface $documentRepository;
    private Document&MockInterface $document;
    private FileInfo&MockInterface $fileInfo;
    private string $documentId = 'documentId';

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentRepository = \Mockery::mock(DocumentRepository::class);
        $this->doctrine = \Mockery::mock(EntityManagerInterface::class);
        $this->doctrine
            ->shouldReceive('getRepository')
            ->with(Document::class)
            ->andReturn($this->documentRepository);
        $this->logger = \Mockery::mock(LoggerInterface::class);
        $this->ingestService = \Mockery::mock(SubTypeIngester::class);
        $this->historyService = \Mockery::mock(HistoryService::class);
        $this->fileStorer = \Mockery::mock(FileStorer::class);
        $this->fileTypeHelper = \Mockery::mock(FileTypeHelper::class);
        $this->file = \Mockery::mock(UploadedFile::class);
        $this->dossier = \Mockery::mock(WooDecision::class);
        $this->document = \Mockery::mock(Document::class);
        $this->fileInfo = \Mockery::mock(FileInfo::class);
    }

    public function testProcess(): void
    {
        $this->documentRepository
            ->shouldReceive('findOneByDossierAndDocumentId')
            ->with($this->dossier, $this->documentId)
            ->andReturn($this->document);

        $this->document
            ->shouldReceive('shouldBeUploaded')
            ->once()
            ->andReturnTrue();

        $this->document
            ->shouldReceive('getFileInfo')
            ->andReturn($this->fileInfo);

        $this->fileInfo
            ->shouldReceive('isUploaded')
            ->once()
            ->andReturnFalse();

        $this->file
            ->shouldReceive('getOriginalFileExtension')
            ->once()
            ->andReturn($originalExtension = 'pdf');

        $this->fileStorer
            ->shouldReceive('storeForDocument')
            ->with($this->file, $this->document, $this->documentId, $originalExtension);

        $this->ingestService
            ->shouldReceive('ingest')
            ->with(
                $this->document,
                \Mockery::on(fn (IngestProcessOptions $options): bool => $options->forceRefresh()),
            );

        $this->fileInfo
            ->shouldReceive('getType')
            ->andReturn($fileInfoType = 'pdf');

        $this->fileInfo
            ->shouldReceive('getSize')
            ->andReturn(1024);

        $this->historyService
            ->shouldReceive('addDocumentEntry')
            ->with(
                $this->document,
                'document_uploaded',
                [
                    'filetype' => $fileInfoType,
                    'filesize' => '1 KB',
                ],
            );

        $strategy = $this->createStrategy();
        $strategy->process($this->file, $this->dossier, $this->documentId);
    }

    public function testProcessWhenFailingToFetchDocument(): void
    {
        $this->file
            ->shouldReceive('getOriginalFilename')
            ->andReturn($originalFile = 'originalFile.pdf');

        $this->documentRepository
            ->shouldReceive('findOneByDossierAndDocumentId')
            ->once()
            ->with($this->dossier, $this->documentId)
            ->andReturnNull();

        $this->dossier
            ->shouldReceive('getId')
            ->andReturn($dossierId = Uuid::v6());

        $this->logger
            ->shouldReceive('info')
            ->with('Could not find document, skipping processing file', [
                'filename' => $originalFile,
                'documentId' => $this->documentId,
                'dossierId' => $dossierId,
            ]);

        $strategy = $this->createStrategy();
        $strategy->process($this->file, $this->dossier, $this->documentId);
    }

    public function testProcessWhenDocumentShouldNotBeUploaded(): void
    {
        $this->file
            ->shouldReceive('getOriginalFilename')
            ->andReturn($originalFile = 'originalFile.pdf');

        $this->documentRepository
            ->shouldReceive('findOneByDossierAndDocumentId')
            ->with($this->dossier, $this->documentId)
            ->andReturn($this->document);

        $this->dossier
            ->shouldReceive('getId')
            ->andReturn($dossierId = Uuid::v6());

        $this->document
            ->shouldReceive('shouldBeUploaded')
            ->once()
            ->andReturnFalse();

        $this->logger
            ->shouldReceive('warning')
            ->with(
                sprintf('Document with id "%s" should not be uploaded, skipping it', $this->documentId),
                [
                    'filename' => $originalFile,
                    'documentId' => $this->documentId,
                    'dossierId' => $dossierId,
                ],
            );

        $strategy = $this->createStrategy();
        $strategy->process($this->file, $this->dossier, $this->documentId);
    }

    public function testCanProcessReturnsTrue(): void
    {
        $this->fileTypeHelper
            ->shouldReceive('fileOfType')
            ->once()
            ->with($this->file, FileType::PDF, FileType::XLS, FileType::DOC, FileType::TXT, FileType::PPT)
            ->andReturnTrue();

        $strategy = $this->createStrategy();
        $result = $strategy->canProcess($this->file, $this->dossier);

        $this->assertTrue($result);
    }

    public function testCanProcessReturnsFalse(): void
    {
        $this->fileTypeHelper
            ->shouldReceive('fileOfType')
            ->once()
            ->with($this->file, FileType::PDF, FileType::XLS, FileType::DOC, FileType::TXT, FileType::PPT)
            ->andReturnFalse();

        $strategy = $this->createStrategy();
        $result = $strategy->canProcess($this->file, $this->dossier);

        $this->assertFalse($result);
    }

    private function createStrategy(): FileStrategy
    {
        return new FileStrategy(
            $this->doctrine,
            $this->logger,
            $this->ingestService,
            $this->historyService,
            $this->fileStorer,
            $this->fileTypeHelper,
        );
    }
}