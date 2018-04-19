<?php

/**
 * FastDFS PHP Client
 * 已完成
 *      1.单文件上传
 *      2.下载
 *      3.删除
 * 待完成
 *      1.大文件切割成小文件上传
 *      2.文件内容追加
 *
 *
 * @date    2016-08-12
 * @author  17340862@qq.com
 */
class plugin_fastdfs{

    /**
     * FastDFS 对象
     * @var null
     */
    protected $_dfs = null;


    /**
     * 存储节点组名称，默认为空，按配置文件读取tracker.conf
     * @var string
     */
    protected $_group = '';


    private $_tracker = '';

    private $_storage = '';

    /**
     * @var mixed|string
     */
    protected static $config = array();

    /**
     * 调试信息集合
     * @var array
     */
    protected $_debug_msg = array();

    public function __construct($groupname='')
    {
        self::$config = require_once('config/fastdfs.php');

        $this->_dfs = new FastDFS();

        $this->setGroupName($groupname);

        $this->_connect();
    }

    /**
     * 按指定文件的路径上传
     *
     * @param string $pathtofile
     * @return array
     */
    public function uploadByFilename($pathtofile='')
    {
        if(! is_file($pathtofile)){
            return $this->format_return(-1, '所要上传的文件不可读或不存在');
        }

        $pinfo = pathinfo($pathtofile);
        $ext = isset($pinfo['extension']) ? $pinfo['extension'] : '';
        $meta = array();


        $file_info = $this->_dfs->storage_upload_by_filename1($pathtofile, $ext, $meta, $this->_group, $this->_tracker, $this->_storage);
        if(! $file_info){
            return $this->format_return($this->getLastErrorNo(), $this->getLastErrorInfo());
        }

        return $this->format_return(0, '', $file_info);
    }


    /**
     * 按指定的文件内容上传
     *
     * @param string $buff      文件内容
     * @param   string  $ext    文件后缀
     * @return array
     */
    public function uploadByBuff($buff='', $ext='')
    {
        $file_info = $this->_dfs->storage_upload_by_filebuff1($buff, $ext, array(), $this->_group, $this->_tracker, $this->_storage);

        if(! $file_info){
            return $this->format_return($this->getLastErrorNo(), $this->getLastErrorInfo());
        }

        return $this->format_return(0, '', $file_info);
    }

    /**
     * 下载文件到本地
     *
     * @param $fileid  存储在远程文件的文件的ID
     * @param $local_filename   本地文件名称，确保该文件可写
     * @param int $offset   开始下载文件的偏移量  默认为0 从文件起始处
     * @param int $downloadbytes    本次下载多少个字节数  默认为0不限制
     * @return mixed
     * @throws Exception|bool
     */
    public function downloadToFilename($fileid, $local_filename='', $offset=0, $downloadbytes=0)
    {
        if(is_writable($local_filename)){
            return $this->format_return(-1, "文件不可写：$local_filename");
        }

        //重连存储器
        $this->setGroupName($this->_getGroupByFileid($fileid));
        $this->_connectStorage();

        $down = $this->_dfs->storage_download_file_to_file1(
            $fileid,
            $local_filename,
            $offset,
            $downloadbytes,
            $this->_tracker,
            $this->_storage
        );

        if($down === false){
            //对于同步失败的情况，直接从源存储节点上获取
            if($err = $this->_conectSourceStorage($fileid) !== true){
                return $err;
            }

            $down = $this->_dfs->storage_download_file_to_file1(
                $fileid,
                $local_filename,
                $offset,
                $downloadbytes,
                $this->_tracker,
                $this->_storage
            );

            if($down === false){
                return $this->format_return($this->getLastErrorNo(), $this->getLastErrorInfo());
            }
        }

        return $this->format_return(0);
    }

    /**
     * 下载文件到缓冲区中
     *
     * @param $fileid  存储在远程文件的文件的ID
     * @param int $offset   开始下载文件的偏移量  默认为0 从文件起始处
     * @param int $downloadbytes    本次下载多少个字节数  默认为0不限制
     * @return mixed
     * @throws Exception|string
     */
    public function downloadToBuff($fileid, $offset=0, $downloadbytes=0)
    {
        //重连存储器
        $this->setGroupName($this->_getGroupByFileid($fileid));
        $this->_connectStorage();

        $buff = $this->_dfs->storage_download_file_to_buff1(
            $fileid,
            $offset,
            $downloadbytes,
            $this->_tracker,
            $this->_storage
        );

        if($buff === false){
            //对于同步失败的情况，直接从源存储节点上获取
            if($err = $this->_conectSourceStorage($fileid) !== true){
                return $err;
            }

            $buff = $this->_dfs->storage_download_file_to_buff1(
                $fileid,
                $offset,
                $downloadbytes,
                $this->_tracker,
                $this->_storage
            );

            if($buff === false){
                return $this->format_return($this->getLastErrorNo(), $this->getLastErrorInfo());
            }
        }

        return $this->format_return(0, 'success', $buff);
    }

    /**
     * 文件删除
     *
     * @param $fileid  存储在远程文件的文件的ID
     */
    public function delete($fileid)
    {
        //重连存储器
        $this->setGroupName($this->_getGroupByFileid($fileid));
        $this->_connectStorage();

        $del = $this->_dfs->storage_delete_file1($fileid, $this->_tracker, $this->_storage);
        if(! $del){
            //对于同步失败的情况，直接从源存储节点上获取
            if($err = $this->_conectSourceStorage($fileid) !== true){
                return $err;
            }

            $del = $this->_dfs->storage_delete_file1($fileid, $this->_tracker, $this->_storage);
            if(! $del){
                return $this->format_return($this->getLastErrorNo(), $this->getLastErrorInfo());
            }
        }

        return $this->format_return(0);
    }

