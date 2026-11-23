<?php

use PHPUnit\Framework\TestCase;

class CspAuditTest extends TestCase
{
    private $scriptPath;

    protected function setUp(): void
    {
        $this->scriptPath = __DIR__ . '/../csp-audit.php';
        $this->assertFileExists($this->scriptPath, 'csp-audit.php must exist for tests');
    }

    public function testGeneratesDefaultCspForEmptyFixture()
    {
        $fixtureDir = __DIR__ . '/fixtures/empty';
        $command = sprintf('php %s %s', escapeshellarg($this->scriptPath), escapeshellarg($fixtureDir));
        $output = trim(shell_exec($command) ?? '');

        $expected = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self';";
        $this->assertEquals($expected, $output);
    }

    public function testGeneratesCspWithDiscoveredResourcesForFixture()
    {
        $fixtureDir = __DIR__ . '/fixtures/with-resources';
        $command = sprintf('php %s %s', escapeshellarg($this->scriptPath), escapeshellarg($fixtureDir));
        $output = trim(shell_exec($command) ?? '');

        $expected = "default-src 'self'; script-src 'self' 'unsafe-inline' https://example.com/app.js; style-src 'self' 'unsafe-inline' https://example.com/styles.css; img-src 'self' data: https://example.com/image.png local.jpg; font-src 'self' https://example.com/font.woff; connect-src 'self';";
        $this->assertEquals($expected, $output);
    }
}
