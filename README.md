本来是想通过PHP的proc_open和进程进行交互，可是中间的坑太多了，不得不转换一下思路，然后想起来宝塔有网页版shell客户端，然后研究了一下，嘿嘿，发现能成😁😁😁。

## 前期准备

PHP连接ssh是基于第三方拓展库，PECL/ssh2（ libssh2的php扩展，允许php程序调用libssh2中的函数）

然后有一个现成的、封装好大部分常用操作的库phpseclib：https://phpseclib.com

通过swoole的协程实现SSH的读和写并发进行以及websocket和浏览器进行通信。

测试Demo ：[http://cname.teiao.com:5707/](http://cname.teiao.com:5707/)

## 1.安装ssh2拓展库

### 1.1 Linux安装
首先要安装libssh2（libssh2是一个C 函数库，用来实现SSH2协议。）https://www.libssh2.org

```
yum install libssh2 libssh2-devel
```

然后通过pcel安装ssh2拓展 ，找准版本[https://pecl.php.net/package/ssh2](https://pecl.php.net/package/ssh2)

```
pecl install ssh2-1.1.2
```
当然也可以通过phpize进行手动安装。

### 1.2 window安装

libssh2好像一般都有，没有就下载丢到系统里，主要是安装ssh2。根据自己PHP的版本去下载，可以看下自己的php版本，以及是32位的还是64位的，32位的下载x86, 64位的下载x64

下载地址：[https://windows.php.net/downloads/pecl/releases/](https://windows.php.net/downloads/pecl/releases/)

php.ini中加入 extension=php_ssh2.dll ，完事。

## 2.swoole安装
参考官网：https://wiki.swoole.com/#/environment

## 3.phpseclib
官网：[https://phpseclib.com](https://phpseclib.com，composer)，composer安装即可：

```
composer require phpseclib/phpseclib:~3.0
```