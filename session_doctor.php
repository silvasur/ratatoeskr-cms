<?php
session_start();
if(isset($_POST['session']))
    $_SESSION = json_decode($_POST['session']);
$s_json = json_encode($_SESSION);
?>
<html>
<head>
    <title>session_doctor.php</title>
    <style type="text/css">* { font-family: monospace; }</style>
</head>
<body>
    <h1>session_doctor</h1>
    <form action="session_doctor.php" method="post">
        <textarea name="session" style="width: 80em; height: 24em;"><?=$s_json?></textarea>
        <input type="submit" />
    </post>
</body>
</html>
