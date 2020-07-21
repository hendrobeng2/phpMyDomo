<?php
class PMD_Page extends PMD_Root_Page{

	var $vars;			// config

	//----------------------------------------------------------------------------------
	//----------------------------------------------------------------------------------
	function __construct(& $class){
		parent::__construct($class);
		$this->RequirePageConf();
		
		if(!$this->vars['url_server']){
			$this->vars['url_server']="http://{$this->vars['server_host']}:{$this->vars['server_port_web']}";
		}
	}

	//----------------------------------------------------------------------------------
	function Run(){
		//$this->LoadApiClient();

		$this->SetHeader('js/squeeze.js',	'js_global');
		$this->SetHeadJavascript("var pmd_sqz_prefs={} ;");
		foreach($this->vars as $k => $v){
			if(is_numeric($v)){
				$this->SetHeadJavascript("pmd_sqz_prefs.$k=$v;");
			}
			else{
				$this->SetHeadJavascript("pmd_sqz_prefs.$k='$v';");
			}
		}

		if($_GET['do']=='ajax'){
			$this->_Ajax();
			exit;
		}

		$data['players']=$this->_RequestPlayersWithStatus();
		$data['prefs']=$this->vars;
		$data['agent']=$this->_DetectMobileBrowser();
		
		// debug @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
		if($_GET['debugall']){
			echo "<hr><pre>\n";print_r($data);echo "\n</pre>\n\n";exit;
		}
						
		$this->Assign('data',	$data);
		$this->Display($page);
	}

	//----------------------------------------------------------------------------------
	private function _RequestPlaylists(){
		$r=$this->_Request(array('',array('playlists',0,0)));
		$arr=$r['result']['playlists_loop'];
		if(is_array($arr)){
			foreach($arr as $k => $row){
				if(preg_match('#^tempplaylist#', $row['playlist'])){
					unset($arr[$k]);
				}
			}
		}
		return $arr;
	}

	//----------------------------------------------------------------------------------
	private function _RequestPlaylistAdd($playlist_id,$url,$title=''){
		//$title 	=rawurlencode($title);
		$r=$this->_Request(array('',array('playlists','edit',"cmd:add","playlist_id:$playlist_id","url:$url")));
		$arr=$r; //['result']
		return $arr;
	}

	//----------------------------------------------------------------------------------
	private function _RequestPlayers(){
		$r=$this->_Request(array('',array('serverstatus',0,999)));
		$arr=$r['result']['players_loop'];
		if(is_array($arr)){
			foreach($arr as $a){
				$id=$a['playerid'];
				$out[$id]=$a;
				//echo "{$a['name']}	{$a['ip']}	{$a['playerid']}\n";
			}
		}
		else $out=array();
		return $out;
	}

	//----------------------------------------------------------------------------------
	private function _RequestPlayersWithStatus($limit=5){
		$players=$this->_RequestPlayers();
		foreach($players as $id => $row){
			$row['status']	= $this->_RequestPlayerSlatus($row['playerid'],$limit);
			if($formated = $this->_FormatPlayer($row)){
				$out[$id]	= $formated;
			}
		}
		return $out;
	}

	//----------------------------------------------------------------------------------
	private function _RequestPlayer($id, $limit=5){
			$row['playerid']=$id;
			$row['status']	= $this->_RequestPlayerSlatus($id, $limit);
			return $this->_FormatPlayer($row, true);
	}

	//----------------------------------------------------------------------------------
	private function _RequestButtonAll($type,$v1,$v2){
		$players=$this->_RequestPlayers();
		$debug=1;
		foreach($players as $js_id => $row){
			$r=$this->_Request(array($row['playerid'],array($type,$v1,$v2)), 1, $debug);
		}
		return $out;
	}


	//----------------------------------------------------------------------------------
	private function _RequestButton($id,$type,$v1,$v2){
		return $this->_RequestButtonWeb($id,$type,$v1,$v2);
	}

	//----------------------------------------------------------------------------------
	private function _RequestButtonWeb($id,$type,$v1,$v2){
		$debug=1;
		$r=$this->_Request(array($id,array($type,$v1,$v2)), 0, $debug);
		echo "ok\n";
	}

