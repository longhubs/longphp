<?php
// long/Validate.php
// LongPHP Framework - 数据验证器基类
// 支持规则：required, email, min, max，可扩展自定义规则

namespace Long;

class Validate
{
    /**
     * 验证规则
     * 格式：['字段名' => '规则1|规则2|规则3']
     * @var array
     */
    protected $rules = [];

    /**
     * 自定义错误消息
     * 格式：['字段名.规则名' => '错误消息']
     * @var array
     */
    protected $messages = [];

    /**
     * 验证错误信息
     * @var array
     */
    protected $errors = [];

    // ─────────────────────────────────────────────────────────────
    // 核心验证方法
    // ─────────────────────────────────────────────────────────────

    /**
     * 执行数据验证
     * @param array $data 待验证的数据
     * @return bool 验证通过返回 true，失败返回 false
     * @example
     * $validate = new UserValidate();
     * if (!$validate->check($_POST)) {
     *     return $this->error($validate->getErrors());
     * }
     */
    public function check($data)
    {
        $this->errors = [];

        foreach ($this->rules as $field => $rule) {
            $rules = explode('|', $rule);
            $value = $data[$field] ?? null;

            foreach ($rules as $r) {
                // 跳过空值（除非是 required）
                if ($r !== 'required' && $this->isEmpty($value)) {
                    continue;
                }

                if (!$this->validateRule($field, $r, $value)) {
                    $this->errors[$field] = $this->getErrorMessage($field, $r);
                    break; // 一个字段只记录第一个错误
                }
            }
        }

        return empty($this->errors);
    }

    // ─────────────────────────────────────────────────────────────
    // 错误处理
    // ─────────────────────────────────────────────────────────────

    /**
     * 获取所有验证错误信息
     * @return array 错误信息数组
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * 获取第一个错误信息
     * @return string|null
     */
    public function getFirstError()
    {
        return reset($this->errors) ?: null;
    }

    /**
     * 检查是否有错误
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    // ─────────────────────────────────────────────────────────────
    // 内部验证方法
    // ─────────────────────────────────────────────────────────────

    /**
     * 验证单个规则
     * @param string $field 字段名
     * @param string $rule 验证规则
     * @param mixed $value 值
     * @return bool 验证通过返回 true
     */
    private function validateRule($field, $rule, $value)
    {
        // 必填
        if ($rule === 'required') {
            return !$this->isEmpty($value);
        }

        // 邮箱格式
        if ($rule === 'email') {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        }

        // URL 格式
        if ($rule === 'url') {
            return filter_var($value, FILTER_VALIDATE_URL) !== false;
        }

        // 整数
        if ($rule === 'int') {
            return filter_var($value, FILTER_VALIDATE_INT) !== false;
        }

        // 数字（整数或小数）
        if ($rule === 'numeric') {
            return is_numeric($value);
        }

        // 手机号（简单验证）
        if ($rule === 'phone') {
            return preg_match('/^1[3-9]\d{9}$/', $value) === 1;
        }

        // 最大长度
        if (strpos($rule, 'max:') === 0) {
            $max = (int) substr($rule, 4);
            return mb_strlen($value) <= $max;
        }

        // 最小长度
        if (strpos($rule, 'min:') === 0) {
            $min = (int) substr($rule, 4);
            return mb_strlen($value) >= $min;
        }

        // 长度区间
        if (strpos($rule, 'length:') === 0) {
            $parts = explode(',', substr($rule, 7));
            $min = isset($parts[0]) ? (int) $parts[0] : 0;
            $max = isset($parts[1]) ? (int) $parts[1] : 9999;
            $len = mb_strlen($value);
            return $len >= $min && $len <= $max;
        }

        // 数值范围
        if (strpos($rule, 'between:') === 0) {
            $parts = explode(',', substr($rule, 8));
            $min = isset($parts[0]) ? (int) $parts[0] : 0;
            $max = isset($parts[1]) ? (int) $parts[1] : PHP_INT_MAX;
            return $value >= $min && $value <= $max;
        }

        // 等于某个值
        if (strpos($rule, 'eq:') === 0) {
            $expected = substr($rule, 3);
            return (string) $value === $expected;
        }

        // 正则表达式
        if (strpos($rule, 'regex:') === 0) {
            $pattern = substr($rule, 6);
            return preg_match($pattern, $value) === 1;
        }

        // 自定义规则（子类扩展）
        if (method_exists($this, $rule)) {
            return $this->$rule($value);
        }

        return true;
    }

