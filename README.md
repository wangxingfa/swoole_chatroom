# swoole_chatroom
# 基于swoole >4.0 的聊天室程序
# WsServer.php是服务器类，在命令模式下运行于服务器端  eg:php WsServer.php
# client/room1.php 运行在客户端 模拟多个登录后的用户进行群聊，客户端的地址
# eg :http://localhost:9097/client/room1.php?user_id=136&user_name=mon
# eg:http://localhost:9097/client/room1.php?user_id=10&user_name=louis
# 暂时没有涉及到对对话信息的保存功能（没有将信息存储到数据库）
