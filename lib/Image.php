<?php

namespace king\lib;

use king\core\Instance;
use king\core\Error;

class Image extends Instance
{
    public $types = [1 => 'imagecreatefromgif', 2 => 'imagecreatefromjpeg', 3 => 'imagecreatefrompng'];
    public $blank_png;
    protected $image = '';
    private $tmp_img;
    private $quality = 100;
    private $mark_img;
    private $error;
    private $txt_array = ['content' => 'mysite', 'size' => 14, 'color' => '#000000'];

    public function __construct($image)
    {
        if (!is_file($image)) {
            Error::showError('图片找不到' . $image);
        }

        $this->image = $this->setImg($image);
        if (is_array($this->image)) {
            $this->tmp_img = $this->image['func']($this->image['file']);
        } else {
            return false;
        }
    }

    public function getError()
    {
        return $this->error;
    }

    protected function setImg($image)
    {
        $img_info = getimagesize($image);
        if (!is_array($img_info) OR count($img_info) < 3) {
            Error::showError('图片不可读' . $image);
        }
        if ($img_info[2] < 1 || $img_info[2] > 3) {
            $this->error = '图片类型不允许';
            return false;
        }

        return [
            'file' => str_replace('\\', '/', realpath($image)),
            'width' => $img_info[0],
            'height' => $img_info[1],
            'type' => $img_info[2],
            'func' => $this->types[$img_info[2]],
            'mime' => $img_info['mime']
        ];

    }


    public function setMarkImg($img)
    {
        $this->mark_img = $img;
    }

    public function setText($params = [])
    {
        if (count($params) > 0) {
            foreach ($params as $key => $value) {
                if (isset($this->txt_array[$key])) {
                    $this->txt_array[$key] = $value;
                }
            }
        }
    }

    private function getText()
    {
        return $this->txt_array;
    }

    /**
     * @param   $new_img string 缩略图名称
     * @param   $scale int  1:自适应缩略图，2：根据宽度比例生成缩略图，3：根据高度比例生成缩略图
     * @return  object
     */
    public function resize($new_img, $width, $height, $scale = 1)//生成缩略图
    {
        $new_img = $new_img ? $new_img : $this->image['file'];
        if (substr($this->image['width'], -1) === '%')//如果是百分号，计算实际的宽度
        {
            $this->image['width'] = round($this->image['width'] * (substr($this->image['width'], 0, -1) / 100));
        }

        if (substr($this->image['height'], -1) === '%') {
            $this->image['height'] = round($this->image['height'] * (substr($this->image['height'], 0, -1) / 100));
        }

        if ($width < $this->image['width'] || $height < $this->image['height']) {
            $wTio = $width / $this->image['width'];
            $hTio = $height / $this->image['height'];
            if ($scale == 1)//如果是自适应
            {
                if ($wTio > $hTio) {
                    $height = round($this->image['height'] * $hTio);
                    $width = round($this->image['width'] * $hTio);
                } else {
                    $height = round($this->image['height'] * $wTio);
                    $width = round($this->image['width'] * $wTio);
                }
            } elseif ($scale == 2) {
                $height = round($this->image['height'] * $wTio);
            } elseif ($scale == 3) {
                $width = round($this->image['width'] * $hTio);
            }
        } else {
            $width = $this->image['width'];
            $height = $this->image['height'];
        }

        switch ($this->image['type']) {
            case 1:
                $quality = 0;
                break;
            case 2:
                $quality = $this->quality;
                break;
            case 3:
                $png_quality = ($this->quality - 100) / 11;
                $quality = round(abs($png_quality));
                break;
        }
        $img = $this->imagecreatetransparent($width, $height);
        imagecopyresampled($img, $this->tmp_img, 0, 0, 0, 0, $width, $height, $this->image['width'], $this->image['height']);
        $func = str_replace('createfrom', '', $this->image['func']);
        $status = ($quality > 0) ? $func($img, $new_img, $quality) : $func($img, $new_img);//如果有设置缩略图质量
        imagedestroy($this->tmp_img);
        if (!$status) {
            $this->error = '缩略图片成失败';
        }

        return $status;
    }

