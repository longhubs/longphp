<?php
// src/Response.php
// LongPHP Framework - HTTP 响应类
// 龙行天下 🐉

namespace Long;

class Response
{
    /**
     * 响应内容
     * @var string
     */
    protected $content = '';

    /**
     * HTTP 状态码
     * @var int
     */
    protected $statusCode = 200;

    /**
     * 响应头
     * @var array
     */
    protected $headers = [];

    /**
     * 响应协议版本
     * @var string
     */
    protected $protocol = '1.1';

    /**
     * 构造函数
     * @param string $content    响应内容
     * @param int    $statusCode HTTP 状态码
     */
    public function __construct($content = '', $statusCode = 200)
    {
        $this->content($content);
        $this->statusCode = $statusCode;
    }

    /**
     * 设置响应内容
     * @param string $content
     * @return $this
     */
    public function content($content)
    {
        $this->content = (string) $content;
        return $this;
    }

    /**
     * 追加响应内容
     * @param string $content
     * @return $this
     */
    public function append($content)
    {
        $this->content .= (string) $content;
        return $this;
    }

    /**
     * 获取响应内容
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * 设置状态码
     * @param int $code
     * @return $this
     */
    public function status($code)
    {
        $this->statusCode = (int) $code;
        return $this;
    }

    /**
     * 获取状态码
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * 设置响应头
     * @param string $name  头名称
     * @param string $value 头值
     * @return $this
     */
    public function header($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * 批量设置响应头
     * @param array $headers
     * @return $this
     */
    public function headers(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }
        return $this;
    }

    /**
     * 获取响应头
     * @param string|null $name
     * @return mixed
     */
    public function getHeader($name = null)
    {
        if ($name === null) {
            return $this->headers;
        }
        return $this->headers[$name] ?? null;
    }

    /**
     * 设置 Cookie
     * @param string $name
     * @param string $value
     * @param int    $expire
     * @param string $path
     * @param string $domain
     * @param bool   $secure
     * @param bool   $httponly
     * @return $this
     */
    public function cookie($name, $value = '', $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = false)
    {
        setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
        return $this;
    }

    /**
     * 发送响应
     */
    public function send()
    {
        // 发送状态码
        http_response_code($this->statusCode);

        // 发送响应头
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // 发送内容
        echo $this->content;
    }

    /**
     * 返回 JSON 响应
     * @param mixed $data
     * @param int   $statusCode
     * @return static
     */
    public static function json($data, $statusCode = 200)
    {
        $response = new static('', $statusCode);
        $response->header('Content-Type', 'application/json');
        $response->content(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response;
    }

    /**
     * 返回 JSONP 响应
     * @param mixed  $data
     * @param string $callback
     * @param int    $statusCode
     * @return static
     */
    public static function jsonp($data, $callback = 'callback', $statusCode = 200)
    {
        $response = new static('', $statusCode);
        $response->header('Content-Type', 'application/javascript');
        $response->content($callback . '(' . json_encode($data, JSON_UNESCAPED_UNICODE) . ')');
        return $response;
    }

    /**
     * 返回 XML 响应
     * @param mixed $data
     * @param int   $statusCode
     * @return static
     */
    public static function xml($data, $statusCode = 200)
    {
        $response = new static('', $statusCode);
        $response->header('Content-Type', 'application/xml');
        $response->content(self::arrayToXml($data));
        return $response;
    }

    /**
     * 返回视图响应
     * @param string $view
     * @param array  $data
     * @param int    $statusCode
     * @return static
     */
    public static function view($view, $data = [], $statusCode = 200)
    {
        $content = View::render($view, $data);
        return new static($content, $statusCode);
    }

    /**
     * 返回下载响应
     * @param string $file    文件路径
     * @param string $name    下载文件名
     * @param int    $statusCode
     * @return static
     */
    public static function download($file, $name = null, $statusCode = 200)
    {
        if (!file_exists($file)) {
            return new static('文件不存在', 404);
        }

        $name = $name ?: basename($file);

        $response = new static('', $statusCode);
        $response->header('Content-Type', mime_content_type($file) ?: 'application/octet-stream');
        $response->header('Content-Disposition', 'attachment; filename="' . $name . '"');
        $response->header('Content-Length', filesize($file));
        $response->content(file_get_contents($file));
        return $response;
    }

    /**
     * 返回重定向响应
     * @param string $url
     * @param int    $statusCode
     * @return static
     */
    public static function redirect($url, $statusCode = 302)
    {
        $response = new static('', $statusCode);
        $response->header('Location', $url);
        return $response;
    }

    /**
     * 判断是否 JSON 响应
     * @return bool
     */
    public function isJson()
    {
        return isset($this->headers['Content-Type']) && strpos($this->headers['Content-Type'], 'application/json') !== false;
    }

    /**
     * 数组转 XML
     * @param mixed $data
     * @param string $root
     * @return string
     */
    protected static function arrayToXml($data, $root = 'root')
    {
        $xml = new \SimpleXMLElement('<' . $root . '/>');
        self::arrayToXmlRecursive($data, $xml);
        return $xml->asXML();
    }

    /**
     * 递归转换数组为 XML
     * @param array $data
     * @param \SimpleXMLElement $xml
     */
    protected static function arrayToXmlRecursive($data, &$xml)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item';
                }
                $child = $xml->addChild($key);
                self::arrayToXmlRecursive($value, $child);
            } else {
                if (is_numeric($key)) {
                    $key = 'item';
                }
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
    }

    /**
     * 输出到浏览器
     */
    public function __toString()
    {
        return $this->content;
    }
}