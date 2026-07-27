<?php

namespace PHPMicroTemplate;

/**
 * Class RenderConfig
 *
 * @package PHPMicroTemplate
 */
class RenderConfig
{
    /** @var bool */
    private $autoIndent;

    /**
     * @param bool $autoIndent Opt-in formatting for standalone {% foreach %}/{% if %} control tags. When enabled,
     *                         a tag that sits alone on its own line has that line's leading whitespace and
     *                         trailing newline stripped, and its body is dedented by the difference between the
     *                         tag's own indentation and its body's first line's indentation - detected
     *                         automatically per tag, not configured, so it adapts to whatever indent width or
     *                         style (spaces, tabs, two columns, four columns) the template already uses. A body
     *                         that isn't indented deeper than its tag is left alone (the detected difference is
     *                         0). When disabled (the default), every line of the template is left exactly as
     *                         written.
     */
    public function __construct(bool $autoIndent = false)
    {
        $this->autoIndent = $autoIndent;
    }

    public function isAutoIndentEnabled(): bool
    {
        return $this->autoIndent;
    }
}
