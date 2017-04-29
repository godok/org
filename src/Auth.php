<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: luofei614 <weibo.com/luofei614>
// +----------------------------------------------------------------------
// | 修改者: fafa2088@qq.com
// +----------------------------------------------------------------------
namespace Godok\Org;

use think\Db;
use think\Config;
use think\Session;
use think\Request;
use think\Loader;

/**
 * 权限认证类
 * 如果有任何不清楚的地方可以加QQ：158937496，欢迎交流
 */

final class Auth
{
    /**
     * 用户session变量空间
     */
    protected static $prefix = 'goauth';
    /**
     * 行为验证
     */
    public static function actionBegin($call)
    {
        $request = Request::instance();
        if ( ! Config::get('auth.auth_on') ) {
            //验证未启用
           return true;
        }
        
        $module = $request->module();
        $controller = $request->controller();
        
        if ( $module == 'install' &&  $controller == 'Index') {
            return true;
        }
        if ( $module == 'auth' &&  $controller == 'Login') {
            return true;
        }
        
        $action = $request->action();
        $param = $request->param();
        //刷新用户信息缓存
        self::fresh();
        $userRule = self::user('rules');
        if (!is_array($userRule)) {
            self::error('对不起！您无权进行该操作.',\think\Request::instance()->root().'/auth/Login/index');
        }
       
        if ( in_array('all', $userRule)) {
            //拥有所有权限
            return true;
        }
        //根据请求找出匹配中的规则
        $query =  Db::connect([],'goauthqq158937496')->name( Config::get('auth.table_rule') )->field('`condition`,`id`')->where('status',1);
        if (!empty($module)) {
            $query->where("`module`='*' or FIND_IN_SET('".$module."',`module`)");
        }
        if (!empty($controller)) {
            $query->where("`controller`='*' or FIND_IN_SET('".$controller."',`controller`)");
        }
        if (!empty($action)) {
            $query->where("`action`='*' or FIND_IN_SET('".$action."',`action`)");
        }
        $list = $query->order('listorder asc')->select();
        $rules = [];
        $rules_p = [];
        foreach ($list as $rule) {
            if (empty($rule['condition'])) {
                //无参规则
                $rules[] = $rule['id'];
            } else {
                //有参规则
                $cond = self::parseParam($rule['condition']);
                $hitall = true;
                foreach($cond as $key=>$val) {
                    if ( !isset($param[$key]) ) {
                        $hitall = false;
                        break;
                    } elseif (!empty($val)) {
                        //匹配指定
                        if ( 0 === strpos($val,'/') ) {
                            //正则匹配
                            if (!preg_match($val,$param[$key])) {
                                $hitall = false;
                                break;
                            }
                        } elseif ($val != $param[$key]) {
                            //相等匹配
                            $hitall = false;
                            break;
                        }
                    } else {
                        //匹配所有值
                    }
                }
                if ($hitall) {
                    //匹配中一条有参规则
                    $rules_p[] = $rule['id'];
                }
            }
        }
        //有参规则被匹配中，验证有参规则
        if( !empty($rules_p) ) {
            $rules = $rules_p;
        }
        foreach ($rules as $id) {
            if ( in_array($id, $userRule) ) {
                return true;
            }
        }
        self::error('对不起！您无权进行该操作',\think\Request::instance()->root().'/auth/Login/index');
        exit;
    }
    /**
     * 字符串参数转数组
     * 这里类似parse_str()函数，但是不会转义特殊字符
     */
    private static function parseParam($str) {
        $param = [];
        $arr = explode('&',$str);
        foreach ( $arr as $p) {
            if( !empty($p) ) {
                if( false === strpos($p,'=') ) {
                    $p .= "=";
                }
                $q = explode('=', $p);
                $param[$q[0]] = $q[1];
            }
        }
        return $param;
    }
    /**
     * 刷新用户信息
     */
    private static function fresh(){ 
        if ( empty(self::user()) ) {
            //游客登录
            self::clean();
        } elseif (
                Config::get('auth.auth_type') == '1' 
                || ( 
                    intval(Config::get('auth.auth_type')) > 1 
                    && intval(self::user('_freshtime')) < time() - 60 * intval(Config::get('auth.auth_type'))
                    )
        ) {
            //实时验证或用户信息已过期
            if( empty(self::user('id'))) {
                //游客
                self::clean();
            } else {
                //登录用户
                $data=Db::connect([],'goauthqq158937496')->name( Config::get('auth.table_user') )->field('id,username,nickname,deleted,status')->where('id',self::user('id'))->find();
                if($data['deleted'] || 1 != $data['status'] ) {
                    //用户被删除或被禁用，切换到游客模式
                    self::clean();
                    return false;
                }
                self::set('username', $data['username']);
                self::set('nickname', $data['nickname']);
                self::set('groupids', self::getGroupids());
                self::set('rules',    self::getRules());
                self::set('_freshtime', time() );  
            }
        }
    }

