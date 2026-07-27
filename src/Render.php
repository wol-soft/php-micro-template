<?php

declare(strict_types = 1);

namespace PHPMicroTemplate;

use ArrayAccess;
use PHPMicroTemplate\Exception\FileSystemException;
use PHPMicroTemplate\Exception\SyntaxErrorException;
use PHPMicroTemplate\Exception\UndefinedSymbolException;

use function call_user_func_array;
use function in_array;
use function is_callable;

/**
 * Class Render
 *
 * @package PHPMicroTemplate
 */
class Render
{
    private const REGEX_VARIABLE = '(?<expression>(?<variable>(\w+|\'[^\']+\'))(?<nestedVariable>(\.\w+)*)(?<methodCall>\((?<parameter>[^{}%]*)\))?)';

    /** @var array */
    private $templates = [];
    /** @var string */
    private $basePath = '';
    /** @var callable */
    private $resolveErrorCallback;
    /** @var WhitespaceControl|null */
    private $whitespaceControl;

    /**
     * Render constructor.
     *
     * @param string                  $basePath          Provide a base path to the templates. If no base path is
     *                                                    provided you must provide correct absolute/relative paths
     *                                                    for the renderTemplate() function calls
     * @param WhitespaceControl|null  $whitespaceControl Opt-in standalone-tag trimming and body dedent for
     *                                                    {% foreach %}/{% if %} blocks. See WhitespaceControl.
     */
    public function __construct(string $basePath = '', ?WhitespaceControl $whitespaceControl = null)
    {
        $this->basePath = $basePath;
        $this->whitespaceControl = $whitespaceControl;
    }

    /**
     * Add a callback to handle resolve errors (eg. call to an unknown variable). By default a resolve error will lead
     * to an UnknownSymbolException.
     *
     * @param callable $resolveErrorCallback
     *
     * @return $this
     */
    public function onResolveError(callable $resolveErrorCallback): self
    {
        $this->resolveErrorCallback = $resolveErrorCallback;

        return $this;
    }

    /**
     * Render a template file
     *
     * @param string $template  The path to the template file
     * @param array  $variables The variables assigned to the template
     *
     * @return string
     * @throws FileSystemException
     * @throws UndefinedSymbolException
     * @throws SyntaxErrorException
     */
    public function renderTemplate(string $template, array $variables = []): string
    {
        return $this->renderTemplateString($this->getTemplate($template), $variables);
    }

    /**
     * Render a given template string
     *
     * @param string $template  The template string
     * @param array  $variables The variables assigned to the template
     *
     * @return string
     * @throws UndefinedSymbolException
     * @throws SyntaxErrorException
     */
    public function renderTemplateString(string $template, array $variables = []): string
    {
        $output = $this->stripComments($template);
        $output = $this->indexControlStructure($output, 'foreach');
        $output = $this->indexControlStructure($output, 'if', ['else']);

        $output = $this->resolveLoops($output, $variables);
        $output = $this->resolveConditionals($output, $variables);

        return $this->replaceVariablesInTemplate($output, $variables);
    }

    /**
     * Strip `{# ... #}` comment blocks
     */
    protected function stripComments(string $template): string
    {
        return preg_replace('/\{#.*?#\}/s', '', $template);
    }

    /**
     * Replace variables in a given template section and execute function calls
     *
     * @param string $template  The template section
     * @param array  $variables The current variable scope
     *
     * @return string
     * @throws UndefinedSymbolException
     * @throws SyntaxErrorException
     */
    protected function replaceVariablesInTemplate(string $template, array $variables) : string
    {
        $template = preg_replace_callback(
            '/\{\{\s*' . self::REGEX_VARIABLE . '\s*\}\}/i',
            function (array $matches) use ($variables): string {
                return (string) $this->getValue($matches, $variables);
            },
            $template
        );

        return $template;
    }

