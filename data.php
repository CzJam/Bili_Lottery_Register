<?php

require('settings.php');

$conn = mysqli_connect($serverName, $userName, $password, $dbName);
if (!$conn) {
    die("连接失败: " . mysqli_connect_error());
}

@$uid=$_POST['userid'];
if(strpos($uid,'UID:')==0){
    $uid=str_replace("UID:","",$uid);
}

//获取用户名
$nick=GetNick($uid);

//获取该用户前十条动态内容
@$dynaContent=GetWebData('https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/space?offset=&host_mid='.$uid);
$dynaContentStatus = json_decode($dynaContent);

//获取动态请求状态码
$dynaContentStatusCode=$dynaContentStatus->code; 

// 定义时间
$currentTimeStamp=strtotime('now');
$stopTimeStamp=strtotime($stopTime);
$luckyUser;
$luckyUserExist=false;

if ($currentTimeStamp<$stopTimeStamp){
    if ($nick==null){
        echo "<script>alert('该用户不存在，请核对UID！');window.location.href='index.html'</script>";
    }else{
        // 检测是否转发指定动态
        if(strpos($dynaContent,$keyWords)){
            $sqlQuery="SELECT * FROM userinfo WHERE uid = $uid";
            $getId;
            $getResu=GetRow($sqlQuery,'id');
             
            // 检测报名状态
            if($getResu<>'noData'){
                echo "<script>alert('$nick 已经报名了！\\n编号：$getResu');</script>";   

                }else{
                    $sysTime=date("m-d H:i:s");
                    $sqlInsert = "INSERT INTO userinfo(uid,nick,regtime) VALUES('{$uid}','{$nick}','{$sysTime}')";
                    $resultInsert = mysqli_query($conn, $sqlInsert);
                    
                    $getId=GetRow($sqlQuery,'id');
                    if($getId<>'noData'){
                        echo "<script>alert('你已成功报名！\\n\\n用户：$nick \\nUID：$uid\ \\n编号： $getId');</script>";
                        }
                    }
                   
        }
        else{
            if($dynaContentStatusCode=="0"){
                mysqli_close($conn);
                echo "<script>alert('$nick [UID:$uid] 未转发指定抽奖动态，请转发相关动态后再参与！【本次仅作测试，您可以转发测试后再删除动态】');window.location.href='index.html'</script>";
            }
            else{
                mysqli_close($conn);
                echo "<script>alert('动态获取失败：$dynaContentStatusCode \\n请稍后再试！');window.location.href='index.html'</script>";                
            }
        }
    }
}
else{
    echo "<script>alert('本期抽奖已结束！点击确定查看结果');</script>";
    
    $sqlQuery = "SELECT * FROM luckyuser";
    //抽奖结束后，继续访问一次将触发随机数进行开奖(详见函数内注释)
    if(mysqli_num_rows(mysqli_query($conn,$sqlQuery))==0){
        AddLuckyUser();
    }
        $sqlQuery = "SELECT nick FROM luckyuser";
        $luckyUserNick=GetRow($sqlQuery,'nick');
        
        $sqlQuery = "SELECT uid FROM luckyuser";
        $luckyUserUid=GetRow($sqlQuery,'uid');
        $luckyUserExist=true;
}


    
function GetNick($uid){
    $contentsNick = GetWebData('https://api.bilibili.com/x/space/top/arc?vmid='.$uid);
    $userNick = json_decode($contentsNick);
    $statusCode=$userNick->code;
    // 部分用户无法使用以上api解析，可解析整个网页获取nick;
    if($statusCode=="53016"){
        $contentsUserSpace = GetWebData('https://space.bilibili.com/'.$uid);
        $userNickLHS=explode('<title>',$contentsUserSpace);
        $userNickRHS=explode("的个人空间",$userNickLHS[1]);
        return $userNickRHS[0];
    }
    else{
        return $userNick->data->owner->name; 
    }
}


$sqlQuery = "SELECT * FROM userinfo";
$sum=mysqli_num_rows(mysqli_query($conn,$sqlQuery));



function GetRow($sqlQuery,$row){
    global $conn;
    $resultQuery = mysqli_query($conn, $sqlQuery);
    $getRow="noData";
    if($resultQuery){
        while($result = mysqli_fetch_array($resultQuery)){
        $getRow=$result[$row];
        }
    }
    
    return $getRow;
}

function AddLuckyUser(){
    global $conn;
    // 获取表中参与者的最大和最小编号
    $sqlQuery = "SELECT MAX(id) AS maxid FROM userinfo";
    $getMaxId=GetRow($sqlQuery,'maxid');
    $sqlQuery = "SELECT MIN(id) AS minid FROM userinfo";
    $getMinId=GetRow($sqlQuery,'minid');
    
    // 通过随机数抽取
    $luckyUserNum=mt_rand($getMinId,$getMaxId);
    
    // 得到中奖用户的昵称和UID并写入lucyuser表中
    $sqlQuery = "SELECT * FROM userinfo WHERE id=$luckyUserNum";
    $luckyUserNick=GetRow($sqlQuery,'nick');
    
    $sqlQuery = "SELECT * FROM userinfo WHERE id=$luckyUserNum";
    $luckyUserUid=GetRow($sqlQuery,'uid');
    
    // echo $getMaxId.'<br>';
    // echo $getMinId.'<br>';
    // echo $luckyUserNum.'<br>';
    // echo $luckyUserNick.'<br>';
    // echo $luckyUserUid.'<br>';

    
    $sqlInsert = "INSERT INTO luckyuser(uid,nick) VALUES('{$luckyUserUid}','{$luckyUserNick}')";
    mysqli_query($conn, $sqlInsert);
}

function GetWebData($url){
    global $uid;
    $curl = curl_init(); 
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($curl, CURLOPT_COOKIE, "DedeUserID=$uid"); 
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36'); 
    $webData = curl_exec($curl); 
    curl_close($curl);
    return $webData;   
}

 ?> 
     
     
     
<html>
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title id="name">报名列表</title>
    </head>
    
    <style>
        td{
            font-size:80%;
        }
        table{
            border-collapse: collapse;
            text-align:center;
            margin:20px auto;
        }
        p{
            text-align: center;
        }
    </style>
	<body style="background:#ecedf0;">
	<p style="margin-top:40px">自动开奖时间：<?php echo $stopTime ?></p>
	<?php 
	//如果已经开奖，则显示中奖者
	if($luckyUserExist){
	    
	    echo '<p style="font-size:20px;color:#00A004;"><b>';
	    echo '中奖用户：'.$luckyUserNick.' ['.$luckyUserUid.']';
	    echo '</b></p>';
	    
	}
	
	
	?>

    <table border="3" cellpadding="5">
              <caption style="font-size:18px;margin:5px">本期参与人数：<?php echo $sum ?></caption>
        <tr>
        <th>编号</th>
        <th>UID</th>
        <th>昵称</th>
        <th>报名时间</th>
        </tr>
    <?php 
    $result=mysqli_query($conn,"SELECT * FROM userinfo order by id asc");
    while($row = mysqli_fetch_array($result)){
        $nick=$row["nick"];
        if(strlen($nick)>12){
            $nick=substr($nick,0,12).'...';
        }
        echo "<tr>";
        echo "<td>".$row["id"]."<td>".$row["uid"]."</td><td>".$nick."</td><td>".$row["regtime"]."</td>";
        echo "</tr>";
    }
    mysqli_close($conn);
        echo "</table>";
        echo "</div>";
    
    ?> 
    	</body>
	
	</html>
  