#!/bin/sh

function sync_time(){
    get=$(/usr/sbin/ntpdate -u $1 | grep "offset");
    getday=$(echo $get | awk '{print $1}');
    today=$(date -d now +"%d");
    now=$(date);
    if [ "$today" = "$getday" ]; then
        echo $now '    ' $1 ' >>>> '  $get  [is True];
        exit 0;
    else
        echo $now '    ' $1 ' >>>> '  $get  [is False];
    fi
}


sync_time ntp.api.bz;
sync_time time.nist.gov
sync_time time-nw.nist.gov
sync_time time-a.nist.gov
sync_time time-b.nist.gov
sync_time asia.pool.ntp.org
sync_time time.windows.com


# yum install -y ntp           //安装ntp服务
# systemctl enable ntpd     //开机启动服务
# systemctl status ntpd     //查看状态
# systemctl start ntpd      //启动服务
# timedatectl set-timezone Asia/Shanghai        //更改时区
# timedatectl set-ntp yes   //启用ntp同步
# ntpq -p                   //同步时间
# 文件 /etc/ntp.conf 是ntp相关配置

