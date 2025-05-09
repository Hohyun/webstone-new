<?
@session_start(); 

extract($_GET);
extract($_POST);
extract($_SERVER);
extract($_COOKIE);

//오늘
date_default_timezone_set("Asia/Seoul");
$ceng_today   = Date("Y-m-d",time());
$ceng_lastday = Date("Y-m-d",time()-(60*60*24*14));
// DB 정보
$d_user = "root";
$d_db   = "new_chunkeng";
// $d_pass = "jino0070";
$d_pass = "hohyun0501";
//$d_host = "localhost";
$d_host = "chunkeng.kr";
// $d_host = "aclass.chunk.kr";

$db= new Db(); 
$db->db_host = $d_host;
$db->db_user = $d_user;
$db->db_pass = $d_pass;
$db->connect($d_db);


//$_ENUM['send_state'] = array("0"=>"O","1"=>"W","2"=>"E");
$enc_key = "chunk";

function ss_encode($string) {
  global $enc_key;
  $key = $enc_key;
  $result = '';
  for($i=0; $i<strlen($string); $i++) {
    $char = substr($string, $i, 1);
    $keychar = substr($key, ($i % strlen($key))-1, 1);
    $char = chr(ord($char)+ord($keychar));
    $result.=$char;
  }

  return base64_encode($result);
}

function ss_decode($string) {
  global $enc_key;
  $key = $enc_key;
  $result = '';
  $string = base64_decode($string);

  for($i=0; $i<strlen($string); $i++) {
    $char = substr($string, $i, 1);
    $keychar = substr($key, ($i % strlen($key))-1, 1);
    $char = chr(ord($char)-ord($keychar));
    $result.=$char;
  }

  return $result;
}

function get_microtime($old,$new)
{
	$old = explode(" ", $old);
	$new = explode(" ", $new);
	$time[msec] = $new[0] - $old[0];
	$time[sec]  = $new[1] - $old[1];
	if($time[msec] < 0) {
		$time[msec] = 1.0 + $time[msec];
		$time[sec]--;
	}
	$ret = $time[sec] + $time[msec];
	return $ret;
}

function array_notnull($arr)
{
	if (!is_array($arr)) return;
	foreach ($arr as $k=>$v) if ($v=="") unset($arr[$k]);
	return $arr;
}

function getVars($except='', $request='')
{
	if ($except) $exc = explode(",",$except);
	if ( is_array( $request ) == false ) $request = $_REQUEST;
	foreach ($request as $k=>$v){
		if (isset($_COOKIE[$k])) continue; # 쿠키 제외(..sunny)
		if (!@in_array($k,$exc) && $v!=''){
			if (!is_array($v)) $ret[] = "$k=".urlencode(stripslashes($v));
			else {
				$tmp = getVarsSub($k,$v);
				if ($tmp) $ret[] = $tmp;
			}
		}
	}
	if ($ret) return implode("&",$ret);
}

function getVarsSub($key,$value)
{
	foreach ($value as $k2=>$v2){
		if ($v2!='') $ret2[] = $key."[".$k2."]=".urlencode(stripslashes($v2));
	}
	if ($ret2) return implode("&",$ret2);
}

### 문자열 자르기 함수
function strcut($str,$len)
{
	if (strlen($str) > $len){
		$len = $len-2;
		for ($pos=$len;$pos>0 && ord($str[$pos-1])>=127;$pos--);
		if (($len-$pos)%2 == 0) $str = substr($str, 0, $len) . "..";
		else $str = substr($str, 0, $len+1) . "..";
	}
	return $str;
}

function strcut2($str, $size, $checkmb=false, $suffix="...")
{
	$size = $size-18;
	$substr = substr( $str, 0, $size * 2 );
	$multi_size = preg_match_all( '/[\\x80-\\xff]/', $substr, $multi_chars );

	if ( $multi_size > 0 )
		$size = $size + intval( $multi_size / 3 ) - 1;

	if ( strlen( $str ) > $size )
	{
		$str = substr( $str, 0, $size );
		$str = preg_replace( '/(([\\x80-\\xff]{3})*?)([\\x80-\\xff]{0,2})$/', '$1', $str );
		$str .= '...';
	}

	return $str;

}

function strcut3($str, $len='', $checkmb=false, $tail='...') 
{ 
	$start=0;
    if($len===0) return; 
    $totalLen=strlen($str); 
    $epos=($len>0)?(int)($start+$len):(int)($totalLen+$len); 

    $s=$start-9; 
    if($s<9) $s=0; 
    while($s<$start) { 
        if(ord($str[$s])>127) $s++; 
        $s++; 
    } 

    $e=($epos-9); 
    if($e<9) $e=0; 
    $str2=substr($str,$s,$epos); 
    while($e<$epos) { 
        if(ord($str2[$e])>127) $e++; 
        $e++; 
    } 

    $str=substr($str,$s,$e); 
    if($totalLen>$epos) $str.=$tail; 
    return $str; 

}


//페이징

class Page{

	var $page	= array();
	/*
	$page[now]		현재 페이지
	$page[num]		한 페이지에 출력되는 레코드 개수
	$page[total]	전체 페이지 수
	$page[url]		페이지 링크 URL
	$page[navi]		페이지 네비게이션
	$page[prev]		이전페이지 아이콘
	$page[next]		다음페이지 아이콘
	*/
	var $recode	= array();
	/*
	$recode[start]	시작 레코드 번호
	$recode[total]	전체 레코드 수 (전체 글수)
	*/

	var $vars		= array();
	var $field		= "*";			// 가져올 필드
	var $cntQuery	= "";			// 전체 레코드 개수 가져올 쿼리문 (조인시 성능 향상을 위해)
	var $nolimit	= false;		// 참일 경우 전체 데이타 추출
	var $idx		= 0;			// 해당페이지 첫번쩨 레코드 번호값

	var $foo = null;

	function Page($page=1,$page_num=20)
	{
		$this->vars['page']= getVars('no,chk,page,password,x,y');
		$this->page['now'] = ($page<1) ? 1 : $page;
		$this->page['num'] = ($page_num<1) ? 20 : $page_num;
		$this->page['url'] = $_SERVER['PHP_SELF'];
		$this->recode['start'] = ($this->page['now']-1) * $this->page['num'];
		$this->page['prev'] = "◀";
		$this->page['next'] = "▶";
	}

	function getTotal()
	{
		if (!$this->cntQuery){
			//$cnt = (!preg_match("/distinct/i",$this->field)) ? "count(*)" : "count($this->field)";

			if(!preg_match("/distinct/i",$this->field)) $cnt = "count(*)";
			else {
				$temp = explode( ",", $this->field );
				$cnt = "count($temp[0])";
			}
			$this->cntQuery = "select $cnt from $this->db_table $this->where";
		}
		list($this->recode['total']) = $GLOBALS['db']->fetch($this->cntQuery);
	}

	function setTotal()
	{
		$limited = ($this->recode['start']+$this->page['num']<$this->recode['total']) ? $this->page['num'] : $this->recode['total'] - $this->recode['start'];
		if (!$this->nolimit) $this->limit = "limit {$this->recode['start']},$limited";
		$this->query = "select $this->field from $this->db_table $this->where $this->tmpQry $this->orderby $this->limit";
		$this->idx = $this->recode['total'] - $this->recode['start'];
	}

	function setQuery($db_table,$where='',$orderby='',$tmp='')
	{
		$this->db_table = $db_table;
		$this->tmpQry = $tmp;
		if ($where) $this->where = "where ".implode(" and ",$where);
		if (trim($orderby)) $this->orderby = "order by ".$orderby;
		if (!isset($this->recode['total'])) $this->getTotal();
	}

	function exec()
	{
		if ($this->foo === null) $this->setTotal();

		$this->page['total']	= ceil($this->recode['total']/$this->page['num']);
		if ($this->page['total'] && $this->page['now']>$this->page['total']) $this->page['now'] = $this->page['total'];
		$page['start']		= (ceil($this->page['now']/10)-1)*10;

		$navi .= "<table border='0' align='center' cellpadding='0' cellspacing='0'>
					<tr> ";

		if($this->page['now']>10){
			$navi .= "
			<td width='20'><a href=\"javascript:goPage(1)\" class=navi><img src='/images/common/numFirst.gif' /></a></td>
			<td width='44'><a href=\"javascript:goPage($page[start])\" class=navi><img src='/images/common/numPrev.gif' /></a></td>
			";
		}

		$navi .= "<td width='10'>&nbsp;</td>";

		$tc = 1;
		$i = 0;
		while($i+$page['start']<$this->page['total']&&$i<10){
			$i++;
			$page['move'] = $i+$page['start'];
			if ($tc>1){
				$navi .= "<td width='6'>&nbsp;</td>";
			}
			if ($this->page[now]==$page[move]){
				$navi .= "<td align='center' background='/images/common/numBg1.gif' style='padding:1px 8px 0px 7px; border:1px solid #3389a6'><b><font color='#FFFFFF'>$page[move]</font></b></td>";
			}else{
				$navi .= "<td align='center' style='padding:1px 8px 0px 7px; border:1px solid #d2d2d2; cursor:pointer' onClick='javascript:goPage($page[move])' class=navi>$page[move]</td>";
			}
			$tc++;
		}

		$navi .= "<td width='10'>&nbsp;</td>";

		if($this->page['total']>$page['move']){
			$page[next] = $page[move]+1;
			$navi .= "
			<td width='44'><a href=\"javascript:goPage($page[next])\" class=navi><img src='/images/common/numNext.gif' /></a></td>
			<td width='20'><a href=\"javascript:goPage({$this->page[total]})\" class=navi><img src='/images/common/numEnd.gif' /></a></td>
			";
		}

		$navi .= "</tr></table>";

		if ($this->recode['total'] && !$this->nolimit) $this->page[navi] = &$navi;
	}

