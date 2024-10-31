<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// print $plugins_url;
// exit();

class Fseg_Admin{
    private $plugins_url = null;
    private $plugin_title = null;
    private $base=null;
    private $schema;
    private $form;
    private $model;
    public $msg;
    public $notice_class;
    private $API_BASE_URL = "http://api.fifthsegment.com/v1";
    function __construct($args){

        $this->plugins_url = plugins_url( 'bower_components', __FILE__ );
        
        $this->plugin_title = $args["settings_page_name"];
        $this->plugin_title_option = $args["settings_page_title"];
        $this->plugin_title_option_other_info = $args["settings_page_title_other_info"];
        $this->plugin_settings_description = $args["settings_description"];
        $this->base =  $args["settings_base"];
        $this->model = $args["formData"];
        $this->schema = $args["schema"];
        $this->form = $args["form"];
        // var_dump($args);
        // exit();
        $defaults = array(
            "schema"=>$this->schema,
            "form"=>$this->form,
            "formData"=>$this->model,
        );
        if ($args["reloadargs"]){
            $this->set_settings($defaults);
        }else{
            // print "Updating settings";
            $this->update_schema($defaults);
            // exit();
        }   
        $this->init();
    }

    public function callApi($endpoint = "/", $method, $data){
        $returnData = array();
        $key_store_name = $this->base.'_fseg_smartfileplugin_api';
        $apikey = get_option ( $key_store_name );
        $url = $this->API_BASE_URL.$endpoint."/?apikey=".$apikey;
        // print $url;
        $data = json_encode($data);
        $response = wp_remote_request( $url, [
            'method' => $method,
            'body' => $data
        ]);
        $rd = array();
        if ( is_wp_error( $response ) ){
            $rd["ok"] = false;
        }else{
            // print_r( $response );

            $rd["ok"] = true;
            $rd["body"] = $response["body"];
            
        }
        return $rd;
    }

    function notifier() {
        ?>
        <div class="<?php echo $this->notice_class ?>">
            <p><?php echo $this->msg; ?></p>
        </div>
        <?php
    }


    public function display_notice( $msg ){
        $this->msg = $msg;
        $this->notice_class="updated";
        add_action( 'admin_notices', array($this, 'notifier' ) );
    }

    public function display_error( $msg ){
        $this->msg = $msg;
        $this->notice_class="error";
        add_action( 'admin_notices', array($this, 'notifier' ) );
    }

    public function add_notice_msg_five_minutes( $msg ){
        $this->msg = $msg;
        $this->notice_class="updated";
        $key_store_name = $this->base.'_fseg_smartfileplugin_fimsg';
        update_option ( $key_store_name , $msg);
        $key_store_name = $this->base.'_fseg_smartfileplugin_fitim';
        $msg = time();
        update_option ( $key_store_name , $msg);
    }

    public function display_notice_msg_five_minutes(){
        $key_store_name = $this->base.'_fseg_smartfileplugin_fitim';
        $t=get_option ( $key_store_name );
        $dbdate = ($t);
        $x = time() - $dbdate;
        // print "<h1>dbdate : $dbdate | x = $x</h1>";
        if ( (time() - $dbdate) < (5 * 60) ) {
            $key_store_name = $this->base.'_fseg_smartfileplugin_fimsg';
            $msg = get_option ( $key_store_name );
            $this->msg = $msg;
            $this->notice_class="updated";
            add_action( 'admin_notices', array($this, 'notifier' ) );
        }

    }

    public function install(){
        $defaults = array(
            "schema"=>$this->schema,
            "form"=>$this->form,
            "formData"=>$this->model,
        );

        // $key_store_name = $this->base.'_fseg_smartfileplugin_api';
        $this->set_settings($defaults);
        $key_store_name = $this->base.'_fseg_smartfileplugin_api';
        $secretkey_store_name = $this->base.'_fseg_smartfileplugin_secret';
        $email = get_option( "admin_email" );
        $siteurl = get_option( "siteurl" );
        $array_to_send = array (
            "siteurl" => $siteurl,

            "email" => $email,
        );
        $apikeyprevious = get_option( $key_store_name );
        $apisecretprevious = get_option( $secretkey_store_name );
        if ( !$apikeyprevious && !$apisecretprevious ){
            $returnData = $this->callApi("/SmartFilePlugin/register", "POST", $array_to_send);
            // print_r ( $returnData );
            if ($returnData["ok"]){
                // print "Ok!";
                $returnedData = json_decode($returnData["body"],true);
                // print_r($returnedData);
                // $returnedData = 
                $this->msg = "Plugin Activated. ". $returnedData["info"];
                add_action( 'admin_notices', array($this, 'notifier' ) );
                // print_r ($returnData["body"]);
                update_option( $key_store_name , $returnedData["apikey"] );
                update_option( $secretkey_store_name , $returnedData["apisecret"] );
            }else{
                $this->msg = "ERROR : The SmartFile Plugin failed to activate its license. ". $returnData["info"];
                add_action( 'admin_notices', array($this, 'notifier' ) );
            }
        }



        // exit();
    }

