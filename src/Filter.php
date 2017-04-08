<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: fafa2088@qq.com
// +----------------------------------------------------------------------
namespace Godok\Org;

/**
 * 过滤器
 */

final class Filter
{
    
    /**
     * 密码
     */
    public static function username($value) {
        if (is_string($value) && preg_match('/^.{4,}$/i', $value)) {
            return $value;
        } else {
            return '';
        }
    }
    /**
     * 密码
     */
    public static function password($value) {
        if (is_string($value) && preg_match('/^.{6,}$/i', $value)) {
            return $value;
        } else {
            return '';
        }
    }
   
}