    /**
     * 获取错误消息
     * @param string $field 字段名
     * @param string $rule 规则名
     * @return string
     */
    private function getErrorMessage($field, $rule)
    {
        // 优先使用自定义消息
        $key = $field . '.' . $rule;
        if (isset($this->messages[$key])) {
            return $this->messages[$key];
        }

        // 字段名友好化
        $fieldName = $this->getFieldName($field);

        // 默认消息
        $messages = [
            'required' => "{$fieldName}不能为空",
            'email'    => "{$fieldName}格式不正确",
            'url'      => "{$fieldName}格式不正确",
            'int'      => "{$fieldName}必须为整数",
            'numeric'  => "{$fieldName}必须为数字",
            'phone'    => "{$fieldName}格式不正确",
            'max'      => "{$fieldName}最多只能输入:max个字符",
            'min'      => "{$fieldName}最少需要:min个字符",
            'length'   => "{$fieldName}长度必须在:min到:max之间",
            'between'  => "{$fieldName}必须在:min到:max之间",
            'eq'       => "{$fieldName}必须等于:eq",
        ];

        // 替换规则参数
        $msg = $messages[$rule] ?? "{$fieldName}验证失败";

        // 替换占位符
        if (strpos($rule, 'max:') === 0) {
            $msg = str_replace(':max', substr($rule, 4), $msg);
        }
        if (strpos($rule, 'min:') === 0) {
            $msg = str_replace(':min', substr($rule, 4), $msg);
        }
        if (strpos($rule, 'length:') === 0) {
            $parts = explode(',', substr($rule, 7));
            $msg = str_replace(':min', $parts[0] ?? '0', $msg);
            $msg = str_replace(':max', $parts[1] ?? '9999', $msg);
        }
        if (strpos($rule, 'between:') === 0) {
            $parts = explode(',', substr($rule, 8));
            $msg = str_replace(':min', $parts[0] ?? '0', $msg);
            $msg = str_replace(':max', $parts[1] ?? '9999', $msg);
        }
        if (strpos($rule, 'eq:') === 0) {
            $msg = str_replace(':eq', substr($rule, 3), $msg);
        }

        return $msg;
    }

    /**
     * 获取友好的字段名
     * @param string $field
     * @return string
     */
    private function getFieldName($field)
    {
        // 如果定义了字段名映射，优先使用
        if (isset($this->fields[$field])) {
            return $this->fields[$field];
        }

        // 默认：将下划线转中文显示
        $map = [
            'id'       => 'ID',
            'name'     => '姓名',
            'email'    => '邮箱',
            'phone'    => '手机号',
            'password' => '密码',
            'status'   => '状态',
            'role'     => '角色',
            'title'    => '标题',
            'content'  => '内容',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ];

        return $map[$field] ?? $field;
    }

    /**
     * 检查值是否为空
     * @param mixed $value
     * @return bool
     */
    private function isEmpty($value)
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (is_array($value) && empty($value)) {
            return true;
        }
        return false;
    }

    // ─────────────────────────────────────────────────────────────
    // 扩展方法（子类可重写）
    // ─────────────────────────────────────────────────────────────

    /**
     * 自定义验证规则示例（子类可添加）
     * @param mixed $value
     * @return bool
     */
    // protected function customRule($value)
    // {
    //     // 自定义验证逻辑
    //     return true;
    // }
}