	function getNavi($total) {

		$this->recode[total] = $total;

		$this->foo = true;
		$this->exec();

		return $this->page['navi'];

	}

}


class Page2{

	var $page	= array();
	/*
	$page[now]		현재 페이지
	$page[num]		한 페이지에 출력되는 레코드 개수
	$page[total]	전체 페이지 수
	$page[url]		페이지 링크 URL
	$page[navi]		페이지 네비게이션
	$page[prev]		이전페이지 아이콘
	$page[next]		다음페이지 아이콘
	*/
	var $recode	= array();
	/*
	$recode[start]	시작 레코드 번호
	$recode[total]	전체 레코드 수 (전체 글수)
	*/

	var $vars		= array();
	var $field		= "*";			// 가져올 필드
	var $cntQuery	= "";			// 전체 레코드 개수 가져올 쿼리문 (조인시 성능 향상을 위해)
	var $nolimit	= false;		// 참일 경우 전체 데이타 추출
	var $idx		= 0;			// 해당페이지 첫번쩨 레코드 번호값

	var $foo = null;

	function Page2($page=1,$page_num=20)
	{
		$this->vars[page]= getVars('no,chk,page,password,x,y');
		$this->page[now] = ($page<1) ? 1 : $page;
		$this->page[num] = ($page_num<1) ? 20 : $page_num;
		$this->page[url] = $_SERVER['PHP_SELF'];
		$this->recode[start] = ($this->page[now]-1) * $this->page[num];
		$this->page[prev] = "◀";
		$this->page[next] = "▶";
	}

	function getTotal()
	{
		if (!$this->cntQuery){
			//$cnt = (!preg_match("/distinct/i",$this->field)) ? "count(*)" : "count($this->field)";

			if(!preg_match("/distinct/i",$this->field)) $cnt = "count(*)";
			else {
				$temp = explode( ",", $this->field );
				$cnt = "count($temp[0])";
			}
			$this->cntQuery = "select $cnt from $this->db_table $this->where";
		}
		list($this->recode[total]) = $GLOBALS[db]->fetch($this->cntQuery);
	}

	function setTotal()
	{
		$limited = ($this->recode[start]+$this->page[num]<$this->recode[total]) ? $this->page[num] : $this->recode[total] - $this->recode[start];
		if (!$this->nolimit) $this->limit = "limit {$this->recode[start]},$limited";
		$this->query = "select $this->field from $this->db_table $this->where $this->tmpQry $this->orderby $this->limit";
		$this->idx = $this->recode[total] - $this->recode[start];
	}

	function setQuery($db_table,$where='',$orderby='',$tmp='')
	{
		$this->db_table = $db_table;
		$this->tmpQry = $tmp;
		if ($where) $this->where = "where ".implode(" and ",$where);
		if (trim($orderby)) $this->orderby = "order by ".$orderby;
		if (!isset($this->recode[total])) $this->getTotal();
	}

	function exec()
	{
		if ($this->foo === null) $this->setTotal();

		$this->page[total]	= @ceil($this->recode[total]/$this->page[num]);
		if ($this->page[total] && $this->page[now]>$this->page[total]) $this->page[now] = $this->page[total];
		$page[start]		= (ceil($this->page[now]/10)-1)*10;

		$navi .= "<table border='0' align='center' cellpadding='0' cellspacing='0'>
					<tr> ";

		if($this->page[now]>10){
			$navi .= "
			<td width='20'><a href=\"javascript:goPage2(1)\" class=navi><img src='/images/common/numFirst.gif' /></a></td>
			<td width='44'><a href=\"javascript:goPage2($page[start])\" class=navi><img src='/images/common/numPrev.gif' /></a></td>
			";
		}

		$navi .= "<td width='10'>&nbsp;</td>";

		$tc = 1;
		while($i+$page[start]<$this->page[total]&&$i<10){
			$i++;
			$page[move] = $i+$page[start];
			if ($tc>1){
				$navi .= "<td width='6'>&nbsp;</td>";
			}
			if ($this->page[now]==$page[move]){
				$navi .= "<td align='center' background='/images/common/numBg1.gif' style='padding:1px 8px 0px 7px; border:1px solid #3389a6'><b><font color='#FFFFFF'>$page[move]</font></b></td>";
			}else{
				$navi .= "<td align='center' style='padding:1px 8px 0px 7px; border:1px solid #d2d2d2; cursor:pointer' onClick='javascript:goPage2($page[move])' class=navi>$page[move]</td>";
			}
			$tc++;
		}

		$navi .= "<td width='10'>&nbsp;</td>";

		if($this->page[total]>$page[move]){
			$page[next] = $page[move]+1;
			$navi .= "
			<td width='44'><a href=\"javascript:goPage2($page[next])\" class=navi><img src='/images/common/numNext.gif' /></a></td>
			<td width='20'><a href=\"javascript:goPage2({$this->page[total]})\" class=navi><img src='/images/common/numEnd.gif' /></a></td>
			";
		}

		$navi .= "</tr></table>";

		if ($this->recode[total] && !$this->nolimit) $this->page[navi] = &$navi;
	}

	function getNavi($total) {

		$this->recode[total] = $total;

		$this->foo = true;
		$this->exec();

		return $this->page['navi'];

	}

}



class Page3{

	var $page	= array();
	/*
	$page[now]		현재 페이지
	$page[num]		한 페이지에 출력되는 레코드 개수
	$page[total]	전체 페이지 수
	$page[url]		페이지 링크 URL
	$page[navi]		페이지 네비게이션
	$page[prev]		이전페이지 아이콘
	$page[next]		다음페이지 아이콘
	*/
	var $recode	= array();
	/*
	$recode[start]	시작 레코드 번호
	$recode[total]	전체 레코드 수 (전체 글수)
	*/

	var $vars		= array();
	var $field		= "*";			// 가져올 필드
	var $cntQuery	= "";			// 전체 레코드 개수 가져올 쿼리문 (조인시 성능 향상을 위해)
	var $nolimit	= false;		// 참일 경우 전체 데이타 추출
	var $idx		= 0;			// 해당페이지 첫번쩨 레코드 번호값

	var $foo = null;

	function Page3($page=1,$page_num=20)
	{
		$this->vars[page]= getVars('no,chk,page,password,x,y');
		$this->page[now] = ($page<1) ? 1 : $page;
		$this->page[num] = ($page_num<1) ? 20 : $page_num;
		$this->page[url] = $_SERVER['PHP_SELF'];
		$this->recode[start] = ($this->page[now]-1) * $this->page[num];
		$this->page[prev] = "◀";
		$this->page[next] = "▶";
	}

	function getTotal()
	{
		if (!$this->cntQuery){
			//$cnt = (!preg_match("/distinct/i",$this->field)) ? "count(*)" : "count($this->field)";

			if(!preg_match("/distinct/i",$this->field)) $cnt = "count(*)";
			else {
				$temp = explode( ",", $this->field );
				$cnt = "count($temp[0])";
			}
			$this->cntQuery = "select $cnt from $this->db_table $this->where";
		}
		list($this->recode[total]) = $GLOBALS[db]->fetch($this->cntQuery);
	}

	function setTotal()
	{
		$limited = ($this->recode[start]+$this->page[num]<$this->recode[total]) ? $this->page[num] : $this->recode[total] - $this->recode[start];
		if (!$this->nolimit) $this->limit = "limit {$this->recode[start]},$limited";
		$this->query = "select $this->field from $this->db_table $this->where $this->tmpQry $this->orderby $this->limit";
		$this->idx = $this->recode[total] - $this->recode[start];
	}

	function setQuery($db_table,$where='',$orderby='',$tmp='')
	{
		$this->db_table = $db_table;
		$this->tmpQry = $tmp;
		if ($where) $this->where = "where ".implode(" and ",$where);
		if (trim($orderby)) $this->orderby = "order by ".$orderby;
		if (!isset($this->recode[total])) $this->getTotal();
	}