    /**
     * 裁剪图片
     * @param string $new_img 截图名称
     * @param number $pixel_x x坐标
     * @param number $pixel_y y坐标
     * @param integer $width 宽度
     * @param integer $height　高度
     * @return boolean
     */
    public function crop($new_img, $pixel_x, $pixel_y, $width, $height, $src_width = '', $src_height = '')
    {
        if (is_numeric($pixel_x) && is_numeric($pixel_y) && is_numeric($width) && is_numeric($height)) {
            $img = $this->imagecreatetransparent($width, $height);
            $src_width = $src_width ? $src_width : ($width + $pixel_x);
            $src_height = $src_height ? $src_height : ($height + $pixel_y);
            imagecopyresampled($img, $this->tmp_img, 0, 0, $pixel_x, $pixel_y, $width, $height, $width, $height);
            $func = str_replace('createfrom', '', $this->image['func']);
            $status = $func($img, $new_img);//如果有设置缩略图质量
            imagedestroy($this->tmp_img);
            if (!$status) {
                $this->error = '图片裁剪失败';
                return false;
            }
            return $status;
        } else {
            $this->error = '参数不合法';
            return false;
        }
    }

    private function getPos($s_width, $s_height, $o_width, $o_height, $pos)
    {
        switch ($pos) {
            case 1:  // 1为顶端居左
                $posX = 0;
                $posY = 0;
                break;
            case 2:  // 2为顶端居中
                $posX = ($s_width - $o_width) / 2;
                $posY = 0;
                break;
            case 3:  // 3为顶端居右
                $posX = $s_width - $o_width;
                $posY = 0;
                break;
            case 4:  // 4为中部居左
                $posX = 0;
                $posY = ($s_height - $o_height) / 2;
                break;
            case 5:  // 5为中部居中
                $posX = ($s_width - $o_width) / 2;
                $posY = ($s_height - $o_height) / 2;
                break;
            case 6:  // 6为中部居右
                $posX = $s_width - $o_width;
                $posY = ($s_height - $o_height) / 2;
                break;
            case 7:  // 7为底端居左
                $posX = 0;
                $posY = $s_height - $o_height;
                break;
            case 8:  // 8为底端居中
                $posX = ($s_width - $o_width) / 2;
                $posY = $s_height - $o_height;
                break;
            case 9:  // 9为底端居右
                $posX = $s_width - $o_width;
                $posY = $s_height - $o_height;
                break;
            default:  // 随机
                $posX = rand(0, ($s_width - $o_width));
                $posY = rand(0, ($s_height - $o_height));
                break;
        }
        return [$posX, $posY];

    }


    /**
     * 文字水印
     * @param string $new_img 生成的图片名称
     * @param int $water_pos 水印位置
     */
    public function textMark($new_img, $water_pos = 9)
    {
        if (!is_file($this->image['file'])) {
            Error::showError('背景图片未设置');
        }

        $set = $this->setImg($this->image['file']);
        $image = $set['func']($set['file']);
        $params = $this->getText();
        if (ctype_alnum($params['content'])) {
            $font = SYS_PATH . 'lib/captcha/en.ttf';
        } else {
            $font = SYS_PATH . 'lib/captcha/cn.ttf';
        }

        $text = imagettfbbox($params['size'], 0, $font, $params['content']);
        $width = $text[2] - $text[6];
        $height = $text[3] - $text[7];
        if (!is_array($water_pos)) {
            $pos = $this->getPos($this->image['width'], $this->image['height'], $width, $height, $water_pos);
        } else {
            $pos = $water_pos;
        }

        $posX = $pos[0];
        $posY = $pos[1] + $params['size'];

        $R = hexdec(substr($params['color'], 1, 2));
        $G = hexdec(substr($params['color'], 3, 2));
        $B = hexdec(substr($params['color'], 5));
        imagealphablending($image, true);
        $status = imagettftext($image, $params['size'], 0, $posX, $posY, imagecolorallocate($image, $R, $G, $B), $font, $params['content']);
        if ($status) {
            $func = str_replace('createfrom', '', $this->image['func']);
            $func($image, $new_img);
            return true;
        } else {
            $this->error = '生成水印失败';
            return false;
        }

    }

