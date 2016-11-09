<?php

/**
 * 中文分词
 * Array
 * (
 * [word] => 的
 * [off] => 6
 * [len] => 3
 * [idf] => 0
 * [attr] => uj
 * )
 *
 * class SimpleCWS  {
 * resource handle;
 * bool close(void);
 * bool set_charset(string charset)
 * bool add_dict(string dict_path[, int mode = SCWS_XDICT_XDB])
 * bool set_dict(string dict_path[, int mode = SCWS_XDICT_XDB])
 * bool set_rule(string rule_path)
 * bool set_ignore(bool yes)
 * bool set_multi(int mode)
 * bool set_duality(bool yes)
 * bool send_text(string text)
 * mixed get_result(void)
 * mixed get_tops([int limit [, string xattr]])
 * bool has_word(string xattr)
 * mixed get_words(string xattr)
 * string version(void)
 * };
 */
//cut::cn('中华人民共和国');

class Word
{


    /*
     * SELECT SQL_CALC_FOUND_ROWS *, MATCH (titlewords, keywords, author, contentwords) AGAINST ('$words') AS matchscore
FROM search
WHERE MATCH (titlewords, keywords, author, contentwords) AGAINST ('$words') > 0.5
LIMIT 10

SELECT SQL_CALC_FOUND_ROWS *, MATCH (testTitle,testBody) AGAINST ('中国|人民') AS matchscore
FROM tabTest
WHERE MATCH (testTitle,testBody) AGAINST ('中国|人民') > 0.5
LIMIT 10


     * */

    //对字串使用 MIME base64 对数据进行编码
    public static function en($str)
    {
//        $E=base64_encode($str);
//        $A=str_replace(['a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','/','='],['O','z','V','d','U','u','T','q','N','r','W','f','P','c','K','t','J','I','H','i','F','p','R','n','v','M','w','L','m','X','s','B','h','E','g','D','j','C','b','S','y','A','e','Z','x','Y','l','o','k','Q','a','G','.','_'],$E);
//        echo '<hr>E='.$E.'<br>A='.$A.'<hr>';
        return str_replace(['/', '='], ['.', '_'], base64_encode($str));
    }

    //解码
    public static function de($str)
    {
        //return base64_decode(str_replace(['O','z','V','d','U','u','T','q','N','r','W','f','P','c','K','t','J','I','H','i','F','p','R','n','v','M','w','L','m','X','s','B','h','E','g','D','j','C','b','S','y','A','e','Z','x','Y','l','o','k','Q','a','G','.','_'],['a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','/','='],$str));
        return base64_decode(str_replace(['.', '_'], ['/', '='], $str));
    }