	function exec()
	{
		if ($this->foo === null) $this->setTotal();

		$this->page[total]	= @ceil($this->recode[total]/$this->page[num]);
		if ($this->page[total] && $this->page[now]>$this->page[total]) $this->page[now] = $this->page[total];
		$page[start]		= (ceil($this->page[now]/10)-1)*10;

		$navi .= "<table border='0' align='center' cellpadding='0' cellspacing='0'>
					<tr> ";

		if($this->page[now]>10){
			$navi .= "
			<td width='20'><a href=\"javascript:goPage3(1)\" class=navi><img src='/images/common/numFirst.gif' /></a></td>
			<td width='44'><a href=\"javascript:goPage3($page[start])\" class=navi><img src='/images/common/numPrev.gif' /></a></td>
			";
		}

		$navi .= "<td width='10'>&nbsp;</td>";

		$tc = 1;
		while($i+$page[start]<$this->page[total]&&$i<10){
			$i++;
			$page[move] = $i+$page[start];
			if ($tc>1){
				$navi .= "<td width='6'>&nbsp;</td>";
			}
			if ($this->page[now]==$page[move]){
				$navi .= "<td align='center' background='/images/common/numBg1.gif' style='padding:1px 8px 0px 7px; border:1px solid #3389a6'><b><font color='#FFFFFF'>$page[move]</font></b></td>";
			}else{
				$navi .= "<td align='center' style='padding:1px 8px 0px 7px; border:1px solid #d2d2d2; cursor:pointer' onClick='javascript:goPage3($page[move])' class=navi>$page[move]</td>";
			}
			$tc++;
		}

		$navi .= "<td width='10'>&nbsp;</td>";

		if($this->page[total]>$page[move]){
			$page[next] = $page[move]+1;
			$navi .= "
			<td width='44'><a href=\"javascript:goPage3($page[next])\" class=navi><img src='/images/common/numNext.gif' /></a></td>
			<td width='20'><a href=\"javascript:goPage3({$this->page[total]})\" class=navi><img src='/images/common/numEnd.gif' /></a></td>
			";
		}

		$navi .= "</tr></table>";

		if ($this->recode[total] && !$this->nolimit) $this->page[navi] = &$navi;
	}

	function getNavi($total) {

		$this->recode[total] = $total;

		$this->foo = true;
		$this->exec();

		return $this->page['navi'];

	}

}



class Page4{

	var $page	= array();
	/*
	$page[now]		현재 페이지
	$page[num]		한 페이지에 출력되는 레코드 개수
	$page[total]	전체 페이지 수
	$page[url]		페이지 링크 URL
	$page[navi]		페이지 네비게이션
	$page[prev]		이전페이지 아이콘
	$page[next]		다음페이지 아이콘
	*/
	var $recode	= array();
	/*
	$recode[start]	시작 레코드 번호
	$recode[total]	전체 레코드 수 (전체 글수)
	*/

	var $vars		= array();
	var $field		= "*";			// 가져올 필드
	var $cntQuery	= "";			// 전체 레코드 개수 가져올 쿼리문 (조인시 성능 향상을 위해)
	var $nolimit	= false;		// 참일 경우 전체 데이타 추출
	var $idx		= 0;			// 해당페이지 첫번쩨 레코드 번호값

	var $foo = null;

	function Page4($page=1,$page_num=20)
	{
		$this->vars[page]= getVars('no,chk,page,password,x,y');
		$this->page[now] = ($page<1) ? 1 : $page;
		$this->page[num] = ($page_num<1) ? 20 : $page_num;
		$this->page[url] = $_SERVER['PHP_SELF'];
		$this->recode[start] = ($this->page[now]-1) * $this->page[num];
		$this->page[prev] = "◀";
		$this->page[next] = "▶";
	}

	function getTotal()
	{
		if (!$this->cntQuery){
			//$cnt = (!preg_match("/distinct/i",$this->field)) ? "count(*)" : "count($this->field)";

			if(!preg_match("/distinct/i",$this->field)) $cnt = "count(*)";
			else {
				$temp = explode( ",", $this->field );
				$cnt = "count($temp[0])";
			}
			$this->cntQuery = "select $cnt from $this->db_table $this->where";
		}
		list($this->recode[total]) = $GLOBALS[db]->fetch($this->cntQuery);
	}

	function setTotal()
	{
		$limited = ($this->recode[start]+$this->page[num]<$this->recode[total]) ? $this->page[num] : $this->recode[total] - $this->recode[start];
		if (!$this->nolimit) $this->limit = "limit {$this->recode[start]},$limited";
		$this->query = "select $this->field from $this->db_table $this->where $this->tmpQry $this->orderby $this->limit";
		$this->idx = $this->recode[total] - $this->recode[start];
	}

	function setQuery($db_table,$where='',$orderby='',$tmp='')
	{
		$this->db_table = $db_table;
		$this->tmpQry = $tmp;
		if ($where) $this->where = "where ".implode(" and ",$where);
		if (trim($orderby)) $this->orderby = "order by ".$orderby;
		if (!isset($this->recode[total])) $this->getTotal();
	}

	function exec()
	{
		if ($this->foo === null) $this->setTotal();

		$this->page[total]	= @ceil($this->recode[total]/$this->page[num]);
		if ($this->page[total] && $this->page[now]>$this->page[total]) $this->page[now] = $this->page[total];
		$page[start]		= (ceil($this->page[now]/5)-1)*5;

		$navi .= "<table border='0' align='center' cellpadding='0' cellspacing='0'>
					<tr> ";

		if($this->page[now]>5){
			$navi .= "
			<td width='20'><a href=\"javascript:goPage3(1)\" class=navi><img src='/images/common/numFirst.gif' /></a></td>
			<td width='44'><a href=\"javascript:goPage3($page[start])\" class=navi><img src='/images/common/numPrev.gif' /></a></td>
			";
		}

		$navi .= "<td width='10'>&nbsp;</td>";

		$tc = 1;
		while($i+$page[start]<$this->page[total]&&$i<5){
			$i++;
			$page[move] = $i+$page[start];
			if ($tc>1){
				$navi .= "<td width='6'>&nbsp;</td>";
			}
			if ($this->page[now]==$page[move]){
				$navi .= "<td align='center' background='/images/common/numBg1.gif' style='padding:1px 8px 0px 7px; border:1px solid #3389a6'><b><font color='#FFFFFF'>$page[move]</font></b></td>";
			}else{
				$navi .= "<td align='center' style='padding:1px 8px 0px 7px; border:1px solid #d2d2d2; cursor:pointer' onClick='javascript:goPage3($page[move])' class=navi>$page[move]</td>";
			}
			$tc++;
		}

		$navi .= "<td width='10'>&nbsp;</td>";

		if($this->page[total]>$page[move]){
			$page[next] = $page[move]+1;
			$navi .= "
			<td width='44'><a href=\"javascript:goPage3($page[next])\" class=navi><img src='/images/common/numNext.gif' /></a></td>
			<td width='20'><a href=\"javascript:goPage3({$this->page[total]})\" class=navi><img src='/images/common/numEnd.gif' /></a></td>
			";
		}

		$navi .= "</tr></table>";

		if ($this->recode[total] && !$this->nolimit) $this->page[navi] = &$navi;
	}

	function getNavi($total) {

		$this->recode[total] = $total;

		$this->foo = true;
		$this->exec();

		return $this->page['navi'];

	}

}


class Page5{

	var $page	= array();
	/*
	$page[now]		현재 페이지
	$page[num]		한 페이지에 출력되는 레코드 개수
	$page[total]	전체 페이지 수
	$page[url]		페이지 링크 URL
	$page[navi]		페이지 네비게이션
	$page[prev]		이전페이지 아이콘
	$page[next]		다음페이지 아이콘
	*/
	var $recode	= array();
	/*
	$recode[start]	시작 레코드 번호
	$recode[total]	전체 레코드 수 (전체 글수)
	*/

	var $vars		= array();
	var $field		= "*";			// 가져올 필드
	var $cntQuery	= "";			// 전체 레코드 개수 가져올 쿼리문 (조인시 성능 향상을 위해)
	var $nolimit	= false;		// 참일 경우 전체 데이타 추출
	var $idx		= 0;			// 해당페이지 첫번쩨 레코드 번호값

	var $foo = null;

	function Page5($page=1,$page_num=20)
	{
		$this->vars[page]= getVars('no,chk,page,password,x,y');
		$this->page[now] = ($page<1) ? 1 : $page;
		$this->page[num] = ($page_num<1) ? 20 : $page_num;
		$this->page[url] = $_SERVER['PHP_SELF'];
		$this->recode[start] = ($this->page[now]-1) * $this->page[num];
		$this->page[prev] = "◀";
		$this->page[next] = "▶";
	}

	function getTotal()
	{
		if (!$this->cntQuery){
			//$cnt = (!preg_match("/distinct/i",$this->field)) ? "count(*)" : "count($this->field)";

			if(!preg_match("/distinct/i",$this->field)) $cnt = "count(*)";
			else {
				$temp = explode( ",", $this->field );
				$cnt = "count($temp[0])";
			}
			$this->cntQuery = "select $cnt from $this->db_table $this->where";
		}
		list($this->recode[total]) = $GLOBALS[db]->fetch($this->cntQuery);
	}

