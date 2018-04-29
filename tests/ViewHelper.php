<?php

namespace PHPMicroTemplate\Tests;

/**
 * Class ViewHelper
 *
 * @package PHPMicroTemplate\Tests
 */
class ViewHelper
{
    public function isEmpty($value)
    {
        return empty($value);
    }

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
