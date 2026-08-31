<?php
// src/Token.php
// LongPHP Framework - JWT 认证服务
// 龙行天下 🐉

namespace Long;

use InvalidArgumentException;
use RuntimeException;

/**
 * LongToken - JWT 认证服务
 * 
 * 功能：
 * - HS256 对称加密（自定义密钥）
 * - Access Token / Refresh Token 双 Token 机制
 * - Token 自动刷新
 * 
 * 命名寓意：Long = Long-lived（长寿命），指 Refresh Token 比 Access Token 寿命更长
 */
class Token
{
    /**
     * @var string 自定义密钥
     */
    protected string $secretKey;

    /**
     * @var string 加密算法
     */
    protected string $algorithm = 'HS256';

    /**
     * @var int Access Token 有效期（秒）
     */
    protected int $accessTtl;

    /**
     * @var int Refresh Token 有效期（秒）
     */
    protected int $refreshTtl;

    /**
     * @var string 签发者
     */
    protected string $issuer;

    /**
     * @var string Token 类型标识
     */
    protected string $tokenType = 'Bearer';

    /**
     * @var array 配置信息
     */
    protected array $config = [];

    /**
     * 构造函数
     * 
     * @param array $config 配置数组
     * @throws RuntimeException
     */
    public function __construct(array $config = [])
    {
        // 加载配置
        $this->config = $config ?: $this->loadConfig();
        $this->initFromConfig();
    }

    /**
     * 从配置文件加载配置
     */
    protected function loadConfig(): array
    {
        $config = require ROOT_PATH . '/config/app.php';
        return $config['token'] ?? [];
    }

    /**
     * 从配置初始化
     */
    protected function initFromConfig(): void
    {
        $this->secretKey = $this->config['secret_key'] ?? '';
        if (empty($this->secretKey)) {
            throw new RuntimeException('Token 密钥未配置，请在 config/app.php 中设置 token.secret_key');
        }

        $this->algorithm = $this->config['algorithm'] ?? 'HS256';
        $this->accessTtl = $this->config['access_ttl'] ?? 3600;
        $this->refreshTtl = $this->config['refresh_ttl'] ?? 604800;
        $this->issuer = $this->config['issuer'] ?? 'longphp-api';
        $this->tokenType = $this->config['token_type'] ?? 'Bearer';
    }

    // ==================== 生成 Token ====================

    /**
     * 生成 Access Token（短寿命）
     * 
     * @param array $data 要存储的用户数据（如 user_id, role 等）
     * @param int|null $ttl 自定义有效期（秒），不传则使用默认值
     * @return string JWT Token
     */
    public function generateAccessToken(array $data, ?int $ttl = null): string
    {
        $ttl = $ttl ?? $this->accessTtl;
        
        $payload = [
            'iss' => $this->issuer,
            'iat' => time(),
            'exp' => time() + $ttl,
            'data' => $data,
            'jti' => $this->generateJti(),
            'type' => 'access'
        ];

        return $this->encode($payload);
    }

    /**
     * 生成 Refresh Token（长寿命）
     * 
     * @param array $data 要存储的用户数据
     * @param int|null $ttl 自定义有效期（秒），不传则使用默认值
     * @return string Refresh Token
     */
    public function generateRefreshToken(array $data, ?int $ttl = null): string
    {
        $ttl = $ttl ?? $this->refreshTtl;
        
        $payload = [
            'iss' => $this->issuer,
            'iat' => time(),
            'exp' => time() + $ttl,
            'data' => $data,
            'jti' => $this->generateJti(),
            'type' => 'refresh'
        ];

        return $this->encode($payload);
    }

    /**
     * 同时生成 Access Token 和 Refresh Token
     * 
     * @param array $data 用户数据
     * @return array ['access_token' => string, 'refresh_token' => string, 'expires_in' => int, 'token_type' => string]
     */
    public function generatePair(array $data): array
    {
        return [
            'access_token' => $this->generateAccessToken($data),
            'refresh_token' => $this->generateRefreshToken($data),
            'expires_in' => $this->accessTtl,
            'token_type' => $this->tokenType
        ];
    }

    /**
     * 编码 JWT（HS256）
     */
    protected function encode(array $payload): string
    {
        // 1. 构建 Header
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ];

