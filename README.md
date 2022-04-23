
# webman push 插件（多进程）

## 简介

> [hsk99/webman-push](https://github.com/hsk99/webman-push "hsk99/webman-push") 继承于 [webman/push](https://github.com/webman-php/push "webman/push") 开发，在原有功能基础上添加了多协议合并、多进程运行。


## 安装

` composer require webman-push `


## 使用

> 引入javascript客户端

` <script src="/plugin/hsk99/push/push.js"> </script> `

> 客户端使用(公有频道)

```
// 建立连接
var connection = new Push({
    url: 'ws://127.0.0.1:8803', // Push服务地址
    app_key: '<app_key，在config/plugin/hsk99/push/app.php里获取>',
    auth: '/plugin/hsk99/push/auth' // 订阅鉴权(仅限于私有频道)
});


// 假设用户uid为1
var uid = 1;
// 浏览器监听user-1频道的消息，也就是用户uid为1的用户消息
var user_channel = connection.subscribe('user-' + uid);


// 当user-1频道有message事件的消息时
user_channel.on('message', function(data) {
    // data里是消息内容
    console.log(data);
});
```

> 详细使用请查看：https://www.workerman.net/plugin/2