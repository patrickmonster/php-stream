<?
//기본 내용 셋팅
$settings = array(
	//"name" => "Radio Liefde",
	//"genre" => "Romance",
	//"url" => $_SERVER["SCRIPT_URI"],//아이툰즈등에서 사용
	"bitrate" => 96,
	"setting_directory" => "setting/",
	"music_directory" => "music/",
	"database_file" => "music.db",
	"buffer_size" => 16384,
	//"max_listen_time" => 14400,
	"randomize_seed" => 31337
);


set_time_limit(0);

function h($var){
	if (is_array($var))return array_map('h', $var);
	else return htmlspecialchars($var, ENT_QUOTES, 'UTF-8');
}

if (!is_dir($settings["setting_directory"]))//폴더가 존재하지 않을 경우
	mkdir($settings["setting_directory"]);//생성
if (!is_dir($settings["music_directory"]))//폴더가 존재하지 않을 경우
	mkdir($settings["music_directory"]);//생성

$today = date("Ymd");
$setting_file = $settings["setting_directory"] . $today . "set";

//기본 설정 로딩
if(!file_exists($setting_file) || isset($_GET[reload])) {
	//파일이 존재 하지 않음으로 기본 셋팅 / 파일 리로드 기능 첨가
	$filenames = array_slice(scandir($settings["music_directory"]), 2);
	//라이브러리는 여기서 한번만 부르자
	require_once("getid3/getid3.php");
	$getID3 = new getID3;
	//생성도 요까지
	
	foreach($filenames as $filename) {// 파일 리스트0
		$id3 = $getID3->analyze($settings["music_directory"].$filename);//분석
		file_put_contents($filename.".tmp", serialize($id3));
		$aau = intval(strval(144 * $id3["audio"]["bitrate"] / $id3["audio"]["sample_rate"]));//AAU
		echo "<br> bit = " .$id3["audio"]["bitrate"] . "sample = ". $id3["audio"]["sample_rate"] . "to AAU = ". $aau;
		if(in_array($id3["fileformat"],array("mp3"))) {//파일이 mp3 일 경우
			$playfile = array(//플레이 파일
				"filename" => $id3["filename"],
				"filesize" => $id3["filesize"],
				"fileformat" => $id3["fileformat"],
				"playtime" => $id3["playtime_seconds"],
				"audiostart" => $id3["avdataoffset"],//데이터 시작 부분
				"audioend" => $id3["avdataend"],//데이터 끝부분
				"aau" => $aau,
				"audiolength" => $id3["avdataend"] - $id3["avdataoffset"],//실제 데이터 길이
				"artist" => $id3["tags"]["id3v2"]["artist"][0],
				"title" => $id3["tags"]["id3v2"]["title"][0]
				//"hash" => md5_file($filename) //동일 파일 탐색 해쉬
				//$getid3->info['comments']['picture'] / 이미지 뽑아낼때 써 보자
				//foreach ($getid3->info['comments']['picture'] as $key => $picture_array) 
					//file_put_contents('whatever_filename_' . $key . '.' . str_replace('image/', '', $picture_array['image_mime']), $picture_array['data']);
			);
			if(empty($playfile["artist"]) || empty($playfile["title"]))
				list($playfile["artist"], $playfile["title"]) = explode(" - ", substr($playfile["filename"], 0 , -4));
			$playfiles[] = $playfile;
		}
	}
	
	/**
	[GETID3_VERSION] =&gt; 1.7.2
    [filesize] =&gt; 8859342
    [avdataoffset] =&gt; 292096
    [avdataend] =&gt; 8859214
    [fileformat] =&gt; mp3
    [audio] =&gt; Array
        (
            [dataformat] =&gt; mp3
            [channels] =&gt; 2
            [sample_rate] =&gt; 44100
            [bitrate] =&gt; 320000
            [channelmode] =&gt; stereo
            [bitrate_mode] =&gt; cbr
            [lossless] =&gt; 
            [encoder_options] =&gt; CBR320
            [compression_ratio] =&gt; 0.22675736961451
            [streams] =&gt; Array
                (
                    [0] =&gt; Array
                        (
                            [dataformat] =&gt; mp3
                            [channels] =&gt; 2
                            [sample_rate] =&gt; 44100
                            [bitrate] =&gt; 320000
                            [channelmode] =&gt; stereo
                            [bitrate_mode] =&gt; cbr
                            [lossless] =&gt; 
                            [encoder_options] =&gt; CBR320
                            [compression_ratio] =&gt; 0.22675736961451
                        )

                )

        )
	
	*/
	
	
	shuffle($playfiles);//배열을 섞어 저장함 (동일 리스트가 나오지 않게 하기 위함)
	
	//sum playtime // 전채 플레이시간을 구함
	foreach($playfiles as $playfile)
		$total_playtime += $playfile["playtime"];
	$settings["total_playtime"] = $total_playtime;//총 재생시간 적용
	$settings["start_time"] = microtime(true);//시작한 시간
	$settings["size"] = count($playfiles);
	//리스트 DB 저장
	
	//설정 파일 저장
	file_put_contents($setting_file, serialize($settingsQ));
	echo "파일 생성 값크기 :" . count($playfiles) . "<br>";
}else{
	//설정파일 로드 
	$settings = unserialize(file_get_contents($setting_file));
	//DB 로드
	$playfiles = unserialize(file_get_contents($settings["database_file"]));
}