	function setTotal()
	{
		$limited = ($this->recode[start]+$this->page[num]<$this->recode[total]) ? $this->page[num] : $this->recode[total] - $this->recode[start];
		if (!$this->nolimit) $this->limit = "limit {$this->recode[start]},$limited";
		$this->query = "select $this->field from $this->db_table $this->where $this->tmpQry $this->orderby $this->limit";
		$this->idx = $this->recode[total] - $this->recode[start];
	}

	function setQuery($db_table,$where='',$orderby='',$tmp='')
	{
		$this->db_table = $db_table;
		$this->tmpQry = $tmp;
		if ($where) $this->where = "where ".implode(" and ",$where);
		if (trim($orderby)) $this->orderby = "order by ".$orderby;
		if (!isset($this->recode[total])) $this->getTotal();
	}

	function exec()
	{
		if ($this->foo === null) $this->setTotal();

		$this->page[total]	= @ceil($this->recode[total]/$this->page[num]);
		if ($this->page[total] && $this->page[now]>$this->page[total]) $this->page[now] = $this->page[total];
		$page[start]		= (ceil($this->page[now]/5)-1)*5;

		$navi .= "<table border='0' align='center' cellpadding='0' cellspacing='0'>
					<tr> ";

		if($this->page[now]>5){
			$navi .= "
			<td width='20'><a href=\"javascript:goPage4(1)\" class=navi><img src='/images/common/numFirst.gif' /></a></td>
			<td width='44'><a href=\"javascript:goPage4($page[start])\" class=navi><img src='/images/common/numPrev.gif' /></a></td>
			";
		}

		$navi .= "<td width='10'>&nbsp;</td>";

		$tc = 1;
		while($i+$page[start]<$this->page[total]&&$i<5){
			$i++;
			$page[move] = $i+$page[start];
			if ($tc>1){
				$navi .= "<td width='6'>&nbsp;</td>";
			}
			if ($this->page[now]==$page[move]){
				$navi .= "<td align='center' background='/images/common/numBg1.gif' style='padding:1px 8px 0px 7px; border:1px solid #3389a6'><b><font color='#FFFFFF'>$page[move]</font></b></td>";
			}else{
				$navi .= "<td align='center' style='padding:1px 8px 0px 7px; border:1px solid #d2d2d2; cursor:pointer' onClick='javascript:goPage4($page[move])' class=navi>$page[move]</td>";
			}
			$tc++;
		}

		$navi .= "<td width='10'>&nbsp;</td>";

		if($this->page[total]>$page[move]){
			$page[next] = $page[move]+1;
			$navi .= "
			<td width='44'><a href=\"javascript:goPage4($page[next])\" class=navi><img src='/images/common/numNext.gif' /></a></td>
			<td width='20'><a href=\"javascript:goPage4({$this->page[total]})\" class=navi><img src='/images/common/numEnd.gif' /></a></td>
			";
		}

		$navi .= "</tr></table>";

		if ($this->recode[total] && !$this->nolimit) $this->page[navi] = &$navi;
	}

	function getNavi($total) {

		$this->recode[total] = $total;

		$this->foo = true;
		$this->exec();

		return $this->page['navi'];

	}

}

class Db
{
	var $db_host, $db_user, $db_pass, $db_conn, $err_report;
	var $count;

	var $page_number=10;

	function db()
	{

	}

	function connect($db_name="",$db_set="")
	{

		$this->db_conn = @mysqli_connect($this->db_host, $this->db_user, $this->db_pass);
		if (!$this->db_conn){
			$err['msg'] = 'DB connection error..';
			$this->error($err);
		}
		mysqli_query($this->db_conn, "set names utf8");
		if ($db_name) $this->select($db_name);


	}

	function select($db_name)
	{
		$ret = mysqli_select_db($this->db_conn, $db_name);
		if (!$ret){
			$err['msg'] = 'DB selection error..';
			$this->error($err);
		}
	}

	function query($query)
	{
		$time[] = microtime();

		$res = mysqli_query($this->db_conn, $query);
		if (preg_match("/^select/",trim(strtolower($query)))) $this->count = $this->count_($res);

		if (!$res){
			$debug = @debug_backtrace();
			if($debug){
				krsort($debug);
				foreach ($debug as $v) $debuginf[] = $v['file']." (line:$v[line])";
				$debuginf = implode("<br>",$debuginf);
			}

			$err['query']	= $query;
			$err['file']	= $debuginf;
			$this->error($err);
		}

		$time[] = microtime();
		// $this->time[] = get_microtime($time[0],$time[1]);
		$this->log[] = $query;

		if ($res) return $res;
	}

	function fetch($res,$mode=0)
	{
		return (!$mode) ? @mysqli_fetch_array($res) : @mysqli_fetch_assoc($res);
	}

	// function fetch($res,$mode=0)
	// {
	// 	if (!is_resource($res)) $res = $this->query($res);
	// 	return (!$mode) ? @mysqli_fetch_array($res) : @mysqli_fetch_assoc($res);
	// }
	//
	function count_($result)
	{
		if(is_resource($result))$rows = mysqli_num_rows($result);
		if ($rows !== null) return $rows;
	}

	function tableCheck($tablename)
	{
		$tableQuery	= "show tables like '".$tablename."'";
		if( $this->count_($this->query($tableQuery)) >= 1 ){
			return true;
		}else{
			return false;
		}
	}


	function _escape($var) {
		return mysqli_real_escape_string($this->db_conn, $var);
	}

	function _query_print($query) {
		$argList = func_get_args();
		array_shift($argList);
		$this->replaceNum=0;
		$this->replaceArgs=$argList;
		$query = preg_replace_callback('/\[(i|d|s|c|cv|vs|v)\]/',array(&$this,'_queryReplace'), $query);
		return $query;
	}

	function _query($query) {
		$result = mysqli_query($this->db_conn, $query);
		if($result) {
			return $result;
		}
		else {
			return false;
		}
	}

	function _last_insert_id() {
		$result = mysqli_query($this->db_conn, "SELECT LAST_INSERT_ID()");
		$row = mysqli_fetch_row($result);
		return $row[0];
	}

	function _select($query) {
		$result = mysqli_query($this->db_conn, $query);
		if(!$result) {
			return false;
		}
		$arResult=array();
		while ($row = mysqli_fetch_assoc($result)) {
			$arResult[]=$row;
		}
		return $arResult;
	}

	function _select_page($number,$page,$query) {
		$start = ($page-1)*$number;
		$query= trim($query)." limit $start , $number";

		if(!preg_match('/SQL_CALC_FOUND_ROWS/',$query)) {
			$query = preg_replace("/^select/i","select SQL_CALC_FOUND_ROWS",$query);
		}

		if(!($result = mysqli_query($this->db_conn, $query))) {
			return false;
		}

		if(!($c_result = mysqli_query($this->db_conn, "SELECT FOUND_ROWS()")))
		{
			return false;
		}
		list($totalcount) = mysqli_fetch_row($c_result);

		return $this->__paging($result,$totalcount,$number,$page);
	}

	function _select_manual_page($number,$page,$totalcount,$query) {
		$start = ($page-1)*$number;
		$query= trim($query)." limit $start , $number";
		if(!preg_match("/^select/i",$query)) {
			return false;
		}

		if(!($result = mysqli_query($this->db_conn, $query))) {
			return false;
		}

		return $this->__paging($result,$totalcount,$number,$page);
	}

	function __paging($result,$totalcount,$number,$page) {
		$start = ($page-1)*$number;
		$ar_return['record'] = array();
		$count=1;
		while($row = mysqli_fetch_assoc($result))
		{
			$row['_no'] =$start+$count;
			$row['_rno'] =$totalcount-($start+$count)+1;
			$ar_return['record'][] = $row;
			$count++;
		}

		if($totalcount%$number)
			$totalpage = (int)($totalcount/$number)+1;
		else
			$totalpage = $totalcount/$number;

		$step = ceil($page/$this->page_number);

		$ar_return['page']=array(
			'totalpage'=>$totalpage,
			'totalcount'=>$totalcount,
			'nowpage'=>$page,
			'page'=>array(),
			'next'=>false,
			'prev'=>false,
			'last'=>false,
			'first'=>false
		);

		if($step*$this->page_number<$totalpage) $ar_return['page']['next']=$step*$this->page_number+1;
		if($step!=1) $ar_return['page']['prev']=($step-1)*$this->page_number;

		if($ar_return['page']['prev']) $ar_return['page']['first']=1;
		if($ar_return['page']['next']) $ar_return['page']['last']=$totalpage;

		if($ar_return['page']['next']) $count=$this->page_number;
		else {
			if($totalpage) $count=$totalpage%$this->page_number ? $totalpage%$this->page_number : $this->page_number;
			else $count=0;
		}

		$loop_start = ($step-1)*$this->page_number+1;
		for($i=0;$i<$count;$i++)
		{
			$ar_return['page']['page'][$i]=$loop_start+$i;
		}

		return $ar_return;
	}



