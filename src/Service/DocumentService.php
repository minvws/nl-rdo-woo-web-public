<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use App\Entity\Dossier;
use App\Service\Storage\DocumentStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;

/**
 * This class will manage documents that are uploaded to the system. It can process either a PDF file and add it to a dossier, or a ZIP where
 * it will find PDFs and add them to the dossier. Note that only PDFs are added when the filename of the PDF matches a document number in
 * the dossier.
 */
class DocumentService
{
    public function __construct(
        private readonly EntityManagerInterface $doctrine,
        private readonly DocumentStorageService $storage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function processDocument(\SplFileInfo $file, Dossier $dossier, string $originalFile): bool
    {
        $parts = pathinfo($originalFile);
        $ext = $parts['extension'] ?? '';

        switch ($ext) {
            case 'mp3':
                return $this->processFile($file, $dossier, $originalFile, 'audio');
            case 'zip':
                return $this->processZip($file, $dossier);
            case 'pdf':
                return $this->processFile($file, $dossier, $originalFile, 'pdf');
            default:
                $this->logger->error('Unsupported filetype detected', [
                    'extension' => $ext,
                    'originalFile' => $originalFile,
                    'dossierId' => $dossier->getId(),
                ]);
                throw new \RuntimeException('Unsupported filetype detected');
        }
    }

    protected function processFile(\SplFileInfo $file, Dossier $dossier, string $originalFile, string $type): bool
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

        $documentNr = $dossier->getDocumentPrefix() . '-' . $documentId;

        // Find matching document entity in the database
        $document = $this->doctrine->getRepository(Document::class)->findOneBy(['documentNr' => $documentNr]);

        if (! $document || $document->getDossiers()->contains($dossier) === false) {
            $this->logger->error("Document with id $documentId not found", [
                'documentId' => $documentId,
                'dossierId' => $dossier->getId(),
            ]);

            throw new \RuntimeException("Document with id $documentId not found");
        }

        // Store document in storage
        if (! $this->storage->storeDocument($file, $document)) {
            $this->logger->error('Failed to store document', [
                'documentId' => $documentId,
                'path' => $file->getRealPath(),
            ]);

            throw new \RuntimeException("Failed to store document with id $documentId");
        }

        $document->setFileType($type);

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
                $this->processFile(new File($tmpPath), $dossier, $filename, 'pdf');
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

    public function removeDocumentFromDossier(Dossier $dossier, Document $document): void
    {
        if ($document->getDossiers()->contains($dossier) === false) {
            throw new \RuntimeException('Document does not belong to dossier');
        }

        $dossier->removeDocument($document);

        if ($document->getDossiers()->count() === 0) {
            // Remove whole document as there are no links left
            $this->doctrine->remove($document);
        }

        $this->doctrine->persist($dossier);
        $this->doctrine->flush();
    }
}