	//----------------------------------------------------------------------------------
	private function _RequestButtonCli($id,$type,$v1,$v2){
		$command="$id $type $v1 $v2";
		
		$socket = fsockopen($this->vars['server_host'], $this->vars['server_port_cli'], $errno, $errstr);
		$buffer = ""; 
		if($socket){ 
			fputs($socket, "$command \r\n"); 
			$buffer .=fread($socket,4096);
		} 
		@fclose($socket); 
		echo "Result: ".urldecode($buffer);
	}

	//----------------------------------------------------------------------------------
	private function _RequestPlayerSlatus($id, $max=1){
		//$r=$this->_Request(array($id,array('status',0,999)));
		$r=$this->_Request(array($id,array('status', '-', $max, "tags:aAbcdeghiIJKlLmNoqrStTuxy")));
		$out=$r['result'];
		if(! is_array($out)){
			$out=array();
		}
		return $out;
	}


	//----------------------------------------------------------------------------------
	private function _Ajax(){
		if($_GET['act']=='but' ){
			$type=$_GET['type'];
			if($type=='pcp'){
				$ip		=$_GET['id'];
				$command=$_GET['v1'];
				if($command=='restartsqlt'){
					$p['server_url']="http://{$ip}/cgi-bin/restartsqlt.cgi";
					echo "Restarting SqueezeLite at $ip..";
					$this->_Request($p, 0);
				}				
			}
			else{ // button, playlist, time
				$id=$_GET['id'];
				$v1=$_GET['v1'];
				$v2=$_GET['v2'];
				if($v1=='undefined'){$v1='';}
				if($v2=='undefined'){$v2='';}
				if($id=="ALL"){
					$r=$this->_RequestButtonAll($type,$v1,$v2);				
				}
				else{
					$r=$this->_RequestButton($id,$type,$v1,$v2);
				}
				
			}
		}
		elseif($_GET['act']=='players' ){
			$limit=$_GET['limit'] or $limit=5;
			if( $id=$_GET['id']){
				$out[$id]=$this->_RequestPlayer($id, $limit);
			}
			else{
				$out=$this->_RequestPlayersWithStatus($limit);
			}
			echo json_encode($out);
		}
		elseif($_GET['act']=='playlists' ){
			$out=$this->_RequestPlaylists();
			echo json_encode($out);
		}
		elseif($_GET['act']=='pl_add' ){
			if($id=$_GET['id'] and $url=$_GET['url'] and $title=$_GET['title']){
				$out=$this->_RequestPlaylistAdd($id,$url,$title);
				echo json_encode($out);
			}
		}
		else{
			echo "Invalid 'act' ";
		}
		exit;
	}


