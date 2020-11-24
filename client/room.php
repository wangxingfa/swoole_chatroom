<?php
session_start();

# 模拟登录
if (!empty($_POST["nick"])) {
    $data = [
        "nick" => $_POST["nick"],
        "id" => uniqid()
    ];
    $_SESSION["user"] = $data;
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
} elseif (!empty($_POST["out"])) {
    unset($_SESSION["user"]);
    exit;
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <title>聊天室</title>
        <style>
            html,body{margin:0;padding:0;font-size:13px}
            .left{width: 20%;height: 600px;border: 1px solid #ddd;float: left;}
            .right{width: 59.7%;height: 400px;border: 1px solid #ddd;border-left: 0px;float: left;overflow: auto;}
            .bottom{width: 79.7%;height: 199px;border: 1px solid #ddd;border-left: 0px;border-top: 0px;float: left;}
            #content{width: 99.5%;height: 165px;}
            .blue{color:blue}
            .red{color:red}
            .div_left{width:100%;float:left}
            .div_right{width:100%;float:left;text-align: right;}
            .div_centent{width:100%;float:left;text-align: center;}
            #USER{width:100%;height: 40px;line-height: 40px;border-bottom: 1px solid #ddd;float:left}
            #error{width:20%;height:400px;float: left;overflow: auto;}
        </style>
        <link rel="stylesheet" type="text/css" href="./css/user.css" />
        <script src="./js/jquery.min.js"></script>
        <script language="javascript" src="./js/jquery.easing.min.js"></script>
        <script language="javascript" src="./js/custom.js"></script>
    </head>

    <body>
        <!--登录导航-->
        <div id="header">
            <div class="common">
                <div class="login fr">
                    <ul>
                        <li class="openlogin"><a href="" onclick="return false;">登录</a></li>
                        <li class="reg" style="display:none"><a href="" onclick="return false;">退出</a></li>
                    </ul>
                </div>
                <div class="clear"></div>
            </div>
        </div>
        <!--模拟登录弹窗-->
        <div class="loginmask"></div>
        <div id="loginalert">
            <div class="pd20 loginpd">
                <h3>
                    <i class="closealert fr"></i>
                    <div class="clear"></div>
                </h3>
                <div class="loginwrap">
                    <div class="loginh">
                        <div class="fl">模拟会员登录</div>
                        <div class="clear"></div>
                    </div>
                    <div class="clear"></div>
                    <div class="logininput">
                        <input type="text" class="loginusername" placeholder="随便输入一个昵称" />
                    </div>
                    <div class="clear"></div>
                    <div class="loginbtn">
                        <div class="loginsubmit fl">
                            <input type="button" id="login_form" value="登录" />
                            <div class="loginsubmiting">
                                <div class="loginsubmiting_inner"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!--以下为聊天室窗口-->
    <div id="USER"></div>
    <div class="left">
        <ul>
            <li>用户列表：</li>
        </ul>
    </div>
    <div class="right"></div>
    <div id="error"></div>
    <div class="bottom">
        <textarea id="content"></textarea>
        <button type="button" id="submit">发送消息</button>
    </div>
</body>
</html>
<script>
    var USER_ID = 0;
    var USER_NICK = "";
    var lockReconnect = false; //正常情况下关闭心跳重连
    var wsServer = "ws://192.168.1.246:9505";
    var websocket;
    var time;
<?php
if (isset($_SESSION["user"])) {
    $user = $_SESSION["user"];
    echo "USER_ID='" . $user["id"] . "';";
    echo "USER_NICK='" . $user["nick"] . "';";
    ?>
        createWebsocket(wsServer);
        $(".openlogin").hide();
        $(".login .reg").show();
        $("#USER").html("您的USER_ID为：" + USER_ID + ",您的昵称为:" + USER_NICK);
<?php } ?>
    function createWebsocket(wsServer) {
        try {
            websocket = new WebSocket(wsServer);
            init();
        } catch (e) {

        }
    }

    function init() {
        websocket.onclose = function (evt) {
            $("#error").append("<p class='red'>Socket断开了...正在试图重新连接.....</p>");
            // reconnect(wsServer);
        }
        websocket.onerror = function (evt) {
            $("#error").append("<p class='red'>Socket连接发生错误...正在试图重新连接.....</p>");
            // reconnect(wsServer);
        }
        websocket.onopen = function (evt) {
            $("#error").append("<p class='blue'>握手成功，打开socket连接了....</p>");
            var data = {
                'code': 1,
                'user_id': USER_ID,
                'user_nick': USER_NICK
            };
            data = JSON.stringify(data);
            console.log(data);
            websocket.send(data);

        }

        var message = "";
        var flag = true;

        //和服务端进行通信
        websocket.onmessage = function (evt) {
            // 心跳开始
            // heartCheck.start();
            var obj = JSON.parse(evt.data);
            //如果是不存在用户，加入到聊天室的用户列表中
            if ($("#" + obj.user_id).length > 0) {

            } else if (obj.user_id != undefined && obj.user_id != null && obj.user_id != '') {
                $('.left ul').append('<li id="' + obj.user_id + '">' + obj.user_nick + ' <span class="blue">(在线)</span></li>');
            }
            //登录广播
            if (obj.code == 1) { //登录广播， 如果此用户是下线状态，需要改成上线
                $("#" + obj.user_id + " span").removeClass("red");
                $("#" + obj.user_id + " span").addClass("blue");
                $("#" + obj.user_id + " span").html("在线");
                //显示登录的广播通知信息
                $(".right").append("<div class='div_centent'>" + obj.content + "</div>");
            } else if (obj.code == 2 || obj.code == 6) {  //自己下线的和心跳检测到下线的消息
                if ($("#" + obj.user_id).length > 0) { //如果当前用户还在聊天列表中 则需要显示当前用户已经下线
                    $("#" + obj.user_id + " span").removeClass("blue");
                    $("#" + obj.user_id + " span").addClass("red");
                    $("#" + obj.user_id + " span").html("(下线)");
                    $(".right").append("<div class='div_centent'>" + obj.content + "</div>"); //只有在列表中的用户才需要提示下线信息
                }

            } else if (obj.code == 3) { //聊天消息广播
                $(".right").append("<div class='div_left'>" + obj.user_nick + ":" + obj.content + "</div>");
                $(".right").scrollTop($(".right")[0].scrollHeight);
            } else if (obj.code == 4) { //心跳的广播 不做任何处理
                return false;
            } else if (obj.code == 5) { //服务端发起了强制心跳检测 返回客户端的心跳包
                $("#error").append("<p class='red'>服务器端发起了一次强制心跳检测...</p>");
                var data = {
                    'code': 4,
                    'user_id': USER_ID,
                    'user_nick': USER_NICK
                };
                data = JSON.stringify(data);
                websocket.send(data);

            } else if (obj.code == 7) { //显示当前的群里的其他成员
                var myArray_objects = JSON.parse(obj.content);
                console.log(JSON.parse(obj.content));
                myArray_objects.forEach(function (item) {
                    console.log(item.user_id);
                    console.log(item.user_nick);
                    console.log(item.status);
                    if ($("#" + item.user_id).length > 0) { //存在 修改状态
                        if (item.status == 0) { //不在线
                            $("#" + item.user_id + " span").removeClass("blue");
                            $("#" + item.user_id + " span").addClass("red");
                            $("#" + item.user_id + " span").html("(下线)");
                        } else {
                            $("#" + item.user_id + " span").removeClass("red");
                            $("#" + item.user_id + " span").addClass("blue");
                            $("#" + item.user_id + " span").html("(在线)");
                        }
                    } else { //不存在
                        if (item.status == 0) {
                            $('.left ul').append('<li id="' + obj.user_id + '">' + item.user_nick + ' <span class="red">(下线)</span></li>');
                        } else {
                            $('.left ul').append('<li id="' + obj.user_id + '">' + item.user_nick + ' <span class="blue">(在线)</span></li>');
                        }
                    }



                });


            }



        }

    }






    //点击发送信息按钮
    $("#submit").click(function () {

        if (USER_ID == "") {
            $(".openlogin").click();
            return;
        }
        var content = $("#content").val();
        $(".right").append("<div class='div_right'>" + content + ":" + USER_NICK + "</div>");
        var data = {
            'code': 3,
            'user_id': USER_ID,
            'user_nick': USER_NICK,
            'content': content
        };
        data = JSON.stringify(data);
        console.log(data);
        websocket.send(data);
        $("#content").val('');
        $(".right").scrollTop($(".right")[0].scrollHeight);

    });

    //点击发送模拟登录请求
    $("#login_form").click(function () {
        var nick = $(".loginusername").val();
        $.ajax({
            type: 'post',
            data: {'nick': nick},
            url: '',
            success: function (data) {
     
                window.location.reload();
                
            }
        });
    });
    //点击退出登录请求
    $(".login .reg a").click(function () {
        $.ajax({
            type: 'post',
            data: {'out':"out"},
            url: '',
            success: function (data) {
                $(".openlogin").show();
                $(".login .reg").hide();
                $("#USER").html("");
                $("#" + USER_ID + ' span').removeClass('blue');
                $("#" + USER_ID + ' span').addClass('red');
                $("#" + USER_ID + ' span').html('(下线)');
                USER_ID = '';
                USER_NICE = '';
                websocket.close();
                window.location.reload();
            }
        });
    });
</script>

