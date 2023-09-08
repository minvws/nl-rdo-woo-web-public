<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use App\Entity\Dossier;
use App\Repository\DocumentRepository;
use App\Service\Storage\DocumentStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;

/**
 * This class will process files that are uploaded to the system. It can process either a PDF file and add it to a dossier, or a ZIP where
 * it will find PDFs and add them to the dossier. Note that only PDFs are added when the filename of the PDF matches a document number in
 * the dossier.
 */
class FileProcessService
{
    public function __construct(
        private readonly EntityManagerInterface $doctrine,
        private readonly DocumentStorageService $storage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function processFile(\SplFileInfo $file, Dossier $dossier, string $originalFile): bool
    {
        $parts = pathinfo($originalFile);
        $ext = $parts['extension'] ?? '';

        switch ($ext) {
            case 'mp3':
                return $this->processSingleFile($file, $dossier, $originalFile, 'audio');
            case 'zip':
                return $this->processZip($file, $dossier);
            case 'pdf':
                return $this->processSingleFile($file, $dossier, $originalFile, 'pdf');
            default:
                $this->logger->error('Unsupported filetype detected', [
                    'extension' => $ext,
                    'originalFile' => $originalFile,
                    'dossierId' => $dossier->getId(),
                ]);
                throw new \RuntimeException('Unsupported filetype detected');
        }
    }

    protected function processSingleFile(\SplFileInfo $file, Dossier $dossier, string $originalFile, string $type): bool
    {
        // Fetch document number from the beginning of the filename. Only use digits
        $originalFile = basename($originalFile);
        preg_match('/^(\d+)/', $originalFile, $matches);
        $documentId = $matches[1] ?? null;

        if (is_null($documentId)) {
            $this->logger->error('Cannot extract document ID from the filename', [
                'filename' => $originalFile,
                'matches' => $matches,
                'dossierId' => $dossier->getId(),
            ]);

            throw new \RuntimeException('Cannot extract document id from file');
        }

        // Find matching document entity in the database
        /** @var DocumentRepository $repo */
        $repo = $this->doctrine->getRepository(Document::class);
        $document = $repo->findOneByDossierAndDocumentId($dossier, $documentId);
        if (! $document) {
            // Document does not exist. That is actually fine.
            $this->logger->info('Could not find document, skipping', [
                'filename' => $originalFile,
                'documentId' => $documentId,
                'dossierId' => $dossier->getId(),
            ]);

            return false;
        }

        if (! $document->shouldBeUploaded()) {
            $this->logger->warning("Document with id $documentId should not be uploaded, skipping it", [
                'documentId' => $documentId,
                'dossierId' => $dossier->getId(),
            ]);

            return true;
        }

        // Store document in storage
        if (! $this->storage->storeDocument($file, $document)) {
            $this->logger->error('Failed to store document', [
                'documentId' => $documentId,
                'path' => $file->getRealPath(),
            ]);

            throw new \RuntimeException("Failed to store document with id $documentId");
        }

        $document->getFileInfo()->setType($type);

        $this->doctrine->persist($document);
        $this->doctrine->flush();

        return true;
    }

    protected function processZip(\SplFileInfo $file, Dossier $dossier): bool
    {
        $zip = new \ZipArchive();
        $zip->open($file->getPathname());

        for ($i = 0; $i != $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (! $filename) {
                continue;
            }
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if ($ext != 'pdf') {
                continue;
            }

            // Extract file to tmp dir
            $zip->extractTo(sys_get_temp_dir(), $filename);

            try {
                $tmpPath = sprintf('%s/%s', sys_get_temp_dir(), $filename);
                $this->processSingleFile(new File($tmpPath), $dossier, $filename, 'pdf');
            } catch (\Exception) {
                // do nothing. Seems like an extra file in the zip
            }

            // Cleanup tmp file if needed
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }

        $zip->close();

        return true;
    }
}