//user agents
$icy_data = false; // 아이툰즈등에서 오는 요청은 어떻하냐?
foreach(array("iTunes", "VLC", "Winamp") as $agent)
	if(substr($_SERVER["HTTP_USER_AGENT"], 0, strlen($agent)) == $agent)
		$icy_data = true;
if($icy_data) {//아이툰즈 , 기타 미디어, 윈도우 미디어 요청은 안받아~
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
	exit;
}//그런거 안받아~


if (isset($_GET[audio])){
	
	/*
	$pos = $_GET[audio];
	if (!isset($playfiles[$pos]))
		$pos = $pos % count($playfiles);// 오버되지 않게
	*/
	
	
	// (현재시간 - 시작시간) % 총 플레이시간 = 한트랙에서 진행시간
	// 에러 예상 부분
	$tmp = (time() - $settings["start_time"]);
	
	if ($tmp > $settings["total_playtime"])
		$play_track_pos = $tmp % $settings["total_playtime"];
	else $play_track_pos = $tmp;
	echo "진행시간 :" . $tmp . "트렉포지션 :" . $play_track_pos;
	foreach($playfiles as $i=>$playfile) {
		$play_sum += $playfile["playtime"];
		if($play_sum > $play_track_pos){//곡이 끝날 타이밍의 시간과 현재 재생중일거 같은 시간비교하여 타이밍시간이 크면 끝
			break;
		}
	}
	file_put_contents("tmp.data.txt", serialize($playfiles));
	echo "연산시간 : " . ($playfile["playtime"] + ($play_track_pos - $play_sum ));
	echo "최대 진행시간 :" . $play_sum ."<br>";
	// 진행시간 * 오디오길이 / 시간 = 실제 바이트단위 위치
	//$track_pos = ($playfiles[$i]["playtime"] + () ) * $playfiles[$i]["audiolength"] / $playfiles[$i]["playtime"];
	//echo "실 :". ($play_track_pos - $play_sum);
	
	//////////////////////////
	// AAU크기를 구함
	
	
	/////////////////////
	
	/*
	set_time_limit(0);
	ob_clean();
	//@ini_set('error_reporting', E_ALL & ~ E_NOTICE);
	@apache_setenv('no-gzip', 1);
	@ini_set('zlib.output_compression', 'Off');
	header('Content-type: application/octet-stream');	
	header('HTTP/1.1 206 Partial Content');
	header('Accept-Ranges: bytes');
	
	//실제 데이터 크기(음원 파일만 전송하면 되니까)
	header('Content-Length: '.$playfiles[$i]["audiolength"]);
	
	
	header(
		sprintf(
			'Content-Range: bytes %d-%d/%d',
			$playfiles[$i][""]  * ($play_track_pos - $play_sum), // The start range
			$playfiles[$i]["audiolength"], // The end range
			$playfiles[$i]["audiolength"] // Total size of the file
		)
	);
	$f = fopen($settings["music_directory"].$playfiles[$i]["filename"], 'rb');
	$chunkSize = 8192;
	fseek($f, $playfiles[$i]["audiostart"] + ($playfiles[$i]["aau"] *  $track_pos));
	while(true){
		if(ftell($f) >= $playfiles[$i]["audiolength"])break;
		echo fread($f, $chunkSize);
		@ob_flush();
		flush();
	}*/
	echo $playfiles[$i]["aau"] . "and" .  ($playfiles[$i]["audiolength"] / $playfiles[$i]["aau"]). "<br>";
	echo sprintf(
			'Content-Range: bytes %d-%d/%d',
			$playfiles[$i]["aau"]  * intval(strval($playfiles[$i]["playtime"] - ($play_sum - $play_track_pos))), // The start range
			$playfiles[$i]["audiolength"], // The end range
			$playfiles[$i]["audiolength"] // Total size of the file
		);
	exit;
}else {
	var_dump($playfiles);
}


?>