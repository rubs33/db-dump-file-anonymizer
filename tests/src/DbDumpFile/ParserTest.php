<?php

namespace Tests\Printi\DbDumpFile;

use Tests\Printi\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Printi\DbDumpFile\Parser;

#[CoversClass(Parser::class)]
final class ParserTest extends TestCase
{
    private static array $tempFiles = [];

    // Helper methods

    private static function openFile(string $mode, bool $existingFile = true, string $content = ''): mixed
    {
        $fileRecord = Fixtures::openFile($mode, $existingFile, $content);

        self::$tempFiles[] = $fileRecord;

        return $fileRecord['stream'];
    }

    // Hook methods

    /**
     * @inheritdoc
     */
    public static function tearDownAfterClass(): void
    {
        foreach (self::$tempFiles as $file) {
            if (is_resource($file['stream'])) {
                fclose($file['stream']);
            }
            if (is_file($file['file'])) {
                unlink($file['file']);
            }
        }
    }

    // Providers

    public static function providerValidInputStreams(): iterable
    {
        yield 'tmpfile' => [tmpfile()];

        yield 'file opened with mode "r"' => [self::openFile('r')];
        yield 'file opened with mode "r+"' => [self::openFile('r+')];
        yield 'file opened with mode "w+"' => [self::openFile('w+')];
        yield 'file opened with mode "a+"' => [self::openFile('a+')];
        yield 'file opened with mode "x+"' => [self::openFile('x+', false)];
        yield 'file opened with mode "c+"' => [self::openFile('c+')];

        yield 'file opened with mode "rb"' => [self::openFile('rb')];
        yield 'file opened with mode "r+b"' => [self::openFile('r+b')];
        yield 'file opened with mode "w+b"' => [self::openFile('w+b')];
        yield 'file opened with mode "a+b"' => [self::openFile('a+b')];
        yield 'file opened with mode "x+b"' => [self::openFile('x+b', false)];
        yield 'file opened with mode "c+b"' => [self::openFile('c+b')];

        yield 'STDIN' => [STDIN];
        yield 'php://stdin with mode "r"' => [fopen('php://stdin', 'r')];
        yield 'php://stdin with mode "rb"' => [fopen('php://stdin', 'rb')];
    }

    public static function providerValidConfig(): iterable
    {
        yield 'empty config' => [[]];

        yield 'read_buffer_size with value null' => [['read_buffer_size' => null]];
        yield 'read_buffer_size with value 1' => [['read_buffer_size' => 1]];

        yield 'tables with value null' => [['tables' => null]];
        yield 'tables with one element' => [['tables' => ['foo']]];
    }

    public static function providerInvalidResources(): iterable
    {
        yield 'null' => [null];
        yield 'string' => [''];
        yield 'integer' => [0];
        yield 'float' => [0.0];
        yield 'boolean' => [true];
        yield 'array' => [[]];
        yield 'object' => [new \stdClass()];
    }

    public static function providerInvalidInputStreams(): iterable
    {
        yield from self::providerInvalidResources();

        yield 'file opened with mode "w"' => [self::openFile('w')];
        yield 'file opened with mode "a"' => [self::openFile('a')];
        yield 'file opened with mode "x"' => [self::openFile('x', false)];
        yield 'file opened with mode "c"' => [self::openFile('c')];
        yield 'file opened with mode "wb"' => [self::openFile('wb')];
        yield 'file opened with mode "ab"' => [self::openFile('ab')];
        yield 'file opened with mode "xb"' => [self::openFile('xb', false)];
        yield 'file opened with mode "cb"' => [self::openFile('cb')];

        yield 'php://stdout with mode "w"' => [fopen('php://stdout', 'w')];
        yield 'php://stderr with mode "w"' => [fopen('php://stderr', 'w')];
        yield 'php://stdout with mode "wb"' => [fopen('php://stdout', 'wb')];
        yield 'php://stderr with mode "wb"' => [fopen('php://stderr', 'wb')];

        yield 'STDOUT' => [STDOUT];
        yield 'STDERR' => [STDERR];
    }

