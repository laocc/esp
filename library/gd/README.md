#生成验证码：
下面有关选项，都不是必选，可以只定义部分，没指定的，以下面这些值为准：
```
$option = [
    'charset' => 'en',      //使用中文或英文验证码，cn=中文，en=英文，若create()指定了，则以指定的为准
    'length' => [3, 5],     //验证码长度范围
    'size' => [150, 30],    //宽度，高度
    'angle' => [-30, 30],   //角度范围，建议不要超过±45
    'line' => [4, 6],       //产生的线条数
    'point' => [80, 100],   //产生的雪花点数
    
    //分别是中英文字体，要确保这些文件真实存在
    'cn_font' => [_ROOT . 'font/hyb9gjm.ttf' => 0, _ROOT . 'font/simkai.ttf' => 1],
    'en_font' => ['/usr/share/fonts/thai-scalable/Waree-Bold.ttf' => 0],
    
    //下面四种颜色均是指范围，0-255之间，值越大颜色越浅。
    'b_color' => [157, 255],     //背景色范围
    'p_color' => [200, 255],     //雪花点颜色范围
    'l_color' => [50, 200],      //干扰线颜色范围
    'c_color' => [10, 156],      //验证码字颜色范围
    
    'cookies' => [  //Cookies相关定义
        'key' => '__C__',   //Cookies键
        'attach' => 'D',    //附加固定字符串
        'date' => 'YmdH',   //附加时间标识用于date()函数
    ],
];
    
\gd\Code::create($option);
```

#生成条形码：
下面有关选项，都不是必选，可以只定义部分，没指定的，以下面这些值为准：
```
$code = [];
$code['code'] = microtime(true);        //条码内容
$code['font'] = null;       //字体，若不指定，则用PHP默认字体
$code['size'] = 10;         //字体大小
$code['label'] = false;     //条码下面标签是否需要个性化，也就是分割并在两头加星号，若=null，则不显示标签
$code['pixel'] = 3;         //分辨率即每个点显示的像素，建议3-5
$code['height'] = 20;       //条码部分高，实际像素为此值乘pixel
$code['style'] = null;      //条码格式，可选：A,B,C,或null，若为null则等同于C
$code['root'] = _ROOT . 'code/';    //保存文件目录，不含在URL中部分
$code['path'] = 'code1/';   //含在URL部分
$code['save'] = false;      //是否保存为文件，否则只显示
\gd\Code1::create($code);
```
```
$code['save']：
=true时，保存为文件，返回文件信息数组；
=false时，直接显示到浏览器；
```

#生成二维码：
```
$option = array();
$option["text"] = 'no Value';
$option["level"] = "Q";    //可选LMQH
$option["size"] = 10;    //每条线像素点,一般不需要动，若要固定尺寸，用width限制
$option["margin"] = 1;    //二维码外框空白，指1个size单位，不是指像素
$option["width"] = 0;     //生成的二维码宽高，若不指定则以像素点计算
$option["color"] = '#000000';   //二维码本色，也可以是图片
$option["background"] = '#ffffff';  //二维码背景色

$option["save"] = false;    //直接保存
$option["root"] = _ROOT . 'code/';  //保存目录
$option["path"] = 'qrCode/';        //目录里的文件夹

$option["logo"] = null;         //LOGO图片
$option["border"] = '#ffffff';  //LOGO外边框颜色

$option["parent"] = null;//一个文件地址，将二维码贴在这个图片上
$option["parent_x"] = null;//若指定，则以指定为准
$option["parent_y"] = null;//为null时，居中

$option["shadow"] = null;//颜色色值，阴影颜色，只有当parent存在时有效
$option["shadow_x"] = 2;//阴影向右偏移，若为负数则向左
$option["shadow_y"] = 2;//阴影向下偏移，若为负数则向上
$option["shadow_alpha"] = 0;//透明度，百分数

\gd\Code2::create($option);
```
```
$option['save']：
=true时，保存为文件，返回文件信息数组；
=false时，直接显示到浏览器；
```

#图片编辑：
##图片尺寸转换：
```
\gd\Image:size();

```



