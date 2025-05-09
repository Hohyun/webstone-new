<?php

error_reporting(E_ALL ^ E_NOTICE);

/*
// $host="mysql.webstone.svc.cluster.local";
$host="chunkeng.kr";
$user="root";
$pswd="hohyun0501";
// $dbname="webstone";
$dbname="new_chunkeng";
$db_conn = mysqli_connect($host, $user, $pswd, $dbname);

if (!db_conn) {
  $error = mysqli_connect_error();
  $errno = mysqli_connect_errno();
  print "$errno: $error\n";
  exit(); 
}

$sql = "select version() as version";
$result = mysqli_query($db_conn, $sql);

if ( $result ) {
	echo "Number of rows: ".mysqli_num_rows($result)."<br />";
	while ($row = mysqli_fetch_assoc($result)) {
		printf ("%s", $row["version"]);
	}

	mysqli_free_result($result);
    
} else {
	echo "Error : ".mysqli_error($db_conn);
}

$sql = "select * from ceng_member limit 10";
$result = mysqli_query($db_conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
  printf("%s : %s", $row["m_id"], $row["m_name"]);
}

mysqli_close($db_conn);
*/

include('./lib.common.php');

$sql = "select * from ceng_member limit 10";
$result = $db->query($sql);

while($row = $db->fetch($result)) {
  printf("%s : %s<br />", $row["m_id"], $row["m_name"]);
}


$output = null;
$retval = null;
exec("/usr/bin/ffmpeg -version", $output, $retval);

echo "<br />Returned with status $retval and output:<br />";
print_r($output);


phpinfo(); ?>
