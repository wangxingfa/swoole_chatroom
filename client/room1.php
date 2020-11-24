<?php
if (empty($_GET) || empty($_GET["user_id"]) || empty("user_name")) {
    echo "非法操作，你是没有登录的用户，不能进入聊天室";
    exit;
}
$user_id = addslashes($_GET["user_id"]);
$user_name = addslashes($_GET["user_name"]);
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <title>Louis聊天室--swoole websocket聊天室</title>
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
                        <li class="openlogin"><a>点击退群</a></li>

                    </ul>
                </div>
                <div class="clear"></div>
            </div>
        </div>
    </div>
    <!--以下为聊天室窗口-->
    <div id="USER">用户ID：<?php echo $user_id ?>,用户名：<?php echo $user_name; ?>，你好，欢迎进入聊天室！</div>
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
    var USER_ID = <?php echo $user_id; ?>;
    var USER_NAME = "<?php echo $user_name; ?>";
    var lockReconnect = false; //正常情况下关闭心跳重连
    var wsServer = "ws://192.168.1.246:9550";
    var websocket = null;
    var time;
    start_websocket(wsServer);
    function start_websocket(wsServer) {
        try {
            websocket = new WebSocket(wsServer);
            init();
        } catch (e) {

        }
    }

    function init() {
        websocket.onclose = function (evt) {
            $("#error").append("<p class='red'>Socket断开了...正在试图重新连接.....</p>");
            //reconnect(wsServer);
        }
        websocket.onerror = function (evt) {
            console.log("onerror");
            console.log(evt);
            $("#error").append("<p class='red'>Socket连接发生错误...正在试图重新连接.....</p>");
            //reconnect(wsServer);
        }
        /* 连接成功后就需要在服务端对用户进行绑定*/
        websocket.onopen = function (evt) {
            $("#error").append("<p class='blue'>握手成功，websocket连接成功！</p>");
            var data = {
                'code': 1, //绑定的标志位
                'user_id': USER_ID,
                'user_name': USER_NAME
            };
            data = JSON.stringify(data);
            console.log("绑定消息：" + data);
            websocket.send(data);
        }

        websocket.onmessage = function (evt) {
            var obj = JSON.parse(evt.data); //转化json对象
            console.log(obj);
            if (obj.code == 1) { //绑定的消息，查看是否绑定成功
                console.log(obj);
                if (obj.error == 1) { //出错信息
                    alert(obj.content);
                } else {
                    console.log(obj.content);
                }
            } else if (obj.code == 2) { //群发的消息
                if (obj.user_id == USER_ID) { //自己发的消息，显示在右边
                    $(".right").append("<div class='div_right'>" + obj.content + ":" + obj.user_name + "</div>");
                } else { //别人发的消息，显示在左边
                    $(".right").append("<div class='div_left'>" + obj.user_name + ":" + obj.content + "</div>");
                }
            } else if (obj.code == 4) { //用户加入聊天室的处理
                if (obj.user_id != USER_ID) {
                    $(".right").append("<div class='div_centent'>" + obj.content + "</div>"); //上线提示信息
                    if ($("#" + obj.user_id).length == 0) {
                        $('.left ul').append('<li id="' + obj.user_id + '">' + obj.user_name + ' <span class="blue">(在线)</span></li>');
                    } else {
                        $("#" + obj.user_id + " span").removeClass("red");
                        $("#" + obj.user_id + " span").addClass("blue");
                        $("#" + obj.user_id + " span").html("(在线)");
                    }
                }
            } else if (obj.code == 5) { //当前加入聊天室的用户
                var current_users = JSON.parse(obj.content);
                current_users.forEach(function (item) {
                    if (item.user_id == USER_ID) {
                        return;
                    }
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
                            $('.left ul').append('<li id="' + item.user_id + '">' + item.user_name + ' <span class="red">(下线)</span></li>');
                        } else {
                            $('.left ul').append('<li id="' + item.user_id + '">' + item.user_name + ' <span class="blue">(在线)</span></li>');
                        }
                    }
                });

            } else if (obj.code == 6) { //心跳检测 收到心跳包 给服务器回应 表示连接正常

                var heart_data = {
                    'code': 6, //心跳包
                    'user_id': USER_ID,
                    'user_name': USER_NAME
                };
                data = JSON.stringify(heart_data);
                console.log("回复服务器端心跳发送：" + data);
                websocket.send(data);

            } else if(obj.code == 7){ // 系统推送的消息
                $("#error").append("<p class='red'>系统消息:"+obj.content +"</p>");
            
            }else if (obj.code == 10) { //服务器端的强制下线

                if ($("#" + obj.user_id).length > 0) { //如果当前用户还在聊天列表中 则需要显示当前用户已经下线
                    $("#" + obj.user_id + " span").removeClass("blue");
                    $("#" + obj.user_id + " span").addClass("red");
                    $("#" + obj.user_id + " span").html("(下线)");
                    $(".right").append("<div class='div_centent'>" + obj.content + "</div>"); //只有在列表中的用户才需要提示下线信息
                }

            }
        }
    }
    /*客户端群发消息*/
    $("#submit").click(function () {

        var content = $.trim($("#content").val());
        if (content == '') {
            alert("没有输入内容，无法完成数据发送");
            return;
        }

        var data = {
            'code': 2, //群发消息标志位
            'user_id': USER_ID,
            'user_name': USER_NAME,
            'content': content
        };
        data = JSON.stringify(data);
        console.log("群发消息:" + data);
        console.log(websocket);
        websocket.send(data);
        $("#content").val("");

    });



    function reconnect(url) {
        if (lockReconnect) {
            return;
        }
        lockReconnect = true;
        // 没连接上会一直重连，设置心跳延迟避免请求过多
        time && clearTimeout(time);
        time = setTimeout(function () {
            start_websocket(url);
            lockReconnect = false;
        }, 5000);
    }








</script>