	//----------------------------------------------------------------------------------
	private function _FormatPlayer($row, $mode_mini=false) {

		$out['playerid']	=$row['playerid'];
		$out['name']		=$row['name'];
		$out['f_jsid']		=strtolower(str_replace(':','',$row['playerid']));
		
		$status=$row['status'];
		$out['time']		=$status['time'];		
		$out['h_time']		=$this->_FormatSeconds($status['time']);
		
		$out['firmware']		=$row['firmware'];
		if(!$row['firmware'] and !$mode_mini){
			return FALSE; // hide ghost player
		}
		$out['mode']		=$status['mode'];
		
		list($out['ip'],$trash['port'])=explode(':',$status['player_ip']);
		//$out['mac']		=strtoupper($row['playerid']);
		//$out['pl_mode']		=$status['playlist mode'];

		$out['volume']		=$status['mixer volume'];
		$out['f_repeat']	=$status['playlist repeat'];
		$out['f_shuffle']	=$status['playlist shuffle'];
		
		//buttons state
		if($status['mode'] =='play')	$out['f_states']['play']	=1;
		if($status['mode'] =='stop')	$out['f_states']['stop']	=1;
		if($status['mode'] =='pause')	$out['f_states']['pause']	=1;
		
		if(!$out['f_repeat'])			$out['f_states']['repeat_0']=1;
		if($out['f_repeat']==1)			$out['f_states']['repeat_1']=1;
		if($out['f_repeat']==2)			$out['f_states']['repeat_2']=1;
		
		if(!$out['f_shuffle'])			$out['f_states']['shuffle_0']=1;
		if($out['f_shuffle']==1)		$out['f_states']['shuffle_1']=1;
		if($out['f_shuffle']==2)		$out['f_states']['shuffle_2']=1;
		
		if($row['power'])				$out['f_states']['power']	=1;
		if($out['volume'] < 0)			$out['f_states']['mute']	=1;
		
		$out['volume']=abs($out['volume']); 

		//make current song & playlists ---------------------------
		if(is_array($status['playlist_loop'])){
			foreach($status['playlist_loop'] as $k => $arr){
				
				//$tmp=$arr;
				$tmp=array();

				$tmp['title']		=$this->_FormatTitle($arr['title']);
				$tmp['h_title']		=preg_replace('# \s*-\s*$#','',$tmp['title']);

				$tmp['artist']		=$this->_FormatTitle($arr['artist'],0);
				$tmp['h_artist']	=$tmp['artist'];
				if($tmp['h_artist']=="No Artist"){
					$tmp['h_artist']='';
				}

				$tmp['album']		=$this->_FormatTitle($arr['album']);
				$tmp['h_album']		=$tmp['album'];
				if($tmp['h_album']=="No Album"){
					$tmp['h_album']='';
				}

				$tmp['duration']	=$arr['duration'];
				$tmp['h_duration']	=$this->_FormatSeconds($arr['duration']);

				$tmp['id']		=$arr['id'];
				$tmp['url']		=$arr['url'];
				$tmp['track']	=$arr['tracknum'];

				$tmp['h_track']	='';
				if($tmp['h_album']){
					$tmp['track'] and $tmp['h_track']=str_pad($tmp['track'], 2, '0', STR_PAD_LEFT);
				}

				$tmp['year']	=$arr['year'];
				$tmp['year']	and $tmp['h_year']	="<u>{$tmp['year']}</u>";

				$tmp['bpm']		=$arr['bpm'];
				$tmp['bpm']		=str_replace('null', '', $tmp['bpm']);

				$tmp['url_img']	='';
				$arr['coverid']		and $tmp['url_img']=$this->vars['url_server']."/music/{$arr['coverid']}/cover.png";


				$type		=strtolower($arr['type']);	//known types 'mp3' , 'mp3 radio'
				list($filetype, $radio)=explode(' ', $type);
				if($radio){
					$tmp['type']	='radio';
					$tmp['filetype']=$filetype;
				}
				else{
					$tmp['type']	='file';
					$tmp['filetype']=$type;
				}

				$tmp['radio_name']='';
				if($status['remote']){ //this is a remote (net or radio)
					$arr['artwork_url']	and $tmp['url_img']	=$arr['artwork_url'];
					$tmp['url_img']	=preg_replace('#^/imageproxy/#','', $tmp['url_img']);
					$tmp['url_img']	=preg_replace('#/image.jpg$#','', $tmp['url_img']);
					$tmp['url_img']=urldecode($tmp['url_img']);
					
					$tmp['h_album']		='';
					if($tmp['h_artist'] ==''){
						list($artist,$title,$empty)=explode(' - ', $tmp['h_title']);
						if($title and !$empty ){
							$tmp['artist']	=trim($artist); // else not shown
							$tmp['h_artist']=trim($artist);

							$tmp['h_title']	=trim($title);
						}
					}
					
					if($tmp['type']=='radio'){
						$tmp['radio_name']	=$status['current_title'];
			
						//fix bad formatted radio
						/*
						similar_text($tmp['radio_name'],$arr['artist'],$perc);
						if($perc >= 70){
							list($artist,$title)=explode(' - ',$arr['title']);
							if(trim($title)){
								$tmp['artist']	=$this->_FormatTitle($artist,0); //. " ($perc)";
								$tmp['title']	=$this->_FormatTitle($title);
								$arr['remote_title'] and $tmp['radio_name']=$arr['remote_title'];
							}
						}
						*/

					}
					else{
						$tmp['type']="net";
					}
				}
				
				
				if($k==0){	//only first song
					list($rate,$info)		=explode(' ',$arr['bitrate']);
					$tmp['bitrate']			=trim(preg_replace('#^(\d+).*#','$1',$rate));
					$tmp['bitrate_unit']	=trim(preg_replace('#^'.$tmp['bitrate'].'#','',$rate));
					$tmp['bitrate_info']	=trim($info);

				
					if( $search_title 	=$this->_makeSongFullTitle($arr)){

						$links['url_youtube']['title']		='YouTube';
						$links['url_youtube']['icon']		='youtube';
						$links['url_youtube']['href']		='https://www.youtube.com/results?search_query='.urlencode($search_title);

						$links['url_allmusic']['title']		='AllMusic';
						$links['url_allmusic']['icon']		='database';
						$links['url_allmusic']['href']		='http://www.allmusic.com/search/songs/'.urlencode($search_title);

						$links['url_google']['title']		='Google';
						$links['url_google']['icon']		='google';
						$links['url_google']['href']		='https://www.google.com/search?q='.urlencode($search_title);					

						$links['url_score']['title']	='Sheet Music';
						$links['url_score']['icon']		='music';
						$links['url_score']['href']		='https://www.google.com/search?q='.urlencode($search_title. " Sheet Music");					

						$links['url_lyrics']['title']	='Lyrics';
						$links['url_lyrics']['icon']	='microphone';
						$links['url_lyrics']['href']	='https://www.google.com/search?q='.urlencode($search_title. " Lyrics");					
					}					
					$out['song'] 	= $tmp;
					$out['song']['links']=$links;
				}
				
				//store it
				$out['playlist'][$k]=$tmp;	
			}
		}

		//$row['remoteMeta']['f_duration']=$this->_FormatSeconds($row['remoteMeta']['duration']);

		$out['raw']		=$row;
		return $out;
	}

