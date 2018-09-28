
create database dbTest;
create user 'userTest'@'%' IDENTIFIED BY 'pwdForUserTest';
grant select,update,delete,insert on dbTest.* to userTest@'%';
flush privileges;
use dbTest;


DROP TABLE IF EXISTS `tabTest`;
CREATE TABLE `tabTest` (
  `testID` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `testTitle` VARCHAR(100) DEFAULT NULL COMMENT 'Title',
  `testBody` text DEFAULT NULL COMMENT 'Body',
  `testTime` int(11) unsigned DEFAULT NULL COMMENT 'Time',
  PRIMARY KEY (`testID`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COMMENT='Test';
