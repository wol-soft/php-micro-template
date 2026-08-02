<?php

declare(strict_types = 1);

namespace PHPMicroTemplate\Tests;

use ArrayAccess;
use ArrayObject;
use PHPMicroTemplate\Exception\FileSystemException;
use PHPMicroTemplate\Exception\SyntaxErrorException;
use PHPMicroTemplate\Exception\UndefinedSymbolException;
use PHPMicroTemplate\Render;
use PHPMicroTemplate\RenderConfig;
use PHPMicroTemplate\Tests\Objects\Product;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Class RenderTest
 *
 * @package PHPMicroTemplate\Tests
 */
class RenderTest extends TestCase
{
    /** @var Render */
    private $render;
    /** @var Render */
    private $renderWithAutoIndent;

    public function setUp(): void
    {
        $this->render = new Render(__DIR__ . '/Templates/');
        $this->renderWithAutoIndent = new Render(__DIR__ . '/Templates/', new RenderConfig(true));
    }

    public function testRenderNotExistingTemplate(): void
    {
        $this->expectException(FileSystemException::class);
        $this->expectExceptionMessage('Template nonExistingTemplate.template not found');
        $this->render->renderTemplate('nonExistingTemplate.template');
    }

    public function testUndefinedVariable(): void
    {
        $this->expectException(UndefinedSymbolException::class);
        $this->expectExceptionMessage('Unknown variable name');
        $this->render->renderTemplate('undefinedVariable.template', ['firstname' => 'John']);
    }

    public function testMethodOnNonObject(): void
    {
        $this->expectException(UndefinedSymbolException::class);
        $this->expectExceptionMessage('Trying to call getPrice on non-object product');
        $this->render->renderTemplate('undefinedMethod.template', ['product' => false]);
    }

    public function testUndefinedMethod(): void
    {
        $this->expectException(UndefinedSymbolException::class);
        $this->expectExceptionMessage('Function getPrice on object product not callable');
        $this->render->renderTemplate('undefinedMethod.template', ['product' => new Product('Wood', true)]);
    }

    /**
     * Check basic template rendering including variable replacement, nested loops and nested conditions
     */
    public function testRenderTemplate(): void
    {
        $products = [
            new Product('Hammer', true),
            new Product('Nails', false),
            new Product('Wood', true, ['Oak', 'Birch']),
        ];

        $result = $this->render->renderTemplate(
            'productList.template',
            [
                'pageTitle' => 'Available products',
                'productHead' => 'Product',
                'products' => $products,
                'showVersion' => true
            ]
        );

        $this->assertXmlStringEqualsXmlFile(__DIR__ . '/Expectations/productListWithVersion', $result);

        $products = [
            new Product('Hammer', true),
            new Product('Nails', true),
            new Product('Wood', false),
        ];

        $result = $this->render->renderTemplate(
            'productList.template',
            [
                'pageTitle' => 'Available products',
                'productHead' => 'Product',
                'products' => $products,
                'showVersion' => false
            ]
        );

        $this->assertXmlStringEqualsXmlFile(__DIR__ . '/Expectations/productListWithoutVersion', $result);
    }

    /**
     * Check loop with key value pair
     */
    public function testRenderKeyValueLoopTemplate(): void
    {
        $products = [
            'Best Hammer' => new Product('Hammer', true),
            'Nailed It'   => new Product('Nails', false),
            'Most Solid'  => new Product('Wood', true, ['Oak', 'Birch']),
        ];

        $result = $this->render->renderTemplate('keyValueLoop.template', ['products' => $products]);

        $this->assertXmlStringEqualsXmlFile(__DIR__ . '/Expectations/keyValueLoop', $result);
    }

