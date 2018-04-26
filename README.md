[![Maintainability](https://api.codeclimate.com/v1/badges/9e3c565c528edb3d58d5/maintainability)](https://codeclimate.com/github/wol-soft/php-micro-template/maintainability)
[![Build Status](https://travis-ci.org/wol-soft/php-micro-template.svg?branch=master)](https://travis-ci.org/wol-soft/php-micro-template)
[![Coverage Status](https://coveralls.io/repos/github/wol-soft/php-micro-template/badge.svg?branch=master)](https://coveralls.io/github/wol-soft/php-micro-template?branch=master)
[![MIT License](https://img.shields.io/packagist/l/wol-soft/php-micro-template.svg)](https://github.com/wol-soft/php-micro-template/blob/master/LICENSE)

# php-micro-template
A minimalistic templating engine for PHP

## Features ##

- Replace variables inside a template
- Iterate over an array
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