    /***
     * $new_img 新图片名称
     * $water_pos 水印位置：默认随机，1左上角，2顶端中间，3右上角，4中左，5正中，6中右，7左下角，8底端中间，9，右下角
     * $opacity 透明度
     */
    public function mark($new_img, $water_pos = 9, $opacity = '')
    {
        if (!is_file($this->image['file'])) {
            Error::showError('背景图片未设置');
        }
        $set = $this->setImg($this->image['file']);
        $image = $set['func']($set['file']);

        if (!is_file($this->mark_img)) {
            Error::showError('水印图片未设置');
        }
        $set2 = $this->setImg($this->mark_img);
        $overlay = $set2['func']($set2['file']);

        imagesavealpha($overlay, TRUE);

        $width = imagesx($overlay);
        $height = imagesy($overlay);
        if (!is_array($water_pos)) {
            $pos = $this->getPos($this->image['width'], $this->image['height'], $width, $height, $water_pos);
        } else {
            $pos = $water_pos;
        }

        $posX = $pos[0];
        $posY = $pos[1];
        $im = imagecreatetruecolor($this->image['width'], $this->image['height']);
        imagecopy($im, $image, 0, 0, 0, 0, $this->image['width'], $this->image['height']);
        imagealphablending($im, true);
        $status = imagecopy($im, $overlay, $posX, $posY, 0, 0, $width, $height);
        if ($status) {
            $func = str_replace('createfrom', '', $this->image['func']);
            $func($im, $new_img);
            imagedestroy($overlay);
            return true;
        } else {
            $this->error = '生成水印失败';
            return false;
        }
    }

    private function imagecreatetransparent($width, $height)//创建白色背景
    {
        // Decode the blank PNG if it has not been done already
        $this->blank_png = imagecreatefromstring(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAACgAAAAoCAYAAACM/rhtAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29' .
            'mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAADqSURBVHjaYvz//z/DYAYAAcTEMMgBQAANegcCBN' .
            'CgdyBAAA16BwIE0KB3IEAADXoHAgTQoHcgQAANegcCBNCgdyBAAA16BwIE0KB3IEAADXoHAgTQoHcgQ' .
            'AANegcCBNCgdyBAAA16BwIE0KB3IEAADXoHAgTQoHcgQAANegcCBNCgdyBAAA16BwIE0KB3IEAADXoH' .
            'AgTQoHcgQAANegcCBNCgdyBAAA16BwIE0KB3IEAADXoHAgTQoHcgQAANegcCBNCgdyBAAA16BwIE0KB' .
            '3IEAADXoHAgTQoHcgQAANegcCBNCgdyBAgAEAMpcDTTQWJVEAAAAASUVORK5CYII='
        ));

        // Set the blank PNG width and height
        $blank_png_width = imagesx($this->blank_png);
        $blank_png_height = imagesy($this->blank_png);

        $img = imagecreatetruecolor($width, $height);

        // Resize the blank image
        imagecopyresized($img, $this->blank_png, 0, 0, 0, 0, $width, $height, $blank_png_width, $blank_png_height);

        // Prevent the alpha from being lost
        imagealphablending($img, false);
        imagesavealpha($img, TRUE);

        return $img;
    }

    public function setQuality($qual)
    {
        $this->quality = $qual;
    }

}