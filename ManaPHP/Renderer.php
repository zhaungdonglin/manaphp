<?php
namespace ManaPHP;

use ManaPHP\Coroutine\Context\Inseparable;
use ManaPHP\Exception\FileNotFoundException;
use ManaPHP\Exception\MisuseException;
use ManaPHP\Exception\PreconditionException;

class RendererContext implements Inseparable
{
    /**
     * @var array
     */
    public $sections = [];

    /**
     * @var array
     */
    public $stack = [];

    /**
     * @var array
     */
    public $templates = [];
}

/**
 * Class ManaPHP\Renderer
 *
 * @package renderer
 * @property-read \ManaPHP\RendererContext $_context
 */
class Renderer extends Component implements RendererInterface
{
    /**
     * @var \ManaPHP\Renderer\EngineInterface[]
     */
    protected $_resolved = [];

    /**
     * @var array
     */
    protected $_engines = [
        '.phtml' => 'ManaPHP\Renderer\Engine\Php',
        '.sword' => 'ManaPHP\Renderer\Engine\Sword'];

    /**
     * @var array array
     */
    protected $_files = [];

    /**
     * Renderer constructor.
     *
     * @param array $options
     */
    public function __construct($options = [])
    {
        if (isset($options['engines'])) {
            $this->_engines = $options['engines'] ?: ['.phtml' => 'ManaPHP\Renderer\Engine\Php'];
        }

        $this->loader->registerFiles('@manaphp/Renderer/helpers.php');
    }

    /**
     * Checks whether $template exists on registered extensions and render it
     *
     * @param string $template
     * @param array  $vars
     * @param bool   $directOutput
     *
     * @return string
     */
    public function render($template, $vars = [], $directOutput = false)
    {
        $context = $this->_context;

        if (DIRECTORY_SEPARATOR === '\\' && strpos($template, '\\') !== false) {
            $template = str_replace('\\', '/', $template);
        }

        if (strpos($template, '/') === false) {
            $template = dirname(end($context->templates)) . '/' . $template;
        }

        $template = $template[0] === '@' ? $this->alias->resolve($template) : $template;

        if (isset($this->_files[$template])) {
            list($file, $extension) = $this->_files[$template];
        } else {
            $file = null;
            $extension = null;
            foreach ($this->_engines as $extension => $engine) {
                if (is_file($file = $template . $extension)) {
                    if (PHP_EOL !== "\n") {
                        $realPath = strtr(realpath($file), '\\', '/');
                        if ($file !== $realPath) {
                            trigger_error("File name ($realPath) case mismatch for $file", E_USER_ERROR);
                        }
                    }

                    break;
                }
            }

            if (!$file) {
                throw new FileNotFoundException([
                    '`:template` with `:extensions` extension file was not found',
                    'template' => $template,
                    'extensions' => implode(', or ', array_keys($this->_engines))
                ]);
            }

            $this->_files[$template] = [$file, $extension];
        }

        if (!isset($this->_resolved[$extension])) {
            $engine = $this->_resolved[$extension] = $this->_di->getShared($this->_engines[$extension]);
        } else {
            $engine = $this->_resolved[$extension];
        }

        if (isset($vars['renderer'])) {
            throw new MisuseException('variable `renderer` is reserved for renderer');
        }
        $vars['renderer'] = $this;

        if (isset($vars['di'])) {
            throw new MisuseException('variable `di` is reserved for renderer');
        }
        $vars['di'] = $this->_di;

        $context->templates[] = $template;

        $eventArguments = ['file' => $file, 'vars' => $vars];
        $this->eventsManager->fireEvent('renderer:rendering', $this, $eventArguments);

        if ($directOutput) {
            $engine->render($file, $vars);
            $content = null;
        } else {
            ob_start();
            ob_implicit_flush(false);
            $engine->render($file, $vars);
            $content = ob_get_clean();
        }

        $this->eventsManager->fireEvent('renderer:rendered', $this, $eventArguments);

        array_pop($context->templates);

        return $content;
    }

    /**
     * @param string $path
     * @param array  $vars
     *
     * @return void
     */
    public function partial($path, $vars = [])
    {
        $this->render($path, $vars, true);
    }

    /**
     * @param string $template
     *
     * @return bool
     */
    public function exists($template)
    {
        if (DIRECTORY_SEPARATOR === '\\' && strpos($template, '\\') !== false) {
            $template = str_replace('\\', '/', $template);
        }

        if (strpos($template, '/') === false) {
            $template = dirname(end($this->_context->templates)) . '/' . $template;
        }

        $template = $template[0] === '@' ? $this->alias->resolve($template) : $template;

        if (isset($this->_files[$template])) {
            return true;
        }

        foreach ($this->_engines as $extension => $_) {
            if (is_file($file = $template . $extension)) {
                if (PHP_EOL !== "\n") {
                    $realPath = strtr(realpath($file), '\\', '/');
                    if ($file !== $realPath) {
                        trigger_error("File name ($realPath) case mismatch for $file", E_USER_ERROR);
                    }
                }
                $this->_files[$template] = [$file, $extension];
                return $file;
            }
        }

        return false;
    }

    /**
     * Get the string contents of a section.
     *
     * @param string $section
     * @param string $default
     *
     * @return string
     */
    public function getSection($section, $default = '')
    {
        $context = $this->_context;

        return $context->sections[$section] ?? $default;
    }

    /**
     * Start injecting content into a section.
     *
     * @param string $section
     * @param string $default
     *
     * @return void
     */
    public function startSection($section, $default = null)
    {
        $context = $this->_context;

        if ($default === null) {
            ob_start();
            ob_implicit_flush(false);
            $context->stack[] = $section;
        } else {
            $context->sections[$section] = $default;
        }
    }

    /**
     * Stop injecting content into a section.
     *
     * @param bool $overwrite
     *
     * @return void
     */
    public function stopSection($overwrite = false)
    {
        $context = $this->_context;

        if (!$context->stack) {
            throw new PreconditionException('cannot stop a section without first starting session');
        }

        $last = array_pop($context->stack);
        if ($overwrite || !isset($context->sections[$last])) {
            $context->sections[$last] = ob_get_clean();
        } else {
            $context->sections[$last] .= ob_get_clean();
        }
    }

    /**
     * @return void
     */
    public function appendSection()
    {
        $context = $this->_context;

        if (!$context->stack) {
            throw new PreconditionException('Cannot append a section without first starting one:');
        }

        $last = array_pop($context->stack);
        if (isset($context->sections[$last])) {
            $context->sections[$last] .= ob_get_clean();
        } else {
            $context->sections[$last] = ob_get_clean();
        }
    }

    public function dump()
    {
        $data = parent::dump();

        foreach ($data['_context']['sections'] as $k => $v) {
            $data['_context']['sections'][$k] = '***';
        }

        return $data;
    }
}