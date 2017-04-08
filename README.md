godok核心类库
适用于godok的相关产品
[《验证规则说明》](http://www.kancloud.cn/fafa2088/goa/299534)
**Auth::**
验证器Auth
验证器保存了用户信息，用户信息是保存在thinkphp的session中。
~~~
$user=[
    'id'=>用户唯一标识,
    'username'=>帐号,
    'nickname'=>昵称,
    'avatar'=>头像url,
    'email'=>邮箱,
    'phone'=>电话,
    'rules'=>[1,5,6] /*权限id*/,
    'groupids'=>[]/*用户组id*/
];
\Godok\Org\Auth::user($user);
~~~
Auth提供了用户信息的读写删接口
**读用户信息**
~~~
\Godok\Org\Auth::user() //读取全部用户信息$user
\Godok\Org\Auth::user('name') //读取$user['name']
~~~
**写用户信息**
~~~
\Godok\Org\Auth::user(['name'=>'test']) //参数为数组表示覆盖用户信息
\Godok\Org\Auth::user('name','test') //写$user['name'] = 'test'，第二个参数也可以是数组
\Godok\Org\Auth::set('name','test') //写$user['name'] = 'test'，第二个参数也可以是数组
~~~
**删除用户信息**
~~~
\Godok\Org\Auth::clear() //注销用户信息
\Godok\Org\Auth::set() //注销用户信息
\Godok\Org\Auth::set('name') //删除$user['name'] 
~~~
**手动验证**

自动验证是验证的request，但是有些权限与request无关，那么就需要在业务手动去判断


>Auth::check($action, $uid) 
@$action  匹配rule中的行为
@$uid 缺省值是当前登录用户的id
return boolen，验证通过时返回true，否则返回false

参考说明：
用传入的参数到rule表中寻找规则，未找到返回false，找到继续判断用户是否拥有该权限
~~~
Auth::check('edit')             //module='' && controller='' && action='edit'
Auth::check('Uer/edit')         //module='' && controller='User' && action='edit'
Auth::check('auth/User/edit')   //module='auth' && controller='User' && action='edit'
//多条用“,”或者“|”隔开，前者是“and”判断，后者是“or”判断
//如：
Auth::check('edit,add,delete') 
Auth::check('edit|add|delete') 
~~~


**返回权限组**
>\Godok\Org\Auth::getGroups($uid, $onlyid)
$uid 缺省值是当前登录用户的id
$onlyid  当$onlyid=true时，表示只返回权限组id数组 ，如：[1,3,5]，当未传入或者传入false时，返回数组的每个成员都是完整的权限信息，如：[{'id'=>1,'title'=>'管理组',...},{},{},...]

**返回权限菜单树**
>\Godok\Org\Auth::menu($where, $pid) 
$where 为可选项
$pid 获取$pid下的子菜单,默认为0，获取所有菜单
*注意：无论传入什么参数，都只能获取到当前用户拥有的权限菜单

**验证规则**
权限规则是GA的核心，理论上所有的请求都要经过GA验证，除非“action_begin”未被触发。
”权限=url=菜单“的思想，为后端开发节约了非常多的时间和精力
**验证流程**
~~~
根据request信息匹配权限规则
├ 未匹配中任何规则，将被拒绝访问。
├ 配中一条会多条规则。
       ├ 用户只要拥有其中一条就可以通过验证
       ├ 无权限，拒绝访问
~~~