<?php

namespace king\lib;

class File
{
    public static function downLoad($file, $name, $mime_type = '')
    {
        if (!is_readable($file)) {
            throw new \Exception('File not found or inaccessible!');
        }

        $name = rawurldecode($name);
        $known_mime_types = array(
            "htm" => "text/html",
            "exe" => "application/octet-stream",
            "zip" => "application/zip",
            "doc" => "application/msword",
            "jpg" => "image/jpg",
            "php" => "text/plain",
            "xls" => "application/vnd.ms-excel",
            "ppt" => "application/vnd.ms-powerpoint",
            "gif" => "image/gif",
            "pdf" => "application/pdf",
            "txt" => "text/plain",
            "html" => "text/html",
            "png" => "image/png",
            "jpeg" => "image/jpg"
        );

        if ($mime_type == '') {
            $file_extension = self::getExt($file);
            if (array_key_exists($file_extension, $known_mime_types)) {
                $mime_type = $known_mime_types[$file_extension];
            } else {
                $mime_type = "application/force-download";
            };
        };

        if (ini_get('zlib.output_compression'))
            ini_set('zlib.output_compression', 'Off');
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header("Content-Transfer-Encoding: binary");
        header('Accept-Ranges: bytes');
        $max_read = 1 * 1024 * 1024;
        $fp = fopen($file, 'r');
        while (!feof($fp)) {
            echo fread($fp, $max_read);
            ob_flush();
        }
    }

    public static function getExt($file)
    {
        $file_info = pathinfo($file);
        return $file_info['extension'] ?? '';
    }

    public static function readFile($file)
    {
        return file_get_contents($file);
    }

    public static function writeFile($path, $data, $mode = 'wb')
    {
        if (!$fp = fopen($path, $mode)) {
            return false;
        }

        flock($fp, LOCK_EX);
        for ($result = $written = 0, $length = strlen($data); $written < $length; $written += $result) {
            if (($result = fwrite($fp, substr($data, $written))) === false) {
                break;
            }
        }

        flock($fp, LOCK_UN);
        fclose($fp);

        return is_int($result);
    }

    public static function changeExt($file, $new_ext)
    {
        $ext = self::getExt($file);
        $new_file = str_replace('.' . $ext, '.' . $new_ext, $file);
        rename($file, $new_file);
    }

}