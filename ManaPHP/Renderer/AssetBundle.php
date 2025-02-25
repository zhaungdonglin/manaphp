<?php
namespace ManaPHP\Renderer;

use ManaPHP\Component;
use ManaPHP\Exception\FileNotFoundException;

class AssetBundle extends Component implements AssetBundleInterface
{
    /**
     * @var int
     */
    protected $_length = 12;

    /**
     * AssetBundle constructor.
     *
     * @param array $options
     */
    public function __construct($options = [])
    {
        if (isset($options['length'])) {
            $this->_length = $options['length'];
        }
    }

    /**
     * @param string $file
     * @param string $content
     *
     * @return string
     */
    protected function _replaceCssUrl($file, $content)
    {
        $path = dirname(substr($this->alias->resolve($file), strlen($this->alias->get('@public'))));

        return preg_replace_callback('#url\((.+?)\)#', function ($match) use ($path) {
            $url = trim($match[1], '\'"');
            if ($url === '' || strpos($url, '//') !== false) {
                return $match[0];
            }

            if ($url[0] !== '/') {
                while (strpos($url, '../') === 0) {
                    $path = dirname($path);
                    $url = substr($url, 3);
                }

                $url = rtrim($path, '/\\') . '/' . $url;
            }
            return sprintf('url("%s")', $this->alias->get('@asset') . $url);
        }, $content);
    }

    /**
     * @param array  $files
     * @param string $name
     *
     * @return string
     */
    public function bundle($files, $name = 'app')
    {
        if (!$files) {
            return '';
        }

        $hash = substr(md5(implode('', $files)), 0, $this->_length);
        $extension = pathinfo($files[0], PATHINFO_EXTENSION);
        if ($pos = strpos($extension, '?')) {
            $extension = substr($extension, 0, $pos);
        }

        $bundle = ($name[0] !== '/' ? "/assets/bundle/$name" : $name) . ".$hash.$extension";

        if ($this->configure->debug || !is_file($target = $this->alias->get('@public') . "/$bundle")) {
            $r = '';
            foreach ($files as $file) {
                if ($file[0] !== '@') {
                    $file = '@public' . $file;
                }
                $source_file = $file;

                if ($pos = strpos($file, '?')) {
                    $file = substr($file, 0, $pos);
                }

                if (($content = file_get_contents($this->alias->resolve($file))) === false) {
                    throw new FileNotFoundException(['bundled `:file` file is not exists', 'file' => $file]);
                }

                if ($extension === 'css') {
                    $content = $this->_replaceCssUrl($file, $content);
                }

                $content = preg_replace('@/\*# sourceMappingURL=[^*]+\s+\*/@', '', $content);

                $r .= PHP_EOL . PHP_EOL . "/* SOURCE_FILE `$source_file` */" . PHP_EOL . $content;
            }

            $this->filesystem->filePut("@public$bundle", $r);
        }

        return $this->alias->resolve("@asset$bundle");
    }
}