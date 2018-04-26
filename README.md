<p align="center">
  <a href="https://codeclimate.com/github/wol-soft/php-micro-template/maintainability"><img src="https://api.codeclimate.com/v1/badges/9e3c565c528edb3d58d5/maintainability" /></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/wol-soft/php-json-schema-model-generator.svg" alt="License"></a>
</p>

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
