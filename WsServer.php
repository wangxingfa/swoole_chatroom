<?php

/* 聊天室服务器端 */

class WsServer {

    private $host = '0.0.0.0';
    private $port = 9550;
    private $user_table;
    private $ws;
    private static $code = [
        'BIND' => 1, //绑定
        'CQUIT' => 9, //客户端退出
        'SQUIT' => 10, //服务器端强退
        'BROAD' => 2, //群发消息
        'ONLINE' => 4,
        'USERS' => 5, //检测用户列表
        'HEARTBEAT' => 6, //心跳检测
    ];
 
    private $heart_beat_max_times = 3; //最多三次心跳发送 
 
    public function run() {
        $this->start_service();
        $this->start_table();
        $this->start_handshake();
        $this->start_message();
        $this->end();
        
        $this->ws->start();
    }

    /* 初始化websocket */

    private function start_service() {
        $this->ws = new Swoole\Websocket\Server($this->host, $this->port);
        $this->ws->set([
            'worker_num' => 4
        ]);
    }

    /* 建立高性能共享内存 */
    /* 在高性能共享内存中绑定 user 和 fd */

    private function start_table() {
        /* 创建最多存储1024个用户的高性能共享内存表 并建立相关的字段 */
        $this->user_table = new Swoole\Table(1024);
        $this->user_table->column('fd', Swoole\Table::TYPE_INT);
        $this->user_table->column('user_id', Swoole\Table::TYPE_INT);
        $this->user_table->column('user_name', Swoole\Table::TYPE_STRING, 50);
        $this->user_table->column('status', Swoole\Table::TYPE_INT);
        $this->user_table->column('heartbeat', Swoole\Table::TYPE_INT);
        $this->user_table->create();
        /* 将共享内存表 存储到 WS的对象中，方便以后的调用 */
        $this->ws->user = $this->user_table;
        $this->ws->atomic=new Swoole\Atomic();
        
    }

    /* 建立好连接后的操作 */

    private function start_handshake() {
        $this->ws->on('open', function($websocket, $request) {
            //暂时不用作处理
        });
    }

    /* 服务中的消息的处理 */

    private function start_message() {
        $this->ws->on('message', function($websocket, $frame) {


            $data = $frame->data;
            $fd = $frame->fd;
            $data = json_decode($data, true);
            if ($data['code'] == self::$code["BIND"]) { //来了绑定的消息
                $user_id = $data["user_id"];
                $user_name = $data["user_name"];
                $ret = $this->ws->user->set($user_id, ["fd" => $fd, "user_id" => $user_id, "user_name" => "'" . $user_name . "'", "status" => 1]);

                if ($ret === false) { //数据绑定失败
                    echo "用户绑定失败\n";
                    //发送点对点消息。通知重新登录
                    $this->sendPeerMsg($websocket, $fd, self::$code['BIND'], $content = "用户绑定失败！", $error = 1);
                } else {
                    echo "用户绑定成功{$user_id}--{$user_name}\n";
                    //用户绑定成功后需要告知当前用户 当前有哪些用户进入了聊天室
                    $current_users = [];
                    foreach ($this->ws->user as $key => $item) {
                        array_push($current_users, ["user_id" => $item["user_id"], "user_name" => $item["user_name"], "status" => $item["status"]]);
                    }
                    if (!empty($current_users)) {
                        $this->sendPeerMsg($websocket, $fd, self::$code['USERS'], $content = $this->json($current_users), $error = 0);
                    }

                    $this->mybroadcast($websocket, ['code' => self::$code["ONLINE"], "user_id" => $user_id, "user_name" => $user_name, "content" => "用户:{$user_name}加入了聊天室"]);
                    //开始心跳检测 心跳检测采用timer
                    $this->ws->atomic->add();
                    $this->timer();
                }
            } elseif ($data['code'] == self::$code["BROAD"]) { //广播消息
                echo "广播信息";

                $this->mybroadcast($websocket, $data);
            } elseif ($data['code'] == self::$code["HEARTBEAT"]) { //收到心跳包
                echo "心跳包信息:{$data['user_id']}--{$data['user_name']}\n";
                if ($this->ws->user->exist($data['user_id'])) { //客户端发来心跳包的处理
                    $this->ws->user->set($data['user_id'], ["heartbeat" => 0, "status" => 1]);
                }
            }
        });
    }

    /* 客户端下线 */

    private function end() {
        /* 退出群聊的处理，发送退群信息到客户端（广播），将退群的用户在在高性能共享内存中的状态设置为0 */
        $this->ws->on("close", function($websocket, $fd) {
            echo "{$fd}退出了群聊";
            $current_user = [];
            foreach ($this->ws->user as $key => $item) {

                if ($item["fd"] == $fd) {
                    $current_user = $item;

                    break;
                }
            }
            $this->ws->user->set($current_user["user_id"], ['status' => 0]);  //不管结果
            $this->mybroadcast($websocket, ['code' => self::$code["CQUIT"], "content" => "用户:{$current_user['user_name']}已经退出了聊天"]);
            $this->ws->close($fd); //回收连接，回收连接资源
        });
    }

    /* 发送指定客户的消息 */

    private function sendPeerMsg($ws, $fd, $msgType, $content = "", $error = 0) {
        $send_data = [
            "code" => $msgType,
            "error" => $error,
            "content" => $content
        ];
        $ws->push($fd, $this->json($send_data));
    }

    /* 发送广播消息 循环发送每一个点的消息 */

    private function mybroadcast($ws, $data) {
        echo "群发消息:\n";
        foreach ($this->ws->user as $key => $item) {
            print_r($item);
            if ($item["status"] == 1) { //群发消息只发给在线用户，离线用户不需要发送信息
                $ws->push($item["fd"], $this->json($data));
            }
        }
    }

    private function heartBeat() {
        $this->mybroadcast($this->ws, ["code" => self::$code["HEARTBEAT"]]);

        foreach ($this->ws->user as $key => $item) {
            if (empty($item["heartbeat"])) {
                $this->ws->user->set($key, ["heartbeat" => 1]);
            } else {
                if ($item["heartbeat"] >= $this->heart_beat_max_times) { //心跳超过 则强制下线  将用户改成下线状态
                    $current_off_line_user = $item;
                    //广播通知下线
                    echo "用户:{$current_off_line_user['user_name']}已经被服务端下线\n";
                    $this->mybroadcast($this->ws, ["code" => self::$code["SQUIT"],"user_id"=>$current_off_line_user['user_id'], "content" => "用户:{$current_off_line_user['user_name']}已经被服务端下线"]);
                    $this->ws->user->set($key, ["status" => 0]);
                    $this->ws->close($item["fd"]); //取消链接 回收连接资源
                } else {
                    $this->ws->user->set($key, ["heartbeat" => $item["heartbeat"] + 1]);
                }
            }
        }
    }

    private function json($array) {
        return json_encode($array, JSON_UNESCAPED_UNICODE);
    }

    private function timer() {
        //心跳检测 每一分钟进行一次心跳检测 ，对于三次检测都失败的用户，服务端强制下线 将对应的用户在高性能共享内存表中删除
        var_dump("无锁计数器：".$this->ws->atomic->get());
        if ($this->ws->atomic->get()==1) {
           

            $obj = $this;
            # 没30秒执行一次心跳检测
            swoole_timer_tick(30000, function($timer_id) use (&$obj) {
                echo "执行心跳\n";
                $obj->heartBeat();
            });
        }
    }

}

$server = new WsServer();
$server->run();
