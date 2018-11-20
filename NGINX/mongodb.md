配置：
===================================
/usr/local/mongodb/bin/mongodb.conf
```
dbpath  = /usr/local/mongodb/data/
logpath = /usr/local/mongodb/log/mongodb.log
port    = 27017
fork    = true
auth    = true
```

启动：
===============================
/usr/local/mongodb/bin/mongod --config /usr/local/mongodb/bin/mongodb.conf


启动客户端：
===============================
/usr/local/mongodb/bin/mongo
    
    切换数据库：use dbPay              （若不切换，默认为test库）
    账号密码：db.auth("root","root")



用户管理：（在客户端下操作）
==========================================
    
    添加用户：   db.createUser({user:"userName",pwd:"userPwd",roles:[{role:"dbAdmin",db:"pay"}]});
    修改用户：   db.updateUser("userName", {roles:[{role:"dbAdmin",db:"pay"}],pwd:"password"})

    role可选值：
        普通用户角色	read、readWrite
        数据库管理员角色	dbAdmin、dbOwner、userAdmin
        集群管理员角色	clusterAdmin、clusterManager、clusterMonitor、hostManager
        数据库备份与恢复角色	backup、restore
        所有数据库角色	readAnyDatabase、readWriteAnyDatabase、userAdminAnyDatabase、dbAdminAnyDatabase
        超级用户角色	root
        核心角色	__system
    
    
    删除用户：db.removeUser("userName")

    修改密码：db.changeUserPassword("root", "password")

    db.createUser({user:"phpUser",pwd:"phpPassword",roles:[{role:"readWrite",db:"pay"}]});

备份：
=======================
    /usr/local/mongodb/bin/mongodump -d 【数据库名】 -o 【保存目录】
    /usr/local/mongodb/bin/mongodump -d pay -o /home/test
    
    打包：
    tar -zcvf pay20180627.tar.gz /home/test/pay



恢复：
==============================
    /usr/local/mongodb/bin/mongorestore -d 【数据库名】 --drop --maintainInsertionOrder --dir=【数据目录】
    /usr/local/mongodb/bin/mongorestore -d pay --drop --maintainInsertionOrder --dir=/home/test/pay
    注意这里的目录和上面的有点不同：要指向最终的目录，备份时，会创建一个数据库名的子目录
    

 