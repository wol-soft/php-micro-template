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

    /**
     * Product constructor.
     *
     * @param string $title
     * @param bool $isVisible
     */
    public function __construct(string $title, bool $isVisible)
    {
        $this->title = $title;
        $this->isVisible = $isVisible;
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
}