    public static function providerInvalidConfig(): iterable
    {
        yield 'read_buffer_size with value false' => [['read_buffer_size' => false]];
        yield 'read_buffer_size with value 1.1' => [['read_buffer_size' => 1.1]];
        yield 'read_buffer_size with value "a"' => [['read_buffer_size' => 'a']];
        yield 'read_buffer_size with value []' => [['read_buffer_size' => []]];
        yield 'read_buffer_size with value object' => [['read_buffer_size' => new \stdClass()]];

        yield 'tables with value false' => [['tables' => false]];
        yield 'tables with value 1' => [['tables' => 1]];
        yield 'tables with value 1.1' => [['tables' => 1.1]];
        yield 'tables with value "a"' => [['tables' => 'a']];
        yield 'tables with value object' => [['tables' => new \stdClass()]];
    }

    public static function providerInvalidInputContent(): iterable
    {
        yield 'Unexpected end of file (missing semicolon for insert statement)' => ['INSERT INTO `foo` VALUES ()'];
        yield 'Unexpected end of file (missing open parentheses for tuple)' => ['INSERT INTO `foo` VALUES 1);'];
        yield 'Unexpected end of file (missing close parentheses for tuple)' => ['INSERT INTO `foo` VALUES (1,'];
        yield 'Unexpected end of file (missing close quotes)' => ['INSERT INTO `foo` VALUES ("'];
        yield 'Unexpected end of file (missing comma after number)' => ['INSERT INTO `foo` VALUES (1'];
        yield 'Unexpected end of file (missing comma after string #1)' => ['INSERT INTO `foo` VALUES ("a"'];
        yield 'Unexpected end of file (missing comma after string #2)' => ["INSERT INTO `foo` VALUES ('a'"];
        yield 'Unexpected end of file (missing comma after NULL)' => ['INSERT INTO `foo` VALUES (NULL'];
        yield 'Unexpected end of file (escape at the end of a string)' => ["INSERT INTO `foo` VALUES ('a\\"];

        yield 'Unexpected token (missing semicolon for insert statement)' => ['INSERT INTO `foo` VALUES (1) INSERT INTO `foo` VALUES (2);'];
        yield 'Unexpected token (missing value for tuple)' => ['INSERT INTO `foo` VALUES ();'];
        yield 'Unexpected token (unexpected tuple value)' => ['INSERT INTO `foo` VALUES (a);'];
        yield 'Unexpected token (invalid token after number "1a")' => ['INSERT INTO `foo` VALUES (1a);'];
        yield 'Unexpected token (invalid token after number "1.a")' => ['INSERT INTO `foo` VALUES (1.a);'];
        yield 'Unexpected token (invalid token after number "1.1a")' => ['INSERT INTO `foo` VALUES (1.1a);'];
        yield 'Unexpected token (missing comma after number)' => ['INSERT INTO `foo` VALUES (1 1);'];
        yield 'Unexpected token (missing comma after string)' => ['INSERT INTO `foo` VALUES ("a" 1);'];
        yield 'Unexpected token (missing comma after special string)' => ['INSERT INTO `foo` VALUES ("a\"b" 1);'];
        yield 'Unexpected token (missing comma after NULL)' => ['INSERT INTO `foo` VALUES (NULL 1);'];
    }

    public static function providerReadBufferSizes(): iterable
    {
        foreach ([1, 2, 3, 7, 64, 4096, Parser::DEFAULT_READ_BUFFER_SIZE] as $size) {
            yield "read_buffer_size = {$size}" => [$size];
        }
    }

    public static function providerInvalidInputContentWithSmallBuffers(): iterable
    {
        foreach (self::providerInvalidInputContent() as $name => [$content]) {
            foreach ([1, 2, 3, 7] as $size) {
                yield "{$name} / read_buffer_size = {$size}" => [$content, $size];
            }
        }
    }

