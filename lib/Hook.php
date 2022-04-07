<?php

namespace king\lib;

use king\lib\permission\LoginCache;
use king\lib\permission\RoleCache;
use king\lib\permission\MenuModel;
use king\lib\exception\UnauthorizedHttpException;

class Hook
{
    public static function listen($class = [], $response = '')
    {
        if (!empty($class)) {
            $call = new $class[0];
            $method = $class[1];
            $response['params'] = self::getParams();
            $response['header'] = H();
            $call->$method($response);
        }

        if (ENV != 'testing' && !defined('SCRIPT')) {
            exit;
        }
    }

    public static function begin($permission)
    {
        if ($permission) { // 如果需要全局校验
            $uris = explode('/', source());
            if ($uris[0] == 'www') {
                array_shift($uris);
            }

            $uris2 = $uris;
            $url = strtolower(join('/', $uris));
            array_pop($uris2);
            $url_variant = trim(strtolower(join('/', $uris2)), '/') . '/*';
            $white_list = explode(',', C('permission.white_list'));
            if (in_array($url, $white_list) || in_array($url_variant, $white_list)) { // 白名单就跳过验证
                return;
            }

            $rs = LoginCache::checkToken(H(C('permission.token_header')));
            if (!$rs) {
                throw new UnauthorizedHttpException('token校验失败');
            }

            $id = self::getMe();
            $role_ids = RoleCache::getRole($id);
            if ($role_ids != 'admin') { // 非超级管理员
                $role_ids = explode(',', $role_ids);
                if (is_array($role_ids) && count($role_ids) > 0) {
                    $menu_id = MenuModel::field(['id'])->where('url', $url)->value();
                    $permissions = RoleCache::getMenu($role_ids);

                    if (!in_array($menu_id, $permissions)) {
                        throw new UnauthorizedHttpException('没有访问该模块的权限');
                    }
                } else {
                    throw new UnauthorizedHttpException('用户角色不存在或被禁用');
                }
            }
        }
    }


    public static function whetherMe($id)
    {
        return ($id == self::getMe());
    }

    public static function getMe($token = '')
    {
        $token = $token ?: H(C('permission.token_header'));
        return LoginCache::getId($token);
    }

    public static function checkJson($value)
    {
        $data = json_decode((string)$value, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new BadRequestHttpException('无法解析Json ' . json_last_error());
        } else {
            if (!is_array($data)) {
                throw new BadRequestHttpException('json格式有误');
            }
        }

        return $data;
    }

    protected static function getParams()
    {
        $method = strtolower($_SERVER['REQUEST_METHOD'] ?? 'get');
        switch ($method) {
            case 'post':
                $data = P();
                break;
            case 'put':
                $data = stream();
                break;
            default:
                $data = G();
                break;
        }

        return $data;
    }
}