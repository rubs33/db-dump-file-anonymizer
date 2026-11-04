<?php

namespace Tests\Printi\Bin;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Tests of the executable script bin/anonymize-db-dump (executed in a separate process)
 */
#[CoversNothing]
final class AnonymizeDbDumpTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../../bin/anonymize-db-dump';
    private const DUMP_CONTENT = "INSERT INTO `customers` VALUES (1,'Real Name');\n";

    private string $tempDir;

    // Helper methods

    /**
     * Execute the script and return the exit status and the STDERR content
     * @param array $args
     * @return array{int, string}
     */
    private function runScript(array $args): array
    {
        $command = array_merge([PHP_BINARY, self::SCRIPT], $args);
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stderr];
    }

    // Hook methods

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/' . uniqid('anonymize-db-dump-test-', true);
        mkdir($this->tempDir);
        file_put_contents($this->tempDir . '/input.sql', self::DUMP_CONTENT);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*'));
        rmdir($this->tempDir);
    }

    // Data providers

    public static function providerInvalidModificationsJson(): iterable
    {
        yield 'invalid syntax' => ['{invalid'];
        yield 'truncated object' => ['{"customers": {"2": {"quote": true, "format": "firstName"}}'];
        yield 'trailing comma' => ['{"customers": {"2": {"quote": true, "format": "firstName"},}}'];
        yield 'null' => ['null'];
        yield 'number' => ['123'];
        yield 'string' => ['"customers"'];
        yield 'boolean' => ['true'];
        yield 'table spec that is not an object' => ['{"customers": "firstName"}'];
        yield 'column spec that is not an object' => ['{"customers": {"2": "firstName"}}'];
    }

    // Tests

    /**
     * @testdox Executing the script with an invalid JSON of modifications (argument) should fail without generating the output
     * @dataProvider providerInvalidModificationsJson
     */
    public function testInvalidModificationsJsonArgumentShouldFail(string $json): void
    {
        $output = $this->tempDir . '/output.sql';

        [$exitStatus, $stderr] = $this->runScript(['-q', '-i', $this->tempDir . '/input.sql', '-o', $output, '-m', $json]);

        $this->assertSame(1, $exitStatus);
        $this->assertStringContainsString('Failed to execute anonymization process', $stderr);
        $this->assertFileDoesNotExist($output);
    }

    /**
     * @testdox Executing the script with an invalid JSON of modifications (file) should fail without generating the output
     * @dataProvider providerInvalidModificationsJson
     */
    public function testInvalidModificationsJsonFileShouldFail(string $json): void
    {
        $output = $this->tempDir . '/output.sql';
        file_put_contents($this->tempDir . '/modifications.json', $json);

        [$exitStatus, $stderr] = $this->runScript(['-q', '-i', $this->tempDir . '/input.sql', '-o', $output, '-f', $this->tempDir . '/modifications.json']);

        $this->assertSame(1, $exitStatus);
        $this->assertStringContainsString('Failed to execute anonymization process', $stderr);
        $this->assertFileDoesNotExist($output);
    }

    /**
     * @testdox Executing the script with a valid JSON of modifications should anonymize the dump
     */
    public function testValidModificationsJsonShouldWork(): void
    {
        $output = $this->tempDir . '/output.sql';
        $json = '{"customers": {"2": {"quote": true, "format": "passthrough", "args": ["Fake Name"]}}}';

        [$exitStatus, $stderr] = $this->runScript(['-q', '-i', $this->tempDir . '/input.sql', '-o', $output, '-m', $json]);

        $this->assertSame(0, $exitStatus, $stderr);
        $this->assertSame("INSERT INTO `customers` VALUES (1,'Fake Name');\n", file_get_contents($output));
    }
}
