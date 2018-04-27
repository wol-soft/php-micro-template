<?php

namespace PHPMicroTemplate;

use PHPMicroTemplate\Exception\FileSystemException;

/**
 * Class Render
 *
 * @package PHPMicroTemplate
 */
class Render
{
    private const REGEX_VARIABLE = '(?<variable>[a-z0-9]+)(\.((?<method>[a-z0-9]+)\(\)))?';

    /** @var array */
    private $templates = [];
    /** @var string */
    private $basePath = '';

    /**
     * Render constructor.
     *
     * @param string $basePath Provide a base path to the templates. If no base path is provided you must provide
     *                         correct absolute/relative paths for the renderTemplate() function calls
     */
    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath;
    }

    /**
     * @param string $template
     * @param array  $variables
     *
     * @return string
     * @throws FileSystemException
     */
    public function renderTemplate(string $template, array $variables = []): string
    {
        $output = $this->getTemplate($template);

        $output = $this->indexControlStructure($output, 'foreach');
        $output = $this->indexControlStructure($output, 'if');

        $output = $this->resolveLoops($output, $variables);
        $output = $this->resolveConditionals($output, $variables);

        return $this->replaceVariablesInTemplate($output, $variables);
    }

    /**
     * Replace variables in a given template section and execute function calls
     *
     * @param string $template  The template section
     * @param array  $variables The current variable scope
     *
     * @return string
     */
    protected function replaceVariablesInTemplate(string $template, array $variables) : string
    {
        $template = preg_replace_callback(
            '/\{\{ ' . self::REGEX_VARIABLE . ' \}\}/i',
            function (array $matches) use ($variables): string {
                return $this->getValue($matches, $variables);
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
     */
    private function resolveLoops($template, $variables): string
    {
        return preg_replace_callback(
            '/\{% foreach(?<index>-[\d]+-) ' . self::REGEX_VARIABLE . ' as (?<scopeVar>[a-z0-9]+) %\}' .
                '(?<body>.+)' .
            '\{% endforeach\k<index> %\}/si',
            function (array $matches) use ($variables): string {
                $output = '';

                foreach ($this->getValue($matches, $variables) as $value) {
                    $scope = array_merge($variables, [$matches['scopeVar'] => $value]);

                    $output .= $this->replaceVariablesInTemplate(
                        $this->resolveConditionals(
                            $this->resolveLoops($matches['body'], $scope),
                            $scope
                        ),
                        $scope
                    );
                }
                return $output;
            },
            $template
        );
    }

    /**
     * Resolve conditionals in a given template section
     *
     * @param string $template  The template section
     * @param array  $variables The current variable scope
     *
     * @return string
     */
    protected function resolveConditionals(string $template, array $variables): string
    {
        do {
            $template = preg_replace_callback(
                '/\{% if(?<index>-[\d]+-) ' . self::REGEX_VARIABLE . ' %\}(?<body>.+)\{% endif\k<index> %\}/si',
                function (array $matches) use ($variables): string {
                    return $this->getValue($matches, $variables) ? $matches['body'] : '';
                },
                $template,
                -1,
                $count
            );
        } while ($count > 0);

        return $template;
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
        if (!isset($this->templates[$template])) {
            $file = $this->basePath . $template;

            if (file_exists($file)) {
                $this->templates[$template] = file_get_contents($file);
            }

            if (!isset($this->templates[$template]) || !$this->templates[$template]) {
                unset($this->templates[$template]);
                throw new FileSystemException("Template $template not found");
            }
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
     */
    protected function getValue(array $matches, array $variables)
    {
        $variable = $matches['variable'] ?? null;
        $method   = $matches['method'] ?? null;

        if (!isset($variables[$variable])) {
            echo "Unknown variable {$variable}";
            return false;
        }

        if (empty($method)) {
            return $variables[$variable];
        }

        if (!is_callable([$variables[$variable], $method])) {
            echo "Function {$method} on object {$variable} not callable";
            return false;
        }

        return $variables[$variable]->{$method}();
    }

    /**
     * Index a control structure in a given template section so a handling of nested control structures of the same
     * type can be offered
     *
     * @param string $template  The template section
     * @param string $structure The control structure (eg. 'foreach', 'if')
     *
     * @return string
     */
    protected function indexControlStructure(string $template, string $structure): string
    {
        $structureCounter = 0;
        return preg_replace_callback(
            "/\{% (?<structure>(end)?$structure)/i",
            function (array $matches) use (&$structureCounter): string {
                return $matches[0] . '-' .
                    (strpos($matches['structure'], 'end') === 0 ? --$structureCounter : $structureCounter++) .
                    '-';
            },
            $template
        );
    }
}
