<?php

declare(strict_types=1);

namespace App\Twig\Components\Public;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class SearchForm
{
    public ?string $id = 'search-field';
}
