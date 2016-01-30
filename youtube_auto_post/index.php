<?php
/*
Plugin Name: [YAP] Youtube Auto Post
Plugin URI:  http://phamthang.info
Description: Auto Post From Youtube Channel
Version:     1.0
Author:      Pham Quoc Thang
Author URI:  http://phamthang.info
*/
set_time_limit(0);
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

$list_channel = array("https://www.youtube.com/channel/UCLa90xY6l4sEY1sC3tlwGGA", 
	"", 
	"");
define("YOUTUBE_API_KEY", "AIzaSyBJDDqThaPUNfEORDJtmZY6DEC-0l_zZLI");
define("AUTHOR_ID", 1);
$category = array();
$daily = false;
$limit = 0;
$totalPosted = 0;
function add_my_custom_menu() {
    add_menu_page (
        'Youtube Auto Post',
        '[YAP] Youtube Auto Post',
        'manage_options',
        'yap-youtube-auto-post',
        'my_admin_page_function',
        plugin_dir_url( __FILE__ ).'icons/my_icon.png',
        '23.56'
    );
}

add_action( 'admin_menu', 'add_my_custom_menu' );

register_activation_hook(__FILE__, 'my_activation');

function my_activation() {
	wp_schedule_event(time(), 'hourly', 'my_hourly_event');
}

add_action('my_hourly_event', 'do_this_hourly');

function do_this_hourly() {
	$daily = true;
	foreach ($list_channel as $channel) {
		$youtube = new YoutubeGetVideo;

    	$channelId = $youtube->getChannelId($channel);

    	if($channelId != 'fail')
    	{
    		$youtube->getAllVideosId($channelId);
    		echo "<hr> Done!";
    	}
    	else
    	{
    		echo "<h3>Fail to get channel ID</h3>";
    	}
	}
	$daily = false;
}

function my_admin_page_function() {
	global $totalPosted;
    ?>
    <div class="wrap">
        <h2>[YAP] Youtube Auto Post</h2>
        <form method="post">
        	<input type="text" name="channel" placeholder="Youtube Channel Link" size="100px" />
        	 | Limit: <input type="text" name="limit" placeholder="50" value="50" />
        	<br />
        	<input class="button" type="submit" value="Post Videos" />
        </form>
    </div>
    <?php
    if(isset($_POST['channel']))
    {
    	$youtube = new YoutubeGetVideo;
    	$youtube->limit = intval($_POST['limit']);

    	$channelId = $youtube->getChannelId($_POST['channel']);

    	if($channelId != 'fail')
    	{
    		$youtube->getAllVideosId($channelId);
    		echo "<hr> Done! Posted " . $totalPosted . " videos";
    	}
    	else
    	{
    		echo "<h3>Fail to get channel ID</h3>";
    	}
    }
}
function inStr($s,$as)
    {
        $s=strtoupper($s);
        if(!is_array($as)) $as=array($as);
        for($i=0;$i<count($as);$i++) if(strpos(($s),strtoupper($as[$i]))!==false) return true;
        return false;
    }
