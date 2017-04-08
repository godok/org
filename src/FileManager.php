<?php
namespace Godok\Org;

use think\Config;
use think\Request;
use think\File;
use Godok\Org\Auth;
/**
 * 文件管理器
 *
 */
final class FileManager
{
    /**
     * 文件储存方式
     * local 本地储存
     * qiniu 七牛储存
     */
    protected static $error = "";
    //允许上传的类型
    protected static $type = 'jpg,gif,png,mp3,mp4,txt,doc,docx,excel,excelx,ppt,pptx,pdf';
    protected static $size = 2000000;//2*1000*1000=2M
    protected static $path = 'uploads';


    /**
     * 保存文件
     *
     * @param $file 上传文件的postname,可以为数组 [$key=>$filename]或者[$key1,$key2,$key3],键名为数字时，$filename 由系统决定
    
     * @return boolean 
     */
    public static function save($file, $savename = true, $storage = null)
    {
        self::$error = "";
        if(! $file instanceof File) {
            self::$error = "上传内容不符合规则";
            return false;
        }
        //判断文件大小是否超出限制
        //判断文件类型是否超出限制
        $user = Auth::user();
        if ( isset($user['upload_size']) ) {
            self::$size = $user['upload_size'];
        }
        if ( isset($user['upload_type']) ) {
            self::$type = $user['upload_type'];
        }
        if ( !$file->validate([ 'size'=>self::$size, 'ext'=>self::$type ])->check() ) {
            self::$error = $file->getError();
            return false;
        }
        //判断保存文件名
        if( true === $savename ) {
            $path = '/uploads';
            if ( isset($user['id']) ) {
                $path .= '/'.'u'.$user['id'];
            }        
        } else {
            $savename = trim($savename, '/');
            $path = '';
        }
        
        switch ( $storage ) {
            case 'qiniu' :
                return self::qiniu($path, $file); 
            default :
                $info = $file->move( str_replace('/', DS, $_SERVER['DOCUMENT_ROOT'].$path) , $savename);
                if($info){
                    return [ $info->md5(), Request::instance()->domain(). $path . '/' . str_replace('\\', '/', $info->getSaveName()) ];
                }else{
                    // 上传失败获取错误信息
                    self::$error =  $file->getError();
                    return false;
                }
                break;
        }
        
    }
    /**
     * 获取错误信息
     */
    public static function getError()
    {
        return self::$error;
    }
    /**
     * 七牛储存
     * 未完善，只是个测试
     */
    protected static function qiniu( $file )
    {
        require VENDOR_PATH . 'qiniu' . DS . 'php-sdk' . DS . 'src' . DS . 'Qiniu' . DS . 'functions.php';
        // 需要填写你的 Access Key 和 Secret Key
        $accessKey = '****';
        $secretKey = '****';
        // 构建鉴权对象
        $auth = new Auth($accessKey, $secretKey);
        // 生成上传 Token
        $token = $auth->uploadToken('godoa');
        $uploadMgr = new UploadManager();
        list($ret, $err) = $uploadMgr->putFile($token, 'uploads/user/1/avatar.jpg', 'D:/wwwroot/godoa/user1.jpg');
        echo "\n====> putFile result: \n";
        if ($err !== null) {
            var_dump($err);
        } else {
            var_dump($ret);
        }
        return true; 
    }
}
