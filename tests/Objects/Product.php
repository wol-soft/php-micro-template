<?php

namespace PHPMicroTemplate\Tests\Objects;

/**
 * Class Product
 *
 * @package PHPMicroTemplate\Tests\Objects
 */
class Product
{
    /** @var string */
    private $title;
    /** @var bool */
    private $isVisible;
    /** @var array */
    private $categories;

    /**
     * Product constructor.
     *
     * @param string $title
     * @param bool   $isVisible
     * @param array  $categories
     */
    public function __construct(string $title, bool $isVisible, array $categories = [])
    {
        $this->title = $title;
        $this->isVisible = $isVisible;
        $this->categories = $categories;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return bool
     */
    public function isVisible(): bool
    {
        return $this->isVisible;
    }

    /**
     * @return array
     */
    public function getCategories(): array
    {
        return $this->categories;
    }
}