class YoutubeGetVideo
{
	public $limit;
	function getChannelId($link)
	{
		$page = @file_get_contents($_POST['channel']);
		if(strpos($page,'itemprop="channelId"'))
		{
			$channelId = $this->getStr($page,'itemprop="channelId" content="','"');
			return $channelId;
		}
		else
			return "fail";
	}
	function getAllVideosId($channelId)
	{
		$url = "https://www.googleapis.com/youtube/v3/search?key=".YOUTUBE_API_KEY."&channelId=".$channelId."&part=snippet,id&order=date&maxResults=50";
		$page = file_get_contents($url);
		$json = json_decode($page,true);
		if(isset($json['nextPageToken']))
		{
			while(isset($json['nextPageToken']))
			{
				$data = $this->getAllVideoData($json);

				$json = json_decode(file_get_contents($url."&PageToken=".$json['nextPageToken']),true);
				if(!isset($json['nextPageToken']))
				{
					$data = $this->getAllVideoData($json);
				}
			}
		}
		else
		{
			$data = $this->getAllVideoData($json);

		}
	}
	function add_new_post_wp($data)
	{
		global $wp_query, $totalPosted, $limit;
		$item = current($data['items'])['snippet'];

		if($totalPosted < $this->limit)
		{
			$post = array(
			  'post_content'   => '<iframe width="560" height="315" src="https://www.youtube.com/embed/'.current($data['items'])['id'].'" frameborder="0" allowfullscreen></iframe>',
			  'post_title'     => $item['title'],
			  'post_status'    => 'publish',
			  'post_type'      => 'post',
			  'post_author'    => AUTHOR_ID,
			  'post_category'  => $category,
			  'tags_input'     => @implode(",", $item['tags']),
			);  
			query_posts('meta_key=yap_id&meta_value=' . current($data['items'])['id']);
			if(!$wp_query->found_posts)
			{
				wp_reset_query();
				$id = wp_insert_post($post, $wp_error);
				$this->Generate_Featured_Image($id, $item);
				
				add_post_meta($id, 'yap_id', current($data['items'])['id'], true);
				echo "Posted => ".$item['title']."<br />";
				$totalPosted++;
			}
		}
		
	}
	function add_new_post_daily($data)
	{
		global $wp_query;
		$item = current($data['items'])['snippet'];
		
		$post = array(
		  'post_content'   => '<iframe width="560" height="315" src="https://www.youtube.com/embed/'.current($data['items'])['id'].'" frameborder="0" allowfullscreen></iframe>',
		  'post_title'     => $item['title'],
		  'post_status'    => 'publish',
		  'post_type'      => 'post',
		  'post_author'    => AUTHOR_ID,
		  'post_category'  => $category,
		  'tags_input'     => @implode(",", $item['tags']),
		);  
		query_posts('meta_key=yap_id&meta_value=' . current($data['items'])['id']);
		if(!$wp_query->found_posts)
		{
			wp_reset_query();
			if(inStr(" ".$item['publishedAt']." ", date("20y-m-d")))
			{
				$id = wp_insert_post($post, $wp_error);
				$this->Generate_Featured_Image($id, $item);
				add_post_meta($id, 'yap_id', current($data['items'])['id'], true);
			}
			
			/*if(isset(var)et($item['thumbnails']['maxres']['url']))
				$this->Generate_Featured_Image($item['thumbnails']['maxres']['url'],$id);
			else
				$this->Generate_Featured_Image($item['thumbnails']['high']['url'],$id);
			*/
			
			//echo "Posted => ".$item['title']."<br />";

		}
	}
	function Generate_Featured_Image( $id, $item  ){
	    if(isset($item['thumbnails']['maxres']['url']))
				$thumb_url = $item['thumbnails']['maxres']['url'];
			else
				$thumb_url = $item['thumbnails']['high']['url'];
		 //'http://img.youtube.com/vi/'. $youtubeid .'/0.jpg';

        if ( ! empty($thumb_url) ) {
            // Download file to temp location
            $tmp = download_url( $thumb_url );

            // Set variables for storage
            // fix file filename for query strings
            preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $thumb_url, $matches);
            $file_array['name'] = basename($matches[0]);
            $file_array['tmp_name'] = $tmp;

            // If error storing temporarily, unlink
            if ( is_wp_error( $tmp ) ) {
                @unlink($file_array['tmp_name']);
                $file_array['tmp_name'] = '';
            }

            // do the validation and storage stuff
            $thumbid = media_handle_sideload( $file_array, $id, $desc );
            // If error storing permanently, unlink
            if ( is_wp_error($thumbid) ) {
                @unlink($file_array['tmp_name']);
                return $thumbid;
            }
        }

        set_post_thumbnail( $id, $thumbid );
	}
	function getAllVideoData($json)
	{
		
		$videos = $this->getVideoId($json);
		foreach ($videos as $video) {
			if($video != null && $video != "")
			{
				$data = $this->getVideoData($video);
				if($daily)
					$this->add_new_post_daily($data);
				else
					$this->add_new_post_wp($data);
			}
		}
		return $data;
	}
	function getVideoData($videoId)
	{
		$page = file_get_contents("https://www.googleapis.com/youtube/v3/videos?part=snippet&id=".$videoId."&key=".YOUTUBE_API_KEY);
		return json_decode($page,true);
	}
	function getVideoId($data)
	{
		$items = $data['items'];
		foreach ($items as $item) {
			$arr_video[] = $item['id']['videoId'];
		}
		return $arr_video;
	}
	function getStr($string,$start,$end){
	    $str = explode($start,$string,2);
	    $str = explode($end,$str[1],2);
	    return $str[0];
	}
}
