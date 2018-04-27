<?php

namespace PHPMicroTemplate\Tests;

use PHPMicroTemplate\Exception\FileSystemException;
use PHPMicroTemplate\Exception\UndefinedSymbolException;
use PHPMicroTemplate\Render;
use PHPMicroTemplate\Tests\Objects\Product;
use PHPUnit\Framework\TestCase;

/**
 * Class RenderTest
 *
 * @package PHPMicroTemplate\Tests
 */
class RenderTest extends TestCase
{
    /** @var Render */
    private $render;

    public function setUp()
    {
        $this->render = new Render(__DIR__ . '/Templates/');
    }

    public function testRenderNotExistingTemplate()
    {
        $this->expectException(FileSystemException::class);
        $this->render->renderTemplate('nonExistingTemplate.template');
    }

    public function testUndefinedVariable()
    {
        $this->expectException(UndefinedSymbolException::class);
        $this->render->renderTemplate('undefinedVariable.template', ['firstname' => 'John']);
    }

    public function testUndefinedMethod()
    {
        $this->expectException(UndefinedSymbolException::class);
        $this->render->renderTemplate('undefinedVariable.template', ['product' => new Product('Wood', true)]);
    }

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
}