	function _queryReplace($matches) {
		if($matches[1]=='i') {
			$result = (int)$this->replaceArgs[$this->replaceNum];
		}
		elseif($matches[1]=='d') {
			$result = (float)$this->replaceArgs[$this->replaceNum];
		}
		elseif($matches[1]=='s') {
			if(!is_scalar($this->replaceArgs[$this->replaceNum])) {
				die('query_error');
			}
			$result = '"'.mysqli_real_escape_string($this->db_conn, $this->replaceArgs[$this->replaceNum]).'"';
		}
		elseif($matches[1]=='c') {
			$cols = &$this->replaceArgs[$this->replaceNum];
			if(!(is_array($cols) && count($cols))) {
				die('query_error');
			}
			foreach($cols as $eachCol) {
				if(!preg_match("/[_a-zA-Z0-9-]+/",$eachCol)) {
					die('query_error');
				}
			}
			$result = '('.implode(",",$cols).')';
		}
		elseif($matches[1]=='v') {
			$values = &$this->replaceArgs[$this->replaceNum];
			if(!(is_array($values) && count($values))) {
				die('fff');
			}
			foreach($values as $k=>$eachValue) {
				if(is_null($eachValue)) {
					$values[$k]='null';
				}
				else {
					$values[$k]='"'.mysqli_real_escape_string($this->db_conn, $eachValue).'"';
				}

			}
			$result = '('.implode(",",$values).')';
		}
		elseif($matches[1]=='vs') {
			$values = &$this->replaceArgs[$this->replaceNum];
			if(!(is_array($values) && count($values))) {
				die('query_error');
			}
			$arRecord=array();
			foreach($values as $eachValue) {
				foreach($eachValue as $k=>$eachElement) {
					if(is_null($eachElement)) {
						$eachValue[$k]='null';
					}
					else {
						$eachValue[$k]='"'.mysqli_real_escape_string($this->db_conn, $eachElement).'"';
					}

				}
				$arRecord[]='('.implode(",",$eachValue).')';
			}
			$result = implode(',',$arRecord);
		}
		elseif($matches[1]=='cv') {
			$colValues = &$this->replaceArgs[$this->replaceNum];
			if(!(is_array($colValues) && count($colValues))) {
				die('query_error');
			}
			$arImplode=array();
			foreach($colValues as $eachCol=>$eachValue) {
				if(is_null($eachValue)) {
					$arImplode[]= $eachCol.'=null';
				}
				else {
					$arImplode[]= $eachCol.'="'.mysqli_real_escape_string($this->db_conn, $eachValue).'"';
				}

			}
			$result = implode(",",$arImplode);
		}
		$this->replaceNum++;
		return $result;
	}


	function close()
	{
		$ret = @mysql_close($this->db_conn);
		$this->db_conn = null;
		return $ret;
	}

	function error($err)
	{
		if($this->err_report){
			//msg("정상적인 요청이 아니거나 DB에 문제가 있습니다",-1);
			echo "
			<div style='background-color:#f7f7f7;padding:2'>
			<table width=100% border=1 bordercolor='#cccccc' style='border-collapse:collapse;font:9pt tahoma'>
			<col width=100 style='padding-right:10;text-align:right;font-weight:bold'><col style='padding:3 0 3 10'>
			<tr><td>error</td><td>".mysqli_error()."</td></tr>
			";
			foreach ($err as $k=>$v) echo "<tr><td>$k</td><td>$v</td></tr>";
			echo "</table></div>";
			//exit();
		}
	}

	function viewLog()
	{
		echo "
		<table width=800 border=1 bordercolor='#cccccc' style='border-collapse:collapse;font:8pt tahoma'>
		<tr bgcolor='#f7f7f7'>
			<th width=40 nowrap>no</th>
			<th width=100%>query</th>
			<th width=80 nowrap>time</th>
		</tr>
		<col align=center><col style='padding-left:5'><col align=center>
		";
		foreach ($this->log as $k=>$v){
			echo "
			<tr>
				<td>".++$idx."</td>
				<td>$v</td>
				<td>{$this->time[$k]}</td>
			</tr>
			";
		}
		echo "
		<tr bgcolor='#f7f7f7'>
			<td>total</td>
			<td></td>
			<td>".array_sum($this->time)."</td>
		</tr>
		</table>
		";
	}
}

function resort($arr)
{
	if (!is_array($arr)) return;
	ksort($arr);
	foreach ($arr as $v) foreach ($v as $v2) $tmp[] = $v2;
	return $tmp;
}


function msg($msg='')
{
	echo "<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\">";
	echo "<script type='text/javascript'>alert('$msg');";
    echo "</script>";
}


// 경고메세지를 경고창으로
function alert($msg='', $url='')
{

    if (!$msg) $msg = '올바른 방법으로 이용해 주십시오.';

	echo "<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\">";
	echo "<script type='text/javascript'>alert('$msg');";
    if (!$url)
        echo "history.go(-1);";
    echo "</script>";
    if ($url)
        goto_url($url);
    exit;
}



// 경고메세지 출력후 창을 닫음
function alert_close($msg)
{

	echo "<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\">";
    echo "<script type='text/javascript'> alert('$msg'); window.close(); </script>";
    exit;
}

function goto_url($url)
{
    echo "<script type='text/javascript'> location.replace('$url'); </script>";
    exit;
}

// 회원 정보를 얻는다.
function get_member($m_id, $fields='*')
{
    global $db;
	$query  = "select $fields from ceng_member where m_id=TRIM('$m_id') ";
	$res    = $db->query($query);
	$row    = $db->fetch($res);
	return $row;
}

// 회원 정보를 얻는다.
function get_member_point($m_id)
{
    global $db,$member,$company_idx;

	//if ($company_idx==1 && $member['m_add1']=="Y"){
	if (1){
		$query   = "select sum(p_score) as total from ceng_publish where m_id=TRIM('$m_id') ";
		$res     = $db->query($query);
		$row     = $db->fetch($res);
		$p_total = $row[total];

		$query   = "select sum(p_score) as total from ceng_unpublish where m_id=TRIM('$m_id') ";
		$res     = $db->query($query);
		$row     = $db->fetch($res);
		if ($row['total']){
			$p_total = $p_total+$row[total];
		}

	}else{
		$query   = "select p_dictation,p_speaking,p_write,p_vocabulary from ceng_point where m_id=TRIM('$m_id') ";
		$res     = $db->query($query);
		$row     = $db->fetch($res);
		$p_total = $row[p_dictation]+$row[p_speaking]+$row[p_write]+$row[p_vocabulary];
	}

	return $p_total;
}

function get_graphbar($gubun,$my_value,$class_value,$class_cnt){

	echo "
									<table border='0' cellspacing='0' cellpadding='0'>
										<tr>
											<td align='left'>
												<table border='0' cellspacing='0' cellpadding='0'>
													<tr>
														<td width='200' bgcolor='ebebeb'>
														<img src='/images/common/graphbar_g.jpg' width='".($my_value*2)."' height='13' /></td>
													</tr>
												</table>
												<table border='0' cellspacing='0' cellpadding='0'>
													<tr>
														<td width='200' bgcolor='ebebeb'><img src='/images/common/graphbar_b.jpg' width='".(($class_value/$class_cnt)*2)."' height='5' /></td>
													</tr>
												</table>
											</td>
											<td width='30' align='right'>".round($my_value)."%</td>
										</tr>
									</table>
	";
}

function get_total_graphbar($gubun,$m_vocabulary,$m_dictation,$m_speaking,$m_write,$c_vocabulary,$c_dictation,$c_speaking,$c_write,$class_cnt){

	$m_cnt   = 0;
	$m_total = 0;
	if ($m_vocabulary){
		$m_total += $m_vocabulary;
		$m_cnt++;
	}
	if ($m_dictation){
		$m_total += $m_dictation;
		$m_cnt++;
	}
	if ($m_speaking){
		$m_total += $m_speaking;
		$m_cnt++;
	}
	if ($m_write){
		$m_total += $m_write;
		$m_cnt++;
	}

	$c_cnt   = 0;
	$c_total = 0;
	if ($c_vocabulary){
		$c_total += $c_vocabulary;
		$c_cnt++;
	}
	if ($c_dictation){
		$c_total += $c_dictation;
		$c_cnt++;
	}
	if ($c_speaking){
		$c_total += $c_speaking;
		$c_cnt++;
	}
	if ($c_write){
		$c_total += $c_write;
		$c_cnt++;
	}

	if (!$m_cnt) $m_cnt = 1;

	echo "
									<table border='0' cellspacing='0' cellpadding='0'>
										<tr>
											<td align='left'>
												<table border='0' cellspacing='0' cellpadding='0'>
													<tr>
														<td width='200' bgcolor='ebebeb'>
														<img src='/images/common/graphbar_g.jpg' width='".(($m_total/$m_cnt)*2)."' height='13' /></td>
													</tr>
												</table>
												<table border='0' cellspacing='0' cellpadding='0'>
													<tr>
														<td width='200' bgcolor='ebebeb'><img src='/images/common/graphbar_b.jpg' width='".((($c_total/$c_cnt)/$class_cnt)*2)."' height='5' /></td>
													</tr>
												</table>
											</td>
											<td width='30' align='right'>".round($m_total/$m_cnt)."%</td>
										</tr>
									</table>
	";

}