    /**
     * Resolve loops in a given template section
     *
     * @param string $template  The template section
     * @param array  $variables The current variable scope
     *
     * @return string
     * @throws UndefinedSymbolException
     * @throws SyntaxErrorException
     */
    protected function resolveLoops(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/(?<indent>[ \t]*)\{%\s*foreach(?<index>-[\d]+-[\d]+-)\s+' . self::REGEX_VARIABLE . '\s+as\s+((?<key>\w+)\s*,\s*)?(?<value>\w+)\s*%\}(?<openTrail>[ \t]*\r?\n)?' .
                '(?<body>.*?)' .
                '(?<closeIndent>[ \t]*)\{%\s*endforeach\k<index>\s*%\}(?<closeTrail>[ \t]*\r?\n)?/si',
            function (array $matches) use ($variables, $template): string {
                // If this foreach is preceded by an unclosed {% if %} open tag in the template, it is nested inside a
                // conditional that hasn't been evaluated yet. Return the original match unchanged so that
                // resolveConditionals can strip the enclosing if-branch first, then call resolveLoops on the result.
                $foreachPos = strpos($template, $matches[0]);
                $prefix = substr($template, 0, $foreachPos);
                $openIfCount = preg_match_all('/\{%\s*if-[\d]+-[\d]+-/i', $prefix);
                $closeIfCount = preg_match_all('/\{%\s*endif-[\d]+-[\d]+-/i', $prefix);
                if ($openIfCount > $closeIfCount) {
                    return $matches[0];
                }

                [$openStandalone, $closeStandalone, $body] = $this->resolveStandaloneBlock(
                    $template,
                    $foreachPos,
                    $matches
                );

                $output = '';

                foreach ($this->getValue($matches, $variables) as $key => $value) {
                    $scope = array_merge(
                        $variables,
                        [$matches['value'] => $value],
                        $matches['key'] ? [$matches['key'] => $key] : []
                    );

                    $output .= $this->replaceVariablesInTemplate(
                        $this->resolveConditionals(
                            $this->resolveLoops($body, $scope),
                            $scope
                        ),
                        $scope
                    );
                }

                return ($openStandalone ? '' : $matches['indent'] . ($matches['openTrail'] ?? ''))
                    . $output
                    . ($closeStandalone ? '' : $matches['closeIndent'] . ($matches['closeTrail'] ?? ''));
            },
            $template
        );
    }

    /**
     * Determine whether a matched {% foreach %}/{% if %} tag's opening and closing sides are each independently
     * standalone (alone on their own line), and dedent the tag's body when whitespace control is enabled and the
     * opening side qualifies. Shared by resolveLoops() and resolveConditionals(), which differ only in which regex
     * produced $matches and where in $template the match starts.
     *
     * A tag only counts as "alone on its own line" - and therefore safe to trim and use to dedent its body - when
     * BOTH sides confirm it: nothing but whitespace precedes it back to the previous newline, AND nothing but
     * whitespace follows it up to the next newline. Trimming based on only one side (eg. Jinja's independent
     * lstrip_blocks/trim_blocks) would strip a tag's trailing newline even when real content precedes it inline on
     * the same line, merging that content into the next line.
     *
     * @return array{0: bool, 1: bool, 2: string} [$openStandalone, $closeStandalone, $body]
     */
    private function resolveStandaloneBlock(string $template, int $matchOffset, array $matches): array
    {
        if ($this->whitespaceControl === null) {
            return [false, false, $matches['body']];
        }

        $openStandalone = $this->isAtLineStart($template, $matchOffset) && ($matches['openTrail'] ?? '') !== '';
        $closeStandalone = ($matches['body'] === '' || substr($matches['body'], -1) === "\n")
            && $this->isAtLineEndOrEof($template, $matchOffset, $matches[0], $matches['closeTrail'] ?? '');

        $body = $openStandalone
            ? $this->dedentBody($matches['body'], $this->whitespaceControl->getBlockIndentWidth())
            : $matches['body'];

        return [$openStandalone, $closeStandalone, $body];
    }

    /**
     * A tag is considered to be alone on its own line if the characters immediately preceding it in the original
     * template are either nothing (start of template) or a newline. Only in that case is it safe to treat its
     * leading whitespace as decorative template indentation rather than meaningful inline content.
     */
    private function isAtLineStart(string $template, int $offset): bool
    {
        return $offset === 0 || $template[$offset - 1] === "\n";
    }

    /**
     * A tag's trailing side is safe to trim either when an actual trailing newline was captured, or when the tag
     * sits at the very end of $template with nothing following it at all - end of template is just as much "nothing
     * meaningful follows" as a newline is, it just has no newline character to capture since there is no next line.
     */
    private function isAtLineEndOrEof(string $template, int $matchOffset, string $fullMatch, string $trail): bool
    {
        if ($trail !== '') {
            return true;
        }

        return $matchOffset + strlen($fullMatch) === strlen($template);
    }

    /**
     * Strip up to $width characters of leading whitespace from every line of $body. This must be a fixed width, not
     * the resolved tag's own (variable) measured indentation - stripping the tag's own column would collapse body
     * content to a constant absolute depth regardless of nesting, instead of preserving it relative to whatever
     * ambient depth the tag itself sits at. A fixed one-level width makes each block transparent: its body ends up
     * exactly as deep as the tag, whether that tag is nested two levels or ten.
     */
    private function dedentBody(string $body, int $width): string
    {
        if ($width <= 0) {
            return $body;
        }

        return preg_replace('/^[ \t]{1,' . $width . '}/m', '', $body);
    }

    /**
     * Resolve conditionals in a given template section
     *
     * @param string $template  The template section
     * @param array  $variables The current variable scope
     *
     * @return string
     * @throws UndefinedSymbolException
     * @throws SyntaxErrorException
     */
    protected function resolveConditionals(string $template, array $variables): string
    {
        do {
            $template = preg_replace_callback(
                '/(?<indent>[ \t]*)\{%\s*if(?<index>-[\d]+-[\d]+-)\s+(?<condition>.+?)\s*%\}(?<openTrail>[ \t]*\r?\n)?' .
                    '(?<body>.*?)' .
                    '(?<closeIndent>[ \t]*)\{%\s*endif\k<index>\s*%\}(?<closeTrail>[ \t]*\r?\n)?/sim',
                function (array $matches) use ($variables, $template): string {
                    $ifPos = strpos($template, $matches[0]);
                    [$openStandalone, $closeStandalone, $body] = $this->resolveStandaloneBlock(
                        $template,
                        $ifPos,
                        $matches
                    );

                    $conditionalBody = $this->splitOnElseTag($body, $matches['index']);

                    $orBranches = [];
                    foreach (explode(' or ', $matches['condition']) as $orLinkedCondition) {
                        $andBranchTrue = true;

                        foreach (explode(' and ', $orLinkedCondition) as $condition) {
                            if (!preg_match(
                                '/^\s*(?<not>not\s+)?' . self::REGEX_VARIABLE . '\s*$/si',
                                $condition,
                                $conditionMatches
                            )) {
                                throw new SyntaxErrorException("Invalid condition {$matches['condition']}");
                            }

                            if(empty($conditionMatches['not']) xor $this->getValue($conditionMatches, $variables)) {
                                $andBranchTrue = false;
                                break;
                            }
                        }
                        $orBranches[] = $andBranchTrue;
                    }

                    $branch = in_array(true, $orBranches) ? $conditionalBody[0] : ($conditionalBody[1] ?? '');

                    return ($openStandalone ? '' : $matches['indent'] . ($matches['openTrail'] ?? ''))
                        . $this->resolveLoops($branch, $variables)
                        . ($closeStandalone ? '' : $matches['closeIndent'] . ($matches['closeTrail'] ?? ''));
                },
                $template,
                -1,
                $count
            );
        } while ($count > 0);

        return $template;
    }

    /**
     * Split $body on its {% else %} tag (if any) into [trueBranch, falseBranch]. The else tag's own surrounding
     * whitespace/newline is only stripped when whitespace control is enabled AND the tag is genuinely alone on its
     * own line (same "both sides must hold" rule as resolveStandaloneBlock() applies to the if/foreach tags
     * themselves); otherwise only the bare tag text is removed, exactly as if it had been written inline, so
     * inline conditional expressions keep their surrounding spacing intact.
     *
     * @return string[] [trueBranch, falseBranch] - falseBranch is '' when there is no else tag
     */
    private function splitOnElseTag(string $body, string $index): array
    {
        if (!preg_match(
            "/(?<elseIndent>[ \t]*)\{%\s*else{$index}\s*%\}(?<elseTrail>[ \t]*\r?\n)?/si",
            $body,
            $elseMatch
        )) {
            return [$body, ''];
        }

        $elseOffset = strpos($body, $elseMatch[0]);
        $elseStandalone = $this->whitespaceControl !== null
            && ($elseOffset === 0 || $body[$elseOffset - 1] === "\n")
            && $this->isAtLineEndOrEof($body, $elseOffset, $elseMatch[0], $elseMatch['elseTrail'] ?? '');

        if ($elseStandalone) {
            return [
                substr($body, 0, $elseOffset),
                substr($body, $elseOffset + strlen($elseMatch[0])),
            ];
        }

        $bareTag = substr(
            $elseMatch[0],
            strlen($elseMatch['elseIndent']),
            strlen($elseMatch[0]) - strlen($elseMatch['elseIndent']) - strlen(($elseMatch['elseTrail'] ?? '')),
        );
        $tagOffset = strpos($body, $bareTag, $elseOffset);

        return [
            substr($body, 0, $tagOffset),
            substr($body, $tagOffset + strlen($bareTag)),
        ];
    }

    /**
     * Get the content for a template
     *
     * @param string $template
     *
     * @return string
     * @throws FileSystemException
     */
    protected function getTemplate(string $template) : string
    {
        if (isset($this->templates[$template])) {
            return $this->templates[$template];
        }

        $file = $this->basePath . $template;

        if (file_exists($file)) {
            $this->templates[$template] = file_get_contents($file);
        }

        if (!isset($this->templates[$template]) || !$this->templates[$template]) {
            unset($this->templates[$template]);
            throw new FileSystemException("Template $template not found");
        }

        return $this->templates[$template];
    }

    /**
     * Get a value for a given match (Either a plain value of the current scope or a function call, in this case return
     * the result of the called function)
     *
     * @param array $matches
     * @param array $variables
     *
     * @return mixed
     * @throws UndefinedSymbolException
     * @throws SyntaxErrorException
     */
    protected function getValue(array $matches, array $variables)
    {
        $resolved     = $variables;
        $variablePath = [$matches['variable']];

        if (!empty($matches['nestedVariable'])) {
            array_push($variablePath, ...explode('.', trim($matches['nestedVariable'], '.')));
        } else {
            $variable = trim($matches['variable']);
            if ($variable === 'true' || $variable === 'false') {
                return $variable === 'true';
            }

            if (substr($variable, 0, 1) === "'" && substr($variable, -1) === "'") {
                return trim($variable, "'");
            }

            if (is_numeric($variable)) {
                return +$variable;
            }
        }

        if (empty($matches['methodCall'])) {
            $this->resolveNestedVariable($resolved, $variablePath, $matches);

            return $resolved;
        }

        return $this->methodCall($matches, $variablePath, $variables);
    }

    /**
     * @param array $matches
     * @param array $variablePath
     * @param array $variables
     *
     * @return mixed
     *
     * @throws SyntaxErrorException
     * @throws UndefinedSymbolException
     */
    private function methodCall(array $matches, array $variablePath, array $variables)
    {
        $resolved = $variables;
        $method = array_pop($variablePath);

        if (empty($variablePath)) {
            $resolvedMethod = array_key_exists($method, $variables) ? $variables[$method] : $method;

            if (!is_callable($resolvedMethod)) {
                throw new UndefinedSymbolException(sprintf('Function %s not callable', $method));
            }

            return call_user_func_array(
                $resolvedMethod,
                $this->extractParameter($matches['parameter'] ?? '', $variables)
            );
        }

        if (!$this->resolveNestedVariable($resolved, $variablePath, $matches)) {
            // resolve error callback result
            return $resolved;
        }

        if (!is_object($resolved)) {
            throw new UndefinedSymbolException(
                sprintf('Trying to call %s on non-object %s', $method, implode('.', $variablePath))
            );
        }

        if (!is_callable([$resolved, $method])) {
            throw new UndefinedSymbolException(
                sprintf('Function %s on object %s not callable', $method, implode('.', $variablePath))
            );
        }

        return call_user_func_array(
            [$resolved, $method],
            $this->extractParameter($matches['parameter'] ?? '', $variables)
        );
    }

    /**
     * Resolve nested variable access and object property access
     *
     * @param mixed $resolved
     * @param array $variablePath
     * @param array $matches
     *
     * @return bool
     *
     * @throws UndefinedSymbolException
     */
    protected function resolveNestedVariable(&$resolved, array $variablePath, array $matches): bool
    {
        foreach ($variablePath as $variable) {
            if (is_object($resolved) && !($resolved instanceof ArrayAccess)) {
                if (property_exists($resolved, $variable)) {
                    $resolved = $resolved->$variable;

                    continue;
                }
            } elseif (isset($resolved[$variable]) || (is_array($resolved) && array_key_exists($variable, $resolved))) {
                $resolved = $resolved[$variable];

                continue;
            }

            if ($this->resolveErrorCallback) {
                $resolved = ($this->resolveErrorCallback)($matches['expression']);

                return false;
            }

            throw new UndefinedSymbolException(sprintf('Unknown variable %s', implode('.', $variablePath)));
        }

        return true;
    }

    /**
     * Index a control structure in a given template section so a handling of nested control structures of the same
     * type can be offered
     *
     * @param string $template             The template section
     * @param string $structure            The control structure (eg. 'foreach', 'if')
     * @param array  $additionalComponents [optional] Holds additional components for the structure (eg. 'else')
     *
     * @return string
     */
    protected function indexControlStructure(
        string $template,
        string $structure,
        array $additionalComponents = []
    ): string {
        $structureDepthCounter = 0;
        $levelCounter = [];

        return preg_replace_callback(
            '/\{%\s*(?<structure>' . $this->getControlStructureRegEx($structure, $additionalComponents) . ')/i',
            function (array $matches) use (&$structureDepthCounter, &$levelCounter, $additionalComponents): string {
                if (in_array($matches['structure'], $additionalComponents)) {
                    return sprintf(
                        '%s-%s-%s-',
                        $matches[0],
                        $levelCounter[$structureDepthCounter - 1],
                        ($structureDepthCounter - 1)
                    );
                }

                $levelCounter[$structureDepthCounter] = $levelCounter[$structureDepthCounter] ?? 0;
                $isEndTag = strpos($matches['structure'], 'end') === 0;
                ($isEndTag) ? --$structureDepthCounter : $levelCounter[$structureDepthCounter]++;

                return sprintf(
                    '%s-%s-%s-',
                    $matches[0],
                    $levelCounter[$structureDepthCounter],
                    ($isEndTag ? $structureDepthCounter : $structureDepthCounter++)
                );
            },
            $template
        );
    }

    /**
     * Get the regular expression for finding control structures
     *
     * @param string $structure            The control structure (eg. 'foreach', 'if')
     * @param array  $additionalComponents [optional] Holds additional components for the structure (eg. 'else')
     *
     * @return string
     */
    protected function getControlStructureRegEx(string $structure, array $additionalComponents): string
    {
        $structureRegex = "(end)?$structure";

        if ($additionalComponents) {
            $structureRegex = "($structureRegex|" . join('|', $additionalComponents) . ')';
        }

        return $structureRegex;
    }

    /**
     * Extract a parameter from a given string
     *
     * @param string $parameter The parameter string of a function
     * @param array  $variables The current scope
     *
     * @return array
     * @throws SyntaxErrorException
     * @throws UndefinedSymbolException
     */
    protected function extractParameter(string $parameter, array $variables): array
    {
        if (empty($parameter)) {
            return [];
        }

        $result = preg_match(
            '/^\s*' . self::REGEX_VARIABLE . '(\s*,\s*(?<next>.+))?\s*$/is',
            $parameter,
            $matches
        );

        if ($result === 0) {
            throw new SyntaxErrorException("Invalid parameter list $parameter");
        }

        return empty($matches['next'])
            ? [$this->getValue($matches, $variables)]
            : array_merge(
                [$this->getValue($matches, $variables)],
                $this->extractParameter($matches['next'], $variables)
            );
    }
}