    //中文字库表，维标为笔画数
    public static function zh_cn()
    {
        $cn = [];
        $cn[0] = '';
        $cn[1] = '一乙';
        $cn[2] = '二十丁厂七卜人入八九几儿了力乃刀又匕刁';
        $cn[3] = '三于干亏士工土才寸下大丈与万上小口巾山千乞川亿个勺久凡及夕丸么广亡门义之尸弓己已子卫也女飞刃习叉马乡';
        $cn[4] = '丰王井开夫天无元专云扎艺木五支厅不太犬区历尤友匹车巨牙屯比互切瓦止少日中冈贝内水见午牛手毛气升长仁什片仆化仇币仍仅斤爪反介父从今凶分乏公仓月氏勿欠风丹匀乌凤勾文六方火为斗忆订计户认心尺引丑巴孔队办以允予劝双书幻丐歹戈夭仑讥冗邓';
        $cn[5] = '玉刊示末未击打巧正扑扒功扔去甘世古节本术可丙左厉右石布龙平灭轧东卡北占业旧帅归且旦目叶甲申叮电号田由史只央兄叼叫另叨叹四生失禾丘付仗代仙们仪白仔他斥瓜乎丛令用甩印乐句匆册犯外处冬鸟务包饥主市立闪兰半汁汇头汉宁穴它讨写让礼训必议讯记永司尼民出辽奶奴加召皮边发孕圣对台矛纠母幼丝艾夯凸卢叭叽皿凹囚矢乍尔冯玄';
        $cn[6] = '式刑动扛寺吉扣考托老执巩圾扩扫地扬场耳共芒亚芝朽朴机权过臣再协西压厌在有百存而页匠夸夺灰达列死成夹轨邪划迈毕至此贞师尘尖劣光当早吐吓虫曲团同吊吃因吸吗屿帆岁回岂刚则肉网年朱先丢舌竹迁乔伟传乒乓休伍伏优伐延件任伤价份华仰仿伙伪自血向似后行舟全会杀合兆企众爷伞创肌朵杂危旬旨负各名多争色壮冲冰庄庆亦刘齐交次衣产决充妄闭问闯羊并关米灯州汗污江池汤忙兴宇守宅字安讲军许论农讽设访寻那迅尽导异孙阵阳收阶阴防奸如妇好她妈戏羽观欢买红纤级约纪驰巡邦迂邢芋芍吏夷吁吕吆屹廷迄臼仲伦伊肋旭匈凫妆亥汛讳讶讹讼诀弛阱驮驯纫';
        $cn[7] = '寿弄麦形进戒吞远违运扶抚坛技坏扰拒找批扯址走抄坝贡攻赤折抓扮抢孝均抛投坟抗坑坊抖护壳志扭块声把报却劫芽花芹芬苍芳严芦劳克苏杆杠杜材村杏极李杨求更束豆两丽医辰励否还歼来连步坚旱盯呈时吴助县里呆园旷围呀吨足邮男困吵串员听吩吹呜吧吼别岗帐财针钉告我乱利秃秀私每兵估体何但伸作伯伶佣低你住位伴身皂佛近彻役返余希坐谷妥含邻岔肝肚肠龟免狂犹角删条卵岛迎饭饮系言冻状亩况床库疗应冷这序辛弃冶忘闲间闷判灶灿弟汪沙汽沃泛沟没沈沉怀忧快完宋宏牢究穷灾良证启评补初社识诉诊词译君灵即层尿尾迟局改张忌际陆阿陈阻附妙妖妨努忍劲鸡驱纯纱纳纲驳纵纷纸纹纺驴纽玖玛韧抠扼汞扳抡坎坞抑拟抒芙芜苇芥芯芭杖杉巫杈甫匣轩卤肖吱吠呕呐吟呛吻吭邑囤吮岖牡佑佃伺囱肛肘甸狈鸠彤灸刨庇吝庐闰兑灼沐沛汰沥沦汹沧沪忱诅诈罕屁坠妓姊妒纬';
        $cn[8] = '奉玩环武青责现表规抹拢拔拣担坦押抽拐拖拍者顶拆拥抵拘势抱垃拉拦拌幸招坡披拨择抬其取苦若茂苹苗英范直茄茎茅林枝杯柜析板松枪构杰述枕丧或画卧事刺枣雨卖矿码厕奔奇奋态欧垄妻轰顷转斩轮软到非叔肯齿些虎虏肾贤尚旺具果味昆国昌畅明易昂典固忠咐呼鸣咏呢岸岩帖罗帜岭凯败贩购图钓制知垂牧物乖刮秆和季委佳侍供使例版侄侦侧凭侨佩货依的迫质欣征往爬彼径所舍金命斧爸采受乳贪念贫肤肺肢肿胀朋股肥服胁周昏鱼兔狐忽狗备饰饱饲变京享店夜庙府底剂郊废净盲放刻育闸闹郑券卷单炒炊炕炎炉沫浅法泄河沾泪油泊沿泡注泻泳泥沸波泼泽治怖性怕怜怪学宝宗定宜审宙官空帘实试郎诗肩房诚衬衫视话诞询该详建肃录隶居届刷屈弦承孟孤陕降限妹姑姐姓始驾参艰线练组细驶织终驻驼绍经贯玫卦坷坯拓坪坤拄拧拂拙拇拗茉昔苛苫苟苞茁苔枉枢枚枫杭郁矾奈奄殴歧卓昙哎咕呵咙呻咒咆咖帕账贬贮氛秉岳侠侥侣侈卑刽刹肴觅忿瓮肮肪狞庞疟疙疚卒氓炬沽沮泣泞泌沼怔怯宠宛衩祈诡帚屉弧弥陋陌函姆虱叁绅驹绊绎';
        $cn[9] = '奏春帮珍玻毒型挂封持项垮挎城挠政赴赵挡挺括拴拾挑指垫挣挤拼挖按挥挪某甚革荐巷带草茧茶荒茫荡荣故胡南药标枯柄栋相查柏柳柱柿栏树要咸威歪研砖厘厚砌砍面耐耍牵残殃轻鸦皆背战点临览竖省削尝是盼眨哄显哑冒映星昨畏趴胃贵界虹虾蚁思蚂虽品咽骂哗咱响哈咬咳哪炭峡罚贱贴骨钞钟钢钥钩卸缸拜看矩怎牲选适秒香种秋科重复竿段便俩贷顺修保促侮俭俗俘信皇泉鬼侵追俊盾待律很须叙剑逃食盆胆胜胞胖脉勉狭狮独狡狱狠贸怨急饶蚀饺饼弯将奖哀亭亮度迹庭疮疯疫疤姿亲音帝施闻阀阁差养美姜叛送类迷前首逆总炼炸炮烂剃洁洪洒浇浊洞测洗活派洽染济洋洲浑浓津恒恢恰恼恨举觉宣室宫宪突穿窃客冠语扁袄祖神祝误诱说诵垦退既屋昼费陡眉孩除险院娃姥姨姻娇怒架贺盈勇怠柔垒绑绒结绕骄绘给络骆绝绞统契贰玷玲珊拭拷拱挟垢垛拯荆茸茬荚茵茴荞荠荤荧荔栈柑栅柠枷勃柬砂泵砚鸥轴韭虐昧盹咧昵昭盅勋哆咪哟幽钙钝钠钦钧钮毡氢秕俏俄俐侯徊衍胚胧胎狰饵峦奕咨飒闺闽籽娄烁炫洼柒涎洛恃恍恬恤宦诫诬祠诲屏屎逊陨姚娜蚤骇';
        $cn[10] = '耕耗艳泰珠班素蚕顽盏匪捞栽捕振载赶起盐捎捏埋捉捆捐损都哲逝捡换挽热恐壶挨耻耽恭莲莫荷获晋恶真框桂档桐株桥桃格校核样根索哥速逗栗配翅辱唇夏础破原套逐烈殊顾轿较顿毙致柴桌虑监紧党晒眠晓鸭晃晌晕蚊哨哭恩唤啊唉罢峰圆贼贿钱钳钻铁铃铅缺氧特牺造乘敌秤租积秧秩称秘透笔笑笋债借值倚倾倒倘俱倡候俯倍倦健臭射躬息徒徐舰舱般航途拿爹爱颂翁脆脂胸胳脏胶脑狸狼逢留皱饿恋桨浆衰高席准座脊症病疾疼疲效离唐资凉站剖竞部旁旅畜阅羞瓶拳粉料益兼烤烘烦烧烛烟递涛浙涝酒涉消浩海涂浴浮流润浪浸涨烫涌悟悄悔悦害宽家宵宴宾窄容宰案请朗诸读扇袜袖袍被祥课谁调冤谅谈谊剥恳展剧屑弱陵陶陷陪娱娘通能难预桑绢绣验继耘耙秦匿埂捂捍袁捌挫挚捣捅埃耿聂荸莽莱莉莹莺梆栖桦栓桅桩贾酌砸砰砾殉逞哮唠哺剔蚌蚜畔蚣蚪蚓哩圃鸯唁哼唆峭唧峻赂赃钾铆氨秫笆俺赁倔殷耸舀豺豹颁胯胰脐脓逛卿鸵鸳馁凌凄衷郭斋疹紊瓷羔烙浦涡涣涤涧涕涩悍悯窍诺诽袒谆祟恕娩骏';
        $cn[11] = '球理捧堵描域掩捷排掉堆推掀授教掏掠培接控探据掘职基著勒黄萌萝菌菜萄菊萍菠营械梦梢梅检梳梯桶救副票戚爽聋袭盛雪辅辆虚雀堂常匙晨睁眯眼悬野啦晚啄距跃略蛇累唱患唯崖崭崇圈铜铲银甜梨犁移笨笼笛符第敏做袋悠偿偶偷您售停偏假得衔盘船斜盒鸽悉欲彩领脚脖脸脱象够猜猪猎猫猛馅馆凑减毫麻痒痕廊康庸鹿盗章竟商族旋望率着盖粘粗粒断剪兽清添淋淹渠渐混渔淘液淡深婆梁渗情惜惭悼惧惕惊惨惯寇寄宿窑密谋谎祸谜逮敢屠弹随蛋隆隐婚婶颈绩绪续骑绳维绵绸绿琐麸琉琅措捺捶赦埠捻掐掂掖掷掸掺勘聊娶菱菲萎菩萤乾萧萨菇彬梗梧梭曹酝酗厢硅硕奢盔匾颅彪眶晤曼晦冕啡畦趾啃蛆蚯蛉蛀唬啰唾啤啥啸崎逻崔崩婴赊铐铛铝铡铣铭矫秸秽笙笤偎傀躯兜衅徘徙舶舷舵敛翎脯逸凰猖祭烹庶庵痊阎阐眷焊焕鸿涯淑淌淮淆渊淫淳淤淀涮涵惦悴惋寂窒谍谐裆袱祷谒谓谚尉堕隅婉颇绰绷综绽缀巢';
        $cn[12] = '琴斑替款堪搭塔越趁趋超提堤博揭喜插揪搜煮援裁搁搂搅握揉斯期欺联散惹葬葛董葡敬葱落朝辜葵棒棋植森椅椒棵棍棉棚棕惠惑逼厨厦硬确雁殖裂雄暂雅辈悲紫辉敞赏掌晴暑最量喷晶喇遇喊景践跌跑遗蛙蛛蜓喝喂喘喉幅帽赌赔黑铸铺链销锁锄锅锈锋锐短智毯鹅剩稍程稀税筐等筑策筛筒答筋筝傲傅牌堡集焦傍储奥街惩御循艇舒番释禽腊脾腔鲁猾猴然馋装蛮就痛童阔善羡普粪尊道曾焰港湖渣湿温渴滑湾渡游滋溉愤慌惰愧愉慨割寒富窜窝窗遍裕裤裙谢谣谦属屡强粥疏隔隙絮嫂登缎缓编骗缘琳琢琼揍堰揩揽揖彭揣搀搓壹搔葫募蒋蒂韩棱椰焚椎棺榔椭粟棘酣酥硝硫颊雳翘凿棠晰鼎喳遏晾畴跋跛蛔蜒蛤鹃喻啼喧嵌赋赎赐锉锌甥掰氮氯黍筏牍粤逾腌腋腕猩猬惫敦痘痢痪竣翔奠遂焙滞湘渤渺溃溅湃愕惶寓窖窘雇谤犀隘媒媚婿缅缆缔缕骚';
        $cn[13] = '瑞魂肆摄摸填搏塌鼓摆携搬摇搞塘摊蒜勤鹊蓝墓幕蓬蓄蒙蒸献禁楚想槐榆楼概赖酬感碍碑碎碰碗碌雷零雾雹输督龄鉴睛睡睬鄙愚暖盟歇暗照跨跳跪路跟遣蛾蜂嗓置罪罩错锡锣锤锦键锯矮辞稠愁筹签简毁舅鼠催傻像躲微愈遥腰腥腹腾腿触解酱痰廉新韵意粮数煎塑慈煤煌满漠源滤滥滔溪溜滚滨粱滩慎誉塞谨福群殿辟障嫌嫁叠缝缠瑟鹉瑰搪聘斟靴靶蓖蒿蒲蓉楔椿楷榄楞楣酪碘硼碉辐辑频睹睦瞄嗜嗦暇畸跷跺蜈蜗蜕蛹嗅嗡嗤署蜀幌锚锥锨锭锰稚颓筷魁衙腻腮腺鹏肄猿颖煞雏馍馏禀痹廓痴靖誊漓溢溯溶滓溺寞窥窟寝褂裸谬媳嫉缚缤剿';
        $cn[14] = '静碧璃墙撇嘉摧截誓境摘摔聚蔽慕暮蔑模榴榜榨歌遭酷酿酸磁愿需弊裳颗嗽蜻蜡蝇蜘赚锹锻舞稳算箩管僚鼻魄貌膜膊膀鲜疑馒裹敲豪膏遮腐瘦辣竭端旗精歉熄熔漆漂漫滴演漏慢寨赛察蜜谱嫩翠熊凳骡缩赘熬赫蔫摹蔓蔗蔼熙蔚兢榛榕酵碟碴碱碳辕辖雌墅嘁踊蝉嘀幔镀舔熏箍箕箫舆僧孵瘩瘟彰粹漱漩漾慷寡寥谭褐褪隧嫡缨';
        $cn[15] = '慧撕撒趣趟撑播撞撤增聪鞋蕉蔬横槽樱橡飘醋醉震霉瞒题暴瞎影踢踏踩踪蝶蝴嘱墨镇靠稻黎稿稼箱箭篇僵躺僻德艘膝膛熟摩颜毅糊遵潜潮懂额慰劈撵撩撮撬擒墩撰鞍蕊蕴樊樟橄敷豌醇磕磅碾憋嘶嘲嘹蝠蝎蝌蝗蝙嘿幢镊镐稽篓膘鲤鲫褒瘪瘤瘫凛澎潭潦澳潘澈澜澄憔懊憎翩褥谴鹤憨履嬉豫缭';
        $cn[16] = '操燕薯薪薄颠橘整融醒餐嘴蹄器赠默镜赞篮邀衡膨雕磨凝辨辩糖糕燃澡激懒壁避缴撼擂擅蕾薛薇擎翰噩橱橙瓢蟥霍霎辙冀踱蹂蟆螃螟噪鹦黔穆篡篷篙篱儒膳鲸瘾瘸糙燎濒憾懈窿缰';
        $cn[17] = '戴擦鞠藏霜霞瞧蹈螺穗繁辫赢糟糠燥臂翼骤壕藐檬檐檩檀礁磷瞭瞬瞳瞪曙蹋蟋蟀嚎赡镣魏簇儡徽爵朦臊鳄糜癌懦豁臀';
        $cn[18] = '鞭覆蹦镰翻鹰藕藤瞻嚣鳍癞瀑襟璧戳';
        $cn[19] = '警攀蹲颤瓣爆疆攒孽蘑藻鳖蹭蹬簸簿蟹靡癣羹';
        $cn[20] = '壤耀躁嚼嚷籍魔灌鬓攘蠕巍鳞糯譬';
        $cn[21] = '蠢霸露霹躏髓';
        $cn[22] = '囊蘸镶瓤';
        $cn[23] = '罐';
        $cn[24] = '矗';
        return $cn;
    }

