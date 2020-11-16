[![Latest Version](https://img.shields.io/packagist/v/wol-soft/php-micro-template.svg)](https://packagist.org/packages/wol-soft/php-micro-template)
[![Maintainability](https://api.codeclimate.com/v1/badges/9e3c565c528edb3d58d5/maintainability)](https://codeclimate.com/github/wol-soft/php-micro-template/maintainability)
[![Build Status](https://travis-ci.com/wol-soft/php-micro-template.svg?branch=master)](https://travis-ci.com/wol-soft/php-micro-template)
[![Coverage Status](https://coveralls.io/repos/github/wol-soft/php-micro-template/badge.svg?branch=master)](https://coveralls.io/github/wol-soft/php-micro-template?branch=master)
[![MIT License](https://img.shields.io/packagist/l/wol-soft/php-micro-template.svg)](https://github.com/wol-soft/php-micro-template/blob/master/LICENSE)

# php-micro-template
A minimalistic, lightweight templating engine for PHP based on regular expressions.

## Features ##

- Replace variables inside a template
- Iterate over an array or iterable object
- Conditional sections
- Pass objects and call functions on the objects

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

Values which are assigned to the template and used directly will be casted to string. For assigned objects you can call methods which return a value. Afterwards the returned value will be casted to string.

```html
{{ simpleValue }}
{{ myObject.getProperty() }}
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

### function calls

The methods which are called on assigned objects can take parameters. Allowed parameters are variables taken out of the current scope or another function call on an object available in the current scope.
As an example a ViewHelper-Object can be assigned to the render process and methods of the ViewHelper can be used in the template for advanced logic inside the template.

```php
<?php

/* ... */

class ViewHelper
{
    public function count(iterable $list)
    {
        return count($list);
    }

    public function sum(...$values)
    {
        return array_sum($values);
    }

    public function toBold($label)
    {
        return "<b>$label</b>";
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
                <span>{{ viewHelper.toBold(product.getTitle()) }}</span>
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