function get_status_point($gubun,$m_vocabulary,$m_dictation,$m_speaking,$m_write){

	$m_cnt   = 0;
	$m_total = 0;

	if ($gubun=="A"){
		if ($m_vocabulary){
			$m_total += $m_vocabulary;
			$m_cnt++;
		}

		if ($m_dictation){
			$m_total += $m_dictation;
			$m_cnt++;
		}

		if ($m_speaking){
			$m_total += $m_speaking;
			$m_cnt++;
		}

		if ($m_write){
			$m_total += $m_write;
			$m_cnt++;
		}
	}else if ($gubun=="D"){
		$m_total += $m_dictation;
	}else if ($gubun=="V"){
		$m_total += $m_vocabulary;
	}else if ($gubun=="W"){
		$m_total += $m_write;
	}else if ($gubun=="S"){
		$m_total += $m_speaking;
	}

	return ($m_total);
}

function get_status_value($gubun,$m_vocabulary,$m_dictation,$m_speaking,$m_write){

	$m_cnt   = 0;
	$m_total = 0;

	if ($gubun=="A"){
		if ($m_vocabulary){
			$m_total += $m_vocabulary;
			$m_cnt++;
		}

		if ($m_dictation){
			$m_total += $m_dictation;
			$m_cnt++;
		}

		if ($m_speaking){
			$m_total += $m_speaking;
			$m_cnt++;
		}

		if ($m_write){
			$m_total += $m_write;
			$m_cnt++;
		}

	}else if ($gubun=="D"){
		$m_total += $m_dictation;
		$m_cnt = 1;
	}else if ($gubun=="V"){
		$m_total += $m_vocabulary;
		$m_cnt = 1;
	}else if ($gubun=="W"){
		$m_total += $m_write;
		$m_cnt = 1;
	}else if ($gubun=="S"){
		$m_total += $m_speaking;
		$m_cnt = 1;
	}
	if ($m_cnt){
		$m_cal = $m_total/$m_cnt;
	}else{
		$m_cal = 0;
	}
	return ($m_cal);
}

function get_total_avg($gubun,$m_vocabulary,$m_dictation,$m_speaking,$m_write,$c_vocabulary,$c_dictation,$c_speaking,$c_write,$class_cnt){

	$m_cnt   = 0;
	$m_total = 0;
	if ($m_vocabulary){
		$m_total += $m_vocabulary;
		$m_cnt++;
	}
	if ($m_dictation){
		$m_total += $m_dictation;
		$m_cnt++;
	}
	if ($m_speaking){
		$m_total += $m_speaking;
		$m_cnt++;
	}
	if ($m_write){
		$m_total += $m_write;
		$m_cnt++;
	}

	$c_cnt   = 0;
	$c_total = 0;
	if ($c_vocabulary){
		$c_total += $c_vocabulary;
		$c_cnt++;
	}
	if ($c_dictation){
		$c_total += $c_dictation;
		$c_cnt++;
	}
	if ($c_speaking){
		$c_total += $c_speaking;
		$c_cnt++;
	}
	if ($c_write){
		$c_total += $c_write;
		$c_cnt++;
	}

	if ($c_cnt==0) $c_cnt = 1;
	if ($m_cnt==0) $m_cnt = 1;

	echo "<strong> <span style='padding:0 10px 0 0'> ".number_format($m_total/$m_cnt)." P <span class='txBlue'>(".number_format($c_total/$c_cnt/$class_cnt)." P)</span></span></strong>";

}


// 회원 정보를 얻는다.
function get_class_count($m_class)
{
    global $db,$company_idx;
	$query   = "select count(*) as cnt from ceng_member where c_idx='$company_idx' and m_class=TRIM('$m_class') ";
	$res     = $db->query($query);
	$row     = $db->fetch($res);
	return $row[cnt];
}

function xss_clean($data) 
{ 


} 
function auth_login(){
	global $member;

	$url = urlencode($_SERVER['REQUEST_URI']);

	if (!$member['m_id']){
//	    alert("로그인후에 이용하실수 있습니다.","/ap_shop/sp_member/login.php?url=".$url);
		goto_url("/ap_shop/sp_member/login.php?url=".$url);
	}
}

function auth_m_login(){
	global $member;

	$url = urlencode($_SERVER['REQUEST_URI']);

	if (!$member['m_id']){
//	    alert("로그인후에 이용하실수 있습니다.","/ap_shop/sp_member/login.php?url=".$url);
		goto_url("/m/member/login.php?url=".$url);
	}
}

function auth_manager(){
	global $member;

	$url = urlencode($_SERVER['REQUEST_URI']);

	if (!$member['m_id']){
		goto_url("/ap_shop/sp_member/login.php?url=".$url);
	}

	if ($member['m_level']=="1"){
//	    alert("접근이 불가능합니다.");
		alert("접근이 불가능합니다.","/ap_shop/um_myroom/myroom_index.php");
	}
}


function auth_parent(){
	global $member;

	$url = urlencode($_SERVER['REQUEST_URI']);

	if (!$member['m_id']){
		goto_url("/ap_shop/sp_member/login.php?url=".$url);
	}

	if (substr($member[m_class],0,3)=="999" || $member['m_level']==2){

	}else{
		alert("부모회원 전용 메뉴입니다.","/ap_shop/um_myroom/myroom_index.php");
	}
}

function auth_master(){
	global $member;

	$url = urlencode($_SERVER['REQUEST_URI']);

	if ($member[m_level]!="9"){
	    alert("접근이 불가능합니다.");
	}
}


function array_value_cheking($ar_fields,$ar_data) {
	$ar_result = array();
	foreach($ar_data as $field_name=>$value)
	{
		$ar_attr = $ar_fields[$field_name];

		if(strlen($value)==0 && $ar_attr['require']!=true) {
			continue;
		}

		if(strlen($value)==0 && $ar_attr['require']==true) {
			$ar_result[$field_name][] = 'require';
			continue;
		}

		switch($ar_attr['type'])
		{
			case 'int':
				if(!ctype_digit((string)$value)) $ar_result[$field_name][] = 'type';
				break;
			case 'float':
				if(!preg_match('/^-?[0-9]+(\.[0-9]+)?$/',$value)) $ar_result[$field_name][] = 'type';
				break;
			case 'digit':
				if(!ctype_digit((string)$value)) $ar_result[$field_name][] = 'type';
				break;
			case 'alnum':
				if(!ctype_alnum($value)) $ar_result[$field_name][] = 'type';
				break;
		}

		if($ar_attr['max_byte'] && $ar_attr['max_byte']<strlen($value))
		{
			$ar_result[$field_name][] = 'max_byte';
		}
		if($ar_attr['min_byte'] && $ar_attr['min_byte']<strlen($value))
		{
			$ar_result[$field_name][] = 'min_byte';
		}

		if($ar_attr['max_length'] && $ar_attr['max_length']<mb_strlen($value,'EUC-KR'))
		{
			$ar_result[$field_name][] = 'max_length';
		}
		if($ar_attr['min_length'] && $ar_attr['min_length']>mb_strlen($value,'EUC-KR'))
		{
			$ar_result[$field_name][] = 'min_length';
		}
		if($ar_attr['pattern'] && !preg_match($ar_attr['pattern'],$value))
		{
			$ar_result[$field_name][] = 'pattern';
		}

		if($ar_attr['array'] && !in_array($value,$ar_attr['array']))
		{
			$ar_result[$field_name][] = 'array';
		}

		if($ar_attr['callback']) {
			if(!call_user_func($ar_attr['callback'],$value)) {
				$ar_result[$field_name][] = 'callback';
			}
		}
	}
	return $ar_result;
}