	//----------------------------------------------------------------------------------
	private function _FormatTitle($txt,$uc=1) {
		if($txt =='-1')	{$txt='';}
		$uc and $txt=ucwords(strtolower($txt));
		$txt=str_replace('No Album','',$txt);
		$txt=trim($txt);
		return $txt;
	}
	//----------------------------------------------------------------------------------
	private function _makeSongFullTitle($metas) {
		$metas['artist'] and $full_title .="{$metas['artist']} - ";
		$full_title .="{$metas['title']}";
		return $full_title;
	}

	//----------------------------------------------------------------------------------
	private function _FormatSeconds($seconds) {
		if ($seconds <= 0) return '';

		$hours = intval($seconds/pow(60,2));
		$minutes = intval($seconds/60)%60;
		$seconds = $seconds%60;
		$out = "";
		if ($hours > 0) 	$out .= $hours . ":"; 
		if ($minutes >= 0)	$out .= str_pad($minutes,2,'0',STR_PAD_LEFT) . ":";
		if ($seconds >=  0)	$out .= str_pad($seconds,2,'0',STR_PAD_LEFT);
		//if(!$hours && !$minutes) $out .='sec';
		return trim($out);
	}


	//----------------------------------------------------------------------------------
	private function _Request($p,$return_transfer=1,$echo=0){
		if($server_url= $p['server_url']){
			//onlyrequest this url
			$params=array();
		}
		else{
			$server_url=$this->vars['url_server']."/jsonrpc.js";
			
			if(count($p[1])==3 and $p[1][2]==''){
				unset($p[1][2]);
			}
			
			$params=array(
				'id'=>1,
				'method'=>'slim.request',
				'params'=>$p
			);
			$params=json_encode($params);
			if($echo) {echo "Sending :". $params."<br><br>\n\n";}
		}

		$ch = curl_init($server_url); 
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($params))
		);       
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  'GET');
		curl_setopt($ch, CURLOPT_USERAGENT, 'phpMyDomo');

		if($return_transfer){
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		}
		else{
			if($echo) {echo "Blind mode : Ignoring answer...";}

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
			curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10); 
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);			
		}
		
		$result = curl_exec($ch);
		curl_close($ch);

		if($echo) {echo "<pre>\n";print_r( json_decode($result,true))."\n</pre>\n\n";}
		if($return_transfer){
			return json_decode($result,true);
		}
	}

	//----------------------------------------------------------------------------------
	private function _ListSounds(){
			$this->o_kernel->PageError(500,"The clock sound directory ($path_files) does not contain any .mp3 file.");			
	}

	//----------------------------------------------------------------------------------
	private function _DetectMobileBrowser(){
			$useragent=$_SERVER['HTTP_USER_AGENT'];
			if(preg_match('#android#i', $useragent)){
				return 'android';
			}
			if(preg_match('#iPhone|iPad|iPod|IOS#i', $useragent)){
				return 'ios';
			}
	}


} 
?>