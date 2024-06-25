<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Publication\Dossier\ViewModel;

use App\Domain\Publication\Dossier\ViewModel\PublicationItemViewFactory;
use App\Entity\FileInfo;
use App\Entity\PublicationItem;
use App\Tests\Unit\UnitTestCase;

final class PublicationItemViewFactoryTest extends UnitTestCase
{
    public function testMake(): void
    {
        $fileInfo = \Mockery::mock(FileInfo::class);
        $fileInfo->shouldReceive('getName')->andReturn('file-name');
        $fileInfo->shouldReceive('getSize')->andReturn(100);
        $fileInfo->shouldReceive('isUploaded')->andReturn(true);

        $publicationItem = \Mockery::mock(PublicationItem::class);
        $publicationItem->shouldReceive('getFileInfo')->andReturn($fileInfo);

        $result = (new PublicationItemViewFactory())->make($publicationItem);

        $this->assertSame('file-name', $result->fileName);
        $this->assertSame(100, $result->fileSize);
        $this->assertTrue($result->isUploaded);
    }
}