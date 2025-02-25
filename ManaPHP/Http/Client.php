<?php
namespace ManaPHP\Http;

use ManaPHP\Component;
use ManaPHP\Exception\NotSupportedException;
use ManaPHP\Http\Client\BadRequestException;
use ManaPHP\Http\Client\ContentTypeException;
use ManaPHP\Http\Client\ForbiddenException;
use ManaPHP\Http\Client\ServiceUnavailableException;
use ManaPHP\Http\Client\TooManyRequestsException;
use ManaPHP\Http\Client\UnauthorizedException;

/**
 * Class ManaPHP\Http\Client
 *
 * @package Curl
 */
abstract class Client extends Component implements ClientInterface
{
    const USER_AGENT_IE = 'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko';

    /**
     * @var string
     */
    protected $_proxy = '';

    /**
     * @var string
     */
    protected $_cafile;

    /**
     * @var int
     */
    protected $_timeout = 10;

    /**
     * @var bool
     */
    protected $_verify_peer = true;

    /**
     * Client constructor.
     *
     * @param array $options
     */
    public function __construct($options = [])
    {
        if (isset($options['proxy'])) {
            $this->_proxy = $options['proxy'];
        }

        if (isset($options['cafile'])) {
            $this->_cafile = $options['cafile'];
        }

        if (isset($options['timeout'])) {
            $this->_timeout = (int)$options['timeout'];
        }

        if (isset($options['verify_peer'])) {
            $this->_verify_peer = (bool)$options['verify_peer'];
        }
    }

    /**
     * @param \ManaPHP\Http\Client\Request $request
     *
     * @return \ManaPHP\Http\Client\Response
     */
    abstract public function do_request($request);

    /**
     * @param string          $method
     * @param string|array    $url
     * @param string|array    $body
     * @param array|string    $headers
     * @param array|int|float $options
     *
     * @return \ManaPHP\Http\Client\Response
     */
    public function request($method, $url, $body = null, $headers = [], $options = [])
    {
        if (is_array($url)) {
            if (count($url) > 1) {
                $uri = $url[0];
                unset($url[0]);
                $url = $uri . (strpos($uri, '?') !== false ? '&' : '?') . http_build_query($url);
            } else {
                $url = $url[0];
            }
        }

        if (preg_match('#^http(s)?://#i', $url) !== 1) {
            throw new NotSupportedException(['only HTTP requests can be handled: `:url`', 'url' => $url]);
        }

        if (is_string($headers)) {
            $headers = [strpos($headers, '://') ? 'Referer' : 'User-Agent' => $headers];
        }

        if (isset($headers['-'])) {
            $headers['Referer'] = $headers['-'];
            unset($headers['-']);
        }

        if (isset($headers['Referer'])) {
            if (is_array($headers['Referer'])) {
                $referer = $headers['Referer'];
                if (isset($referer[0])) {
                    $str = $referer[0];
                    unset($referer[0]);
                } else {
                    $str = 'http://TRACK/';
                }
                $headers['Referer'] = $str . (strpos($str, '?') ? '&' : '?') . http_build_query($referer);
            } elseif (!strpos($headers['Referer'], '://')) {
                $headers['Referer'] = 'http://TRACE/' . $headers['Referer'];
            }
        }

        if (!isset($headers['User-Agent'])) {
            $headers['User-Agent'] = self::USER_AGENT_IE;
        }

        if (is_int($options) || is_float($options)) {
            $options = ['timeout' => $options];
        }

        if (!isset($options['timeout'])) {
            $options['timeout'] = $this->_timeout;
        }

        if ($this->_proxy && !isset($options['proxy'])) {
            $options['proxy'] = $this->_proxy;
        }

        if ($this->_cafile && !isset($options['cafile'])) {
            $options['cafile'] = $this->_cafile;
        }

        $options['verify_peer'] = $this->_verify_peer;

        $request = $this->_di->get('ManaPHP\Http\Client\Request');
        $request->method = $method;
        $request->url = $url;
        $request->headers = $headers;
        $request->body = $body;
        $request->options = $options;

        $request->normalize();

        $this->eventsManager->fireEvent('httpClient:requesting', $this, $request);
        $response = $this->do_request($request);
        $response_text = $response->body;
        if (strpos($response->content_type, '/json') !== false && $json = json_parse($response->body)) {
            $response->body = $json;
        }
        $this->eventsManager->fireEvent('httpClient:requested', $this, $response);
        $response->request = $request;
        $response->user_data = $options['user_data'] ?? null;

        if ($response->http_code === 429) {
            throw new TooManyRequestsException($response->url, $response);
        } elseif ($response->http_code === 403) {
            throw new ForbiddenException($response->url, $response);
        } elseif ($response->http_code === 401) {
            throw new UnauthorizedException($response->url, $response);
        }

        if ($response->http_code >= 500) {
            throw new ServiceUnavailableException([':url => `:response`', 'url' => $response->url, 'response' => $response_text], $response);
        }

        if ($response->http_code >= 400) {
            throw new BadRequestException([':url => `:response`', 'url' => $response->url, 'response' => $response_text,], $response);
        }

        return $response;
    }