    /**
     * Content with values that cross the boundaries of small buffers
     * (escapes, both string delimiters, long strings, long whitespace sequences, numbers, NULL and UTF-8).
     * The tuples are repeated with different lengths, so the boundaries fall in different positions
     * of each kind of value (the INSERT detection reads 250 bytes ahead, so short contents are fully buffered).
     * @return array With 'content' (string), 'values' (expected values of each tuple) and 'separators'
     */
    private static function getBufferBoundaryFixture(): array
    {
        $tuples = [];
        $values = [];
        $separators = [];
        for ($i = 1; $i <= 40; $i++) {
            $padding = str_repeat('x', $i);
            $whitespaces = str_repeat(" \n\t", $i % 5 + 1);
            $number = '-' . str_repeat('1', $i % 7 + 1) . '.5e-3';
            $tuples[] = "({$i},'O\\'Brien{$padding}','a\\\\b','end\\\\',\"double \\\" quotes\",'',NULL,null,{$number},"
                . "'ação{$padding}\\n{$padding}'{$whitespaces})";
            $values[] = [
                1 => ['type' => 'number', 'raw' => (string) $i],
                2 => ['type' => 'string', 'raw' => "'O\\'Brien{$padding}'"],
                3 => ['type' => 'string', 'raw' => "'a\\\\b'"],
                4 => ['type' => 'string', 'raw' => "'end\\\\'"],
                5 => ['type' => 'string', 'raw' => '"double \" quotes"'],
                6 => ['type' => 'string', 'raw' => "''"],
                7 => ['type' => 'null', 'raw' => 'NULL'],
                8 => ['type' => 'null', 'raw' => 'null'],
                9 => ['type' => 'number', 'raw' => $number],
                10 => ['type' => 'string', 'raw' => "'ação{$padding}\\n{$padding}'"],
            ];
            $separators[] = ',' . $whitespaces;
        }
        array_pop($separators);

        $content = "-- header line longer than the small buffers\n"
            . 'INSERT INTO `foo` VALUES ' . implode('', array_map(
                fn($tuple, $separator) => $tuple . $separator,
                $tuples,
                array_merge($separators, ['']),
            ))
            . ";\n"
            . "INSERT INTO `bar` VALUES ('" . str_repeat('z', 3000) . "');\n"
            . '-- trailer without new line';

        return ['content' => $content, 'values' => $values, 'separators' => $separators];
    }

    // Tests

    /**
     * @testdox Constructor setting a valid input stream should work
     * @dataProvider providerValidInputStreams
     */
    public function testConstructorSettingValidInputStreamShouldWork($input): void
    {
        // Prepare
        $validConfig = [];

        // Execute
        $obj = new Parser($input, $validConfig);

        // Expect
        $this->assertInstanceOf(Parser::class, $obj);
    }

    /**
     * @testdox Constructor setting an invalid input stream should break
     * @dataProvider providerInvalidInputStreams
     */
    public function testConstructorSetingInvalidInputStreamShouldBreak($input)
    {
        // Prepare
        $validConfig = [];

        // Expect
        $this->expectException(\InvalidArgumentException::class);

        // Execute
        new Parser($input, $validConfig);
    }

    /**
     * @testdox Constructor setting a valid config should work
     * @dataProvider providerValidConfig
     */
    public function testConstructorSettingValidConfigShouldWork($config): void
    {
        // Prepare
        $validInput = STDIN;

        // Execute
        $obj = new Parser($validInput, $config);

        // Expect
        $this->assertInstanceOf(Parser::class, $obj);
    }

    /**
     * @testdox Constructor setting an invalid config should break
     * @dataProvider providerInvalidConfig
     */
    public function testConstructorSettingInvalidConfigShouldBreak($config): void
    {
        // Prepare
        $validInput = STDIN;

        // Expect
        $this->expectException(\InvalidArgumentException::class);

        // Execute
        new Parser($validInput, $config);
    }

