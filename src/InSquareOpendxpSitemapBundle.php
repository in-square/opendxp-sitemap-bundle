<?php

namespace InSquare\OpendxpSitemapBundle;

use InSquare\OpendxpSitemapBundle\DependencyInjection\InSquareOpendxpSitemapExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class InSquareOpendxpSitemapBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new InSquareOpendxpSitemapExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return __DIR__;
    }
}
