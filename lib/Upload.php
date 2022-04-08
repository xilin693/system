<?php

namespace king\lib;

use king\core\Error;
use king\core\Instance;
use king\lib\File;
use king\lib\Image;

class Upload extends Instance
{
    private $upload;
    private $error;

    public function __construct()
    {
        $this->upload = C('upload.*');
    }

    public function getError()
    {
        return $this->error;
    }

    /**
     * 保存图片到远程服务器
     * @param array $token 验证token
     * @param string $obj 上传的远程文件夹名
     * @param string $field 上传的文件，可以为网址或上传字段名
     * @param number $is_img 是否为图片
     * @param string $filename 上传的文件名，默认由系统生成文件名
     * @param string $url　上传的接口地址，默认由系统获取地址
     * @return boolean|mixed
     */
    public function saveToServer($token, $obj, $field, $is_img = 1, $filename = '', $url = '') // $obj为项目名,每个项目必须要有唯一的项目名
    {
        if (valid::url($field)) // 如果为文件网址
        {
            $file['content'] = file_get_contents($field);
            $file['size'] = strlen($file['content']);
            $file['name'] = basename($field);
            $valid_type = false;
            $is_img = '';
        } else {
            $valid_type = true;
            $file = $_FILES[$field];
        }

        if (!$file['size'] || !upload::size($file, $this->upload['max_size'])) {
            $this->error = '文件大小超过允许值';
            return false;
        }

        if ($valid_type) {
            $allow_type = $is_img ? $this->upload['allow_img_type'] : $this->upload['allow_types'];
            if (!upload::type($file, $allow_type)) {
                $this->error = '不允许上传该类型的文件';
                return false;
            }
        }

        if ($is_img == 1) {
            $imgInfo = getimagesize($file['tmp_name']);
            if (count($imgInfo) < 3) {
                $this->error = '图片类型错误';
                return false;
            }
        }

        if (!$filename) {
            $ext = rand(10, 99) . '.' . self::getExt($file['name']);
            $filename = time() . $ext; // 如果文件名为空,使用时间戳作文件名
        }

        if ($this->upload['remove_spaces'] === true) {
            $filename = preg_replace('/\s+/', '_', $filename);
        }

        if (!$url) // url为空,采用随机图片域名
        {
            if (is_array($this->upload['server'])) {
                $key = array_rand($this->upload['server']);
                $url = 'http://' . $this->upload['server'][$key] . '/receive';
            } else {
                $this->error = '服务器图片地址未定义';
                return false;
            }
        }

        $req = Request::getClass($url, 'post');
        $req->header = ['Authorization' => $token];
        $content = isset($file['content']) ? $file['content'] : file_get_contents($file['tmp_name']);
        $req->setBody(['content' => urlencode($content), 'obj' => $obj, 'filename' => $filename]);
        $req->sendRequest();

        return $req->getResponseBody();
    }

    /**
     * 生成远程图片水印
     * @param string $token 验证token
     * @param string $url 接口地址
     * @param string $img_url 图片地址
     * @param array $mark_array 水印参数
     * @param array $auth http验证内容
     * @return mixed
     */
    public function setMarkServer($token, $url, $img_url, $mark_array)
    {
        $req = Request::getClass($url, 'post');
        $req->header = ['Authorization' => $token];
        $req->setBody(['url' => $img_url, 'mark' => $mark_array]);
        $req->sendRequest();
        return $req->getResponseBody();
    }

    public function getImgMarkUrl($domain)
    {
        return str_replace('imgs', 'server', $domain) . '/receive/mark';
    }

    public static function getExt($name)
    {
        $path_parts = pathinfo($name);
        $ext = strtolower($path_parts['extension'] ?? '');
        return $ext;
    }

    public function saveFile($field = 'userfile', $filename = '', $directory = null)
    {
        return $this->save($filename, $directory, 0, $field);
    }

    /**
     * 上传图片
     * @param number $is_img　是否为图片
     * @param string $file 图片字段名
     * @param string $filename 图片名，默认由系统自动生成
     * @param string $directory 图片上传路径
     * @param number $chmod 生成的图片存限
     * @return boolean|string
     */
    public function save($filename = '', $directory = null, $is_img = 1, $field = 'userfile', $chmod = 0644)
    {
        $file = $_FILES[$field];

        if (!$file['size'] || !self::size($file, $this->upload['max_size'])) {
            $this->error = '文件大小超过允许值';
            return false;
        }

        if (!$this->type($file, $is_img)) {
            $this->error = '不允许上传该类型的文件';
            return false;
        }

        if ($is_img == 1) {

            $imgInfo = getimagesize($file['tmp_name']);
            if (count($imgInfo) < 3) {
                $this->error = '图片类型错误';
                return false;
            }
        }

        if (!$filename) {
            $filename = self::geneUniqueId() . '.' . self::getExt($file['name']);
        }

        $directory = $this->setDirectory($directory);
        if (is_uploaded_file($file['tmp_name']) AND move_uploaded_file($file['tmp_name'], $filename = $directory . $filename)) {
            if ($chmod !== false) {
                chmod($filename, $chmod); // 使用chmod修改目录权限
            }

            return $filename;
        }

        return false;
    }