        // 2. Base64Url 编码
        $base64Header = $this->base64UrlEncode(json_encode($header));
        $base64Payload = $this->base64UrlEncode(json_encode($payload));

        // 3. 生成签名（使用 HMAC）
        $signature = $this->sign("{$base64Header}.{$base64Payload}");
        $base64Signature = $this->base64UrlEncode($signature);

        // 4. 组合 JWT
        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }

    /**
     * HMAC 签名
     */
    protected function sign(string $data): string
    {
        $algo = $this->getAlgo();
        $signature = hash_hmac($algo, $data, $this->secretKey, true);
        
        if ($signature === false) {
            throw new RuntimeException('签名失败');
        }
        
        return $signature;
    }

    /**
     * 获取 HMAC 算法
     */
    protected function getAlgo(): string
    {
        $map = [
            'HS256' => 'sha256',
            'HS384' => 'sha384',
            'HS512' => 'sha512',
        ];
        
        return $map[$this->algorithm] ?? 'sha256';
    }

    // ==================== 验证 Token ====================

    /**
     * 验证 Token（返回完整载荷）
     * 
     * @param string $token JWT Token
     * @return array 验证成功返回载荷数据
     * @throws InvalidArgumentException Token 格式无效
     * @throws RuntimeException Token 已过期或签名无效
     */
    public function validate(string $token): array
    {
        // 1. 解析 Token
        $parts = $this->parseToken($token);
        [$base64Header, $base64Payload, $base64Signature] = $parts;

        // 2. 验证签名
        $signature = $this->base64UrlDecode($base64Signature);
        $data = "{$base64Header}.{$base64Payload}";
        
        if (!$this->verifySignature($data, $signature)) {
            throw new RuntimeException('Token 签名无效');
        }

        // 3. 解码 Payload
        $payload = json_decode($this->base64UrlDecode($base64Payload), true);
        if (!$payload || !is_array($payload)) {
            throw new RuntimeException('Token 数据格式无效');
        }

        // 4. 验证过期时间
        if (isset($payload['exp']) && time() > $payload['exp']) {
            throw new RuntimeException('Token 已过期');
        }

        return $payload;
    }

    /**
     * 友好验证（不抛异常）
     * 
     * @param string $token JWT Token
     * @return array ['success' => bool, 'data' => array|null, 'payload' => array|null, 'error' => string|null]
     */
    public function verify(string $token): array
    {
        try {
            $payload = $this->validate($token);
            return [
                'success' => true,
                'data' => $payload['data'] ?? [],
                'payload' => $payload,
                'error' => null
            ];
        } catch (RuntimeException | InvalidArgumentException $e) {
            return [
                'success' => false,
                'data' => null,
                'payload' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * HMAC 验证签名
     */
    protected function verifySignature(string $data, string $signature): bool
    {
        $expected = $this->sign($data);
        return hash_equals($expected, $signature);
    }

    /**
     * 解析 Token 为三部分
     */
    protected function parseToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Token 格式无效');
        }
        return $parts;
    }

    // ==================== Base64Url 编解码 ====================

    /**
     * Base64Url 编码
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64Url 解码
     */
    protected function base64UrlDecode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode($data);
        if ($decoded === false) {
            throw new RuntimeException('Base64Url 解码失败');
        }
        return $decoded;
    }

    // ==================== 辅助方法 ====================

    /**
     * 获取 Token 中的载荷（不验证签名和过期时间）
     */
    public function getPayload(string $token): ?array
    {
        try {
            $parts = $this->parseToken($token);
            $payload = json_decode($this->base64UrlDecode($parts[1]), true);
            return is_array($payload) ? $payload : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 获取用户数据（简化获取）
     */
    public function getUserData(string $token): ?array
    {
        $payload = $this->getPayload($token);
        return $payload['data'] ?? null;
    }

    /**
     * 获取用户ID（简化获取）
     */
    public function getUserId(string $token): ?int
    {
        $data = $this->getUserData($token);
        return $data['user_id'] ?? null;
    }

    /**
     * 检查 Token 是否过期
     */
    public function isExpired(string $token): bool
    {
        $payload = $this->getPayload($token);
        if (!$payload || !isset($payload['exp'])) {
            return true;
        }
        return time() > $payload['exp'];
    }

    /**
     * 获取 Token 剩余有效时间（秒）
     */
    public function getRemainingTime(string $token): int
    {
        $payload = $this->getPayload($token);
        if (!$payload || !isset($payload['exp'])) {
            return -1;
        }
        $remaining = $payload['exp'] - time();
        return $remaining > 0 ? $remaining : -1;
    }

    /**
     * 生成 Token 唯一 ID
     */
    protected function generateJti(): string
    {
        return bin2hex(random_bytes(16));
    }

    // ==================== Token 刷新 ====================

    /**
     * 刷新 Token（用 Refresh Token 换取新的 Access Token 和 Refresh Token）
     * 
     * @param string $refreshToken Refresh Token
     * @return array ['access_token' => string, 'refresh_token' => string, 'expires_in' => int]
     * @throws RuntimeException 刷新失败
     */
    public function refresh(string $refreshToken): array
    {
        $payload = $this->validate($refreshToken);
        
        // 检查是否是 Refresh Token
        if (($payload['type'] ?? '') !== 'refresh') {
            throw new RuntimeException('无效的 Refresh Token，请使用 Refresh Token');
        }

        $data = $payload['data'] ?? [];

        // 生成新的 Token 对
        return $this->generatePair($data);
    }

    // ==================== 从请求中提取 Token ====================

    /**
     * 从 HTTP 请求中提取 Token
     * 支持：Authorization: Bearer <token> 或 Cookie
     * 
     * @param string|null $headerName 请求头名称
     * @param string|null $cookieName Cookie 名称
     * @return string|null
     */
    public function extractFromRequest(
        ?string $headerName = null,
        ?string $cookieName = null
    ): ?string {
        $headerName = $headerName ?? $this->config['extract']['header'] ?? 'Authorization';
        $cookieName = $cookieName ?? $this->config['extract']['cookie'] ?? null;

        // 1. 从请求头获取
        if ($headerName) {
            $authHeader = $this->getAuthorizationHeader($headerName);
            if ($authHeader) {
                // Bearer Token
                if (stripos($authHeader, 'Bearer ') === 0) {
                    return substr($authHeader, 7);
                }
                // 其他格式
                if (stripos($authHeader, $this->tokenType . ' ') === 0) {
                    return substr($authHeader, strlen($this->tokenType) + 1);
                }
                return $authHeader;
            }
        }

        // 2. 从 Cookie 获取
        if ($cookieName && isset($_COOKIE[$cookieName])) {
            return $_COOKIE[$cookieName];
        }

        // 3. 从 GET 参数获取（可选，用于 WebSocket 等场景）
        if (isset($_GET['token'])) {
            return $_GET['token'];
        }

        return null;
    }

    /**
     * 获取请求头（兼容 CLI 和 FPM 环境）
     */
    protected function getAuthorizationHeader(string $headerName): ?string
    {
        // 方法1：getallheaders()
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === strtolower($headerName)) {
                    return $value;
                }
            }
        }

        // 方法2：$_SERVER（Apache/Nginx 环境）
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
        return $_SERVER[$key] ?? null;
    }

    // ==================== 中间件辅助 ====================

    /**
     * 获取当前请求的用户（中间件使用）
     * 
     * @return array|null 用户数据
     */
    public function currentUser(): ?array
    {
        $token = $this->extractFromRequest();
        if (!$token) {
            return null;
        }

        $result = $this->verify($token);
        return $result['success'] ? $result['data'] : null;
    }

    /**
     * 获取当前请求的用户ID
     */
    public function currentUserId(): ?int
    {
        $user = $this->currentUser();
        return $user['user_id'] ?? null;
    }

    // ==================== Getter 方法 ====================

    /**
     * 获取配置信息
     */
    public function getConfig(): array
    {
        return [
            'algorithm' => $this->algorithm,
            'access_ttl' => $this->accessTtl,
            'refresh_ttl' => $this->refreshTtl,
            'issuer' => $this->issuer,
            'token_type' => $this->tokenType,
        ];
    }

    /**
     * 获取 Access Token 有效期
     */
    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }

    /**
     * 获取 Refresh Token 有效期
     */
    public function getRefreshTtl(): int
    {
        return $this->refreshTtl;
    }
}