    /**
     * @dataProvider conditionalsDataProvider
     */
    public function testConditionals(string $condition, string $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            $this->render->renderTemplateString("{% if $condition %}true{% else %}false{% endif %}", ['true' => true])
        );
    }

    public function conditionalsDataProvider(): array
    {
        return [
            'simple true'    => ['true', 'true'],
            'simple not'     => ['not true', 'false'],
            'and both false' => ['not true and not true', 'false'],
            'and one false'  => ['not true and true', 'false'],
            'and both true'  => ['true and true', 'true'],
            'or both false' => ['not true or not true', 'false'],
            'or one false'  => ['not true or true', 'true'],
            'or both true'  => ['true or true', 'true'],
            'and before or' => ['not true and true or not true', 'false'],
        ];
    }

    public function testInvalidConditionalThrowsSyntaxError(): void
    {
        $this->expectException(SyntaxErrorException::class);
        $this->expectExceptionMessage('Invalid condition object.bla(');
        $this->render->renderTemplateString('{% if object.bla( %}content{% endif %}');
    }

    /**
     * `{# ... #}` blocks are template-time comments: stripped before any other processing
     * happens and never reach the rendered output.
     *
     * @dataProvider commentDataProvider
     */
    public function testCommentBlockIsStrippedFromOutput(string $template, string $expected, array $variables = []): void
    {
        $this->assertSame($expected, $this->render->renderTemplateString($template, $variables));
    }

    public function commentDataProvider(): array
    {
        return [
            'single-line comment stripped' => [
                'before {# this should not appear #} after',
                'before  after',
            ],
            'multi-line comment stripped' => [
                "before {# this comment\nspans multiple\nlines #} after",
                'before  after',
            ],
            'multiple comments stripped' => [
                '{# one #}A{# two #}B{# three #}',
                'AB',
            ],
            'comment between control structures' => [
                '{% if flag %}body{% endif %}{# trailing comment #}',
                'body',
                ['flag' => true],
            ],
            'comment inside conditional body is also stripped' => [
                '{% if flag %}before{# inner #}after{% endif %}',
                'beforeafter',
                ['flag' => true],
            ],
            'empty comment' => [
                'a{##}b',
                'ab',
            ],
            'comment with only whitespace' => [
                'a{#   #}b',
                'ab',
            ],
        ];
    }

    /**
     * The comment-stripping pass runs before variable resolution, so a `{{ var }}` inside a
     * comment is removed along with the comment and never triggers UndefinedSymbolException
     * for a variable that was never provided.
     */
    public function testCommentSuppressesUndefinedVariableInsideIt(): void
    {
        $this->assertSame(
            'kept',
            $this->render->renderTemplateString('{# {{ someUndefinedVariable }} #}kept')
        );
    }

    /**
     * Comment-stripping must also run before control-structure indexing — a `{% if %}`
     * inside a comment is removed and never participates in the if/endif pairing logic, so
     * an unmatched-tag scenario inside a comment does not corrupt subsequent real
     * conditionals.
     */
    public function testCommentSuppressesControlStructureInsideIt(): void
    {
        $this->assertSame(
            'after',
            $this->render->renderTemplateString(
                '{# {% if undefined %}never{% endif %} #}after'
            )
        );
    }

    /**
     * The first `#}` closes the comment — comments are non-greedy. A literal `#}` cannot
     * appear inside a comment block; if a template author needs that sequence in the
     * rendered output, they must place it outside any comment.
     */
    public function testCommentNonGreedyClosesAtFirstHashBrace(): void
    {
        $this->assertSame(
            ' between #} outer',
            $this->render->renderTemplateString('{# first #} between #} outer')
        );
    }

    /**
     * A `{# ... #}` comment - possibly spanning multiple lines itself - that is the only non-whitespace content on
     * its line contributes nothing to the rendered output when autoIndent is enabled, exactly like a standalone
     * {% if %}/{% foreach %} tag: its own leading whitespace and trailing newline are stripped too, so it doesn't
     * leave a blank line behind.
     *
     * @dataProvider standaloneCommentDataProvider
     */
    public function testStandaloneCommentTagsAreTrimmed(string $template, string $expected): void
    {
        $this->assertSame($expected, $this->renderWithAutoIndent->renderTemplateString($template));
    }

    public function standaloneCommentDataProvider(): array
    {
        // See standaloneControlTagDataProvider() for why each heredoc is assigned to a variable (terminated by
        // ";") rather than written inline as an array element (which would require a "," terminator) - PHP 7.1/7.2
        // only recognize a heredoc closing marker when nothing but a semicolon follows it on that line.
        $singleLineTemplate = <<<'TEMPLATE'
before
{# a standalone comment #}
after
TEMPLATE;

        $multiLineTemplate = <<<'TEMPLATE'
before
{# a
multi
line
comment #}
after
TEMPLATE;

        $indentedTemplate = <<<'TEMPLATE'
before
    {# indented comment #}
after
TEMPLATE;

        // The expected value for this case has a trailing space after "leading" (the space between "leading" and
        // the comment tag is preserved since the tag isn't standalone) - built via implode() rather than a heredoc
        // so that trailing space can't be silently stripped by an editor the way trailing heredoc whitespace could
        // be (see testAutoIndentDisabledLeavesTemplateUnchanged()).
        $precededByContentTemplate = <<<'TEMPLATE'
before
leading {# comment #}
after
TEMPLATE;
        $precededByContentExpected = implode("\n", ['before', 'leading ', 'after']);

        $followedByContentTemplate = <<<'TEMPLATE'
before
{# comment #} trailing
after
TEMPLATE;
        $followedByContentExpected = <<<'EXPECTED'
before
 trailing
after
EXPECTED;

        return [
            'standalone single-line comment leaves no blank line' => [
                $singleLineTemplate,
                "before\nafter",
            ],
            'standalone multi-line comment leaves no blank line' => [
                $multiLineTemplate,
                "before\nafter",
            ],
            'standalone indented comment leaves no blank line' => [
                $indentedTemplate,
                "before\nafter",
            ],
            'non-standalone comment preceded by real content on the same line' => [
                $precededByContentTemplate,
                $precededByContentExpected,
            ],
            'non-standalone comment followed by real content on the same line' => [
                $followedByContentTemplate,
                $followedByContentExpected,
            ],
        ];
    }

    /**
     * Without autoIndent enabled, a standalone comment behaves exactly like the pre-existing plain `{# ... #}`
     * stripping: only the tag itself is removed, leaving its own line as a blank line behind - the same opt-in
     * behavior testAutoIndentDisabledLeavesTemplateUnchanged() documents for control structure tags.
     */
    public function testAutoIndentDisabledLeavesStandaloneCommentBlankLine(): void
    {
        $template = <<<'TEMPLATE'
before
{# a standalone comment #}
after
TEMPLATE;

        $this->assertSame("before\n\nafter", $this->render->renderTemplateString($template));
    }

    /**
     * A standalone comment positioned at the very end of the template, with no trailing newline or content after
     * it, must be treated the same as a standalone control structure tag in the same position (see
     * testStandaloneTagAtEndOfTemplateWithNoTrailingNewline()): end of template counts as much as "nothing
     * meaningful follows the tag" as an actual trailing newline does.
     */
    public function testStandaloneCommentAtEndOfTemplateWithNoTrailingNewline(): void
    {
        // deliberately no trailing newline after the comment tag - that's the case under test
        $template = <<<'TEMPLATE'
before
{# comment #}
TEMPLATE;

        $this->assertSame("before\n", $this->renderWithAutoIndent->renderTemplateString($template));
    }

    /**
     * Test if the syntax of a template is whitespace tolerant
     */
    public function testWhitespaceTolerance(): void
    {
        $products = [
            new Product('Hammer', true),
            new Product('Nails', true),
            new Product('Wood', false),
        ];

        $result = $this->render->renderTemplate(
            'productListWhitespaceTolerance.template',
            [
                'pageTitle' => 'Available products',
                'productHead' => 'Product',
                'products' => $products,
                // null must be handled as a falsely value
                'showVersion' => null
            ]
        );

        $this->assertXmlStringEqualsXmlFile(__DIR__ . '/Expectations/productListWithoutVersion', $result);
    }

    /**
     * A {% foreach %}/{% if %}/{% else %}/{% endif %}/{% endforeach %} tag that is the only non-whitespace content
     * on its line contributes nothing to the rendered output - its own leading whitespace and trailing newline are
     * stripped, when whitespace control is enabled. Since these templates use the conventional style of indenting
     * a block's body one level deeper than the tag, the body is also auto-dedented to match the tag's own column
     * (see testBodyDedentAutoDetectsTagBodyIndentDifference for dedent-focused coverage in isolation).
     *
     * @dataProvider standaloneControlTagDataProvider
     */
    public function testStandaloneControlTagsAreTrimmed(string $template, array $variables, string $expected): void
    {
        $this->assertSame($expected, $this->renderWithAutoIndent->renderTemplateString($template, $variables));
    }

    public function standaloneControlTagDataProvider(): array
    {
        // Heredocs are assigned to a variable and terminated with ";" on their own statement rather than being
        // written inline as an array element terminated by ",": PHP before 7.3 only recognizes a heredoc/nowdoc
        // closing marker when nothing but a semicolon follows it on that line, so a marker immediately followed
        // by a comma (as an inline array element would require) fails to parse on PHP 7.1/7.2.
        $standaloneForeachTemplate = <<<'TEMPLATE'
before
    {% foreach items as item %}
        [{{ item }}]
    {% endforeach %}
after
TEMPLATE;
        $standaloneForeachExpected = <<<'EXPECTED'
before
    [a]
    [b]
after
EXPECTED;

        $standaloneIfTemplate = <<<'TEMPLATE'
before
    {% if flag %}
        shown
    {% endif %}
after
TEMPLATE;
        $standaloneIfTrueExpected = <<<'EXPECTED'
before
    shown
after
EXPECTED;

        $standaloneIfElseTemplate = <<<'TEMPLATE'
before
    {% if flag %}
        true branch
    {% else %}
        false branch
    {% endif %}
after
TEMPLATE;
        $standaloneIfElseTrueExpected = <<<'EXPECTED'
before
    true branch
after
EXPECTED;
        $standaloneIfElseFalseExpected = <<<'EXPECTED'
before
    false branch
after
EXPECTED;

        return [
            'standalone foreach leaves no blank line' => [
                $standaloneForeachTemplate,
                ['items' => ['a', 'b']],
                $standaloneForeachExpected,
            ],
            'standalone if (true) leaves no blank line' => [
                $standaloneIfTemplate,
                ['flag' => true],
                $standaloneIfTrueExpected,
            ],
            'standalone if (false) leaves no blank line' => [
                $standaloneIfTemplate,
                ['flag' => false],
                "before\nafter",
            ],
            'standalone if/else, true branch' => [
                $standaloneIfElseTemplate,
                ['flag' => true],
                $standaloneIfElseTrueExpected,
            ],
            'standalone if/else, false branch' => [
                $standaloneIfElseTemplate,
                ['flag' => false],
                $standaloneIfElseFalseExpected,
            ],
        ];
    }

    /**
     * A tag that is NOT alone on its own line (real content precedes or follows it on the same line) must keep its
     * surrounding whitespace exactly as written - trimming must never remove a meaningful separating space.
     *
     * @dataProvider inlineControlTagDataProvider
     */
    public function testInlineControlTagsPreserveSurroundingWhitespace(string $template, array $variables, string $expected): void
    {
        $this->assertSame($expected, $this->renderWithAutoIndent->renderTemplateString($template, $variables));
    }

    public function inlineControlTagDataProvider(): array
    {
        return [
            'if/endif inline within a single line' => [
                '<li>{% if visible %}shown{% endif %}</li>',
                ['visible' => true],
                '<li>shown</li>',
            ],
            'if/else/endif inline, false branch' => [
                '<li>{% if visible %}shown{% else %}hidden{% endif %}</li>',
                ['visible' => false],
                '<li>hidden</li>',
            ],
            'tag preceded by real content on the same line keeps a meaningful separating space' => [
                'label {% if flag %}x{% endif %} tail',
                ['flag' => true],
                'label x tail',
            ],
        ];
    }

    /**
     * A tag only counts as "alone on its own line" - eligible for the leading-whitespace-and-trailing-newline trim -
     * when BOTH sides confirm it. If real content precedes the tag on the same line, the fact that a newline happens
     * to follow the tag must not cause that newline to be swallowed: doing so would merge the following line into
     * this one. Regression test for exactly that bug, found while wiring this feature into a real multi-line
     * docblock template that mixes inline tag usage with normal line breaks.
     */
    public function testTagPrecededByContentDoesNotConsumeFollowingNewline(): void
    {
        $template = <<<'TEMPLATE'
/**
 * Foo
{% if namespace %} * @package {{ namespace }} {% endif %}
 * next line
 */
TEMPLATE;

        // trailing space after "App" is intentional: the one space that separated {{ namespace }} from the inline
        // {% endif %} in the template. Asserted separately via rtrim()+assertSame() on that one line, rather than
        // relying on a trailing space at the end of a heredoc line, which an editor could silently strip unnoticed.
        $result = $this->renderWithAutoIndent->renderTemplateString($template, ['namespace' => 'App']);
        $resultLines = explode("\n", $result);

        $expected = <<<'EXPECTED'
/**
 * Foo
 * @package App
 * next line
 */
EXPECTED;

        $this->assertSame(' * @package App ', $resultLines[2]);
        $this->assertSame($expected, implode("\n", array_map('rtrim', $resultLines)));
    }

    /**
     * A block tag's body is dedented by the difference between the body's first line's column and the tag's own
     * column, detected per tag rather than configured - not by the tag's own (variable) absolute column, and not
     * by a caller-supplied fixed width - regardless of how deeply the tag itself is nested in the template source.
     * This makes every {% foreach %}/{% if %} transparent: its body ends up at the same column as its own tag,
     * whatever that column is, instead of accumulating one extra indent level per level of template nesting.
     *
     * @dataProvider autoDetectedDedentDataProvider
     */
    public function testBodyDedentAutoDetectsTagBodyIndentDifference(string $template, array $variables, string $expected): void
    {
        $render = new Render('', new RenderConfig(true));

        $this->assertSame($expected, $render->renderTemplateString($template, $variables));
    }

    public function autoDetectedDedentDataProvider(): array
    {
        // See standaloneControlTagDataProvider() for why each heredoc is assigned to a variable (terminated by
        // ";") rather than written inline as an array element (which would require a "," terminator).
        $nestedIfTemplate = <<<'TEMPLATE'
class Foo
{
    public function bar()
    {
        {% if flag %}
            statement();
        {% endif %}
    }
}
TEMPLATE;
        $nestedIfExpected = <<<'EXPECTED'
class Foo
{
    public function bar()
    {
        statement();
    }
}
EXPECTED;

        $nestedForeachTemplate = <<<'TEMPLATE'
class Foo
{
    {% foreach props as prop %}
        {% foreach prop.attrs as attr %}
            #[{{ attr }}]
        {% endforeach %}
        member {{ prop.name }};
    {% endforeach %}
}
TEMPLATE;
        $nestedForeachExpected = <<<'EXPECTED'
class Foo
{
    #[A]
    #[B]
    member x;
}
EXPECTED;

        $ifInsideForeachTemplate = <<<'TEMPLATE'
class Foo
{
    {% foreach items as item %}
        {% if item.visible %}
            public ${{ item.name }};
        {% endif %}
    {% endforeach %}
}
TEMPLATE;
        $ifInsideForeachExpected = <<<'EXPECTED'
class Foo
{
    public $a;
    public $c;
}
EXPECTED;

        $sameColumnTemplate = <<<'TEMPLATE'
class Foo
{
    {% if flag %}
    statement();
    {% endif %}
}
TEMPLATE;
        $sameColumnExpected = <<<'EXPECTED'
class Foo
{
    statement();
}
EXPECTED;

        return [
            'if nested two real levels deep keeps its real depth, not its template-literal depth' => [
                $nestedIfTemplate,
                ['flag' => true],
                $nestedIfExpected,
            ],
            'nested foreach does not accumulate indentation per level' => [
                $nestedForeachTemplate,
                ['props' => [(object) ['attrs' => ['A', 'B'], 'name' => 'x']]],
                $nestedForeachExpected,
            ],
            'if nested inside foreach stays transparent for both, filtering still works' => [
                $ifInsideForeachTemplate,
                [
                    'items' => [
                        (object) ['visible' => true, 'name' => 'a'],
                        (object) ['visible' => false, 'name' => 'b'],
                        (object) ['visible' => true, 'name' => 'c'],
                    ],
                ],
                $ifInsideForeachExpected,
            ],
            'body at the same column as its tag is left alone - the detected difference is 0' => [
                $sameColumnTemplate,
                ['flag' => true],
                $sameColumnExpected,
            ],
        ];
    }

    /**
     * Without a RenderConfig with autoIndent enabled (Render's default, and the instance setUp() builds as
     * $this->render), neither standalone-tag trimming nor body dedent applies. The lines the {% if %}/{% endif %}
     * tags occupied are left behind as whitespace-only lines (their own leading indent, now with nothing after it)
     * - the exact pre-existing behavior this whole feature is opt-in to fix, preserved byte-for-byte for anyone who
     * doesn't opt in. Built via explode()/implode() rather than a heredoc so the two whitespace-only lines under
     * test can't be silently stripped by an editor the way trailing heredoc whitespace could be.
     */
    public function testAutoIndentDisabledLeavesTemplateUnchanged(): void
    {
        $template = <<<'TEMPLATE'
class Foo
{
    public function bar()
    {
        {% if flag %}
            statement();
        {% endif %}
    }
}
TEMPLATE;

        $eightSpaces = str_repeat(' ', 8);
        $expected = implode("\n", [
            'class Foo',
            '{',
            '    public function bar()',
            '    {',
            $eightSpaces,
            '            statement();',
            $eightSpaces,
            '    }',
            '}',
        ]);

        $this->assertSame($expected, $this->render->renderTemplateString($template, ['flag' => true]));
    }

    /**
     * @dataProvider emptyBlockBodyDataProvider
     */
    public function testEmptyBlockBodyProducesNoResidualWhitespace(string $template, array $variables, string $expected): void
    {
        $this->assertSame($expected, $this->renderWithAutoIndent->renderTemplateString($template, $variables));
    }

    public function emptyBlockBodyDataProvider(): array
    {
        // See standaloneControlTagDataProvider() for why each heredoc is assigned to a variable (terminated by
        // ";") rather than written inline as an array element (which would require a "," terminator).
        $emptyForeachTemplate = <<<'TEMPLATE'
before
    {% foreach items as item %}
    {% endforeach %}
after
TEMPLATE;

        $emptyIfTemplate = <<<'TEMPLATE'
before
    {% if flag %}
    {% endif %}
after
TEMPLATE;

        return [
            'foreach over an empty array leaves nothing behind' => [
                $emptyForeachTemplate,
                ['items' => []],
                "before\nafter",
            ],
            'if with an empty true branch leaves nothing behind' => [
                $emptyIfTemplate,
                ['flag' => true],
                "before\nafter",
            ],
        ];
    }

    /**
     * A standalone tag positioned at the very end of the template, with no trailing newline or content after it,
     * must not raise a PHP warning for an undefined "closeTrail" match group. PCRE omits a named capture group from
     * the matches array entirely (rather than including it as an empty string) when it is both optional and the
     * last group in the pattern and it did not participate in the match - and end of template is just as much
     * "nothing meaningful follows the tag" as an actual trailing newline is, so it must still count as standalone.
     */
    public function testStandaloneTagAtEndOfTemplateWithNoTrailingNewline(): void
    {
        // deliberately no trailing newline after {% endif %} - that's the case under test
        $template = <<<'TEMPLATE'
before
    {% if flag %}
        shown
    {% endif %}
TEMPLATE;

        $this->assertSame(
            "before\n    shown\n",
            $this->renderWithAutoIndent->renderTemplateString($template, ['flag' => true])
        );
    }

    /**
     * Test multiple loops following each other
     *
     * @dataProvider loopDataProvider
     */
    public function testMultipleLoops($products): void
    {
        $result = $this->render->renderTemplate('multipleLoops.template', ['products' => $products]);

        $this->assertXmlStringEqualsXmlFile(__DIR__ . '/Expectations/multipleLoops', $result);
    }

    public function loopDataProvider(): array
    {
        $products = [
            new Product('Hammer', true),
            new Product('Nails', true),
            new Product('Wood', true),
        ];

        return [
            'array' => [$products],
            'ArrayObject' => [new ArrayObject($products)],
        ];
    }

    /**
     * Test if function parameters are resolved.
     * Test nested function calls and multiple parameters for a single function
     */
    public function testFunctionParameter(): void
    {
        $products = [
            new Product('Hammer', true),
            new Product('Wood', true, ['Oak', 'Birch']),
        ];

        $result = $this->render->renderTemplate(
            'parameters.template',
            [
                'viewHelper' => new ViewHelper(),
                'products' => $products,
                'productsNextPage' => 5
            ]
        );

        $this->assertXmlStringEqualsXmlFile(__DIR__ . '/Expectations/parameters', $result);
    }

    /**
     * Test if invalid function parameters throw a SyntaxErrorException
     *
     * @dataProvider invalidFunctionParameterProvider
     *
     * @param string $template
     */
    public function testInvalidFunctionParameter(string $template): void
    {
        $this->expectException(SyntaxErrorException::class);
        $this->render->renderTemplateString(
            $template,
            [
                'viewHelper' => new ViewHelper(),
                'variable' => 10,
                'object' => new class () {
                    public function get()
                    {
                        return 11;
                    }
                }
            ]
        );
    }

    public function invalidFunctionParameterProvider(): array
    {
        return [
            ['{{ viewHelper.sum(,) }}'],
            ['{{ viewHelper.sum(,) }}'],
            ['{{ viewHelper.sum(variable,) }}'],
            ['{{ viewHelper.sum(,variable) }}'],
            ['{{ viewHelper.sum(object.get(),) }}'],
            ['{{ viewHelper.sum(,object.get()) }}'],
            ['{{ viewHelper.sum(variable,object.get(),) }}'],
            ['{{ viewHelper.sum(variable object.get()) }}'],
        ];
    }

    public function testNestedVariable(): void
    {
        $vars = [
            'person' => [
                'name' => [
                    'firstName' => 'Hans',
                    'lastName' => 'Schmidt',
                ],
            ],
        ];

        $this->assertSame(
            'Schmidt, Hans',
            $this->render->renderTemplateString('{{ person.name.lastName }}, {{ person.name.firstName }}', $vars)
        );
    }

    public function testNestedMethod(): void
    {
        $vars = [
            'person' => [
                'name' => [
                    'firstName' => 'Hans',
                    'lastName' => 'Schmidt',
                    'render' => new class () {
                        public function renderName(string $firstName, string $lastName): string
                        {
                            return "$lastName, $firstName";
                        }
                    }
                ],
            ],
        ];

        $this->assertSame(
            'Schmidt, Hans',
            $this->render->renderTemplateString(
                '{{ person.name.render.renderName(person.name.firstName, person.name.lastName) }}',
                $vars
            )
        );
    }

    /**
     * @dataProvider resolveErrorDataProvider
     *
     * @param string $var
     */
    public function testResolveErrorCallback(string $var): void
    {
        $this->render->onResolveError(function (string $resolveError) use ($var): string {
            $this->assertSame($var, $resolveError);

            return 'callback-result';
        });

        $this->assertSame('callback-result', $this->render->renderTemplateString("{{ $var }}"));
    }

    public function resolveErrorDataProvider()
    {
        return [
            'simple variable' => ['person'],
            'nested variable' => ['person.name'],
            'object function call' => ['person.renderName()'],
            'object function call with parameters' => ['person.renderName(firstname, lastname)'],
        ];
    }

    /**
     * @dataProvider propertyDataProvider
     */
    public function testAccessExistingProperty($person): void
    {
        $this->assertSame(
            ' Schmidt, Hans',
            $this->render->renderTemplateString(
                '{{ person.title }} {{ person.lastName }}, {{ person.firstName }}',
                [
                    'person' => $person,
                ]
            )
        );
    }

    /**
     * @dataProvider propertyDataProvider
     */
    public function testAccessNonExistingPropertyFails($person): void
    {
        $this->expectException(UndefinedSymbolException::class);
        $this->expectExceptionMessage('Unknown variable person.age');

        $this->render->renderTemplateString(
            '{{ person.age }}',
            [
                'person' => $person,
            ]
        );
    }

    public function testConstantExpressions(): void
    {
        $this->assertSame(
            'Schmidt, Hans!!;43;1;',
            $this->render->renderTemplateString("{{ 'Schmidt, Hans!!' }};{{ 43 }};{{ true }};{{ false }}")
        );
    }

    public function testConstantExpressionsAsFunctionParameter(): void
    {
        $this->assertSame(
            'ABC;abc;10',
            $this->render->renderTemplateString(
                "{{ viewHelper.castCase( 'aBc', true ) }};{{ viewHelper.castCase( 'AbC', false ) }};{{ viewHelper.double( 5 ) }}",
                [
                    'viewHelper' => new ViewHelper(),
                ]
            )
        );
    }

    public function testBuiltinFunctionCall(): void
    {
        $this->assertSame('HELLO WORLD', $this->render->renderTemplateString("{{ strtoupper('hello world') }}"));
    }

    /**
     * @dataProvider topLevelFunctionCallDataProvider
     */
    public function testTopLevelFunctionCall(callable $callback): void
    {
        $this->assertSame(
            'HELLO WORLD',
            $this->render->renderTemplateString("{{ up('hello world') }}", ['up' => $callback])
        );
    }

    public function topLevelFunctionCallDataProvider()
    {
        return [
            'builtin' => ['strtoupper'],
            'closure' => [function ($i) { return strtoupper($i); } ],
            'object method' => [[new ViewHelper(), 'up']],
            'static method' => [[ViewHelper::class, 'up']],
        ];
    }

    public function testNotCallableMethod(): void
    {
        $this->expectException(UndefinedSymbolException::class);
        $this->expectExceptionMessage('Function unknownMethod not callable');

        $this->render->renderTemplateString("{{ unknownMethod('abc') }}");
    }

    /**
     * A {% foreach %} whose iterable is a method call on a nullable variable must not be evaluated when
     * it is wrapped in a falsy {% if %} guard — previously the foreach iterable was resolved before the
     * enclosing if, causing a fatal "Trying to call ... on non-object" error.
     */
    public function testForeachInsideFalsyIfIsNotEvaluated(): void
    {
        $template = '{% if items %}{% foreach items.getCategories() as cat %}{{ cat }}{% endforeach %}{% endif %}';

        $this->assertSame(
            '',
            $this->render->renderTemplateString($template, ['items' => null])
        );
    }

    /**
     * When the {% if %} guard is truthy the {% foreach %} inside must still execute normally.
     */
    public function testForeachInsideTruthyIfIsEvaluated(): void
    {
        $template = '{% if items %}{% foreach items.getCategories() as cat %}{{ cat }},{% endforeach %}{% endif %}';

        $this->assertSame(
            'Oak,Birch,',
            $this->render->renderTemplateString(
                $template,
                ['items' => new Product('Wood', true, ['Oak', 'Birch'])]
            )
        );
    }

    /**
     * A {% if %} inside a {% foreach %} that uses the loop variable in its condition must still work
     * correctly — the loop variable must be in scope when the conditional is evaluated.
     */
    public function testIfInsideForeachCanUseLoopVariable(): void
    {
        $template = '{% foreach items as item %}{% if item.isVisible() %}{{ item.getTitle() }}{% endif %}{% endforeach %}';

        $products = [
            new Product('Hammer', true),
            new Product('Nails', false),
            new Product('Wood', true),
        ];

        $this->assertSame(
            'HammerWood',
            $this->render->renderTemplateString($template, ['items' => $products])
        );
    }

    /**
     * A {% foreach %} inside the true-branch of a top-level {% if %}, and a sibling {% foreach %} outside
     * any conditional, must both resolve correctly in a single template.
     */
    public function testForeachInsideIfAlongsideSiblingForeach(): void
    {
        $template =
            '{% if extra %}{% foreach extra.getCategories() as cat %}[{{ cat }}]{% endforeach %}{% endif %}' .
            '{% foreach items as item %}{{ item.getTitle() }}{% endforeach %}';

        $products = [new Product('Hammer', true), new Product('Wood', true)];

        $this->assertSame(
            'HammerWood',
            $this->render->renderTemplateString($template, ['items' => $products, 'extra' => null])
        );

        $this->assertSame(
            '[Oak][Birch]HammerWood',
            $this->render->renderTemplateString(
                $template,
                ['items' => $products, 'extra' => new Product('Extra', true, ['Oak', 'Birch'])]
            )
        );
    }

    public function propertyDataProvider(): array
    {
        return [
            'array' => [
                [
                    'firstName' => 'Hans',
                    'lastName' => 'Schmidt',
                    'title' => null,
                ],
            ],
            'arrayAccess' => [$this->getArrayAccessObject()],
            'object' => [$this->getPersonObject()],
        ];
    }

    private function getArrayAccessObject(): ArrayAccess
    {
        return new class () implements ArrayAccess {
            private $data = [
                'firstName' => 'Hans',
                'lastName' => 'Schmidt',
                'title' => null,
            ];

            #[\ReturnTypeWillChange]
            public function offsetExists($offset)
            {
                return array_key_exists($offset, $this->data);
            }

            #[\ReturnTypeWillChange]
            public function offsetGet($offset)
            {
                return $this->data[$offset];
            }

            #[\ReturnTypeWillChange]
            public function offsetSet($offset, $value): void
            {
                $this->data[$offset] = $value;
            }

            #[\ReturnTypeWillChange]
            public function offsetUnset($offset): void
            {
                unset($this->data[$offset]);
            }
        };
    }

    public function getPersonObject(): stdClass
    {
        $person = new stdClass();
        $person->lastName = 'Schmidt';
        $person->firstName = 'Hans';
        $person->title = null;

        return $person;
    }
}
