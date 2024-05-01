<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Search\Result\WooDecision;

use App\Domain\Search\Index\ElasticDocumentType;
use App\Domain\Search\Result\SubTypeEntry;
use App\Domain\Search\Result\WooDecision\DocumentResultMapper;
use App\Repository\DocumentRepository;
use App\Repository\DossierRepository;
use App\ViewModel\DocumentSearchEntry;
use App\ViewModel\DossierReference;
use Jaytaph\TypeArray\TypeArray;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\MockInterface;

class DocumentResultMapperTest extends MockeryTestCase
{
    private DocumentRepository&MockInterface $documentRepository;
    private DossierRepository&MockInterface $dossierRepository;
    private DocumentResultMapper $mapper;

    public function setUp(): void
    {
        $this->documentRepository = \Mockery::mock(DocumentRepository::class);
        $this->dossierRepository = \Mockery::mock(DossierRepository::class);

        $this->mapper = new DocumentResultMapper(
            $this->documentRepository,
            $this->dossierRepository,
        );
    }

    public function testMapReturnsNullWhenPrefixIsMissing(): void
    {
        $hit = \Mockery::mock(TypeArray::class);
        $hit->shouldReceive('getStringOrNull')->with('[fields][document_nr][0]')->andReturnNull();

        $this->assertNull($this->mapper->map($hit));
    }

    public function testMapReturnsNullWhenViewModelCannotBeLoaded(): void
    {
        $hit = \Mockery::mock(TypeArray::class);
        $hit->shouldReceive('getStringOrNull')->with('[fields][document_nr][0]')->andReturn('foo');

        $this->documentRepository->shouldReceive('getDocumentSearchEntry')->with('foo')->andReturnNull();

        $this->assertNull($this->mapper->map($hit));
    }

    public function testMapSuccessful(): void
    {
        $hit = \Mockery::mock(TypeArray::class);
        $hit->shouldReceive('getStringOrNull')->with('[fields][document_nr][0]')->andReturn('foo');
        $hit->shouldReceive('exists')->with('[highlight][pages.content]')->andReturnTrue();
        $hit->shouldReceive('getTypeArray->toArray')->andReturn(['x', 'y']);
        $hit->shouldReceive('exists')->with('[highlight][dossiers.title]')->andReturnFalse();
        $hit->shouldReceive('exists')->with('[highlight][dossiers.summary]')->andReturnFalse();

        $viewModel = \Mockery::mock(DocumentSearchEntry::class);
        $dossierReference = \Mockery::mock(DossierReference::class);

        $this->documentRepository->shouldReceive('getDocumentSearchEntry')->with('foo')->andReturn($viewModel);
        $this->dossierRepository->shouldReceive('getDossierReferencesForDocument')->with('foo')->andReturn([$dossierReference]);

        $entry = $this->mapper->map($hit);

        $this->assertInstanceOf(SubTypeEntry::class, $entry);
        $this->assertSame($viewModel, $entry->getDocument());
        $this->assertSame([$dossierReference], $entry->getDossiers());
        $this->assertSame(['x', 'y'], $entry->getHighlights());
        $this->assertSame(ElasticDocumentType::WOO_DECISION_DOCUMENT, $entry->getType());
    }
}
