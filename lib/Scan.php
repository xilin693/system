<?php

namespace king\lib;

use king\core\Instance;

class Scan extends Instance
{
    private $path;
    private $compare_time = 0;
    private $suffix;
    private $filter_fun;
    private $filter_str = [];
    private $svn_path;
    private static $instance;

    public function __construct($path)
    {
        $this->path = $path;
        //$this->compare_time		= strtotime(date('Y-m-d'));
        $this->suffix = ['php'];
    }

    public function run()
    {
        $preg = false;
        if (count($this->filter_str) > 0)//如果有搜索的字符串
        {
            $preg = '/(' . implode('|', $this->filter_str) . ')/i';
        } elseif (is_array($this->filter_fun)) {
            $preg = '/\s+(' . implode('|', $this->filter_fun) . ')\s*(\(|\%)/i';
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->path));
        $it->rewind();
        while ($it->valid()) {
            if (!$it->isDot()) {
                $filename = $it->key();
                $mtime = fileMtime($filename);
                $extension = $this->getExtension($filename);
                if (in_array($extension, $this->suffix)) {
                    if ($mtime > $this->compare_time) {
                        if ($preg) {
                            $file = file($filename);
                            foreach ($file as $line => $value) {
                                if (preg_match($preg, $value, $match)) {
                                    echo '&nbsp;&nbsp;' . $match[0] . ' is on line: ' . $line . ' in file:' . $filename . '<br>';
                                }
                            }
                        } else {
                            echo $filename . ' ' . date('Y-m-d H:i:s', fileMtime($filename)) . '<br>';
                        }
                    }
                }
            }
            $it->next();
        }
    }

    public function runSvnCompare()
    {
        if ($this->svn_path) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->path));
            $it->rewind();
            while ($it->valid()) {
                if (!$it->isDot()) {
                    $filename = $it->key();
                    $mtime = fileMtime($filename);
                    $extension = $this->getExtension($filename);
                    if (in_array($extension, $this->suffix)) {
                        $path = str_replace('/', '\\', $this->path);
                        $svnSux = str_replace($path, '', $filename);
                        $svn_file = $this->svn_path . $svnSux;
                        $value = md5_file($filename);
                        if (is_file($svn_file)) {
                            if ($value != md5_file($svn_file)) {
                                echo $filename . ' ' . date('Y-m-d H:i:s', fileMtime($filename)) . '<br>';
                                echo $svn_file . ' ' . date('Y-m-d H:i:s', fileMtime($filename)) . '<br>';
                            }
                        } else
                            echo $svn_file . ' <b>not exists</b> ' . date('Y-m-d H:i:s', fileMtime($filename)) . '<br>';
                    }
                }
                $it->next();
            }
        }
    }

// 		public function getFilesMd5($dir='')//取得所有文件的时间,功能与上面的方法相同,效率更高
// 		{
// 			$dir	= $dir ? $dir :$this->path;
// 			$d 		= dir($dir);
// 			$files	= [];
// 			while (false !== ($entry = $d->read()))
// 			{
// 				$entryPath 		= $dir.$entry;
// 				if($entry!='.' && $entry!='..' && $entry!='.svn' && $entryPath!= $this->path.'data')
// 				{
// 					if (is_file($entryPath))
// 					{
// 						$mtime			= fileMtime($entryPath);
// 						$extension		= $this->getExtension($entryPath);
// 						if (in_array($extension,$this->suffix))
// 						{
// 							$name		= str_replace($this->path,'',$entryPath);
// 							$this->files[$name]	= array($mtime,md5_file($entryPath));//返回所有文件的时间
// 						}
// 					}
// 					elseif(is_dir($entryPath))
// 					{
// 						$currentPath	= $entryPath.'/';
// 						$subdirs 		= $this->getFilesMd5($currentPath);
// 					}
// 				}
// 			}
// 			$d->close();
// 			return $this->files;
// 		}		

    public function setCompareTime($time)
    {
        if (strpos($time, '-') !== false) {
            $time = strtotime($time);
        }
        $this->compare_time = $time;
    }

    public function setSvnPath($newPath)
    {
        $this->svn_path = $newPath;
    }

    public function setDiscuzHackDomain($domain)
    {
        $len = strlen($domain);
        $dec = '';
        for ($i = 0; $i < $len; $i++) {
            $ascii = ord($domain{$i});
            $dec .= dechex($ascii);
        }
        $this->filter_str[] = $dec;
    }

    public function setSuffix($newSuffix)
    {
        $this->suffix = $newSuffix;
    }

    public function setFilterFun($newFilter = '')
    {
        if (!$newFilter)
            $this->filter_fun = ['eval', 'system', 'exec', 'passthru', 'call_user_func', 'create_function'];
        else
            $this->filter_fun[] = $newFilter;
    }

    public function setFilterStr($newFilter = '')
    {
        if ($newFilter)
            $this->filter_str[] = $newFilter;
    }

    private function getExtension($name)
    {
        $pathParts = pathinfo($name);
        $ext = strtolower($pathParts['extension']);
        return $ext;
    }
}