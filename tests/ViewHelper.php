<?php

declare(strict_types = 1);

namespace PHPMicroTemplate\Tests;

/**
 * Class ViewHelper
 *
 * @package PHPMicroTemplate\Tests
 */
class ViewHelper
{
    public function isEmpty($value): bool
    {
        return empty($value);
    }

    public function count(iterable $list): int
    {
        return count($list);
    }

    public function sum(...$values): int
    {
        return array_sum($values);
    }

    public function toBold(string $label): string
    {
        return "<b>$label</b>";
    }

    public function castCase(string $string, bool $toUpperCase): string
    {
        return $toUpperCase ? strtoupper($string) : strtolower($string);
    }

    public function double(int $number): int
    {
        return $number * 2;
    }
}
