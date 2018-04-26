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

        $output = preg_replace_callback(
            '/\{% foreach (?P<variable>[a-z0-9]+) as (?P<scopeVar>[a-z0-9]+) %\}(?P<body>.+)\{% endforeach %\}/si',
            function (array $matches) use ($variables): string {
                $output = '';
                foreach ($variables[$matches['variable']] as $value) {
                    $scope = array_merge($variables, [$matches['scopeVar'] => $value]);

                    $output .= $this->replaceVariablesInTemplate(
                        $this->resolveConditionals($matches['body'], $scope),
                        $scope
                    );
                }
                return $output;
            },
            $output
        );

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
            '/\{\{ (?P<variable>[a-z0-9]+)\.(?P<method>[a-z0-9]+)\(\) \}\}/i',
            function (array $matches) use ($variables): string {
                return $this->executeMethod($matches, $variables);
            },
            $template
        );

        foreach ($variables as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            $template = str_replace("{{ $key }}", trim($value), $template);
        }

        return $template;
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
        return preg_replace_callback(
            '/\{% if (?P<variable>[a-z0-9]+)(\.((?P<method>[a-z0-9]+)\(\)))? %\}(?P<body>.+)\{% endif %\}/si',
            function (array $matches) use ($variables): string {
                if (!isset($variables[$matches['variable']])) {
                    return '';
                }

                if (!empty($matches['method'])) {
                    return $this->executeMethod($matches, $variables) ? $matches['body'] : '';
                }

                return $variables[$matches['variable']] ? $matches['body'] : '';
            },
            $template
        );
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
            $this->templates[$template] = file_get_contents($this->basePath . $template);

            if (!$this->templates[$template]) {
                unset($this->templates[$template]);
                throw new FileSystemException("Template $template not found");
            }
        }

        return $this->templates[$template];
    }

    /**
     * Execute a method on a object
     *
     * @param array $matches
     * @param array $variables
     *
     * @return mixed
     */
    protected function executeMethod(array $matches, array $variables)
    {
        if (!isset($variables[$matches['variable']]) ||
            !is_callable([$variables[$matches['variable']], $matches['method']])
        ) {
            echo "Function {$matches['method']} on object {$matches['variable']} not callable";
            return '';
        }

        return $variables[$matches['variable']]->{$matches['method']}();
    }
}