    public function get_settings_data(){
        return $this->get_settings()["formData"];
    }

    public function update_schema($data){
        $s=$this->get_settings();
        $s["schema"]=$data["schema"];
        $s["form"]=$data["form"];
        $this->set_settings($s);
    }

    public function init(){
        $this->load_scripts();
        // Register plugin settings
        // add_action( 'admin_init' , array( $this, 'register_settings' ) );

        // Add settings page to menu
        add_action( 'admin_menu' , array( $this, 'add_menu_item' ) );
        add_action( 'wp_loaded' , array( &$this , 'handle_api_requests'), 99 );
        $m = $this->get_nag_message();

        if (strlen($m)>0){
            // print "Displaying error message";
            $this->display_error($m);
        }
        $this->display_notice_msg_five_minutes();

    }


    public function display_nag( $msg ){
        $key_store_name = $this->base.'_fseg_smartfileplugin_nagmsg';
        $msg = urldecode($msg);
        update_option ( $key_store_name , $msg);
        $this->display_error($msg);
    }

    public function get_nag_message(){
        $key_store_name = $this->base.'_fseg_smartfileplugin_nagmsg';
        $apikey = get_option ( $key_store_name );
        return $apikey;       
    }

    public function getKey(){
        $key_store_name = $this->base.'_fseg_smartfileplugin_api';
        $apikey = get_option ( $key_store_name );
        return $apikey;
    }

    public function getSecret(){
        $secretkey_store_name = $this->base.'_fseg_smartfileplugin_secret';
        $apikey = get_option ( $secretkey_store_name );
        return $apikey;
    }

    public function get_settings($t){
        $key = $this->getKey();
        if (strlen($key)<1){
            $this->display_error( "ERROR : Plugin failed to activate. Please reactivate the plugin.");
        }
        
        if (($t)){
            $option_name = $this->base."$t";
            $settings = get_option( $option_name );
            return $settings;
        }
        $option_name = $this->base."_settings_object";
        $settings = get_option( $option_name );
        return $settings;
    }

    public function set_settings($data){
        $option_name = $this->base.'_settings_object';
        $return = update_option($option_name, $data);
        // var_dump($return);
        if (is_wp_error($return)){
            $error_string = $result->get_error_message();
            echo '<div id="message" class="error"><p>' . $error_string . '</p></div>';

        }
    }


    public function load_defaults(){


    }

    public function handle_api_requests(){
        $handle = false;
        if ( is_user_logged_in() ){
            if (isset($_GET["page"]) && $_GET["page"]== $this->plugin_title){
                if (isset($_GET["API"])){
                    if (current_user_can( "manage_options" )){
                        $handle = true;                     
                    }else{
                        header("HTTP/1.0 403 Not Allowed");
                        print "NOTHING";
                        exit();
                    }
                }
            }
        }
        if ($handle){
            $settings = array();
            if ($_GET["action"]=="DEVMODE"){
                $this->load_defaults();
                $settings = $this->get_settings();
                // print_r($settings);
                // exit();
            }
            if ($_GET["action"]=="CONSOLE"){
                // $msg = $this->regions_remove();
                $t = $_GET["option"];
                $settings = $this->get_settings($t);
            }
            if ($_GET["action"]=="UPDATE"){
                // $msg = $this->regions_remove();
                $data =json_decode(file_get_contents('php://input'),true);;
                $this->set_settings($data);
                $settings = $this->get_settings();
                $email = get_option( "admin_email" );
                $data = $data["formData"];
                $array_to_send = array (
                    "sf_api" => $data["keyname"],
                    "sf_pwd" => $data["keypwd"],
                    "email" => $email,
                );
                // print_r( $data );
                $returnData = $this->callApi("/SmartFilePlugin/register", "PUT", $array_to_send);
                // print_r( $returnData );
                if ($returnData["ok"]){

                }
            }
            if ($_GET["action"]=="GET"){
                // $msg = $this->regions_remove();
                $settings = $this->get_settings();
            }

            // $settings = $this->get_settings();
            $json = json_encode($settings);
            if ($json=="false"){
                $json = "{}";
            }
            header('Content-Type: application/json');
            print $json;
            exit();
        }else{
            // http_response_code(403);

        }
    }

