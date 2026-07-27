[![Latest Version](https://img.shields.io/packagist/v/wol-soft/php-micro-template.svg)](https://packagist.org/packages/wol-soft/php-micro-template)
[![Maintainability](https://qlty.sh/gh/wol-soft/projects/php-micro-template/maintainability.svg)](https://qlty.sh/gh/wol-soft/projects/php-micro-template)
[![Build Status](https://github.com/wol-soft/php-micro-template/actions/workflows/main.yml/badge.svg)](https://github.com/wol-soft/php-micro-template/actions/workflows/main.yml)
[![Coverage Status](https://coveralls.io/repos/github/wol-soft/php-micro-template/badge.svg?branch=master)](https://coveralls.io/github/wol-soft/php-micro-template?branch=master)
[![MIT License](https://img.shields.io/packagist/l/wol-soft/php-micro-template.svg)](https://github.com/wol-soft/php-micro-template/blob/master/LICENSE)

# php-micro-template
A minimalistic, lightweight templating engine for PHP with zero dependencies based on regular expressions.

## Features ##

- Replace variables inside a template
- Iterate over an array or iterable object
- Conditional sections
- Pass objects
- call functions
- opt-in whitespace control for standalone control tags

## Requirements ##

- Requires at least PHP 7.1

## Installation ##

The recommended way to install php-micro-template is through [Composer](http://getcomposer.org):
```
$ composer require wol-soft/php-micro-template
```

## Examples ##

First create your template file:

```html
<html>
    <h1>{{ pageTitle }}</h1>

    <ul class="row">
        {% foreach products as product %}
            {% if product.isVisible() %}
                <li class="product">
                    <span>{{ productHead }}</span>
                    <span>{{ product.getTitle() }}</span>
                </li>
            {% endif %}
        {% endforeach %}
    </ul>

    {% if showVersion %}
        <div class="version">1.0.1</div>
    {% endif %}
</html>
```

Afterwards create a new instance of the Render class and render your template:

```php
<?php

use PHPMicroTemplate\Render;

/* ... */

$render = new Render(__DIR__ . '/Templates/');

$result = $render->renderTemplate(
    'productList.template',
    [
        'pageTitle' => $translator->translate('availableProducts'),
        'productHead' => $translator->translate('product'),
        'products' => $products,
        'showVersion' => true
    ]
);

/* ... */
```

Instead of saving your templates into files you can also prepare a string which contains the template:

```php
<?php

use PHPMicroTemplate\Render;

/* ... */

$myPartialTemplate = '
    {% foreach products as product %}
        {% if product.isVisible() %}
            <li class="product">
                <span>{{ productHead }}</span>
                <span>{{ product.getTitle() }}</span>
            </li>
        {% endif %}
    {% endforeach %}
';

$render = new Render();

$result = $render->renderTemplateString(
    $myPartialTemplate,
    [
        'productHead' => $translator->translate('product'),
        'products' => $products
    ]
);

/* ... */
```

### Replacement of variables

Values which are assigned to the template and used directly will be casted to string.
For assigned objects you can call methods which return a value.
Afterwards the returned value will be casted to string.
As constant values integer numbers, strings in single quotes and booleans (true, false) are supported. 

```html
{{ simpleValue }}
{{ myObject.getProperty() }}
{{ 'Hello World' }}
{{ 12345 }}
```

Your provided data may be a nested array which can be resolved in the template:

```php
$render->renderTemplateString(
    '{{ render.productRender.renderProductName(product.details.name) }}',
    [
        'product' => [
            'details' => [
                'name' => 'MyProduct',
            ],
        ],
        'render' => [
            'productRender' => new ProductRender(),
        ]
    ]
);
```

Also, public properties of objects may be accessed from the template:

```php
$person = new stdClass();
$person->name = 'Hans';

$render->renderTemplateString(
    '{{ person.name }}',
    [
        'person' => $person,
    ]
);
```

By default, a used variable which is not provided will result in an `UndefinedSymbolException`. You can register a callback function via `onResolveError` to handle unresolved variable errors. The callback function must implement the signature `function (string $unresolvedVariable): string`. The provided `$unresolvedVariable` will contain the whole expression which failed to resolve (eg. `myUnresolvedVariable`, `myUnresolvedObject.render(var1, var2)`).

```php
$render->onResolveError(function (string $var): string {
    return 'Undefined';
});

// will result in "Person name: Undefined"
$result = $render->renderTemplateString('Person name: {{ name }}');
```

### Loops

If you assign an array or an iterable object you can use the *foreach* loop to iterate.

```html
{% foreach products as product %}
    <span>{{ product.getTitle() }}</span>
{% endforeach %}
```

All variables of the parent scope are available inside the loop as well as the current item of the loop. Multiple foreach loops can be nested (compare tests). You can also provide a function which returns an array or an iterable object:

```html
{% foreach product.getIngredients() as ingredient %}
    <span>{{ ingredient.getTitle() }}</span>
{% endforeach %}
```

Loops support the usage of key value pairs:

```html
{% foreach products as bestSellerNumber, product %}
    <b>Bestseller Nr. {{ bestSellerNumber }}:</b>{{ product.getTitle() }}<br/>
{% endforeach %}
```

### Conditional sections

With the *if* statement you can create conditional sections. As a condition you can pass either a value which will be casted to bool or call a method on an object. In this case the return value of the function will be casted to bool.
Neither multiple values in a single condition combined by operators nor calculations or similar additional functions are provided. For advanced conditions compare the section *function calls* with a ViewHelper-Object.

```html
{% if showProducts %}
    {% if product.isVisible() %}
        <span>{{ product.getTitle() }}</span>
    {% else %}
        <span>Product {{ product.getTitle() }} currently not available</span>
    {% endif %}
{% endif %}
```

Multiple if statements can be nested. To invert an if condition the keyword *not* can be used:

```html
{% if not product.isVisible() %}
    <span>Product {{ product.getTitle() }} currently not available</span>
{% endif %}
```

To compose multiple conditions in a single if, the keywords *and* and *or* can be used (*and* has the higher precedence). Brackets in if statements are not supported.

```html
{% if not product.isVisible() or not product.isAvailable() %}
    <span>Product {{ product.getTitle() }} currently not available</span>
{% endif %}
```

### function calls

The methods which are called can take parameters.
Allowed parameters are variables taken out of the current scope or another function call on an object available in the current scope as well as the supported constant values integer numbers, strings in single quotes and booleans (true, false).
As an example a ViewHelper-Object can be assigned to the render process and methods of the ViewHelper can be used in the template for advanced logic inside the template.

```php
<?php

use PHPMicroTemplate\Render;

/* ... */

class ViewHelper
{
    public function count(iterable $list): int
    {
        return count($list);
    }

    public function sum(float ...$values): float
    {
        return array_sum($values);
    }

    public function weight(string $label, int $weight = 400): string
    {
        return sprintf('<span style="font-weight: %d;">%s</span>', $weight, $label);
    }
}

/* ... */

$render = new Render(__DIR__ . '/Templates/');

$result = $render->renderTemplate(
    'functionExample.template',
    [
        'viewHelper' => new ViewHelper(),
        'currencyFormatter' => new CurrencyFormatter(),
        'basePrice' => 3.00,
        'products' => $products
    ]
);

/* ... */

```

```html
<html>
    <p>Products: {{ viewHelper.count(products) }}
    <ul class="row">
        {% foreach products as product %}
            <li class="product">
                <span>{{ viewHelper.weightFont(product.getTitle(), 600) }}</span>
                <span>Price: {{
                    currencyFormatter.format(
                        viewHelper.sum(
                            product.getPrice(),
                            basePrice
                        )
                    )
                }}</span>
            </li>
        {% endforeach %}
    </ul>
</html>
```

Additionally, PHP global functions can be used directly in the template as well as assigned callback methods:

```php
<?php

use PHPMicroTemplate\Render;

/* ... */

$render = new Render(__DIR__ . '/Templates/');

$result = $render->renderTemplate(
    'functionExample.template',
    [
        'customCallback' => function(string $in): string {
            return trim(strtoupper($in));
        },
    ]
);

/* ... */

```

```html
<html>
    <p>{{ customCallback('products') }}</p>
    <span>{{ strtolower('UNDER CONSTRUCTION') }}</span>
</html>
```
### Comments

With the `{# ... #}` syntax, the template can contain comments which are stripped from the output:

```html
<html>
    {# customCallback delivers a summary, just like we need it here #}
    <p>{{ customCallback('products') }}</p>
</html>
```

### Whitespace tolerance

The templating syntax is whitespace tolerant so a template like the one below would be perfectly fine:

```html
{%if
    product.getCategories()
%}
    <p>Categories:</p>
    <ul>
    {%foreach
         product.getCategories()
            as
         category
    %}
        <li>{{product.getTitle()} [{{   category   }}]</li>
    {%endforeach%}
    </ul>
{%endif%}
```

### Whitespace control

By default, a `{% foreach %}`/`{% if %}` tag that sits alone on its own line contributes nothing to the rendered
output, but its own line (leading whitespace and trailing newline) is left behind as-is. For a template like

```html
<ul>
    {% foreach items as item %}
        <li>{{ item }}</li>
    {% endforeach %}
</ul>
```

that produces:

```html
<ul>
    
        <li>Hammer</li>
        <li>Nails</li>
    
</ul>
```

- a whitespace-only line left over from each tag, and the body accumulating one extra indent level per level of
template nesting instead of staying at the column the surrounding markup would suggest.

Passing a `RenderConfig` with `autoIndent` enabled as the second argument to `Render` opts into cleaning both up:

```php
<?php

use PHPMicroTemplate\Render;
use PHPMicroTemplate\RenderConfig;

/* ... */

$render = new Render(__DIR__ . '/Templates/', new RenderConfig(true));
```

With `autoIndent` enabled, a standalone tag's own line is stripped entirely, and its body is dedented by however
far the body's first line is indented past the tag itself - detected automatically per tag, not configured, so it
adapts to whatever indent width or style (spaces, tabs, two columns, four columns) the template already uses. The
same template now renders as:

```html
<ul>
    <li>Hammer</li>
    <li>Nails</li>
</ul>
```

A tag only counts as standalone when nothing but whitespace precedes it back to the previous newline *and*
nothing but whitespace follows it up to the next newline - a tag that shares its line with real content (eg.
`<li>{% if visible %}...{% endif %}</li>`) always keeps its surrounding whitespace exactly as written, since
stripping it there could merge unrelated content together. Likewise, a body that isn't indented deeper than its
own tag is left untouched - the detected difference is 0, so there is nothing to dedent.

`autoIndent` defaults to `false` - without passing a `RenderConfig`, or with `new RenderConfig(false)`, every line
of the template is rendered exactly as written, including the whitespace-only lines left behind by standalone
tags.
