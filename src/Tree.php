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
 * 无限分层
 * @param $template : 解析模版，如果传入数组格式，返回多级数组，否则返回解析后的字符串
 * @param $data : 数组数据，必须包含“id”和“pid”两个键名
 * 返回层级菜单
 */

class Tree
{
    //原始数据
    private $data = [];
    //模版
    private $template = '';
    //节点
    private $node = [];
    //列表前缀
    private $prefix = '';
    //列表后缀
    private $postfix = '';
    public function __construct($template = '', $data = [], $prefix = '', $postfix = '')
    {
        $this->data = $data;
        $this->template = $template;
        $this->prefix = $prefix;
        $this->postfix = $postfix;
    }
    /**
     * 设置前后缀
     * @param 前缀 <ul>
     * @param 后缀 </ul>
     */
    public function setfix($prefix = '', $postfix = '') {
        $this->prefix = $prefix;
        $this->postfix = $postfix;
        return $this;
    }
    /**
     * 格式化tree
     */
    private function getTree($data = null, $pid = 0)
    {

        if (null === $data ) {
            return [];
        }
        $tree = [];
        foreach( $data as $item ) {
            if($pid == $item['pid']) {
                if( isset($this->node[$item['id']]) ) {
                    $item['children'] = $this->getTree($this->node[$item['id']], $item['id']);
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }
    /**
     * 返回层级菜单【分支数组】
     * @param $template : 解析模版，如果传入数组格式，返回多级数组，否则返回模版解析后的字符串
     * @param $data : 数组数据，必须包含“id”和“pid”两个键名
     * @param $pid : 根节点
     * 返回参考
     * [
     *  [
     *    'id' => 1,
     *    'pid' =>0, 
     *    'children' => [
     *      [
     *        'id' => 3,
     *        'pid' => 1
     *      ]
     *    ]
     *  ],
     *  [
     *    'id' => 2,
     *    'pid' => 0
     *  ]
     * ]
     */
    public function fetch($template = null, $data = null, $pid = 0)
    {
        if ( null === $data ) {
            $data = $this->data;
        }
        if ( null !== $template) {
            $this->template = $template;
        }
        if ( empty($data) ) {
            return is_array( $this->template ) ? [] : '';
        }
        //创建节点
        $this->node = [];
        foreach ($data as $item) {
            if ($item['pid'] == $item['id']) {
                //避免死循环
                continue;
            }
            if ( isset($this->node[$item['pid']]) ) {
                $this->node[$item['pid']][] = $item;
            } else {
                $this->node[$item['pid']] = [$item];
            }
        }
        $tree = $this->getTree($data, $pid);
        if ( is_array( $this->template ) ) {
            return $tree;
        } else {
            return $this->parse($tree);
        }
    }
    /**
     * 数组排序【无分支】
     * @param 原始数组
     * @param 根节点
     * 返回参考 ：  子项目排在父项目后面
     * [
     *  [
     *    'id' => 1,
     *    'pid' =>0, 
     *  ],
     *  [
     *    'id' => 3,
     *    'pid' =>1, 
     *  ],
     *  [
     *    'id' => 2,
     *    'pid' => 0
     *  ]
     * ]
     */
    public function sort($data = null, $pid = 0)
    {
        if ( null === $data ) {
            $data = $this->data;
        }
        //创建节点
        $this->node = [];
        foreach ($data as $item) {
            if ($item['pid'] == $item['id']) {
                //避免死循环
                continue;
            }
            if ( isset($this->node[$item['pid']]) ) {
                $this->node[$item['pid']][] = $item;
            } else {
                $this->node[$item['pid']] = [$item];
            }
        }
        $this->noTree($data, $pid, $list);
        return $list;
    }
    /**
     * 无分支数组组装
     */
    private function noTree($data = [], $pid=0, &$result,  $n = 0){
    
        if(empty($data) ) {
            return false;
        }
        foreach( $data as $item ) {
            if($n == 1 ) {
                $item['__prefix'] = '┕ ';
            } else if($n > 1) {
                $item['__prefix'] = str_repeat('...', $n-1).'┕ ';
            } else {
                $item['__prefix'] = '';
            }
            if($pid == $item['pid']) {
                $result[] = $item;
                if( isset($this->node[$item['id']]) ) {
                    $this->noTree($this->node[$item['id']], $item['id'], $result,  $n+1);
                }
            }
        }
    }
    /**
     * 解析多层菜单
     */
    public function parse($tree = [], $template = null)
    {
        if ( null !== $template ) {
            $this->template = $template;
        }
        if ( empty($this->template)) {
            return '';
        }
        $html = $this->prefix;
        foreach ( $tree as $item ) {
            $str = $this->template;
            if (preg_match_all('/{\$([0-9a-zA-Z_]*)}/', $str, $matches, PREG_SET_ORDER)) {
                foreach ( $matches as $val ) {
                    if ( isset($item[$val[1]]) ) {
                        switch( $val[1] ) {
                            case 'children' :
                                $children = $this->parse($item['children']);
                                $str = str_replace($val[0],$children,$str);
                                break;
                            default :
                                $str = str_replace($val[0],$item[$val[1]],$str);
                                break;
                        }
                    } else {
                        $str = str_replace($val[0], '', $str);
                    }
                } 
            }
            $html .= $str;
        }
        $html .= $this->postfix;
        return $html;
    }
    
   
   
}