<?php

class mock_store {
    private $store = array();

    public function get($key){
        if (empty($this->store[$key]))
            return '';
        else
            return $this->store[$key];
    }

    public function set($key, $value){
        $this->store[$key] = $value;
    }
}

class wp_store {
    public function get($key){
		return get_option($key);
    }
    public function set($key, $value){
        update_option($key, $value);
    }
}

class BablicSDK {
    private $site_id = '';
    private $save_flag = true;
    private $done = false;
    private $url = '';
    private $nocache = false;
    private $access_token = '';
    private $channel_id = '';
    private $version = '';
    private $meta = array();

    function __construct($options) {
        if (empty($options['channel_id'])){
            $options['channel_id'] = 'php';
        }       
        $this->channel_id = $options['channel_id'];
        if ($this->channel_id === 'wp')
            $this->store = new wp_store();
        else
            $this->store = new mock_store();
        if ($this->store->get('site_id') != '') 
            $this->get_data_from_store();
    }

    private function save_data_to_store(){
        $this->store->set('meta', $this->meta);
        $this->store->set('access_token', $this->access_token);
        $this->store->set('version', $this->version);
        $this->store->set('meta', $this->meta);
        $this->store->set('snippet', $this->snippet);
        $this->store->set('site_id', $this->site_id);
    }

    private function get_data_from_store() {
       $this->site_id = $this->store->get('site_id');
       $this->version = $this->store->get('version');
       $this->meta = $this->store->get('meta');
       $this->snippet = $this->store->get('snippet');
       $this->access_token = $this->store->get('access_token');
    }

