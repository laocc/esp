
# 服务器时间同步设置：

```bash

yum install -y ntp        //安装ntp服务
systemctl enable ntpd     //开机启动服务
systemctl status ntpd     //查看状态
systemctl start ntpd      //启动服务
timedatectl set-timezone Asia/Shanghai        //更改时区
timedatectl set-ntp yes   //启用ntp同步
ntpq -p                   //同步时间
 
```
 文件 /etc/ntp.conf 是ntp相关配置
 
 
 