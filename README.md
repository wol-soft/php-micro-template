[![Latest Version](https://img.shields.io/packagist/v/wol-soft/php-micro-template.svg)](https://packagist.org/packages/wol-soft/php-micro-template)
[![Maintainability](https://api.codeclimate.com/v1/badges/9e3c565c528edb3d58d5/maintainability)](https://codeclimate.com/github/wol-soft/php-micro-template/maintainability)
[![Build Status](https://travis-ci.org/wol-soft/php-micro-template.svg?branch=master)](https://travis-ci.org/wol-soft/php-micro-template)
[![Coverage Status](https://coveralls.io/repos/github/wol-soft/php-micro-template/badge.svg?branch=master)](https://coveralls.io/github/wol-soft/php-micro-template?branch=master)
[![MIT License](https://img.shields.io/packagist/l/wol-soft/php-micro-template.svg)](https://github.com/wol-soft/php-micro-template/blob/master/LICENSE)

# php-micro-template
A minimalistic, lightweight templating engine for PHP based on regular expressions.

## Features ##

- Replace variables inside a template
- Iterate over an array or iterable object
- Conditional sections
- Pass objects and call functions on the objects

## Installation ##

The recommended way to install php-micro-template is through [Composer](http://getcomposer.org):
```
$ composer require wol-soft/php-micro-template
```

## Examples ##

First create your template file.

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

Afterwards create a new instance of the Render class and render your template

```php
<?php

/* ... */

$render = new Render(__DIR__ . '/Templates/');

$result = $render->renderTemplate(
    'productList.template',
    [
        'pageTitle' => 'Available products',
        'productHead' => 'Product',
        'products' => $products,
        'showVersion' => true
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

### Conditional sections

With the *if* statement you can create conditional sections. As a condition you can pass either a value which will be casted to bool or call a method on an object. In this case the return value of the function will be casted to bool.
Neither multiple values in a single condition combined by operators nor calculations or similar additional functions are provided.

```html
{% if showProducts %}
    {% if product.isVisible() %}
        <span>{{ product.getTitle() }}</span>
    {% endif %}
{% endif %}
```

Multiple if statements can be nested.