    /**
     * 获取最后错误码
     * @return  string
     */
    public function getLastErrorNo()
    {
        return $this->_dfs->get_last_error_no();
    }

    /**
     * 获取最后错误描述
     * @return string
     */
    public function getLastErrorInfo()
    {
        return $this->_dfs->get_last_error_info();
    }

    /**
     * 设置组
     * @param string $groupname
     */
    public function setGroupName($groupname='')
    {
        $this->_group = $groupname ? $groupname : $this->getGroupName();
    }



    private function _connect()
    {
        $this->_log('start', __FUNCTION__);

        $this->_connectTracker();
        $this->_connectStorage();

        $this->_log('end', __FUNCTION__);
    }

    /**
     * 连接监视器
     * @throws Exception
     */
    private function _connectTracker()
    {
        $trackers = self::$config['tracker'];
        $selected = $trackers[rand(0, 4) % count($trackers)];
        $this->_tracker = $this->_dfs->connect_server($selected['ip_addr'], $selected['port']);

        if(! $this->_tracker){
            //当链接失败时，随机获取可用的tracker链接地址[ip_addr, port, sock]
            $tracker = $this->_dfs->tracker_get_connection();
            if(! $tracker){
                throw new Exception(-1, '无可用的tracker');
            }

            $this->_tracker = $this->_dfs->connect_server($tracker['ip_addr'], $tracker['port']);
            if(! $this->_tracker){
                throw new Exception($this->getLastErrorNo().', [重连]'.$this->getLastErrorInfo());
            }
        }
    }

    /**
     * 连接存储器
     * @param   array   $storage   [ip_addr, port]
     * @throws Exception
     */
    private function _connectStorage($storage = array())
    {
        $storages = self::$config['storage'][$this->getGroupName()];

        $storage = $storage ? $storage : ($storages[rand(0, 4) % count($storages)]) ;

        $this->_storage = $this->_dfs->connect_server($storage['ip_addr'], $storage['port']);
        if(! $this->_storage){
            //当第一次链接失败时，从监视器获取可用的存储器

            $active_storage = $this->_dfs->tracker_query_storage_store($this->_group, $this->_tracker);
            if(! $active_storage){
                throw new Exception('无可用的storage');
            }else{
                $this->_storage = $this->_dfs->connect_server($active_storage['ip_addr'], $active_storage['port']);
                if(! $this->_storage){
                    throw new Exception($this->getLastErrorNo().', [重连]'.$this->getLastErrorInfo());
                }
            }
        }

        //必须添加否则，报错 找不到路径
        $this->_storage['store_path_index'] = 0;
    }

    /**
     * @param $fileid
     * @return array
     * @throws Exception
     */
    private function _conectSourceStorage($fileid)
    {
        $source_storage = $this->_dfs->tracker_query_storage_fetch1($fileid, $this->_tracker);
        if(! $source_storage){
            return $this->format_return(-1, '无法获取源存储节点'.$this->getLastErrorNo().','.$this->getLastErrorInfo());
        }

        $this->_connectStorage($source_storage);

        return true;
    }

    /**
     * 获取storage分组名
     * @return string
     */
    public function getGroupName()
    {
        if($this->_group)
            return $this->_group;

        $index = rand(0, 4) % count(self::$config['storage']);

        return 'group'.($index+1);
    }

    /**
     * 根据fileid 获取分组名
     * @param string $fileid
     * @return string   $groupname
     */
    protected function _getGroupByFileid($fileid='')
    {
        $slices = explode('/', $fileid);

        return $slices[0];
    }


    /**
     * 记录调试信息
     *
     * @param string $msg
     * @param string $type
     */
    protected function _log($msg='', $type='debug')
    {
         if(isset($_REQUEST['debug']) && $_REQUEST['debug'] == 1)
             $this->_debug_msg[] = '['.$type.']['.date('Y-m-d H:i:s').']['.microtime(true).'] '.(is_string($msg) ? $msg : json_encode($msg));
    }

    public function __destruct()
    {
        $this->_dfs->disconnect_server($this->_tracker);
        $this->_dfs->disconnect_server($this->_storage);
    }

    /**
     * 统一返回数组格式
     *
     * @param string $code 0 成功
     * @param string $msg
     * @param array $data
     * @return array
     */
    protected function format_return($code=0, $msg = '', $data = array()) {
        return array(
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        );
    }
}



/*
$dfs = new plugin_fastdfs();

//上传
$upload = $dfs->uploadByBuff('hello world', 'txt');
//$upload 的返回数据结构如下, code 为0 代表成功
$upload =  Array
        (
            [code] => 0
            [msg] =>
            [data] =>  group3/M00/6D/A8/SYcBAFeyfdSAAP44AAAACy4IbJs091.txt
        )


//下载
$download = $dfs->downloadToBuff('group3/M00/6D/A8/SYcBAFeyfdSAAP44AAAACy4IbJs091.txt');
//$download 的返回数据结构如下, code 为0 代表成功

$download = Array
            (
                [code] => 0
                [msg] => success
                [data] => hello wlord
            )

//删除
$del = $dfs->delete('group3/M00/6D/A8/SYcBAFeyfdSAAP44AAAACy4IbJs091.txt');
//$del 的返回数据结构如下, code 为0 代表成功
$del = Array
            (
                [code] => 0
                [msg] => success
                [data] => array()
            )
*/


?>