    /*
     * 将一串中文转换为可以存入数据库的拼音
     * */
    public static function cut_py($word, $delimiter = ' ', $Simplify = false)
    {
        $str = self::cut(text($word));//分词,先删除所有HTML
        return self::PinYin($str, $delimiter, $Simplify);//转换拼音
    }

    //转换全部拼音
    public static function PinYin($chinese, $delimiter = ' ', $Simplify = false)
    {
        if (is_array($chinese)) {//以数组中的字串为准
            return self::arr_to_py($chinese, $delimiter, $Simplify);
        } else {             //每个字一个串
            $fun = $Simplify ? 'str_to_sim' : 'str_to_py';
            return self::$fun($chinese, $delimiter);
        }
    }

    //将一个数组中的汉字转拼音，不指定$delimiter时输出数组
    private static function arr_to_py($chinese, $delimiter = null, $Simplify = false)
    {
        $result = [];
        $fun = $Simplify ? 'str_to_sim' : 'str_to_py';
        foreach ($chinese as $cn) {
            $result[] = self::$fun($cn, '');
        }
        return $delimiter !== null ? implode($delimiter, $result) : $result;
    }

    //将一段中文按字转换为拼音
    private static function str_to_py($chinese, $delimiter = null)
    {
        $result = [];
        $chinese = iconv("utf-8", "gb2312//IGNORE", $chinese);

        for ($i = 0; $i < strlen($chinese); $i++) {
            $p = ord(substr($chinese, $i, 1));
            if ($p > 160) {
                $q = ord(substr($chinese, ++$i, 1));
                $p = $p * 256 + $q - 65536;
            }
            $result[] = self::zh_to_py($p);
        }
        return $delimiter !== null ? implode($delimiter, $result) : $result;
    }

