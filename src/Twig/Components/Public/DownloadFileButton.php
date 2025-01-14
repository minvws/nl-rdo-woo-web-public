<?php

declare(strict_types=1);

namespace App\Twig\Components\Public;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class DownloadFileButton
{
    public string $href;
    public string $e2eName = 'download-file-link';
}
