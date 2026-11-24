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

    public function testOutputsJsonWithDiscoveredResources()
    {
        $fixtureDir = __DIR__ . '/fixtures/with-resources';
        $command = sprintf('php %s --json %s', escapeshellarg($this->scriptPath), escapeshellarg($fixtureDir));
        $output = trim(shell_exec($command) ?? '');
        $data = json_decode($output, true);

        $this->assertIsArray($data, 'JSON output should decode to array');
        $this->assertArrayHasKey('sources', $data);
        $this->assertArrayHasKey('hasInlineScript', $data);
        $this->assertArrayHasKey('hasInlineStyle', $data);
        $this->assertTrue($data['hasInlineScript']);
        $this->assertTrue($data['hasInlineStyle']);

        $sources = $data['sources'];
        $this->assertEquals(['https://example.com/app.js'], $sources['script']);
        $this->assertEquals(['https://example.com/styles.css'], $sources['style']);
        $this->assertEquals(['https://example.com/image.png', 'local.jpg'], $sources['img']);
        $this->assertEquals(['https://example.com/font.woff'], $sources['font']);
        $this->assertEquals([], $sources['connect']);
    }
}