    /**
     * @testdox Testing the main parser method should work
     */
    public function testParseTokens()
    {
        // Prepare
        $fileContent = <<<EOF
-- Some comment
INSERT INTO `foo` VALUES ('foo', 1, -1, 1.1, 1e1, NULL), ('foo', 2, -2, 2.2, 2e2, NULL),
('foo', 3, -3, 3.3, 3e3, NULL);

INSERT INTO `bar` VALUES ('bar', 1, -1, 1.1, 1e1, NULL), ('bar', 2, -2, 2.2, 2e2, NULL),
('bar', 3, -3, 3.3, 3e3, NULL);

INSERT INTO `baz` VALUES ('baz', 1, -1, 1.1, 1e1, NULL), ('baz', 2, -2, 2.2, 2e2, NULL),
('baz', 3, -3, 3.3, 3e3, NULL);

-- Other comment
EOF;

        $input = self::openFile('r+', true, $fileContent);
        $config = ['tables' => ['foo', 'bar']];
        $parser = new Parser($input, $config);

        // Execute
        $iterator = $parser->parseTokens();
        $tokens = iterator_to_array($iterator);

        // Expect
        $this->assertInstanceOf(\Iterator::class, $iterator);

        $expectedTokens = [
            ['type' => Parser::TYPE_CHUNK_TOKEN, 'raw' => "-- Some comment\n"],
            ['type' => Parser::TYPE_INSERT_START_TOKEN, 'raw' => 'INSERT INTO `foo` VALUES ', 'table' => 'foo'],
            [
                'type' => Parser::TYPE_INSERT_TUPLE_TOKEN,
                'raw' => "('foo', 1, -1, 1.1, 1e1, NULL)",
                'values' => [
                    1 => ['type' => 'string', 'raw' => "'foo'"],
                    2 => ['type' => 'number', 'raw' => '1'],
                    3 => ['type' => 'number', 'raw' => '-1'],
                    4 => ['type' => 'number', 'raw' => '1.1'],
                    5 => ['type' => 'number', 'raw' => '1e1'],
                    6 => ['type' => 'null', 'raw' => 'NULL'],
                ],
            ],
            ['type' => Parser::TYPE_INSERT_TUPLE_SEPARATOR_TOKEN, 'raw' => ', '],
            [
                'type' => Parser::TYPE_INSERT_TUPLE_TOKEN,
                'raw' => "('foo', 2, -2, 2.2, 2e2, NULL)",
                'values' => [
                    1 => ['type' => 'string', 'raw' => "'foo'"],
                    2 => ['type' => 'number', 'raw' => '2'],
                    3 => ['type' => 'number', 'raw' => '-2'],
                    4 => ['type' => 'number', 'raw' => '2.2'],
                    5 => ['type' => 'number', 'raw' => '2e2'],
                    6 => ['type' => 'null', 'raw' => 'NULL'],
                ],
            ],
            ['type' => Parser::TYPE_INSERT_TUPLE_SEPARATOR_TOKEN, 'raw' => ",\n"],
            [
                'type' => Parser::TYPE_INSERT_TUPLE_TOKEN,
                'raw' => "('foo', 3, -3, 3.3, 3e3, NULL)",
                'values' => [
                    1 => ['type' => 'string', 'raw' => "'foo'"],
                    2 => ['type' => 'number', 'raw' => '3'],
                    3 => ['type' => 'number', 'raw' => '-3'],
                    4 => ['type' => 'number', 'raw' => '3.3'],
                    5 => ['type' => 'number', 'raw' => '3e3'],
                    6 => ['type' => 'null', 'raw' => 'NULL'],
                ],
            ],
            ['type' => Parser::TYPE_INSERT_END_TOKEN, 'raw' => ";\n\n"],
            ['type' => Parser::TYPE_INSERT_START_TOKEN, 'raw' => 'INSERT INTO `bar` VALUES ', 'table' => 'bar'],
            [
                'type' => Parser::TYPE_INSERT_TUPLE_TOKEN,
                'raw' => "('bar', 1, -1, 1.1, 1e1, NULL)",
                'values' => [
                    1 => ['type' => 'string', 'raw' => "'bar'"],
                    2 => ['type' => 'number', 'raw' => '1'],
                    3 => ['type' => 'number', 'raw' => '-1'],
                    4 => ['type' => 'number', 'raw' => '1.1'],
                    5 => ['type' => 'number', 'raw' => '1e1'],
                    6 => ['type' => 'null', 'raw' => 'NULL'],
                ],
            ],
            ['type' => Parser::TYPE_INSERT_TUPLE_SEPARATOR_TOKEN, 'raw' => ', '],
            [
                'type' => Parser::TYPE_INSERT_TUPLE_TOKEN,
                'raw' => "('bar', 2, -2, 2.2, 2e2, NULL)",
                'values' => [
                    1 => ['type' => 'string', 'raw' => "'bar'"],
                    2 => ['type' => 'number', 'raw' => '2'],
                    3 => ['type' => 'number', 'raw' => '-2'],
                    4 => ['type' => 'number', 'raw' => '2.2'],
                    5 => ['type' => 'number', 'raw' => '2e2'],
                    6 => ['type' => 'null', 'raw' => 'NULL'],
                ],
            ],
            ['type' => Parser::TYPE_INSERT_TUPLE_SEPARATOR_TOKEN, 'raw' => ",\n"],
            [
                'type' => Parser::TYPE_INSERT_TUPLE_TOKEN,
                'raw' => "('bar', 3, -3, 3.3, 3e3, NULL)",
                'values' => [
                    1 => ['type' => 'string', 'raw' => "'bar'"],
                    2 => ['type' => 'number', 'raw' => '3'],
                    3 => ['type' => 'number', 'raw' => '-3'],
                    4 => ['type' => 'number', 'raw' => '3.3'],
                    5 => ['type' => 'number', 'raw' => '3e3'],
                    6 => ['type' => 'null', 'raw' => 'NULL'],
                ],
            ],
            ['type' => Parser::TYPE_INSERT_END_TOKEN, 'raw' => ";\n\n"],
            ['type' => Parser::TYPE_CHUNK_TOKEN, 'raw' => "INSERT INTO `baz` VALUES ('baz', 1, -1, 1.1, 1e1, NULL), ('baz', 2, -2, 2.2, 2e2, NULL),\n"],
            ['type' => Parser::TYPE_CHUNK_TOKEN, 'raw' => "('baz', 3, -3, 3.3, 3e3, NULL);\n"],
            ['type' => Parser::TYPE_CHUNK_TOKEN, 'raw' => "\n"],
            ['type' => Parser::TYPE_CHUNK_TOKEN, 'raw' => '-- Other comment'],
        ];
        $this->assertSame($expectedTokens, $tokens);
    }