function imageResize($sourceFile, $destFile, $destWidth = NULL, $destHeight = NULL, $fileType = 'jpg')
{
	if (!file_exists($sourceFile))
		return false;


		$orgWidth  = $destWidth;
		$orgHeight = $destHeight;

		list($sourceWidth, $sourceHeight, $type, $attr) = getimagesize($sourceFile);
		// If PS_IMAGE_QUALITY is activated, the generated image will be a PNG with .jpg as a file extension.
		// This allow for higher quality and for transparency. JPG source files will also benefit from a higher quality
		// because JPG reencoding by GD, even with max quality setting, degrades the image.
		if ($type == IMAGETYPE_PNG)
			$fileType = 'png';
		
		if (!$sourceWidth)
			return false;
		if ($destWidth == NULL) $destWidth = $sourceWidth;
		if ($destHeight == NULL) $destHeight = $sourceHeight;



		$sourceImage = createSrcImage($type, $sourceFile);

		$widthDiff = $destWidth / $sourceWidth;
		$heightDiff = $destHeight / $sourceHeight;

		if ($widthDiff > 1 AND $heightDiff > 1)
		{
			$nextWidth = $sourceWidth;
			$nextHeight = $sourceHeight;
		}
		else
		{
			$imgMerge  = "Y";
			if ($widthDiff > $heightDiff)
			{
				$nextHeight = $destHeight;
				$nextWidth = round(($sourceWidth * $nextHeight) / $sourceHeight);
				$destWidth = (int)$nextWidth;
			}
			else
			{
				$nextWidth = $destWidth;
				$nextHeight = round($sourceHeight * $destWidth / $sourceWidth);
				$destHeight = (int)$nextHeight;
			}
		}
		//@umask(0);

		if ($orgHeight  == NULL || $orgWidth  == NULL ) {
			$orgWidth  = $destWidth;
			$orgHeight = $destHeight;
		}

		$destImage = imagecreatetruecolor($orgWidth, $orgHeight);

		// If image is a PNG and the output is PNG, fill with transparency. Else fill with white background.
		if ($fileType == 'png')
		{
//			imagealphablending($destImage, false);
//			imagesavealpha($destImage, true);	
//			$transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
//			imagefilledrectangle($destImage, 0, 0, $destWidth, $destHeight, $transparent);
			$white = imagecolorallocate($destImage, 255, 255, 255);
			imagefilledrectangle($destImage, 0, 0, $orgWidth, $orgHeight, $white);

		}else
		{
			$white = imagecolorallocate($destImage, 255, 255, 255);
			imagefilledrectangle($destImage, 0, 0, $orgWidth, $orgHeight, $white);
		}
							//NEW    , ORG
		imagecopyresampled($destImage, $sourceImage, (int)(($orgWidth - $nextWidth) / 2), (int)(($orgHeight - $nextHeight) / 2), 0, 0, $nextWidth, $nextHeight, $sourceWidth, $sourceHeight);
		if ($imgMerge){

		}
		return (returnDestImage($fileType, $destImage, $destFile));

}

function createSrcImage($type, $filename)
{
	switch ($type)
	{
		case 1:
			return imagecreatefromgif($filename);
			break;
		case 3:
			return imagecreatefrompng($filename);
			break;
		case 2:
		default:
			return imagecreatefromjpeg($filename);
			break;
	}
}

function returnDestImage($type, $ressource, $filename)
{
	$flag = false;
	switch ($type)
	{
		case 'gif':
			$flag = imagegif($ressource, $filename);
			break;
		case 'png':
			$quality = 7;
			$flag = imagepng($ressource, $filename, (int)$quality);
			break;
		case 'jpeg':
		default:
			$quality = 90;
			$flag = imagejpeg($ressource, $filename, (int)$quality);
			break;
	}
	imagedestroy($ressource);
	@umask(0);
	@chmod($filename, 0664);
	return $flag;
}

// 세션변수 생성
function set_session($session_name, $value)
{
    if (PHP_VERSION < '5.3.0')
        session_register($session_name);
    // PHP 버전별 차이를 없애기 위한 방법
    $$session_name = $_SESSION["$session_name"] = $value;
}


// 세션변수값 얻음
function get_session($session_name)
{
    return $_SESSION[$session_name];
}


// 쿠키변수 생성
function set_cookie($cookie_name, $value, $expire)
{
    setcookie(md5($cookie_name), ss_encode($value), $g4[server_time] + $expire, '/', "");
}


// 쿠키변수값 얻음
function get_cookie($cookie_name)
{
    return ss_decode(@$_COOKIE[md5($cookie_name)]);
}


function get_token() 
{ 
    $token = md5(uniqid(rand(), true)); 
    set_session("ss_token", $token); 

    return $token; 
} 

// POST로 넘어온 토큰과 세션에 저장된 토큰 비교 
function check_token() 
{ 
    // 세션에 저장된 토큰과 폼값으로 넘어온 토큰을 비교하여 틀리면 에러 
    if ($_POST['token'] && get_session('ss_token') == $_POST['token']) { 
        // 맞으면 세션을 지운다. 세션을 지우는 이유는 새로운 폼을 통해 다시 들어오도록 하기 위함 
        set_session('ss_token', ''); 
    } else { 
        alert_close('토큰 에러'); 
    } 
}


function cal_time($input_time){
	$cal_time = $input_time;
	$secs     = $cal_time % 60; 
	$cal_time = floor($cal_time / 60); 
	$minutes  = $cal_time % 60; 
	$cal_time = floor($cal_time / 60); 
	$hours    = $cal_time % 24; 
	$return_time = "";
	if ($hours < 10) $return_time = $return_time . '0' . $hours .':';
	else  $return_time = $return_time . $hours .':';

	if ($minutes < 10) $return_time = $return_time . '0' . $minutes .':';
	else  $return_time = $return_time . $minutes .':';

	if ($secs >= 10) $return_time = $return_time . $secs;
	else $return_time = $return_time . '0' . $secs;

	return $return_time;
}

function getInputWidth($str){

	$userAgent = strtolower($_SERVER['HTTP_USER_AGENT']); 

	if (preg_match('/opera/', $userAgent) || preg_match('/safari/', $userAgent)) { 
		$input_width = "13px";
		if ( $str=='W' || $str=='M') {$input_width = "21px";
		}else if ( $str=='A' || $str=='C' || $str=='D' || $str=='G' || $str=='H' || $str=='N' || $str=='O' || $str=='Q' || $str=='R' || $str=='U' || $str=='V' || $str=='X' || $str=='Y') {$input_width = "17px";
		}else if ( $str=='w' || $str=='m') {$input_width = "20px";
		}else if ( $str=='i' || $str=='l') {$input_width = "8px";
		}else if ( $str=='f' || $str=='r' || $str=='t') {$input_width = "11px";
		}

	}else{ // 여기가 주로사용하는 브라우저임

		$input_width = "11px";
		
		if ( $str=='W') {$input_width = "19px";
		}else if ( $str=='M') {$input_width = "17px";
		}else if ( $str=='A' || $str=='B' || $str=='C' || $str=='G' || $str=='H' || $str=='K' || $str=='N' || $str=='R' || $str=='S' || $str=='U' || $str=='V' || $str=='X' || $str=='Y') {$input_width = "13px";
		}else if ( $str=='D' || $str=='G' ) {$input_width = "14px";
		}else if ( $str=='O' || $str=='Q') {$input_width = "15px";
		}else if ( $str=='I' ) {$input_width = "7px";
		
		}else if ( $str=='a' || $str=='c' || $str=='s' || $str=='z' || $str=='0') {$input_width = "10px";
		}else if ( $str=='b' || $str=='d' || $str=='h' || $str=='n') {$input_width = "12px";
		}else if ( $str=='w') {$input_width = "16px";
		}else if ( $str=='m') {$input_width = "17px";
		}else if ( $str=='i' || $str=='l') {$input_width = "4px";
		}else if ( $str=='f' || $str=='r' ) {$input_width = "7px";
		}else if ( $str=='t' || $str=='j') {$input_width = "6px";

		}

	}


	return $input_width;
}

function getInputWidth2($str){
	$input_width = "10px";
	
	if ( $str=='W' || $str=='M') {$input_width = "11px";
	}else if ( $str=='A' || $str=='C' || $str=='D' || $str=='G' || $str=='H' || $str=='N' || $str=='O' || $str=='Q' || $str=='R' || $str=='U' || $str=='V' || $str=='X' || $str=='Y') {$input_width = "9px";
	}else if ( $str=='w' || $str=='m') {$input_width = "9px";
	}else if ( $str=='i' || $str=='l') {$input_width = "4px";
	}else if ( $str=='f' || $str=='r' || $str=='t') {$input_width = "5px";
	}

	return $input_width;
}

function currPosition($category,$mode=0)
{
	global $db,$company_idx;
	$query = "
	select * from ceng_directory
	where
		c_idx='$company_idx' and d_group='C' and d_category in (left('$category',3),left('$category',6),left('$category',9),'$category')
		
	order by d_category
	";

	$res = $db->query($query);
	$tc = 0;
	while ($data=$db->fetch($res)) {
		if ($data[d_type]=="1"){	
			$pos[] = "$data[d_name]";
		}else{
			//$pos[] = "<a href=\"javascript:ListLoad('".$data[d_category]."')\">$data[d_name]</a>";
			$pos[] = "<span style='cursor:pointer' onclick=\"ListLoad('".$data[d_category]."')\">$data[d_name]</span>";
		}
		$tc++;
	}
	if ($tc){
		$ret = @implode(" > ",$pos);
		if ($mode) $ret = strip_tags($ret);
	}else{
		$ret = "미지정";
	}
	return $ret;
}

