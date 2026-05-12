<?php

namespace InSquare\OpendxpSitemapBundle\Tests\Util;

use InSquare\OpendxpSitemapBundle\Util\HostNormalizer;
use PHPUnit\Framework\TestCase;

final class HostNormalizerTest extends TestCase
{
    public function testNormalizeAddsHttpsAndTrimsTrailingSlash(): void
    {
        self::assertSame('https://example.com', HostNormalizer::normalize('example.com/'));
    }

    public function testNormalizeKeepsScheme(): void
    {
        self::assertSame('http://example.com', HostNormalizer::normalize('http://example.com/'));
        self::assertSame('https://example.com', HostNormalizer::normalize('https://example.com/'));
    }

    public function testBuildAbsoluteUrlBuildsSingleSlashPath(): void
    {
        self::assertSame(
            'https://example.com/path/to/page',
            HostNormalizer::buildAbsoluteUrl('example.com/', '/path/to/page')
        );
    }
}