    //拼音首个字母
    private static function str_to_sim($chinese, $delimiter = null)
    {
        $result = [];
        for ($i = 0; $i < strlen($chinese); $i++) {
            $p = ord(substr($chinese, $i, 1));
            if ($p > 160) {
                $q = ord(substr($chinese, ++$i, 1));
                $p = $p * 256 + $q - 65536;
            }
            $result[] = substr(self::zh_to_py($p), 0, 1);
        }
        return $delimiter !== null ? implode($delimiter, $result) : $result;
    }


    //-------------------中文转拼音--------------------------------//
    private static function zh_to_py($num)
    {
        if ($num > 0 && $num < 160) {
            return chr($num);
        } elseif ($num < -20319 || $num > -10247) {
            return '';//超出范围
        } else {
            $result = '';
            foreach (self::$pyList as $py => $code) {
                if ($code > $num) break;
                $result = $py;
            }
            return $result;
        }
    }


    protected static $wordObj = null;
    protected static $CustomBefore = null;
    protected static $CustomBeforeRe = null;
    protected static $CustomAfter = null;

    //分词

    public static function cut($word = null, $minLen = 0)
    {
        return [];
//        if (!$word) return [];
//        $tmp = self::cut_word($word);
//        $newWord = [];
//        foreach ($tmp as $wd) {
//            if ((int)$wd['len'] >= $minLen * 3) $newWord[] = $wd['word'];
//        }
//        return $newWord;
    }


