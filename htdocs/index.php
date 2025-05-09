<?php

$host="mysql.webstone.svc.cluster.local";
// $host="chunkeng.kr";
$user="root";
$pswd="hohyun0501";
$dbname="webstone";
// $dbname="new_chunkeng";
$db_conn = mysqli_connect($host, $user, $pswd, $dbname);

if (!db_conn) {
  $error = mysqli_connect_error();
  $errno = mysqli_connect_errno();
  print "$errno: $error\n";
  exit(); 
}

$query = "select version() as version";
$result = mysqli_query($db_conn, $query);

if ( $result ) {
	echo "Number of rows: ".mysqli_num_rows($result)."<br />";
	while ($row = mysqli_fetch_assoc($result)) {
		printf ("%s", $row["version"]);
	}

	mysqli_free_result($result);
    
} else {
	echo "Error : ".mysqli_error($db_conn);
}

mysqli_close($db_conn);

phpinfo(); ?>
