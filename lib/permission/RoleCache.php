<?php

namespace king\lib\permission;

use king\lib\Cache;

class RoleCache extends Cache
{
    public static function setRole($user_id, $role_ids)
    {
        return parent::set('user:' . $user_id . ':roles', $role_ids);
    }

    public static function getRole($user_id) {
        return parent::get('user:' . $user_id . ':roles');
    }

    public static function getMenu($ids)
    {
        $permission_ids = [];
        foreach ($ids as $id) {
            $role_permission = parent::get('role:' . $id);
            //如果没缓存就直接取数据库
            if ($role_permission == false) {
                $role_permission = RoleModel::field(['permission_ids'])->where('id', $id)->value();
                parent::set('role:' . $id, $role_permission);
            }

            $role_ids = json_decode($role_permission) ?: [];
            $permission_ids = array_merge($permission_ids, $role_ids);
        }

        return array_unique($permission_ids);
    }

    public static function setMenu($id, $permissions) {
        return parent::set('role:' . $id, $permissions);
    }
}