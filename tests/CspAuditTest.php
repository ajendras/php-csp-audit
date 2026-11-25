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

    public function testPrintsManPageHelpWhenNoArgumentGiven()
    {
        $command = sprintf('php %s', escapeshellarg($this->scriptPath));
        $output = shell_exec($command) ?? '';

        $this->assertStringContainsString('NAME', $output);
        $this->assertStringContainsString('SYNOPSIS', $output);
        $this->assertStringContainsString('DESCRIPTION', $output);
        $this->assertStringContainsString('OPTIONS', $output);
        $this->assertStringContainsString('csp-audit.php(1)', $output);
        $this->assertStringContainsString('--json', $output);
        $this->assertStringContainsString('--nonce', $output);
        $this->assertStringContainsString('--ignore=LIST', $output);
        $this->assertStringContainsString('--help', $output);
        $this->assertStringContainsString('When no argument is supplied this help text is displayed', $output);
        $this->assertStringContainsString('Content-Security-Policy', $output);
    }

    public function testGeneratesDefaultCspForEmptyFixture()
    {
        $fixtureDir = __DIR__ . '/fixtures/empty';
        $command = sprintf('php %s %s', escapeshellarg($this->scriptPath), escapeshellarg($fixtureDir));
        $output = trim(shell_exec($command) ?? '');

        $expected = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; worker-src 'self'; object-src 'self'; frame-ancestors 'self';";
        $this->assertEquals($expected, $output);
    }

    public function testGeneratesCspWithDiscoveredResourcesForFixture()
    {
        $fixtureDir = __DIR__ . '/fixtures/with-resources';
        $command = sprintf('php %s %s', escapeshellarg($this->scriptPath), escapeshellarg($fixtureDir));
        $output = trim(shell_exec($command) ?? '');

        $expected = "default-src 'self'; script-src 'self' 'unsafe-inline' https://example.com; style-src 'self' 'unsafe-inline' https://example.com; img-src 'self' data: https://example.com local.jpg; font-src 'self' https://example.com; connect-src 'self'; worker-src 'self' https://example.com; object-src 'self' https://example.com; frame-ancestors 'self';";
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
        $this->assertEquals(['https://example.com'], $sources['script']);
        $this->assertEquals(['https://example.com'], $sources['style']);
        $this->assertEquals(['https://example.com', 'local.jpg'], $sources['img']);
        $this->assertEquals(['https://example.com'], $sources['font']);
        $this->assertEquals([], $sources['connect']);
        $this->assertEquals(['https://example.com'], $sources['worker']);
        $this->assertEquals(['https://example.com'], $sources['object']);
        $this->assertEquals([], $sources['frame']);
    }

    public function testOutputsNonceAndExampleMetaTags()
    {
        $fixtureDir = __DIR__ . '/fixtures/with-resources';
        $command = sprintf('php %s --nonce %s', escapeshellarg($this->scriptPath), escapeshellarg($fixtureDir));
        $output = shell_exec($command) ?? '';

        $this->assertStringContainsString('Generated nonce:', $output);
        $this->assertStringContainsString('Example meta tag:', $output);
        $this->assertStringContainsString('<meta http-equiv="Content-Security-Policy" content="', $output);
        $this->assertStringContainsString("'nonce-", $output);
        $this->assertStringContainsString('https://example.com', $output);
        $this->assertStringContainsString('https://example.com', $output);
        $this->assertStringContainsString('<script nonce="', $output);
        $this->assertStringContainsString('<style nonce="', $output);

        $this->assertMatchesRegularExpression('/Generated nonce: [A-Za-z0-9+\/]{20,24}/', $output);
    }

    public function testIgnoreFlagSkipsDirectories()
    {
        $fixtureDir = __DIR__ . '/fixtures/with-resources';
        // ignore the fixture directory itself to confirm skipping logic produces default CSP
        $command = sprintf('php %s --ignore=with-resources %s', escapeshellarg($this->scriptPath), escapeshellarg($fixtureDir));
        $output = trim(shell_exec($command) ?? '');

        $expected = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; worker-src 'self'; object-src 'self'; frame-ancestors 'self';";
        $this->assertEquals($expected, $output);
    }

    public function testDoesNotMatchInsideCommentsOrStrings()
    {
        $fixtureDir = __DIR__ . '/fixtures/with-resources';
        $command = sprintf('php %s --json %s', escapeshellarg($this->scriptPath), escapeshellarg($fixtureDir));
        $output = trim(shell_exec($command) ?? '');
        $data = json_decode($output, true);

        $sources = $data['sources'];
        $allSources = array_merge(
            $sources['script'],
            $sources['style'],
            $sources['img'],
            $sources['font'],
            $sources['connect'],
            $sources['worker'],
            $sources['object'],
            $sources['frame']
        );

        // Resources present only inside comments or JS strings must not appear
        $this->assertNotContains('https://commented.com', $allSources);
        $this->assertNotContains('https://string.example.com', $allSources);
    }
}