    public function saveMulti($field = 'userfile', $directory = '', $filename = '', $is_img = 1)
    {
        $files = $this->reArrayFiles($_FILES[$field]);
        $urls = [];
        $directory = $this->setDirectory($directory);
        foreach ($files as $file) {
            if (!$file['size'] || !self::size($file, $this->upload['max_size'])) {
                $this->error = '文件大小超过允许值';
                return false;
            }

            if (!$this->type($file, $is_img)) {
                $this->error = '不允许上传该类型的文件';
                return false;
            }

            if ($is_img == 1) {
                $imgInfo = getimagesize($file['tmp_name']);
                if (count($imgInfo) < 3) {
                    $this->error = '图片类型错误';
                    return false;
                }
            }

            $filename = self::geneUniqueId() . '.' . self::getExt($file['name']);
            if (is_uploaded_file($file['tmp_name']) AND move_uploaded_file($file['tmp_name'], $directory . $filename)) {
                $urls[] = $directory . $filename;
            }
        }

        return $urls;
    }

    private function reArrayFiles($file)
    {
        $file_ary = [];
        $file_count = count($file['name']);
        $file_key = array_keys($file);

        for ($i = 0; $i < $file_count; $i++) {
            foreach ($file_key as $val) {
                $file_ary[$i][$val] = $file[$val][$i];
            }
        }
        return $file_ary;
    }

    private function setDirectory($directory = '')
    {
        $directory = $directory ? $directory : date('Ym/d');
        $directory = $this->upload['directory'] . '/' . $directory;
        $directory = rtrim($directory, '/') . '/';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (!is_writable($directory)) {
            Error::showError('文件夹不可写:' . $directory);
        }

        return $directory;
    }

    public function saveData($data, $size = '', $quality = 90, $param = '')
    {
        $front_txt = $param['front_txt'] ?? ';base64,';
        $directory = $param['directory'] ?? '';
        $filename = $param['filename'] ?? '';
        $img = $param['img'] ?? 1;
        $pos = strpos($data, $front_txt);
        $type = explode('/', substr($data, 0, $pos));
        if (!$this->type('.' . $type[1], $img)) {
            $this->error = '不允许上传该类型的文件';
            return false;
        }
        $directory = $this->setDirectory($directory);
        $filename = $filename  ?: self::geneUniqueId();
        if ($pos !== false) {
            $ext = ($type[1] == 'jpeg') ? '.jpg' : '.' . $type[1];
            file_put_contents($directory . $filename . $ext, base64_decode(substr($data, ($pos + strlen($front_txt)))));
            $img_path = $directory . $filename . $ext;
            if (is_array($size)) {
                foreach ($size as $s) {
                    $image = Image::getClass($img_path);
                    $image->setQuality($quality);
                    $image->resize($directory . $filename . $s . $ext, $s, $s, 2);
                }

                if (!empty($param['deleteSource'])) {
                    unlink($img_path);
                }
                return ['key' => $filename, 'url' => $img_path, 'ext' => $ext];
            } else {
                return ['key' => $filename, 'url' => $img_path, 'ext' => $ext];
            }
        }
    }

    public static function geneUniqueId()
    {
        $unquid = uniqid();
        $chaos_data = substr(session_create_id(), 0, 5);

        return $unquid . $chaos_data;
    }

    /**
     * 校验文件类型
     *
     * @param   array $_FILES item
     * @param   array    allowed file extensions
     * @return  bool
     */
    protected function type($file, $is_img = false)
    {
        $file = is_array($file) ? $file['name'] : $file;
        $allow_type = $is_img ? $this->upload['allow_img_type'] : $this->upload['allow_types'];
        $extension = strtolower(substr(strrchr($file, '.'), 1));
        return in_array($extension, $allow_type);
    }

    /**
     * 校验文件大小
     * 单位为B,K,M,G.必须为大字
     *
     * @param   array $_FILES item
     * @param   array    maximum file size
     * @return  bool
     */
    protected static function size(array $file, $size)
    {
        if (!preg_match('/[0-9]++[BKMG]/', $size))
            return false;
        switch (substr($size, -1)) {
            case 'G':
                $size = intval($size) * pow(1024, 3);
                break;
            case 'M':
                $size = intval($size) * pow(1024, 2);
                break;
            case 'K':
                $size = intval($size) * pow(1024, 1);
                break;
            default:
                $size = intval($size);
                break;
        }
        return ($file['size'] <= $size);
    }

}