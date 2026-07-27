<?php

namespace PHPMicroTemplate;

/**
 * Class WhitespaceControl
 *
 * Opt-in formatting behavior for standalone {% foreach %}/{% if %} control tags, passed to Render's constructor.
 * With an instance: a tag that sits alone on its own line has that line's leading whitespace and trailing newline
 * stripped, and its body is dedented by blockIndentWidth characters. Without an instance (Render's default),
 * every line of the template is left exactly as written.
 *
 * A single object instead of a bare int keeps the option self-documenting at the call site and leaves room to grow
 * - e.g. a future flag to control tab vs. space dedent - without another constructor parameter on Render.
 *
 * @package PHPMicroTemplate
 */
class WhitespaceControl
{
    /** @var int */
    private $blockIndentWidth;

    /**
     * @param int $blockIndentWidth Leading whitespace stripped from every line of a standalone block's body. Lets
     *                               template authors indent a block's body one level deeper than the block tag
     *                               itself - normal code style - while the block still renders "transparently", at
     *                               the same effective column as the tag, regardless of how deeply the tag itself
     *                               is nested in the template source.
     */
    public function __construct(int $blockIndentWidth)
    {
        $this->blockIndentWidth = $blockIndentWidth;
    }

    public function getBlockIndentWidth(): int
    {
        return $this->blockIndentWidth;
    }
}
