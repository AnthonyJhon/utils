<?php

namespace henan\utils;

/**
 * 海报工具
 */
class Poster
{
    /**
     * 当前实例
     * @var
     */
    protected static $instance;

    /**
     * 海报背景
     * @var string
     */
    protected $bg;

    /**
     * 海报字体
     * @var string
     */
    protected $font;

    /**
     * 图片
     * @var
     */
    protected $image;

    /**
     * 等比例缩放
     */
    const SCALE = 'scale';

    /**
     * 裁剪
     */
    const CROP = 'crop';

    /**
     * 构造方法
     * @param string $bg
     * @param string $font
     */
    protected function __construct(string $bg, string $font)
    {
        $this->bg = $bg;
        $this->font = $font;
        $img = imagecreatefromstring(file_get_contents($bg)); //从字符串的图像流中新建图像
        $width = imagesx($img);
        $height = imagesy($img);
        $thumb = imagecreatetruecolor($width, $height); //新建真彩色图像
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $width, $height, $width, $height); //重采样拷贝部分图像并调整大小
        imagecopymerge($img, $thumb, 0, 0, 0, 0, $width, $height, 100); //合并
        $this->image = $img;
        return $this;
    }

    /**
     * 获取实例对象
     * @param string $bg
     * @param string $font
     * @return Poster
     */
    public static function instance(string $bg, string $font): Poster
    {
        if (is_null(self::$instance)) self::$instance = new static($bg, $font);
        return self::$instance;
    }

    /**
     * 添加图片
     * @param $file
     * @param int $x
     * @param int $y
     * @param int $w
     * @param int $h
     * @param string $model
     * @param int $radius
     * @param bool $isBase64
     * @return Poster
     */
    public function addImage($file, int $x = 0, int $y = 0, int $w = 0, int $h = 0, string $model = 'scale', int $radius = 0, bool $isBase64 = false): Poster
    {
        $content = $isBase64 ? base64_decode($file) : file_get_contents($file);
        $img = imagecreatefromstring($content); //从字符串的图像流中新建图像
        switch ($model) {
            case 'scale':
                $img = self::scaleImage($img, $w, $h);
                break;
            case 'crop':
                $img = self::cropImage($img, $w, $h);
                break;
            default:
                break;
        }
        $radius && $img = self::borderRadius($img, $radius);
        imagecopymerge($this->image, $img, $x, $y, 0, 0, imagesx($img), imagesy($img), 100); //拷贝并合并图像的一部分
        return $this;
    }

    /**
     * 添加文本
     * @param $text
     * @param int $w
     * @param int $h
     * @param string $color
     * @param int $size
     * @param int $angle
     * @return $this
     */
    public function addText($text, int $w = 0, int $h = 0, string $color = '#1C2833', int $size = 24, int $angle = 0): Poster
    {

        [$r, $g, $b] = self::hexToRGB($color);
        $color = imagecolorallocate($this->image, $r, $g, $b); //为图像分配颜色
        imagettftext($this->image, $size, $angle, $w, $h, $color, $this->font, $text); // TrueType字体向图像写入文本
        return $this;
    }

    /**
     * 添加水平渐变色蒙板
     * @param string $color
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     * @param int $deep 渐变深度
     * @param bool $reverse 是否反转
     * @return $this
     */
    public function addHorizontalGradients(string $color, int $x1, int $y1, int $x2, int $y2, int $deep = 20, bool $reverse = false): Poster
    {
        [$r, $g, $b] = self::hexToRGB($color);
        $deep > 100 && $deep = 100;
        $deep <= 0 && $deep = 1;
        $h = abs($y2 - $y1);
        $step = round($h / $deep);
        $alphaStep = round((127 - 60) / $deep);
        for ($i = 0; $i < $deep; $i++) {
            $alpha = $reverse ? 60 + $i * $alphaStep : 127 - $i * $alphaStep;
            $color = imagecolorallocatealpha($this->image, $r, $g, $b, $alpha);
            imagefilledrectangle($this->image, $x1, ($y1 + $step * $i + 1), $x2, ($y1 + $step * ($i + 1)), $color);
        }
        return $this;
    }

    /**
     * 缩放图像
     * @param mixed $image GD图片资源
     * @param int $w 宽度（像素）
     * @param int $h 高度（像素）
     * @return false|\GdImage|resource
     */
    public static function scaleImage($image, int $w = 0, int $h = 0)
    {
        [$width, $height] = [imagesx($image), imagesy($image)];
        $w == 0 && $h != 0 && $w = round($h * ($width / $height));
        $h == 0 && $w != 0 && $h = round($w * ($height / $width));
        $thumb = imagecreatetruecolor($w, $h); //新建真彩色图像
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $width, $height); //重采样拷贝部分图像并调整大小
        imagedestroy($image); //销毁图像
        return $thumb;
    }

    /**
     * 裁剪图像
     * @param mixed $image GD图片资源
     * @param int $w 宽度（像素）
     * @param int $h 高度（像素）
     * @return false|\GdImage|resource
     */
    public static function cropImage($image, int $w, int $h)
    {
        [$width, $height] = [imagesx($image), imagesy($image)];
        $setRatio = $w / $h;
        $curRatio = $width / $height;
        if ($setRatio > $curRatio) {
            $resizeX = $width;
            $resizeY = $resizeX * $h / $w;
            $x = 0;
            $y = ($height - $resizeY) / 2;
        } elseif ($setRatio < $curRatio) {
            $resizeY = $height;
            $resizeX = $resizeY * $w / $h;
            $x = ($width - $resizeX) / 2;
            $y = 0;
        } else {
            $resizeX = $width;
            $resizeY = $height;
            $x = $y = 0;
        }
        $thumb = imagecreatetruecolor($w, $h); //新建真彩色图像
        imagecopyresampled($thumb, $image, 0, 0, $x, $y, $w, $h, $resizeX, $resizeY); //重采样拷贝部分图像并调整大小
        imagedestroy($image); //销毁图像
        return $thumb;
    }

    /**
     * 圆角图片
     * @param mixed $image GD图片资源
     * @param int $radius 圆角大小
     */
    public static function borderRadius($image, int $radius = 10)
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $img = imagecreatetruecolor($w, $h);
        $color = imagecolorallocate($image, 255, 255, 255);
        imagecolortransparent($image, $color);
        // imagesavealpha($img, true); // 设置透明通道
        // $bg = imagecolorallocatealpha($img, 255, 255, 255, 127); // 拾取一个完全透明的颜色, 最后一个参数127为全透明
        imagefill($img, 0, 0, $color);
        $r = $radius; // 圆 角半径
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgbColor = imagecolorat($image, $x, $y);
                if (($x >= $radius && $x <= ($w - $radius)) || ($y >= $radius && $y <= ($h - $radius))) {
                    imagesetpixel($img, $x, $y, $rgbColor); // 不在四角的范围内,直接画
                } else { // 在四角的范围内选择画
                    // 上左
                    $yx = $r; // 圆心X坐标
                    $yy = $r; // 圆心Y坐标
                    if (((($x - $yx) * ($x - $yx) + ($y - $yy) * ($y - $yy)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                    // 上右
                    $yx = $w - $r; // 圆心X坐标
                    $yy = $r; // 圆心Y坐标
                    if (((($x - $yx) * ($x - $yx) + ($y - $yy) * ($y - $yy)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                    // 下左
                    $yx = $r; // 圆心X坐标
                    $yy = $h - $r; // 圆心Y坐标
                    if (((($x - $yx) * ($x - $yx) + ($y - $yy) * ($y - $yy)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                    // 下右
                    $yx = $w - $r; // 圆心X坐标
                    $yy = $h - $r; // 圆心Y坐标
                    if (((($x - $yx) * ($x - $yx) + ($y - $yy) * ($y - $yy)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                }
            }
        }
        return $img;
    }

    /**
     * 颜色hex值转换成rgb
     * @param string $hex
     * @return array
     */
    public static function hexToRGB(string $hex): array
    {
        if ($hex[0] == '#') $hex = substr($hex, 1);
        if (strlen($hex) == 6) {
            [$r, $g, $b] = [$hex[0] . $hex[1], $hex[2] . $hex[3], $hex[4] . $hex[5]];
        } elseif (strlen($hex) == 3) {
            [$r, $g, $b] = [$hex[0] . $hex[0], $hex[1] . $hex[1], $hex[2] . $hex[2]];
        } else {
            return [0, 0, 0];
        }
        $r = hexdec($r);
        $g = hexdec($g);
        $b = hexdec($b);
        return [$r, $g, $b];
    }

    /**
     * 保存为base64格式图片
     * @return string
     */
    public function writeBase64(): string
    {
        ob_start();
        imagepng($this->image);
        $string = ob_get_contents();
        ob_end_clean();
        return base64_encode($string);
    }
}