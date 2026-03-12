<?php

namespace OpenCart\CLI\Tests\Unit;

use OpenCart\CLI\Support\ReleaseVersion;
use PHPUnit\Framework\TestCase;

class ReleaseVersionTest extends TestCase
{
    public function testNormalizeSupportsTaggedVersions(): void
    {
        $this->assertSame('1.0.2', ReleaseVersion::normalize('v1.0.2'));
        $this->assertSame('1.0.2', ReleaseVersion::normalize('refs/tags/v1.0.2'));
        $this->assertSame('1.0.2', ReleaseVersion::normalize('1.0.2'));
    }

    public function testNormalizeRejectsNonReleaseVersions(): void
    {
        $this->assertNull(ReleaseVersion::normalize(''));
        $this->assertNull(ReleaseVersion::normalize('main'));
        $this->assertNull(ReleaseVersion::normalize('1.0'));
        $this->assertNull(ReleaseVersion::normalize('v1.0.2-beta'));
    }

    public function testNextIncrementsPatch(): void
    {
        $this->assertSame('1.0.3', ReleaseVersion::next('1.0.2'));
    }

    public function testNextCarriesPatchIntoMinor(): void
    {
        $this->assertSame('1.1.0', ReleaseVersion::next('1.0.9'));
    }

    public function testNextCarriesMinorIntoMajor(): void
    {
        $this->assertSame('2.0.0', ReleaseVersion::next('1.9.9'));
    }

    public function testSelectLatestUsesHighestSemanticTag(): void
    {
        $tags = ['v1.0.9', 'v1.0.2', '1.1.0', 'junk', 'refs/tags/v1.9.9'];

        $this->assertSame('1.9.9', ReleaseVersion::selectLatest($tags));
    }

    public function testSelectLatestFallsBackToBaseline(): void
    {
        $this->assertSame('1.0.2', ReleaseVersion::selectLatest(['junk'], '1.0.2'));
    }
}
