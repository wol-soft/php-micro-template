<?php

namespace PHPMicroTemplate;

/**
 * Class WhitespaceControl
 *
 * Opt-in formatting behavior for standalone {% foreach %}/{% if %} control tags, passed to Render's constructor.
 * With an instance: a tag that sits alone on its own line has that line's leading whitespace and trailing newline
 * stripped, and its body is dedented by the difference between the tag's own indentation and its body's first
 * line's indentation - detected automatically per tag, not configured, so it adapts to whatever indent width or
 * style (spaces, tabs, two columns, four columns) the template already uses instead of requiring a caller to get
 * a matching width right. A body that isn't indented deeper than its tag is left alone (the detected difference
 * is 0). Without an instance (Render's default), every line of the template is left exactly as written.
 *
 * A dedicated type instead of a bare bool keeps the option self-documenting at the call site and leaves room to
 * grow - e.g. a future flag to control tab vs. space dedent - without another constructor parameter on Render.
 *
 * @package PHPMicroTemplate
 */
class WhitespaceControl
{
}