    /**
     * @param string          $method
     * @param string|array    $url
     * @param string|array    $body
     * @param array|string    $headers
     * @param array|int|float $options
     *
     * @return \ManaPHP\Http\Client\Response
     */
    public function rest($method, $url, $body = null, $headers = [], $options = [])
    {
        if (is_string($body) && $body !== '') {
            $headers['Content-Type'] = preg_match('#^\[|\{#', $body) ? 'application/json' : 'application/x-www-form-urlencoded';
        } else {
            $headers['Content-Type'] = 'application/json';
            if (is_array($body)) {
                $body = json_stringify($body);
            }
        }

        if (!isset($headers['X-Requested-With'])) {
            $headers['X-Requested-With'] = 'XMLHttpRequest';
        }

        if (!isset($headers['Accept'])) {
            $headers['Accept'] = 'application/json';
        }

        $response = $this->request($method, $url, $body, $headers, $options);

        if (is_string($response->body) && strpos($response->content_type, '/html') !== false) {
            $response->body = json_parse($response->body);
        }

        if (is_string($response->body)) {
            throw new ContentTypeException(['content-type of response is not application/json: :content-type => `:url`',
                'content-type' => $response->content_type,
                'url' => $response->url],
                $response);
        }

        return $response;
    }

    /**
     * @param array|string    $url
     * @param array|string    $headers
     * @param array|int|float $options
     *
     * @return \ManaPHP\Http\Client\Response
     */
    public function get($url, $headers = [], $options = [])
    {
        return $this->request('GET', $url, null, $headers, $options);
    }

    /**
     * @param array|string    $url
     * @param string|array    $body
     * @param array|string    $headers
     * @param array|int|float $options
     *
     * @return \ManaPHP\Http\Client\Response
     */
    public function post($url, $body = [], $headers = [], $options = [])
    {
        return $this->request('POST', $url, $body, $headers, $options);
    }

    /**
     * @param array|string    $url
     * @param array|string    $headers
     * @param array|int|float $options
     *
     * @return \ManaPHP\Http\Client\Response
     */
    public function delete($url, $headers = [], $options = [])
    {
        return $this->request('DELETE', $url, null, $headers, $options);
    }

    /**
     * @param array|string    $url
     * @param string|array    $body
     * @param array|string    $headers
     * @param array|int|float $options
     *
     * @return \ManaPHP\Http\Client\Response
     */
    public function put($url, $body = [], $headers = [], $options = [])
    {
        return $this->request('PUT', $url, $body, $headers, $options);
    }

    /**
     * @param array|string    $url
     * @param string|array    $body
     * @param array|string    $headers
     * @param array|int|float $options
     *
     * @return \ManaPHP\Http\Client\Response
     */
    public function patch($url, $body = [], $headers = [], $options = [])
    {
        return $this->request('PATCH', $url, $body, $headers, $options);
    }

