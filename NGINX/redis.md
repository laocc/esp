
bind 127.0.0.1
- 绑定的主机地址

protected-mode yes
- 保护模式

port 6379
- 指定Redis监听端口，默认端口为6379

requirepass ""
- 设置Redis连接密码，如果配置了连接密码，客户端在连接Redis时需要通过AUTH <password>命令提供密码，默认关闭

tcp-backlog 511
- TCP 监听的最大容纳数量
- 在高并发的环境下，你需要把这个值调高以避免客户端连接缓慢的问题。
- Linux 内核会一声不响的把这个值缩小成 /proc/sys/net/core/somaxconn 对应的值，
- 所以你要修改这两个值才能达到你的预期。

unixsocket /tmp/redis.sock
unixsocketperm 777
- 指定 unix socket 的路径
- 777是指对这个socket的权限

timeout 0
- 当客户端闲置多长时间后关闭连接，如果指定为0，表示关闭该功能

tcp-keepalive 300
- tcp 心跳频率，秒
- 如果设置为非零，则在与客户端缺乏通讯的时候使用 SO_KEEPALIVE 发送 tcp acks 给客户端。

daemonize yes
- Redis默认不是以守护进程的方式运行，可以通过该配置项修改，使用yes启用守护进程

supervised no

pidfile /var/run/redis_6379.pid
- 当Redis以守护进程方式运行时，Redis默认会把pid写入/var/run/redis.pid文件，可以通过pidfile指定

loglevel notice
- 指定日志记录级别，Redis总共支持四个级别：debug、verbose、notice、warning，默认为verbose

logfile /var/log/redis/redis.log
- 日志记录方式，默认为标准输出，如果配置Redis为守护进程方式运行，而这里又配置为日志记录方式为标准输出，则日志将会发送给/dev/null


databases 16
- 设置数据库的数量，默认数据库为0，可以使用SELECT <dbid>命令在连接上指定数据库id

save 900 1
save 300 10
save 60 10000
- 指定在多长时间内，有多少次更新操作，就将数据同步到数据文件，可以多个条件配合
- save <seconds> <changes>
- Redis默认配置文件中提供了三个条件，分别表示900秒（15分钟）内有1个更改，300秒（5分钟）内有10个更改以及60秒内有10000个更改。

stop-writes-on-bgsave-error yes
- 默认情况下，如果 redis 最后一次的后台保存失败，redis 将停止接受写操作，这样以一种强硬的方式让用户知道数据不能正确的持久化到磁盘，否则就会没人注意到灾难的发生。
- 如果后台保存进程重新启动工作了，redis 也将自动的允许写操作。
- 如果安装了靠谱的监控，可能不希望 redis 这样做，那就改成 no

rdbcompression yes
- 指定存储至本地数据库时是否压缩数据，默认为yes，Redis采用LZF压缩，如果为了节省CPU时间，可以关闭该选项，但会导致数据库文件变的巨大

rdbchecksum yes

dbfilename dump.rdb
- 指定本地数据库文件名，默认值为dump.rdb

dir /var/lib/redis
- 指定本地数据库存放目录




slave-serve-stale-data yes

slave-read-only yes

repl-diskless-sync no

repl-diskless-sync-delay 5


repl-disable-tcp-nodelay no

slave-priority 100



rename-command FLUSHALL ""
rename-command FLUSHDB  ""
rename-command CONFIG   ""
rename-command KEYS     ""
- 禁用危险命令
- 若禁用了FLUSHALL，则下面appendonly只能选no


appendonly no
- 指定是否在每次更新操作后进行日志记录，Redis在默认情况下是异步的把数据写入磁盘，如果不开启，可能会在断电时导致一段时间内的数据丢失。
- 因为 redis本身同步数据文件是按上面save条件来同步的，所以有的数据会在一段时间内只存在于内存中。默认为no


appendfilename "appendonly.aof"
- 指定更新日志文件名，默认为appendonly.aof

appendfsync everysec
- 指定更新日志条件，共有3个可选值： 
    no：表示等操作系统进行数据缓存同步到磁盘（快） 
    always：表示每次更新操作后手动调用fsync()将数据写到磁盘（慢，安全） 
    everysec：表示每秒同步一次（折衷，默认值）

no-appendfsync-on-rewrite no


auto-aof-rewrite-percentage 100
auto-aof-rewrite-min-size 64mb

aof-load-truncated yes


lua-time-limit 5000


slowlog-log-slower-than 10000

slowlog-max-len 128


latency-monitor-threshold 0


notify-keyspace-events ""


hash-max-ziplist-entries 512
hash-max-ziplist-value 64

list-max-ziplist-size -2

list-compress-depth 0

set-max-intset-entries 512

zset-max-ziplist-entries 128
zset-max-ziplist-value 64

hll-sparse-max-bytes 3000

activerehashing yes
- 指定是否激活重置哈希，默认为开启


client-output-buffer-limit normal 0 0 0
client-output-buffer-limit slave 256mb 64mb 60
client-output-buffer-limit pubsub 32mb 8mb 60



hz 10
- Redis需要调用内部函数来执行许多后台任务，比如超时关闭客户端连接，清除已过期的密钥，等等
- Hz用来控制这些频率，范围：1-500之间，
- 如果加大这个值，Redis响应速度将越快，同时占用CPU也更多，一般情况下10就可以了。



aof-rewrite-incremental-fsync yes






---------------------

- 设置当本机为slav服务时，设置master服务的IP地址及端口，在Redis启动时，它会自动从master进行数据同步
- slaveof <masterip> <masterport>

当master服务设置了密码保护时，slav服务连接master的密码
  masterauth <master-password>

设置同一时间最大客户端连接数，默认无限制，Redis可以同时打开的客户端连接数为Redis进程可以打开的最大文件描述符数，如果设置 maxclients 0，表示不作限制。当客户端连接数到达限制时，Redis会关闭新的连接并向客户端返回max number of clients reached错误信息
  maxclients 128

指定Redis最大内存限制，Redis在启动时会把数据加载到内存中，达到最大内存后，Redis会先尝试清除已到期或即将到期的Key，当此方法处理 后，仍然到达最大内存设置，将无法再进行写入操作，但仍然可以进行读取操作。Redis新的vm机制，会把Key存放内存，Value会存放在swap区  
maxmemory <bytes>


指定是否启用虚拟内存机制，默认值为no，简单的介绍一下，VM机制将数据分页存放，由Redis将访问量较少的页即冷数据swap到磁盘上，访问多的页面由磁盘自动换出到内存中（在后面的文章我会仔细分析Redis的VM机制）
vm-enabled no


指定包含其它的配置文件，可以在同一主机上多个Redis实例之间使用同一份配置文件，而同时各个实例又拥有自己的特定配置文件
include /path/to/local.conf
  
     
---------------------