    /**
     * 注册用户信息
     * @参数1 $name
     *   1. 数组：覆盖用户信息
     *   2. 为空：返回用户信息
     *   3. 字符串
     *     3.1 未传入参数2：返回user[$name]
     *     3.2 传入参数2：   设置user[$name] = $value
     * @参数2 $value 当传入该参数时，为赋值操作
     */
    public static function user($name = null, $value = null)
    {
        if ( is_array($name) ) {
            Session::set('user',$name, self::$prefix);
        } elseif ( empty($name) ) {
            return Session::get('user', self::$prefix);
        } elseif ( is_string($name) && null === $value ) {
            return Session::get('user.'.$name, self::$prefix);
        } elseif ( is_string($name) ) {
            Session::set('user.'.$name, $value, self::$prefix);
        } else {
            return null;
        }
    }
    /**
     * 设置用户信息
     * @param 键名 ，为空时清空用户信息
     * @param 键值，省略该参数时删除$name变量
     */
    public static function set($name = null, $value = null)
    {
        if(empty($name)) {
            Session::delete('user',self::$prefix);
        } elseif ( is_string($name) && null === $value ) {
            Session::delete('user.'.$name, self::$prefix);
        } elseif ( is_string($name) ) {
            Session::set('user.'.$name,$value,self::$prefix);
        } elseif ( is_array($name) ) {
            Session::set('user',$value,self::$prefix);
        }
    }
    /**
     * 注销用户,切换为游客模式
     */
    public static function clean()
    {
        $group = Db::connect([],'goauthqq158937496')->name( Config::get('auth.table_group') )->field('id,rules')->where('id',3)->find();
        if ( $group) {
            $user = ['rules' => array_unique( explode(',', $group['rules']) ), 'groupids'=>[3], '_freshtime'=>time()];
            Session::set('user', $user, self::$prefix);
        } else {
            Session::delete('user',self::$prefix);
        }
        
    }
    /**
     * 获取树状菜单
     * @param 查询条件
     * @param 父节点id
     */
    public static function menu($where = false , $pid = 0)
    {
        $menu = [];
        $ids = self::user('rules');
        if(!is_array($ids)) {
            return [];
        }
        $query =  Db::connect([],'goauthqq158937496')->name( Config::get('auth.table_rule') );
        if( !in_array('all', $ids)) {
            $query->where('id', 'in', $ids);
        }
        if( $where ) {
            $query->where( $where );
        }
        $list = $query->order("listorder asc")->limit(2048)->select();
        if( $list) {
            return self::toTree($list, $pid, true);
        } else {
            return [];
        }
    }
    /**
     * 权限树格式化
     */
    private static function toTree($data = null, $pid = 0, $reset = false)
    {
        static $node = [];
        if(empty($data) || !is_array($data) ) {
            return [];
        }
        if( $reset ) {
            $node = [];
        }
        //父节点
        if( $node === [] ) {
            foreach ($data as $item) {
                if($item['pid'] == $item['id']) {
                    //避免死循环
                    continue;
                }
                if( isset($node[$item['pid']]) ) {
                    $node[$item['pid']][] = $item;
                }else {
                    $node[$item['pid']] = [$item];
                }
            }
        }
        
        $tree = [];
        foreach( $data as $item ) {
            if($pid == $item['pid']) {
                if( isset($node[$item['id']]) ) {
                    $item['children'] = self::toTree($node[$item['id']], $item['id']);
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }
    /**
     * 根据用户id获取用户组,返回值为数组
     * @param  $uid int     用户id ，默认是当前登录用户
     * @param  $onlyid boolen 是否只返回id,self::getGroupids()
     * @return array       用户所属的用户组 array(
     *              array('uid'=>'用户id','group_id'=>'用户组id','title'=>'用户组名称','rules'=>'用户组拥有的规则id,多个,号隔开'),
     *              ...)
     */
    public static function getGroups($uid = null, $onlyid = false)
    {
        if(empty($uid)) { 
            $uid = self::user('id');
        }
        if(empty($uid)) { 
            return [];//游客组
        }
        static $groups = [];
        if (isset($groups[$uid])) {
            if($onlyid) {
                return self::getGroupids($groups[$uid]);
            } else {
                return $groups[$uid];
            }
        }
        $user_groups = Db::connect([],'goauthqq158937496')->view( Config::get('auth.table_group_relation'), 'uid')
            ->view( Config::get('auth.table_group') , 'status,rules,id,listorder,title', Config::get('auth.table_group').'.id='.Config::get('auth.table_group_relation').'.group_id')
            ->where(['uid'=>$uid,'status'=>1])
            ->order('listorder asc,id asc')
            ->limit(99)
            ->select();
        $groups[$uid] = $user_groups ?: [];
        if($onlyid) {
           return self::getGroupids($groups[$uid]);
        } else {
           return $groups[$uid];
        }
    }
    /**
     * 获取用户组id数组
     * @params mixed
     * 传入数组：groups
     * 传入字符串或整数： uid
     * 缺省值：当前用户的groups
     */
    public static function getGroupids($groups = null){
        if (null === $groups) {
            $groups = self::getGroups();
        } elseif (is_string($groups) || is_int($groups) ) {
            $groups = self::getGroups($groups);
        }
        $ids = [];
        foreach($groups as $val) {
            if( isset($val['id']) ) {
                $ids[] = $val['id'];
            }
        }
        return $ids;
    }
    /**
     * 获取权限id数组
     * @params mixed
     * 传入groupids数组 ： 返回对应groups包含的权限
     * 传入groups数组 ： 返回groups包含的权限
     * 传入字符串或者整数：返回指定用户的权限,继承游客和登录用户组的权限
     * 缺省值：返回当前用户的权限,继承游客和登录用户组的权限
     */
    public static function getRules($groups = null){
        $ext = false;
        if (null === $groups) {
            //当前用户的权限组
            $groups = self::getGroups();
            $ext = true;
        } elseif (is_string($groups) || is_int($groups) ) {
            //uid的权限组
            $groups = self::getGroups($groups);
            $ext = true;
        } elseif ( is_array($groups) && isset($groups[0]) && !isset($groups[0]['id'])) {
            //groupids转groups
            $groups = Db::connect([],'goauthqq158937496')->name( Config::get('auth.table_group') )->field('id,rules')->where('status',1)->where('id','in',$groups)->select();
        }
        if ( $ext ) {
            $group23 = Db::connect([],'goauthqq158937496')->name( Config::get('auth.table_group') )->field('id,rules')->where('id IN(2,3) AND status=1')->select();
            $groups = array_merge($groups,$group23);
        }      
        $rules = '';
        foreach ( $groups as $group) {
            $rules .= $rules ? ',' . $group['rules'] : $group['rules'];  
        }
        if( !empty($rules) ) {
            return array_unique( explode(',', $rules) );
        } else {
            return [];
        }
       
    }
    /**
     * 判断权限
     * @param 权限标识1 与action匹配
     * @param 用户ID，如果为空，则判断当前登录用户的权限
     * return boolen
     */
    public static function check($action, $uid = null) {
        if ( empty($uid) ) {
            $userRule = self::user('rules');
        } else {
            $userRule = self::getRules($uid);
        }
        if ( in_array('all', $userRule) ) {
            return true;
        }
        $type = 'and';
        if( strpos($action,',') ) {
            //and判断
            $action = explode(',', $action);
        } elseif( strpos($action,'|') ) {
            //or判断
            $type = 'or';
            $action = explode('|', $action);
        } else {
            $action = [$action];
        }
        foreach($action as $val) {
            if( empty($val) ) {
                return false;
            }
            $route = explode('/',$val);
            $query = Db::connect([],'goauthqq158937496')->name( Config::get('auth.table_rule') )->field('id')->where('status',1);
            $act = array_pop($route);
            if( $i = strpos($act,'?') ) {
                $query->where("`condition`='" . substr($act,$i+1) . "'");
                $act = substr($act,0,$i);
            } else {
                $query->where("`condition`=''");
            }
            $query->where("`action`='" . $act . "'");
            if (!empty($route)) {
                $query->where("`controller`='" . array_pop($route) . "'");
            } else {
                $query->where("`controller` = ''");
            }
            if (!empty($route)) {
                $query->where("`module`='" . array_pop($route) . "'");
            } else {
                $query->where("`module` = ''");
            }
            $rule = $query->order('listorder asc,id asc')->find();
            if ( $rule ) {
                if( in_array($rule['id'], $userRule) && $type == 'or') {
                    //【or】其中一条有权限
                    return true;
                } elseif( !in_array($rule['id'], $userRule) && $type == 'and' ) {
                    //【and】其中一条无权限
                    return false;
                }
            } elseif( $type == 'and') {
                //【and】其中一条无权限
                return false;
            }
        }
        if ($type == 'and') {
           //【and】其中一条无权限都不会到这里，到了这里就表示全部有权限
           return true; 
        } else {
           //【or】其中一条有权限都不会到这里，到这里表示全部无权限
           return false;
        }
    }
    /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param mixed     $msg 提示信息
     * @param string    $url 跳转的URL地址
     * @param mixed     $data 返回的数据
     * @param integer   $wait 跳转等待时间
     * @param array     $header 发送的Header信息
     * @return void
     */
    protected static function error($msg = '', $url = null, $data = '', $wait = 9999, array $header = [])
    {
        $code = -1;
        if (is_numeric($msg)) {
            $code = $msg;
            $msg  = '';
        }
        if (is_null($url)) {
            $url = Request::instance()->isAjax() ? '' : 'javascript:history.back(-1);';
        } elseif ('' !== $url) {
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : \think\Url::build($url);
        }
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
            'url'  => $url,
            'wait' => $wait,
        ];
    
        $type = self::getResponseType();
        if ('html' == strtolower($type)) {
            $result = \think\View::instance(Config::get('template'), Config::get('view_replace_str'))
            ->fetch(Config::get('dispatch_error_tmpl'), $result);
        }
        $response = \think\Response::create($result, $type)->header($header);
        throw new  \think\exception\HttpResponseException($response);
    }
    /**
     * 获取当前的response 输出类型
     * @access protected
     * @return string
     */
    protected static function getResponseType()
    {
        $isAjax = Request::instance()->isAjax();
        return $isAjax ? Config::get('default_ajax_return') : Config::get('default_return_type');
    }
}