    public function load_scripts(){
        $pluginsurl = $this->plugins_url;
        if (preg_match('/page='.$this->plugin_title.'/i', $_SERVER['QUERY_STRING'])) {
            wp_enqueue_script( 'angular',$pluginsurl . '/angular/angular.min.js', array(), '1.0.0', true );
            wp_enqueue_script( 'angular-sanitize',$pluginsurl . '/angular-sanitize/angular-sanitize.min.js', array(), '1.0.0', true );
            wp_enqueue_script( 'tv4',$pluginsurl . '/tv4/tv4.js', array(), '1.0.0', true );
            wp_enqueue_script( 'objectpath',$pluginsurl . '/objectpath/lib/ObjectPath.js', array(), '1.0.0', true );
            wp_enqueue_script( 'angular-schema-form',$pluginsurl . '/angular-schema-form/dist/schema-form.min.js', array(), '1.0.0', true );
            wp_enqueue_script( 'bootstrap-decorator',$pluginsurl . '/angular-schema-form/dist/bootstrap-decorator.min.js', array(), '1.0.0', true );
        }
    }

    public function load_styles(){
        $dirpath = plugins_url( 'styles', __FILE__ );
        if (preg_match('/page='.$this->plugin_title.'/i', $_SERVER['QUERY_STRING'])) {
            wp_enqueue_style( 'bootstrap', "$dirpath/bootstrap.css" );
            wp_enqueue_style( 'bootstrap-theme', "$dirpath/bootstrap-theme.css" );
        }
    }

    public function getUsage(){
        $response = $this->callApi("/SmartFilePlugin/service","GET", array());
        if ($response["ok"]){
            $json = json_decode($response["body"]);
            $a = $json->consumed;
            $a = floatval($a);
            $a = number_format($a, 2, '.', '');
            $json->consumed = $a;
            return $json;
        }
        return false;
    }

    public function settings_page(){

        $dirpath = plugins_url( 'js', __FILE__ );
        wp_enqueue_script( 'fseg_app',$dirpath . '/fseg_app.js', array(), '1.0.0', true );

        $this->load_styles();
        print "<h2>$this->plugin_title_option</h2>";
        print "<h4>$this->plugin_title_option_other_info</h4>";
        print "<p>$this->plugin_settings_description</p>";
        $path = dirname(__FILE__);

        $page = file_get_contents($path.'/index.html.php');
        $u = $this->getUsage();
        // print_r($u);
        if ($u){
            if ($u->freeplan){
                $str = "You have used ";
                $str .=  "<b>$u->consumed $u->allowedunit</b>";
                $str .= " of ";
                $str .=  "<b>$u->allowed $u->allowedunit</b>";
                $str .= " of usage allowed by the Free version of this plugin. <br>Activating this plugin would allow you to offload upto <b>100Gb</b> of content on Smartfile, click here to <b><a href='http://www.fifthsegment.com/product/offsite-content-storage-with-smartfile-premium/'>activate</a></b>. ";
                print "<a style='color:#b30000'>$str</a>";
            }

        }
        echo $page;
        $adminurl =  admin_url( 'options-general.php?page='.$this->plugin_title.'&API=true' );
        echo "<script>var fsegAdminUrl = '$adminurl'</script>";

       
    }

    function my_admin_add_help_tab () {

        $screen = get_current_screen();

        // Add my_help_tab if current screen is My Admin Page
        $screen->add_help_tab( array(
            'id'    => 'my_help_tab',
            'title' => __('My Help Tab'),
            'content'   => '<p>' . __( 'Descriptive content that will show in My Help Tab-body goes here.' ) . '</p>',
        ) );
    }


    public function admin_page(){
        $dirpath = plugins_url( 'js', __FILE__ );
        wp_enqueue_script( 'fseg_app',$dirpath . '/fseg_app_admin.js', array(), '1.0.0', true );

        $this->load_styles();
        print "<h1>Settings</h1>";
        $path = dirname(__FILE__);

        $page = file_get_contents($path.'/admin.html');
        echo $page;
        $adminurl =  admin_url( 'options-general.php?page='.$this->plugin_title.'&API=true' );
        echo "<script>var fsegAdminUrl = '$adminurl'</script>";

    }

    public function add_menu_item () {


        $page = add_menu_page(
            $this->plugin_title_option,
            $this->plugin_title_option, 
            'manage_options', 
            $this->plugin_title,
            // "fseg_options", 
            array( $this, 'settings_page' )
            );
        add_action('load-'.$page, array( $this, 'my_admin_add_help_tab' ));

    }
}