function DictationCurrPosition($category,$b_idx,$mode=0)
{
	global $db,$company_idx,$qstr;
	$query = "
	select * from ceng_directory
	where
		c_idx='$company_idx' and d_group='D' and d_category in (left('$category',3),left('$category',6),left('$category',9),'$category')
		
	order by d_category
	";

	$res = $db->query($query);
	$tc = 0;
	while ($data=$db->fetch($res)) {

		$data[d_name] = stripslashes($data[d_name]);
		if ($data[d_type]=="1"){	
			$pos[] = "$data[d_name]";
		}else{
			if ($b_idx){
			//	$pos[] = "<a href=\"javascript:ViewLoad('".$b_idx."')\">$data[d_name]</a>";
				$pos[] = "<a href=\"board_cmn_view.php?b_idx=".$b_idx.$qstr."\">$data[d_name]</a>";
			}else{
				$pos[] = "$data[d_name]";
			}
		}
		$tc++;
	}
	if ($tc){
		$ret = @implode(" > ",$pos);
		if ($mode) $ret = strip_tags($ret);
	}
	return $ret;
}


function VocabularyCurrPosition($category,$vl_gubun,$mode=0)
{
	global $db,$company_idx,$qstr;
	$query = "
	select * from ceng_directory
	where
		c_idx='$company_idx' and d_group='V' and pos_category in (left('$category',3),left('$category',6),left('$category',9),'$category')
		
	order by pos_category
	";

	$res = $db->query($query);
	$tc = 0;
	while ($data=$db->fetch($res)) {

		$data[d_name] = stripslashes($data[d_name]);
		if ($data[d_type]=="1"){	
			$pos[] = "$data[d_name]";
		}else{
			$pos[] = "$data[d_name]";
		}
		$tc++;
	}
	if ($tc){
		$ret = @implode(" > ",$pos);
		if ($mode) $ret = strip_tags($ret);
	}
	return $ret;
}

function DictationCurrPosition2($category,$b_idx,$mode=0)
{
	global $db,$company_idx,$qstr;
	$query = "
	select * from ceng_directory
	where
		c_idx='$company_idx' and d_group='D' and d_category in (left('$category',3),left('$category',6),left('$category',9),'$category')
		
	order by d_category
	";

	$res = $db->query($query);
	$tc = 0;
	while ($data=$db->fetch($res)) {

		$data[d_name] = stripslashes($data[d_name]);
		if ($data[d_type]=="1"){	
			$pos[] = "$data[d_name]";
		}else{
			if ($b_idx){
			//	$pos[] = "<a href=\"javascript:ViewLoad('".$b_idx."')\">$data[d_name]</a>";
				$pos[] = "<a href=\"my_cmn_view.php?b_idx=".$b_idx.$qstr."\">$data[d_name]</a>";
			}else{
				$pos[] = "$data[d_name]";
			}
		}
		$tc++;
	}
	if ($tc){
		$ret = @implode(" > ",$pos);
		if ($mode) $ret = strip_tags($ret);
	}
	return $ret;
}


function SpeakingCurrPosition($category,$b_idx,$mode=0)
{
	global $db,$company_idx,$qstr;
	$query = "
	select * from ceng_directory
	where
		c_idx='$company_idx' and d_group='S' and d_category in (left('$category',3),left('$category',6),left('$category',9),'$category')
		
	order by d_category
	";

	$res = $db->query($query);
	$tc = 0;
	while ($data=$db->fetch($res)) {

		$data[d_name] = stripslashes($data[d_name]);
		if ($data[d_type]=="1"){	
			$pos[] = "$data[d_name]";
		}else{
			if ($b_idx){
			//	$pos[] = "<a href=\"javascript:ViewLoad('".$b_idx."')\">$data[d_name]</a>";
				$pos[] = "<a href=\"board_cmn_view.php?b_idx=".$b_idx.$qstr."\">$data[d_name]</a>";
			}else{
				$pos[] = "$data[d_name]";
			}
		}
		$tc++;
	}
	if ($tc){
		$ret = @implode(" > ",$pos);
		if ($mode) $ret = strip_tags($ret);
	}
	return $ret;
}


function SpeakingCurrPosition2($category,$b_idx,$mode=0)
{
	global $db,$company_idx,$qstr;
	$query = "
	select * from ceng_directory
	where
		c_idx='$company_idx' and d_group='S' and d_category in (left('$category',3),left('$category',6),left('$category',9),'$category')
		
	order by d_category
	";

	$res = $db->query($query);
	$tc = 0;
	while ($data=$db->fetch($res)) {

		$data[d_name] = stripslashes($data[d_name]);
		if ($data[d_type]=="1"){	
			$pos[] = "$data[d_name]";
		}else{
			if ($b_idx){
			//	$pos[] = "<a href=\"javascript:ViewLoad('".$b_idx."')\">$data[d_name]</a>";
				$pos[] = "<a href=\"my_cmn_view.php?b_idx=".$b_idx.$qstr."\">$data[d_name]</a>";
			}else{
				$pos[] = "$data[d_name]";
			}
		}
		$tc++;
	}
	if ($tc){
		$ret = @implode(" > ",$pos);
		if ($mode) $ret = strip_tags($ret);
	}
	return $ret;
}

function CurrPositionNew($gubun,$category)
{
	global $db,$company_idx,$qstr;
	$query = "
	select * from ceng_directory
	where
		c_idx='$company_idx' and d_group='$gubun' and pos_category in (left('$category',3),left('$category',6),left('$category',9),'$category')
		
	order by pos_category
	";

	$res = $db->query($query);
	$tc = 0;
	while ($data=$db->fetch($res)) {

		$data[d_name] = stripslashes($data[d_name]);
		if ($data[d_type]=="1"){	
			$pos[] = "$data[d_name]";
		}else{
			if ($b_idx){
			//	$pos[] = "<a href=\"javascript:ViewLoad('".$b_idx."')\">$data[d_name]</a>";
				$pos[] = "<a href=\"board_cmn_view.php?b_idx=".$b_idx.$qstr."\">$data[d_name]</a>";
			}else{
				$pos[] = "$data[d_name]";
			}
		}
		$tc++;
	}
	if ($tc){
		$ret = @implode(" > ",$pos);
		if ($mode) $ret = strip_tags($ret);
	}
	return $ret;
}


function GetAccessToken($FQDN,$api_key,$secret_key,$scope){

  $accessTok_Url = $FQDN."/oauth/token";
	    
  //http header values
  $accessTok_headers = array(
			     'Content-Type: application/x-www-form-urlencoded'
			     );

  //Invoke the URL
  $post_data = "client_id=".$api_key."&client_secret=".$secret_key."&scope=".$scope."&grant_type=client_credentials";
/*
SCOPE="SPEECH,STTC,TTS"

curl "https://api.att.com/oauth/v4/token" \
    --insecure \
    --header "Accept: application/json" \
    --header "Content-Type: application/x-www-form-urlencoded" \
    --data "client_id=${APP_KEY}&client_secret=${APP_SECRET}&grant_type=client_credentials&scope=${API_SCOPES}"

*/

  $accessTok = curl_init();
  curl_setopt($accessTok, CURLOPT_URL, $accessTok_Url);
  curl_setopt($accessTok, CURLOPT_HTTPGET, 1);
  curl_setopt($accessTok, CURLOPT_HEADER, 0);
  curl_setopt($accessTok, CURLINFO_HEADER_OUT, 0);
  curl_setopt($accessTok, CURLOPT_HTTPHEADER, $accessTok_headers);
  curl_setopt($accessTok, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($accessTok, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($accessTok, CURLOPT_POST, 1);
  curl_setopt($accessTok, CURLOPT_POSTFIELDS,$post_data);
  $accessTok_response = curl_exec($accessTok);
  
  $responseCode=curl_getinfo($accessTok,CURLINFO_HTTP_CODE);
  $currentTime=time();
  /*
   If URL invocation is successful fetch the access token and store it in session,
   else display the error.
  */
  if($responseCode==200)
    {
      $jsonObj      = json_decode($accessTok_response);
      $accessToken  = $jsonObj->{'access_token'};//fetch the access token from the response.
      $refreshToken = $jsonObj->{'refresh_token'};
      $expiresIn    = $jsonObj->{'expires_in'};

       if($expiresIn == 0) {
          $expiresIn = 24*60*60*365*100;
          }


      $refreshTime=$currentTime+(int)($expiresIn); // Time for token refresh
      $updateTime=$currentTime + ( 24*60*60); // Time to get for a new token update, current time + 24h

      $fullToken["accessToken"]=$accessToken;
      $fullToken["refreshToken"]=$refreshToken;
      $fullToken["refreshTime"]=$refreshTime;
      $fullToken["updateTime"]=$updateTime;
      
    }else{
 
    $fullToken["accessToken"]=null;
    $fullToken["errorMessage"]=curl_error($accessTok).$accessTok_response;

  }
  curl_close ($accessTok);
  return $fullToken;
}

?>
