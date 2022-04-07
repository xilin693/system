<?php

namespace king\lib;

use king\core\Instance;
use king\lib\exception\BadRequestHttpException;
use king\lib\permission\CaptchaValid;
use king\lib\permission\LoginCache;
use king\lib\permission\RoleCache;
use king\lib\permission\AccountModel;
use king\lib\permission\LoginHelper;

class Permission extends Instance
{
    private $config;

    public function __construct()
    {
        $this->config = C('permission.*');
    }

    public function loadByUser($username, $password)
    {
        $params = $this->config['params'];

        // 如果密码有设置重试上限
        if (!empty($params['max_retry_times']))
        {
            $max_retry_period = $params['max_retry_period'] ?? 86400;
            LoginCache::check($username, $params['max_retry_times'], $max_retry_period);
        }
        $row = AccountModel::field(['id', 'role_ids', 'admin', 'status'])->where('username', $username)->where('password',
            LoginHelper::crypt($password))->find();
        if ($row) {
            if ($row['status'] != 1) {
                throw new BadRequestHttpException('用户未启用或被禁用');
            }

            $token = LoginHelper::makeToken($row['id'], $this->config['token_salt'], $this->config['token_expire']);
            // 写入用户的角色
            $role_ids = $row['admin'] ? 'admin' : $row['role_ids'];
            RoleCache::setRole($row['id'], $role_ids);
            return ['token' => $token];
        } else {
            if (!empty($params['max_retry_times'])) {
                LoginCache::incrTimes($username);
            }

            throw new BadRequestHttpException('用户名或密码错误');
        }
    }

    public function tokenValid($token)
    {
        if (strlen($token) != 32 || !preg_match('/^[A-Za-z0-9]+$/', $token)) {
            throw new BadRequestHttpException('数据校验失败'); // 此处仅针对恶意调用,不作明确提示
        }
    }

    public function logout($token)
    {
        LoginCache::expireToken($token, -1);
    }
}