    protected static function cut_word($word = null)
    {
        if (!$word) return [];
        if (self::$CustomAfter === null) {
            self::$CustomBefore = load_php('cache_handler/word/before.php');
            self::$CustomAfter = load_php('cache_handler/word/after.php');

            self::$CustomAfter = array_merge(self::$CustomAfter, self::$CustomBefore);
            self::$CustomAfter = array_flip(self::$CustomAfter);//对换键名和键值
            foreach (self::$CustomBefore as $v) {
                self::$CustomBeforeRe[] = "/([^a-z0-9])({$v})([^a-z0-9])/i";
            }
        }
        $disc = __DIR__ . '/cache_handler/word/disc.txt';

        if (self::$wordObj === null) {
            self::$wordObj = scws_new();               //创建对象
            self::$wordObj->set_charset('utf8');       //设定UTF8
            self::$wordObj->set_ignore(true);          //过滤符号
            self::$wordObj->set_duality(true);         //设定是否将闲散文字自动以二字分词法聚合
            self::$wordObj->set_multi(true);         //设定分词返回结果时是否复式分割，如“中国人”返回“中国＋人＋中国人”三个词
            self::$wordObj->set_dict($disc);
        }
        $word = preg_replace(self::$CustomBeforeRe, "$1 $2 $3", $word);

        self::$wordObj->send_text($word);
        $newWord = [];
        while ($tmp = self::$wordObj->get_result()) {
            $newWord = array_merge($newWord, $tmp);
        }
        //self::$wordObj->close();//不能关闭
        return self::reCustomAfter($newWord);//分词后重新组合自定义的关键词
    }

