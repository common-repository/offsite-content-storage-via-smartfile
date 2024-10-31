<?php
/**
 * Plugin Name: Offsite Content Storage via Smartfile
 * Description: The Smartfile external storage plugin allows you to store your media files on Smartfile and serve these files from Smartfile services, this would help you save space on your hosting and reduce the load on your site. Think of it like a CDN (not really) that offloads files on your site to Smartfiles own servers.
 * Author: Abdullah Irfan
 * Author URI: http://www.abdullahirfan.com
 * Version: 1.5
 * Plugin URI: http://www.fifthsegment.com
 * License: GPL2+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// require_once 'lib/Services/SmartFile/BasicClient.php';



class Smartfile_Storage_Tank{

	public $table_prefix = "sfassets";
	public $_s = null; 
	public $settings = null;
	public $enabled = false;
	public $enable_wp_copy = false;
	private $_token = "";

	private $image_mimes = array(
		'image/gif' ,
		'image/jpeg' ,
		'image/png' ,
		'image/tiff', 
		'image/bmp' ,
	);
	public function __construct( $settings_instance ){

		register_activation_hook(__FILE__,  array( $this, 'install' ) );
		add_filter('wp_get_attachment_url', array( $this, 'wpurl_to_smartfile_url') );
		add_filter('wp_generate_attachment_metadata', array( $this, 'handle_attachments_and_other_sizes'));
		add_filter('wp_handle_upload', array($this, 'media_upload_handler'));
		$this->_s = $settings_instance;
		$this->settings = $this->_s->get_settings_data();
		$this->enabled = $this->settings["enabled"];
		$this->enable_wp_copy = $this->settings["enable_wp_copy"];
		define("API_KEY", $this->settings["keyname"]);
		define("API_PWD", $this->settings["keypwd"]);
		$this->_token = "smartfile_storage_tank";


		add_action( 'template_redirect', array($this, 'my_page_template_redirect' ));
		// print_r( $x );
		// exit();

		if ($this->enabled && (!$this->settings["keyname"] || !$this->settings["keypwd"])){
			$this->_s->display_error("<b>Smartfile Offloader:</b> You must enter a SmartFile API key to be able to use the SmartFile Offloader plugin. Smartfile.com allows you to store upto <b>100Gb</b> of data for free on the Developer plan. <a href='http://smartfile.com/developer/'>Click here to check it out.</a>");
			// $this->_s->display_error("");
		}
		if (!$this->enabled){
			$this->_s->display_notice('<b>Smartfile Offloader:</b> You must enter a SmartFile API key to be able to use the SmartFile Offloader plugin. Smartfile.com allows you to store upto <b>100Gb</b> of data for free on the Developer plan. <a href="http://smartfile.com/developer/">Click here to check it out.</a>');
		}

	}

	public function verify_api_request_received(){
		$secret = $this->_s->getSecret();
		if ( $secret == $_GET["s"] ){
			return true;
		}
		return false;
	}

	public function media_upload_handler($upload){
		global $wpdb;
		$file_name = $upload["filename"];
		$file_url = $upload["url"];

		if (!in_array($upload["type"], $this->image_mimes)){
			// print "Sending this url for upload";
			$smartfile_url = $this->handle_upload( $file_url );
		}else{
			// print "Not sending for upload ";
		}
		// exit;
		return $upload;
	}

	public	function my_page_template_redirect()
	{
		global $wpdb;

		if (isset($_GET["Fseg-sf-handler"])){
			print "sf handler";
			if ($this->verify_api_request_received()){
				print "Request Verified<br>";
				if (isset($_GET["fseg_msg"])){
					print "Displaying Nag<br>";
					$this->_s->display_nag($_GET["fseg_msg"]);
				}
				if (isset($_GET["wp_url"], $_GET["sf_url"])){
					$wpdb->insert( 
						$wpdb->prefix.$this->table_prefix, 
						array( 
							'wp_url' =>$_GET["wp_url"], 
							'sf_url' =>$_GET["sf_url"],
						)
					);
					$extURL = $_GET["wp_url"];
					$dirX = wp_upload_dir();
					// $dirX["baseurl"];
					$a = str_replace($dirX["baseurl"], "", $extURL);
					$local_path = $dirX["basedir"].$a;
					print "<br>$local_path<br>";
					$removethis = array();
					$removethis[] = $local_path;
					$this->remove_media_locally($removethis);
					// print "INSERTED!";
				}
			}else{


			}
			

			exit();
		}
	}
	public function install(){
		global $wpdb;

		$this->_s->install();

		$table_name =$wpdb->prefix.$this->table_prefix; 
		$sql = "CREATE TABLE $table_name (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		  wp_url varchar(500) DEFAULT '' NOT NULL,
		  sf_url varchar(900) DEFAULT '' NOT NULL,
		  type varchar(900) DEFAULT '' NOT NULL,
		  UNIQUE KEY id (id)
		); $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$result = dbDelta( $sql );

	}

	public function make_file_name(){
		$key = "4583745";
		$time = time();
		$hash = md5($key . $time);
		$hash = substr($hash, -50);
		return $hash;
	}

	public function mime_to_extension($mime){
		$list = array(
			'image/gif' => 'gif' ,
			'image/jpeg' => 'jpg' ,
			'image/png' => 'png' ,
			'image/tiff' => 'tiff' ,
			'image/bmp' => 'bmp' ,
		);
		return $list[$mime];
	}


	public function handle_upload($file_name_path){
		$urls = array($file_name_path);	
		$array_to_send = array (
            "urls" => $urls,
        );
        $returnData = $this->_s->callApi("/SmartFilePlugin/media", 
        	"POST", 
        	$array_to_send
        );
        $this->_s->add_notice_msg_five_minutes("Uploading your files to Smartfile, please allow atleast 5 minutes for uploads to complete...");
        // print_r($returnData);
        $returnDataD = json_decode( $returnData["body"] );
        // print_r($returnDataD->uploaded);
        $urls = $returnDataD->uploaded;
        // print $urls[0];
        // exit;
        return $urls[0];
	}

	public function wpurl_to_smartfile_url( $url ){
		if (!$this->enabled){
			return $url;
		}
		global $wpdb;


		$keys = parse_url( $url ); // parse the url
     	$path = explode( "/", $keys['path']); // splitting the path
     	$file_name = end( $path ); // get the value of the last element 
		$table_name = $wpdb->prefix.$this->table_prefix;
	 	$sf_url = $wpdb->get_var( $wpdb->prepare( 
			 "
			  SELECT sf_url 
			  FROM $table_name 
			  WHERE wp_url = %s
			 ", 
			 $file_name
		 ));

	 	if (strlen($sf_url)>0){
	 		return $sf_url;
	 	}else{
			return $url; 		
	 	}
	}

	public function handle_attachments_and_other_sizes( $args ){
		if (!$this->enabled){
			return $args;
		}
		// 	print "<pre>";
		// 	print "handle_attachments_and_other_sizes\n";
		// print_r ( $args );
		// print "</pre>";
		// exit;
		if (!isset($args["file"])){
			// print "Not my file";
			return $args;	
		}
		global $wpdb;
		$media_bundle = array();
		$dir = wp_upload_dir();
		$path_for_api = $dir["baseurl"] . "/";

		$dir = $dir['basedir'];  
		$dir .= DIRECTORY_SEPARATOR  ;
		$file = $args["file"];

		$file_path = $dir . $args["file"];
		$dir = dirname( $file_path );
		$dir .= DIRECTORY_SEPARATOR  ;
		// print "Dir : $dir<br>";
		$path_for_api .= $args["file"];
		try { 
			// print $path_for_api . "<br>";
			// exit;

			$smartfile_url = $this->handle_upload($path_for_api );
			$keys = parse_url( $args["file"] ); // parse the url
	     	$path = explode( "/", $keys['path']); // splitting the path
	     	$file_name = end( $path ); // get the value of the last element 

			$media_bundle[] = $file_path;
			
		} catch (Exception $e) {
			 // print $e->getMessage();
		}

		$sizes = $args["sizes"];
		$media_bundle[] = $file_path . $args["file"];
		foreach ($sizes as $size_store) {
			$sized_file_name = $size_store["file"];
			$sized_file_name_with_path = $dir.$sized_file_name;
			$dirX = wp_upload_dir();
			$link_to_send_to_api = $dirX["baseurl"].$dirX["subdir"]."/";
			$link_to_send_to_api .= $sized_file_name;

			try { 
				$smartfile_url = $this->handle_upload($link_to_send_to_api);

				$media_bundle[] = $sized_file_name_with_path;
				// exit();
				
			} catch (Exception $e) {
				 // print "Exception <br>";
				 // print $e->getMessage();
			}
		}
		// $this->remove_media_locally( $media_bundle );
		// exit;
		return $args;
		
	}

	public function record_media_size ( $file ){
		$filesz = filesize( $file ) ;
		$previous_bytes = get_option( $this->_token . "total_bytes_uploaded" );
		if ( $previous_bytes ){
			$filesz = floatval( $previous_bytes ) + ($filesz);
			update_option( $this->_token . "total_bytes_uploaded" , $filesz );
		}else{
			update_option( $this->_token . "total_bytes_uploaded" , $filesz );
		}
	}

	public function remove_media_locally( $media_bundle ){
		foreach ($media_bundle as $file) {
			$this->record_media_size( $file );
			if (!$this->enable_wp_copy){
				unlink( $file );	
				// print "Media removed";
			}else{
				// print "Switched off";
			}
		}
	}
}




require_once( 'admin/fseg-admin.php' );

        $schema = array(
            "type" => "object",
            "title" => "Types",
            "properties" => array(
            	"enabled"=>array(
			      "title" => "Enabled",
			      "description" => "Enable Smartfile Offloading",
			      "type" => "boolean",
            	),
                "keyname"=>array(
                	"title" => "API key", 
                	"description" => "Don't have a key? Obtain one from <a href='http://www.smartfile.com/developer'>here</a>",
                    "type"=>"string",
                ),
                "keypwd"=>array(
                	"title" => "API Password", 
                	"description" => "Your API Password can be obtained from <a href='https://app.smartfile.com/dev/'>this page</a>.",
                    "type"=>"string",
                ),
                "enable_wp_copy"=>array(
			      "title" => "Keep Media Copies Locally",
			      "description" => "If this option is enabled your media files will also remain on the Wordpress filesystem so that incase you disable Offloading in future your files will be served from your local Wordpress install.",
			      "type" => "boolean",
            	),
            ),
            "required"=>array(
                "keyname"
            )
        );
        
        $form = array(
            "*",
            array(
                "type"=>"submit",
                "title"=>"Save Settings"
            ),

        );
        $model = array(
        	"keyname" => "",
        	"keypwd" => "",
            "enabled"=> false,
            "enable_wp_copy" => false,
        );


        // Please set reloadargs to true the first time you use this
        $Fseg_args = array(
            "schema"=>$schema,
            "form"=>$form,
            "formData"=>$model,
            "reloadargs"=>false,
            "settings_page_name" => "fseg_options",
            "settings_page_title" => "SmartFile Offloader",
            "settings_page_title_other_info" => "Settings | <a href='http://www.fifthsegment.com/support'>Help and Support</a> | <a href='http://www.fifthsegment.com/support'>Feedback</a>",
            "settings_base" =>  "fseg_smartfile_",
            "settings_description" => "Once you enter your keys and enable the Offloading feature, any new media that you upload to WordPress will automatically be uploaded to your Smartfile account as well and it will be served from Smartfile hence saving you both bandwidth and storage space. <i>Also, please note that this plugin is not in any way produced by or related to Smartfile.com, it's a third party implementation that simply connects WordPress to the Smartfile platform</i>.<br><br>Modify your API keys or Enable/Disable Smartfile Offloading from this page<br>",
        );


$_S = new Fseg_Admin( $Fseg_args );
$sf = new Smartfile_Storage_Tank( $_S );

?>