    /**
     * @testdox Testing the main parser method with invalid input content should break
     * @dataProvider providerInvalidInputContent
     */
    public function testExecuteWithInvalidInputContentShouldBreak($fileContent)
    {
        // Prepare
        $input = self::openFile('r+', true, $fileContent);
        $config = ['tables' => ['foo']];
        $parser = new Parser($input, $config);

        // Expect
        $this->expectException(\RuntimeException::class);

        // Execute
        foreach ($parser->parseTokens() as $token) {
            // do nothing (only traverse the iterator to throw the expected exception)
        }
    }

    /**
     * @testdox Parsing values that cross the boundaries of the read buffer should work
     * @dataProvider providerReadBufferSizes
     */
    public function testParseTokensWithValuesCrossingBufferBoundaries(int $readBufferSize): void
    {
        // Prepare
        $fixture = self::getBufferBoundaryFixture();
        $input = self::openFile('r+', true, $fixture['content']);
        $parser = new Parser($input, ['tables' => ['foo'], 'read_buffer_size' => $readBufferSize]);

        // Execute
        $tokens = iterator_to_array($parser->parseTokens());

        // Expect: no byte is lost or duplicated
        $this->assertSame($fixture['content'], implode('', array_column($tokens, 'raw')));

        // Expect: only the INSERT of the configured table is parsed
        $tokensOfType = fn(int $type) => array_values(array_filter($tokens, fn($token) => $token['type'] === $type));
        $this->assertSame(['foo'], array_column($tokensOfType(Parser::TYPE_INSERT_START_TOKEN), 'table'));

        // Expect: the tuple values and the separators (with the whitespaces) are detected correctly
        $this->assertSame($fixture['values'], array_column($tokensOfType(Parser::TYPE_INSERT_TUPLE_TOKEN), 'values'));
        $this->assertSame($fixture['separators'], array_column($tokensOfType(Parser::TYPE_INSERT_TUPLE_SEPARATOR_TOKEN), 'raw'));
    }

    /**
     * @testdox Parsing a line longer than the read buffer should keep the line intact
     * @dataProvider providerReadBufferSizes
     */
    public function testParseTokensWithLinesLongerThanBuffer(int $readBufferSize): void
    {
        // Prepare
        $fileContent = str_repeat('a', 5000) . "\n" . str_repeat('b', 10) . "\n\n" . str_repeat('c', 5000);
        $input = self::openFile('r+', true, $fileContent);
        $parser = new Parser($input, ['tables' => ['foo'], 'read_buffer_size' => $readBufferSize]);

        // Execute
        $tokens = iterator_to_array($parser->parseTokens());

        // Expect
        $this->assertSame($fileContent, implode('', array_column($tokens, 'raw')));
        foreach ($tokens as $token) {
            $this->assertSame(Parser::TYPE_CHUNK_TOKEN, $token['type']);
            // A chunk token never contains a new line in the middle
            $this->assertFalse(strpos(rtrim($token['raw'], "\n"), "\n"));
        }
    }

    /**
     * @testdox Parsing invalid input content with small buffers should break
     * @dataProvider providerInvalidInputContentWithSmallBuffers
     */
    public function testExecuteWithInvalidInputContentAndSmallBuffersShouldBreak(string $fileContent, int $readBufferSize): void
    {
        // Prepare
        $input = self::openFile('r+', true, $fileContent);
        $parser = new Parser($input, ['tables' => ['foo'], 'read_buffer_size' => $readBufferSize]);

        // Expect
        $this->expectException(\RuntimeException::class);

        // Execute
        foreach ($parser->parseTokens() as $token) {
            // do nothing (only traverse the iterator to throw the expected exception)
        }
    }
}
