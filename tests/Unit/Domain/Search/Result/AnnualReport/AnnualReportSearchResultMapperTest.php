<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Search\Result\AnnualReport;

use App\Domain\Publication\Dossier\Type\AnnualReport\AnnualReportRepository;
use App\Domain\Search\Index\ElasticDocumentType;
use App\Domain\Search\Result\AnnualReport\AnnualReportSearchResultMapper;
use App\Domain\Search\Result\DossierTypeSearchResultMapper;
use App\Domain\Search\Result\ResultEntryInterface;
use Jaytaph\TypeArray\TypeArray;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\MockInterface;

class AnnualReportSearchResultMapperTest extends MockeryTestCase
{
    private DossierTypeSearchResultMapper&MockInterface $baseMapper;
    private AnnualReportRepository&MockInterface $repository;
    private AnnualReportSearchResultMapper $mapper;

    public function setUp(): void
    {
        $this->baseMapper = \Mockery::mock(DossierTypeSearchResultMapper::class);
        $this->repository = \Mockery::mock(AnnualReportRepository::class);

        $this->mapper = new AnnualReportSearchResultMapper(
            $this->baseMapper,
            $this->repository,
        );
    }

    public function testMapForwardsToBaseMapper(): void
    {
        $hit = \Mockery::mock(TypeArray::class);

        $expectedResult = \Mockery::mock(ResultEntryInterface::class);

        $this->baseMapper
            ->expects('map')
            ->with($hit, $this->repository, ElasticDocumentType::ANNUAL_REPORT)
            ->andReturn($expectedResult);

        $this->assertEquals(
            $expectedResult,
            $this->mapper->map($hit),
        );
    }
}