<?php

declare(strict_types = 1);

namespace PHPMicroTemplate\Tests;

use ArrayAccess;
use ArrayObject;
use PHPMicroTemplate\Exception\FileSystemException;
use PHPMicroTemplate\Exception\SyntaxErrorException;
use PHPMicroTemplate\Exception\UndefinedSymbolException;
use PHPMicroTemplate\Render;
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

    public function setUp(): void
    {
        $this->render = new Render(__DIR__ . '/Templates/');
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
     * @dataProvider nonExistingPropertyDataProvider
     */
    public function testAccessExistingProperty($person): void
    {
        $this->assertSame(
            'Schmidt, Hans',
            $this->render->renderTemplateString(
                '{{ person.lastName }}, {{ person.firstName }}',
                [
                    'person' => $person,
                ]
            )
        );
    }

    /**
     * @dataProvider nonExistingPropertyDataProvider
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

    public function nonExistingPropertyDataProvider(): array
    {
        return [
            'array' => [
                [
                    'firstName' => 'Hans',
                    'lastName' => 'Schmidt',
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
            ];

            public function offsetExists($offset)
            {
                return array_key_exists($offset, $this->data);
            }

            public function offsetGet($offset)
            {
                return $this->data[$offset];
            }

            public function offsetSet($offset, $value)
            {
                $this->data[$offset] = $value;
            }

            public function offsetUnset($offset)
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

        return $person;
    }
}