    public function create_site($options) {
        $url = "https://www.bablic.com/api/v1/site?channel_id=$this->channel_id";
        $payload = array(
            'url' => $options['site_url'],
            'email'=> $options['email'],
            'original' => $options['original_locale'],
            'callback' => $options['callback'],
        );
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json","Expect:"));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        if (!empty($result['error'])) {
            return array("error" => "Bablic returned error");
        }
        $this->access_token = $result['access_token'];
        $this->site_id = $result['id'];
        $this->snippet = $result['snippet'];
        $this->version = $result['version'];
        $this->meta = json_encode($result['meta']);
        $this->save_data_to_store();
    }

    public function get_site_from_bablic() {
        $url = "https://www.bablic.com/api/v1/site/$this->site_id?access_token=$this->access_token";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        if (!empty($result['error'])) {
            return array("error" => "Bablic returned error");
        }
        $this->access_token = $result['access_token'];
        $this->site_id = $result['id'];
        $this->snippet = $result['snippet'];
        $this->version = $result['version'];
        $this->meta = json_encode($result['meta']);
        $this->save_data_to_store();
    }

    public function clear_cache(){
        $tmp_dir = sys_get_temp_dir();
		$folder = "$tmp_dir/bablic_cache";
		if (!file_exists($folder)){
			echo "not exists";
			return;
		} 
        array_map('unlink', glob("$folder/*"));
    }
    
    public function get_site(){
        return array (
            "meta" => $this->meta,
            "site_id" => $this->site_id,
            "version" => $this->version,
            "snippet" => $this->snippet,
        );
    }

    public function get_snippet() {
        return $this->snippet;
    }

    private function get_all_headers() {
        $headers = array();
        foreach($_SERVER as $key => $value) {
            if (substr($key, 0, 5) <> 'HTTP_') {
                continue;
            }
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$header] = $value;
        }
        return $headers;
    }

    public function detect_locale_from_header() {
        $headers = $this->get_all_headers();
        $lang = explode(',', $headers['Accept-Language']);
        if (!empty($lang)) return $lang[0];
        return false;
    }

    public function detect_locale_from_cookie($allowed_keys) {
        if (!empty($_COOKIE['bab_locale']) && !empty($allowed_keys)){
            $cookie_locale = $_COOKIE['bab_locale'];
            $match = false;
            foreach ($allowed_keys as &$value) {
                if ($value === $cookie_locale)
                    $match = true;
                if (!$match)
                    if (substr($value,0,2) === substr($cookie_locale,0,2))
                        $match = true;
            }
            if ($match)
                return $cookie_locale;
        }
        return false;
    }

    public function get_locale() {
        $meta = json_decode($this->meta, true);
        $auto = $meta['autoDetect'];
        $default = $meta['default'];
        $custom_urls = $meta['customUrls'];
        $locale_keys = $meta['localeKeys'];
        $locale_detection = $meta['localeDetection'];
        $detected = '';
        if($auto && !empty($locale_keys)){
            $detected_lang = $this->detect_locale_from_header();
            $normalized_lang = strtolower(str_replace('-','_',$detected_lang));
            $match = false;
            foreach ($locale_keys as &$value) {
                if ($value === $normalized_lang)
                    $match = true;
                if (!$match)
                    if (substr($value,0,2) === substr($normalized_lang,0,2))
                        $match = true;
            }
            if ($match)
                $detected = $normalized_lang;
        }
        $from_cookie = $this->detect_locale_from_cookie($locale_keys);
        $parsed_url = parse_url($this->get_current_url());
        switch ($locale_detection) {
            case 'querystring':
                if ((!empty($_GET)) && (!empty($_GET['locale'])))
                    return $_GET['locale'];
                else if ($from_cookie) 
                    return $from_cookie;
                else if ($detected) 
                    return $detected;
                return $default;
            case 'subdir':
                $path = $parsed_url['path'];
                preg_match("/^(\/(\w\w(_\w\w)?))(?:\/|$)/", $path, $matches);
                if ($matches) return $matches[2];
                if ($detected) return $detected;
                return $default;
                break;
            case 'custom':
                function create_domain_regex($str) {
                    $new_str = preg_replace("/([.?+^$[\]\\(){}|-])/g", "\\$1", $str);
                    return preg_replace("/\*/g",'.*', $new_str);
                }
                foreach ($custom_urls as &$value) {
                    $pattern = create_domain_regex($value);
                    if (preg_match($pattern, $url, $matches))
                        return $value;
                }
                return $default;
                break;
            default:
                return $from_cookie;
        }
        return;
    }

    public function editor_url() {
        return "https://www.bablic.com/channels/editor?site=$this->site_id&access_token=$this->access_token";
    }

    public function remove_site(){
        $url = "https://www.bablic.com/api/v1/site/$this->site_id?access_token=$this->access_token";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode === 204) {
            $this->store->set(null);
        }
        curl_close($ch);
    }
   
    public function handle_request($options) {
        if (!empty($options['url']))
            $this->url = $options['url'];
        else
            $this->url = $this->get_current_url();
        if (!empty($options['nocache']) && $options['nocache'] == true) $this->nocache = true;
        if (($this->is_bot() == false) && (!empty($options['debug']) && $options['debug'] == false)) return;
        if($this->meta){
           $meta = json_decode($this->meta, true);
           $default = $meta['default'];
           if ($default === $this->get_locale()) return;
           $this->get_html_for_url($this->url);
        }
        else {
           $this->get_html_for_url($this->url);
        }
    }

    private function is_bot() {
    	if(empty($_SERVER['HTTP_USER_AGENT']))
            return false;
        $is_bot =  '/bot|crawler|baiduspider|facebookexternalhit|Twitterbot|80legs|mediapartners-google|adsbot-google/i';
        if(preg_match($is_bot, $_SERVER['HTTP_USER_AGENT'], $matches))
            return true;
        return false;
    }

    private function get_current_url() {
        $protocol = 'http';
        if ($_SERVER['SERVER_PORT'] == 443 || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on'))
            $protocol .= 's';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];
        return "$protocol://$host$uri";
    }
  
    public function ignorable($url) {
      $filename_tester = "/\.(js|css|jpg|jpeg|png|mp3|avi|mpeg|bmp|wav|pdf|doc|xml|docx|xlsx|xls|json|kml|svg|eot|woff|woff2)/";
      return preg_match($filename_tester, $url, $matches);
    }

    public function process_buffer($buffer) {
        $headers = headers_list();
        $rcode = http_response_code();
        if ($rcode < 200 || $rcode > 300) return false; 
        if ($this->ignorable($this->get_current_url())) return false;
        foreach ($headers as &$value) {
            $html_found = 0;
            $contenttype_found = 0;
            $html_found = strpos($value, "text/html;");
            $contenttype_found = strpos($value, "Content-Type");
            if ($html_found === false){
                // do nothing
            }else {
                break;
            }
        }
        if (($html_found === false)&&($contenttype_found === 0)) return false;
        $html = ob_get_contents();
        $url = $this->url;
        $response = $this->send_to_bablic($url, $html);
        return $response;
    }
    private function filename_from_url($url) {
        return md5($url);
    }

    private function full_path_from_url($url) {
        $tmp_dir = sys_get_temp_dir();
        $filename = $this->filename_from_url($url);
        return "$tmp_dir/$filename";
    }

    private function send_to_bablic($url, $html) {
        $bablic_url = "http://seo.bablic.com/api/engine/seo?site=$this->site_id&url=".urlencode($url);
        $curl = curl_init($bablic_url);

        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: text/html","Expect:"));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $html);
        
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if (($status != 200 ) && ($status != 301)) {
            return $html;
            die("Error: curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
        }

        curl_close($curl);
        $this->save_html($response, $this->full_path_from_url($url));
        return $response;
    }

    private function save_html($content, $filename) {
        $file = fopen($filename, "w") or die("Unable to open file!");
        fwrite($file, $content);
        fclose($file);
    }

    public function noop() {
        return '';
    }

    public function get_html_for_url($url) {      
        $cached_file = $this->read_from_cache($this->full_path_from_url($url));
        if ($cached_file)
            return ob_start(array(&$this, "noop"));
        ob_start(array(&$this, "process_buffer"));
        return ;
        
    }

    private function read_from_cache($filename) {
        if ($this->nocache == true) return false;
        $html_file = file_exists($filename);
        if ($html_file) {
            $file_modified = filemtime($filename);
            $now = round(microtime(true) * 1000);
            $validity = ($now - (2*24*60*60*1000) > $file_modified);
            if ($validity === false) return false;
            readfile($filename);
            return true;
        } else {
            return false;
        }
    }
}
?>