    /*
     * 附加对分词结果进行整理，主要是对自定义词的过滤
    */
    protected static function reCustomAfter($word)
    {
        foreach ($word as $i => $wd) {
            if (!isset($word[$i + 1])) break;
            $nd = $word[$i + 1];
            $w = strtolower($wd['word'] . $nd['word']);
            if (isset(self::$CustomAfter[$w])) {
                $wd['word'] = $w;
                $wd['len'] = $wd['len'] + $nd['len'];
                $word[$i] = $wd;
                unset($word[$i + 1]);
            }
        }
        return $word;
    }


    /*返回两个串的相似度*/
    public static function similar($str1, $str2)
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        $len = strlen(self::same($str1, $str2, $len1, $len2));
        return $len * 2 / ($len1 + $len2);
    }

    //查找两个串相同部分
    public static function same($str1, $str2, $len1 = 0, $len2 = 0)
    {
        if ($len1 == 0) $len1 = strlen($str1);
        if ($len2 == 0) $len2 = strlen($str2);

        function initC($len1, $len2, $str1, $str2)
        {
            $iniC = [];
            for ($i = 0; $i < $len1; $i++) $iniC[$i][0] = 0;
            for ($j = 0; $j < $len2; $j++) $iniC[0][$j] = 0;
            for ($i = 1; $i < $len1; $i++) {
                for ($j = 1; $j < $len2; $j++) {
                    if ($str1[$i] == $str2[$j]) {
                        $iniC[$i][$j] = $iniC[$i - 1][$j - 1] + 1;
                    } else if ($iniC[$i - 1][$j] >= $iniC[$i][$j - 1]) {
                        $iniC[$i][$j] = $iniC[$i - 1][$j];
                    } else {
                        $iniC[$i][$j] = $iniC[$i][$j - 1];
                    }
                }
            }
            return $iniC;
        }

        function printLCS($iniC, $i, $j, $str1, $str2)
        {
            if ($i == 0 || $j == 0) {
                if ($str1[$i] == $str2[$j]) return $str2[$j];
                else return "";
            }
            if ($str1[$i] == $str2[$j]) {
                return printLCS($iniC, $i - 1, $j - 1, $str1, $str2) . $str2[$j];
            } else if ($iniC[$i - 1][$j] >= $iniC[$i][$j - 1]) {
                return printLCS($iniC, $i - 1, $j, $str1, $str2);
            } else {
                return printLCS($iniC, $i, $j - 1, $str1, $str2);
            }
        }


        $initC = initC($len1, $len2, $str1, $str2);
        return printLCS($initC, $len1 - 1, $len2 - 1, $str1, $str2);
    }


    protected static $ms_pinyin = null;


    //数组中的汉字转拼音，输出数组,采用微软字库
    private static function ms_cn_to_py($chinese, $delimiter = null)
    {
        $result = [];
        if (self::$ms_pinyin === null) {
            self::$ms_pinyin = load_php('cache_handler/pinyin/Cache_pinyin.php');
            //   self::$ms_pinyin=self::a_array_unique(self::$ms_pinyin);
            print_r(self::$ms_pinyin);
        }

        foreach ($chinese as $cn) {
            $result[] = self::ms_pinyin($cn);
        }
        return $delimiter !== null ? implode($delimiter, $result) : $result;
    }

    protected static function a_array_unique($array)//过滤数组中相同的部分
    {
        $out = [];
        foreach ($array as $key => $value) {
            if (!in_array($value, $out)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    private static function ms_pinyin($s)
    {
        $s = trim($s);
        $len = strlen($s);
        if ($len < 3) return $s;

        $rs = '';
        for ($i = 0; $i < $len; $i++) {
            $o = ord($s[$i]);
            if ($o < 0x80) {
                if (($o >= 48 && $o <= 57) || ($o >= 97 && $o <= 122)) {
                    $rs .= $s[$i]; // 0-9 a-z
                } elseif ($o >= 65 && $o <= 90) {
                    $rs .= strtolower($s[$i]); // A-Z
                } else {
                    $rs .= '_';
                }
            } else {
                $z = $s[$i] . $s[++$i] . $s[++$i];
                if (isset(self::$ms_pinyin[$z])) {
                    $rs .= self::$ms_pinyin[$z];
                }
            }
        }
        return $rs;
    }


    protected static $pyList = array(
        'a' => -20319, 'ai' => -20317, 'an' => -20304, 'ang' => -20295, 'ao' => -20292,
        'ba' => -20283, 'bai' => -20265, 'ban' => -20257, 'bang' => -20242, 'bao' => -20230, 'bei' => -20051, 'ben' => -20036, 'beng' => -20032, 'bi' => -20026, 'bian' => -20002, 'biao' => -19990, 'bie' => -19986, 'bin' => -19982, 'bing' => -19976, 'bo' => -19805, 'bu' => -19784,
        'ca' => -19775, 'cai' => -19774, 'can' => -19763, 'cang' => -19756, 'cao' => -19751, 'ce' => -19746, 'ceng' => -19741, 'cha' => -19739, 'chai' => -19728, 'chan' => -19725, 'chang' => -19715, 'chao' => -19540, 'che' => -19531, 'chen' => -19525, 'cheng' => -19515, 'chi' => -19500, 'chong' => -19484, 'chou' => -19479, 'chu' => -19467, 'chuai' => -19289, 'chuan' => -19288, 'chuang' => -19281, 'chui' => -19275, 'chun' => -19270, 'chuo' => -19263, 'ci' => -19261, 'cong' => -19249, 'cou' => -19243, 'cu' => -19242, 'cuan' => -19238, 'cui' => -19235, 'cun' => -19227, 'cuo' => -19224,
        'da' => -19218, 'dai' => -19212, 'dan' => -19038, 'dang' => -19023, 'dao' => -19018, 'de' => -19006, 'deng' => -19003, 'di' => -18996, 'dian' => -18977, 'diao' => -18961, 'die' => -18952, 'ding' => -18783, 'diu' => -18774, 'dong' => -18773, 'dou' => -18763, 'du' => -18756, 'duan' => -18741, 'dui' => -18735, 'dun' => -18731, 'duo' => -18722,
        'e' => -18710, 'en' => -18697, 'er' => -18696,
        'fa' => -18526, 'fan' => -18518, 'fang' => -18501, 'fei' => -18490, 'fen' => -18478, 'feng' => -18463, 'fo' => -18448, 'fou' => -18447, 'fu' => -18446,
        'ga' => -18239, 'gai' => -18237, 'gan' => -18231, 'gang' => -18220, 'gao' => -18211, 'ge' => -18201, 'gei' => -18184, 'gen' => -18183, 'geng' => -18181, 'gong' => -18012, 'gou' => -17997, 'gu' => -17988, 'gua' => -17970, 'guai' => -17964, 'guan' => -17961, 'guang' => -17950, 'gui' => -17947, 'gun' => -17931, 'guo' => -17928,
        'ha' => -17922, 'hai' => -17759, 'han' => -17752, 'hang' => -17733, 'hao' => -17730, 'he' => -17721, 'hei' => -17703, 'hen' => -17701, 'heng' => -17697, 'hong' => -17692, 'hou' => -17683, 'hu' => -17676, 'hua' => -17496, 'huai' => -17487, 'huan' => -17482, 'huang' => -17468, 'hui' => -17454, 'hun' => -17433, 'huo' => -17427,
        'ji' => -17417, 'jia' => -17202, 'jian' => -17185, 'jiang' => -16983, 'jiao' => -16970, 'jie' => -16942, 'jin' => -16915, 'jing' => -16733, 'jiong' => -16708, 'jiu' => -16706, 'ju' => -16689, 'juan' => -16664, 'jue' => -16657, 'jun' => -16647,
        'ka' => -16474, 'kai' => -16470, 'kan' => -16465, 'kang' => -16459, 'kao' => -16452, 'ke' => -16448, 'ken' => -16433, 'keng' => -16429, 'kong' => -16427, 'kou' => -16423, 'ku' => -16419, 'kua' => -16412, 'kuai' => -16407, 'kuan' => -16403, 'kuang' => -16401, 'kui' => -16393, 'kun' => -16220, 'kuo' => -16216,
        'la' => -16212, 'lai' => -16205, 'lan' => -16202, 'lang' => -16187, 'lao' => -16180, 'le' => -16171, 'lei' => -16169, 'leng' => -16158, 'li' => -16155, 'lia' => -15959, 'lian' => -15958, 'liang' => -15944, 'liao' => -15933, 'lie' => -15920, 'lin' => -15915, 'ling' => -15903, 'liu' => -15889, 'long' => -15878, 'lou' => -15707, 'lu' => -15701, 'lv' => -15681, 'luan' => -15667, 'lue' => -15661, 'lun' => -15659, 'luo' => -15652,
        'ma' => -15640, 'mai' => -15631, 'man' => -15625, 'mang' => -15454, 'mao' => -15448, 'me' => -15436, 'mei' => -15435, 'men' => -15419, 'meng' => -15416, 'mi' => -15408, 'mian' => -15394, 'miao' => -15385, 'mie' => -15377, 'min' => -15375, 'ming' => -15369, 'miu' => -15363, 'mo' => -15362, 'mou' => -15183, 'mu' => -15180,
        'na' => -15165, 'nai' => -15158, 'nan' => -15153, 'nang' => -15150, 'nao' => -15149, 'ne' => -15144, 'nei' => -15143, 'nen' => -15141, 'neng' => -15140, 'ni' => -15139, 'nian' => -15128, 'niang' => -15121, 'niao' => -15119, 'nie' => -15117, 'nin' => -15110, 'ning' => -15109, 'niu' => -14941, 'nong' => -14937, 'nu' => -14933, 'nv' => -14930, 'nuan' => -14929, 'nue' => -14928, 'nuo' => -14926,
        'o' => -14922, 'ou' => -14921,
        'pa' => -14914, 'pai' => -14908, 'pan' => -14902, 'pang' => -14894, 'pao' => -14889, 'pei' => -14882, 'pen' => -14873, 'peng' => -14871, 'pi' => -14857, 'pian' => -14678, 'piao' => -14674, 'pie' => -14670, 'pin' => -14668, 'ping' => -14663, 'po' => -14654, 'pu' => -14645,
        'qi' => -14630, 'qia' => -14594, 'qian' => -14429, 'qiang' => -14407, 'qiao' => -14399, 'qie' => -14384, 'qin' => -14379, 'qing' => -14368, 'qiong' => -14355, 'qiu' => -14353, 'qu' => -14345, 'quan' => -14170, 'que' => -14159, 'qun' => -14151,
        'ran' => -14149, 'rang' => -14145, 'rao' => -14140, 're' => -14137, 'ren' => -14135, 'reng' => -14125, 'ri' => -14123, 'rong' => -14122, 'rou' => -14112, 'ru' => -14109, 'ruan' => -14099, 'rui' => -14097, 'run' => -14094, 'ruo' => -14092,
        'sa' => -14090, 'sai' => -14087, 'san' => -14083, 'sang' => -13917, 'sao' => -13914, 'se' => -13910, 'sen' => -13907, 'seng' => -13906, 'sha' => -13905, 'shai' => -13896, 'shan' => -13894, 'shang' => -13878, 'shao' => -13870, 'she' => -13859, 'shen' => -13847, 'sheng' => -13831, 'shi' => -13658, 'shou' => -13611, 'shu' => -13601, 'shua' => -13406, 'shuai' => -13404, 'shuan' => -13400, 'shuang' => -13398, 'shui' => -13395, 'shun' => -13391, 'shuo' => -13387, 'si' => -13383, 'song' => -13367, 'sou' => -13359, 'su' => -13356, 'suan' => -13343, 'sui' => -13340, 'sun' => -13329, 'suo' => -13326,
        'ta' => -13318, 'tai' => -13147, 'tan' => -13138, 'tang' => -13120, 'tao' => -13107, 'te' => -13096, 'teng' => -13095, 'ti' => -13091, 'tian' => -13076, 'tiao' => -13068, 'tie' => -13063, 'ting' => -13060, 'tong' => -12888, 'tou' => -12875, 'tu' => -12871, 'tuan' => -12860, 'tui' => -12858, 'tun' => -12852, 'tuo' => -12849,
        'wa' => -12838, 'wai' => -12831, 'wan' => -12829, 'wang' => -12812, 'wei' => -12802, 'wen' => -12607, 'weng' => -12597, 'wo' => -12594, 'wu' => -12585,
        'xi' => -12556, 'xia' => -12359, 'xian' => -12346, 'xiang' => -12320, 'xiao' => -12300, 'xie' => -12120, 'xin' => -12099, 'xing' => -12089, 'xiong' => -12074, 'xiu' => -12067, 'xu' => -12058, 'xuan' => -12039, 'xue' => -11867, 'xun' => -11861,
        'ya' => -11847, 'yan' => -11831, 'yang' => -11798, 'yao' => -11781, 'ye' => -11604, 'yi' => -11589, 'yin' => -11536, 'ying' => -11358, 'yo' => -11340, 'yong' => -11339, 'you' => -11324, 'yu' => -11303, 'yuan' => -11097, 'yue' => -11077, 'yun' => -11067,
        'za' => -11055, 'zai' => -11052, 'zan' => -11045, 'zang' => -11041, 'zao' => -11038, 'ze' => -11024, 'zei' => -11020, 'zen' => -11019, 'zeng' => -11018, 'zha' => -11014, 'zhai' => -10838, 'zhan' => -10832, 'zhang' => -10815, 'zhao' => -10800, 'zhe' => -10790, 'zhen' => -10780, 'zheng' => -10764, 'zhi' => -10587, 'zhong' => -10544, 'zhou' => -10533, 'zhu' => -10519, 'zhua' => -10331, 'zhuai' => -10329, 'zhuan' => -10328, 'zhuang' => -10322, 'zhui' => -10315, 'zhun' => -10309, 'zhuo' => -10307, 'zi' => -10296, 'zong' => -10281, 'zou' => -10274, 'zu' => -10270, 'zuan' => -10262, 'zui' => -10260, 'zun' => -10256, 'zuo' => -10254
    );


}