    /**
     * @param array|string    $url
     * @param string|array    $body
     * @param array|string    $headers
     * @param array|int|float $options
     *
     * @return \ManaPHP\Http\Client\Response
     */
    public function head($url, $body = [], $headers = [], $options = [])
    {
        return $this->request('HEAD', $url, $body, $headers, $options);
    }

    /**
     * @param string|array           $files
     * @param string|int|array|float $options
     *
     * @return string|array
     */
    public function download($files, $options = [])
    {
        if (is_string($files)) {
            if (is_string($options) && strpos($options, '://') === false) {
                $return_file = $options;
            } else {
                $path = parse_url($files, PHP_URL_PATH);
                if ($pos = strrpos($path, '.')) {
                    $ext = strtolower(substr($path, $pos));
                    if ($ext === '.php' || preg_match('#^\.\w+$#', $ext) === 0) {
                        $ext = '.tmp';
                    }
                } else {
                    $ext = '.tmp';
                }
                $return_file = $this->alias->resolve('@tmp/download/' . md5($files . gethostname()) . $ext);
            }
            $files = [$files => $return_file];
        } else {
            if (is_int($options)) {
                $options = ['concurrent' => $options];
            } elseif (is_float($options)) {
                $options = ['timeout' => $options];
            } elseif (is_string($options)) {
                $options = [preg_match('#^https?://#', $options) ? CURLOPT_REFERER : CURLOPT_USERAGENT => $options];
            }
            $return_file = null;
        }

        $mh = curl_multi_init();

        $template = curl_init();

        if (isset($options['timeout'])) {
            $timeout = $options['timeout'];
            unset($options['timeout']);
        } else {
            $timeout = 10;
        }

        if (isset($options['concurrent'])) {
            $concurrent = $options['concurrent'];
            unset($options['concurrent']);
        } else {
            $concurrent = 10;
        }

        curl_setopt($template, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($template, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($template, CURLOPT_USERAGENT, self::USER_AGENT_IE);
        curl_setopt($template, CURLOPT_HEADER, 0);
        /** @noinspection CurlSslServerSpoofingInspection */
        curl_setopt($template, CURLOPT_SSL_VERIFYHOST, false);
        /** @noinspection CurlSslServerSpoofingInspection */
        curl_setopt($template, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($template, CURLOPT_BINARYTRANSFER, true);

        foreach ($options as $k => $v) {
            if (is_int($k)) {
                curl_setopt($template, $k, $v);
            }
        }

        foreach ($files as $url => $file) {
            $file = $this->alias->resolve($file);
            if (is_file($file)) {
                unset($files[$url]);
            } else {
                $this->filesystem->dirCreate(dirname($file));
                $files[$url] = $file;
            }
        }

        $handles = [];
        $failed = [];
        do {
            foreach ($files as $url => $file) {
                if (count($handles) === $concurrent) {
                    break;
                }
                $curl = curl_copy_handle($template);
                $id = (int)$curl;

                curl_setopt($curl, CURLOPT_URL, $url);
                $fp = fopen($file . '.tmp', 'wb');
                curl_setopt($curl, CURLOPT_FILE, $fp);

                curl_multi_add_handle($mh, $curl);
                $handles[$id] = ['url' => $url, 'file' => $file, 'fp' => $fp];

                unset($files[$url]);
            }

            $running = null;
            while (curl_multi_exec($mh, $running) === CURLM_CALL_MULTI_PERFORM) {
                null;
            }

            usleep(100);

            while ($info = curl_multi_info_read($mh)) {
                $curl = $info['handle'];
                $id = (int)$curl;

                $url = $handles[$id]['url'];
                $file = $handles[$id]['file'];

                fclose($handles[$id]['fp']);

                if ($info['result'] === CURLE_OK) {
                    rename($file . '.tmp', $file);
                } else {
                    $failed[$url] = curl_strerror($curl);
                    unlink($file . '.tmp');
                }

                curl_multi_remove_handle($mh, $curl);
                curl_close($curl);

                unset($handles[$id]);
            }
        } while ($handles);

        curl_multi_close($mh);
        curl_close($template);

        if ($return_file) {
            return $failed ? false : $return_file;
        } else {
            return $failed;
        }
    }
}