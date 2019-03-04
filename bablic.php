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


class file_store {
    private $store = array();

    function __construct() {
        $tmp_dir = sys_get_temp_dir();
        $this->filename = "$tmp_dir/bablic_snippet";
        if(file_exists($this->filename)){
            $str = file_get_contents($this->filename);
            $this->store = json_decode($str, true);
        }
    }
    public function get($key){
        if (empty($this->store[$key]))
            return '';
        else
            return $this->store[$key];
    }

    public function set($key, $value){
        $this->store[$key] = $value;
        $file = fopen($this->filename, "w");
        if($file){
            $str = json_encode($this->store);
            fwrite($file, $str);
            fclose($file);
        }
    }
}


class BablicSDK {
    public $site_id = '';
    private $save_flag = true;
    private $done = false;
    private $subdir = false;
    private $subdir_base = '';
    private $url = '';
    private $nocache = false;
    private $nourl = false;
    public $access_token = '';
    private $channel_id = '';
    private $version = '';
    private $snippet = '';
    private $meta = '';
    public $trial_started = false;
	private $_body = '';
	private $pos = 0;
	private $timestamp = 0;
	private $use_snippet_url = false;
	private $dont_add_sub_dir_base = false;
	private $bablic_base = 'https://www.bablic.com';
	private $bablic_seo_base = 'http://seo.bablic.com';
	private $_locale = '';
    private $folders = array();

    function __construct($options) {
        if (empty($options['channel_id'])){
            $options['channel_id'] = 'php';
        }
        if(!empty($options['test']) && $options['test']){
            $this->bablic_base = 'http://staging.bablic.com';
            $this->bablic_seo_base = 'http://staging.bablic.com';
        }

        $this->channel_id = $options['channel_id'];
        if(!empty($options['store']))
            $this->store = $options['store'];
        else
            $this->store = new file_store();
        if ($this->store->get('site_id') != '')
            $this->get_data_from_store();
		if(!empty($options['site_id'])){
			$this->site_id = $options['site_id'];
			if($this->store->get('site_id') != $this->site_id)
				$this->get_site_from_bablic();
            else if(!empty($_SERVER['HTTP_X_BABLIC_REFRESH']))
                $this->get_site_from_bablic();
		}
        if($this->site_id){
            try{
                header('x-bablic-id: '.$this->site_id);
            }
            catch(Exception $e){
            }
        }
		if($this->site_id && (empty($this->timestamp) || ((time() - $this->timestamp) > 12000))){
			$this->timestamp = time();
			$this->get_site_from_bablic();
		}

        if(!empty($options['subdir']) && $this->meta){
            $meta = json_decode($this->meta, true);
            $locale_keys = $meta['localeKeys'];
            if(count($locale_keys) > 0){
                $this->subdir = $options['subdir'];
                if(!empty($options['subdir_base']))
                    $this->subdir_base = $options['subdir_base'];
            }
        }
        if(isset($options['use_snippet_url']))
            $this->use_snippet_url = true;
        if (!empty($options['folders'])) {
            $this->folders = $options['folders'];
        }
        if(isset($options['dont_add_sub_dir_base']))
            $this->dont_add_sub_dir_base = true;
	
    }

    private function getFolder($locale)
    {
        foreach ($this->folders as $folder => $l) {
            if ($l == $locale) {
                return $folder;
            }
        }
        $locale = substr($locale, 0, 2);
        foreach ($this->folders as $folder => $l) {
            if (substr($l, 0, 2) == $locale) {
                return $folder;
            }
        }

        return '';
    }

    private function getLocaleFromFolder($folderLocale, $locales)
    {
        foreach ($locales as $l) {
            if ($l == $folderLocale) {
                return $l;
            }
        }
        $folderLocale = substr($folderLocale, 0, 2);
        foreach ($locales as $l) {
            if (substr($l, 0, 2) == $folderLocale) {
                return $l;
            }
        }

        return $folderLocale;
    }
    private function save_data_to_store(){
        $this->store->set('meta', $this->meta);
        $this->store->set('access_token', $this->access_token);
        $this->store->set('version', $this->version);
        $this->store->set('trial_started', $this->trial_started?'1':'');
        $this->store->set('snippet', $this->snippet);
        $this->store->set('site_id', $this->site_id);
        $this->store->set('time',$this->timestamp);
    }

	public function clear_data(){
		$this->site_id = '';
		$this->version = '';
		$this->trial_started = false;
		$this->snippet = '';
		$this->meta = '';
		$this->access_token = '';
		$this->save_data_to_store();
	}

    private function get_data_from_store() {
       $this->site_id = $this->store->get('site_id');
       $this->version = $this->store->get('version');
       $this->trial_started = $this->store->get('trial_started') == '1';
       $this->meta = $this->store->get('meta');
       $this->snippet = $this->store->get('snippet');
       $this->access_token = $this->store->get('access_token');
       $this->timestamp = $this->store->get('time');
    }

    public function set_site($site,$callback=''){
        if(empty($site['id']))
            die('No site id');
        $this->site_id = $site['id'];
		if($this->access_token == '')
			$this->access_token = isset($site['access_token']) ? $site['access_token'] : '';
        $this->get_site_from_bablic();
        if($callback == '')
            return;
        $url = $this->bablic_base . "/api/v1/site/".$site['id']."?access_token=$this->access_token&channel_id=$this->channel_id";
        $payload = array(
            'callback' => $callback
        );
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json","X-HTTP-Method-Override:PUT","Expect:"));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        if (!empty($result['error'])) {
            return array("error" => "Bablic returned error");
        }
    }

    public function create_site($options) {
        $url = $this->bablic_base . "/api/v1/site?channel_id=$this->channel_id";
        $payload = array(
            'url' => $options['site_url'],
            'email'=> isset($options['email']) ? $options['email'] : '',
            'original' => isset($options['original_locale']) ? $options['original_locale'] : '',
            'callback' => isset($options['callback']) ? $options['callback'] : '',
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
        $this->snippet = $this->use_snippet_url ? $result['snippetURL'] : $result['snippet'];
        $this->version = $result['version'];
        $this->trial_started = false;
        $this->meta = json_encode($result['meta']);
        $this->save_data_to_store();
    }

    public function get_site_from_bablic() {
        $url = $this->bablic_base . "/api/v1/site/$this->site_id?access_token=$this->access_token&channel_id=$this->channel_id";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        if (!empty($result['error'])) {
			if(!empty($result['error']['code']) && $result['error']['code'] == 410){
				$this->clear_data();
				$this->save_data_to_store();
				return array("error" => "Site removed");
			}

            return array("error" => "Bablic returned error");
        }
		if(!empty($result['access_token']))
			$this->access_token = $result['access_token'];
        $this->site_id = $result['id'];
        $this->snippet = $this->use_snippet_url ? $result['snippetURL'] : $result['snippet'];
        $this->version = $result['version'];
        $this->trial_started = $result['trialStarted'];
        $this->meta = json_encode($result['meta']);
        $this->timestamp = time();
        $this->save_data_to_store();
    }
	
	public function refresh_site(){
		$this->get_site_from_bablic();
	}

    public function clear_cache(){
        $tmp_dir = sys_get_temp_dir();
		$folder = "$tmp_dir/bablic_cache";
		if (!file_exists($folder)){
			return;
		}
        array_map('unlink', glob("$folder/*"));
    }
    
    public function get_meta() {
      return $this->meta;
    }
    public function get_site(){
        return array (
            "meta" => $this->meta,
			"access_token" => $this->access_token,
            "site_id" => $this->site_id,
            "version" => $this->version,
            "trial_started" => $this->trial_started,
            "snippet" => $this->snippet
        );
    }

    public function get_snippet($async) {
        $snippet = $this->snippet;
        if($async) {
            $snippet = str_replace('<script ', '<script async ', $snippet);
        }

        if($this->subdir){
            $locale = $this->get_locale();
            return '<script ' . $asyncAttr . 'type="text/javascript">var bablic=bablic||{};bablic.localeURL="subdir";bablic.subDirBase="'.$this->subdir_base.'";bablic.locale="'.$locale.'"</script>'.$snippet;
        }
        return $snippet;
    }

    public function get_bablic_top(){
        return '<!-- start Bablic Head -->'.$this->get_alt_tags().$this->get_snippet($this->get_locale() == $this->get_original()) . '<!-- end Bablic Head -->';
    }

    public function bablic_top(){
        echo '<!-- start Bablic Head -->';
        $this->alt_tags();

        echo $this->get_snippet($this->get_locale() == $this->get_original());
        echo '<!-- end Bablic Head -->';
    }


    public function get_alt_tags(){
        $meta = json_decode($this->meta, true);
        $locale_keys = $meta['localeKeys'];
        $locale = $this->get_locale();
        $url = $_SERVER['REQUEST_URI'];
        $str = '';
        if(is_array($locale_keys)){
            foreach( $locale_keys as $alt){
                if($alt != $locale) {
                    $parts = explode('_', $alt);
                    $iso = sizeof($parts) > 1 ? $parts[0].'-'.strtoupper($parts[1]) : $parts[0];
                    array_push($res, array($this->get_link($alt, $url), $iso));
                }
            }
            if($locale != $meta['original'])
                $str .= '<link rel="alternate" href="' . $this->get_link($meta['original'],$url) . '" hreflang="x-default">';
        }
        return $str;
    }

    public function alt_tags(){
        $meta = json_decode($this->meta, true);
        $locale_keys = $meta['localeKeys'];
        $locale = $this->get_locale();
        $url = $_SERVER['REQUEST_URI'];
        if(is_array($locale_keys)){
            foreach( $locale_keys as $alt){
                if($alt != $locale) {
                    $parts = explode('_', $alt);
                    $iso = sizeof($parts) > 1 ? $parts[0].'-'.strtoupper($parts[1]) : $parts[0];
                    echo '<link rel="alternate" href="' . $this->get_link($alt,$url) . '" hreflang="'.$iso.'">';
                }
            }
            if($locale != $meta['original'])
                echo '<link rel="alternate" href="' . $this->get_link($meta['original'],$url) . '" hreflang="x-default">';
        }
    }

    private function get_all_headers() {
        $headers = array();
        foreach($_SERVER as $key => $value) {
            if (substr($key, 0, 5) != 'HTTP_') {
                continue;
            }
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$header] = $value;
        }
        return $headers;
    }

    public function detect_locale_from_header() {
        $headers = $this->get_all_headers();
        if(!isset($headers['Accept-Language']))
            return false;
        $lang = explode(',', $headers['Accept-Language']);
        if (!empty($lang)) return $lang[0];
        return false;
    }

    public function detect_locale_from_cookie($allowed_keys) {
        if (!empty($_COOKIE) && !empty($_COOKIE['bab_locale']) && !empty($allowed_keys)){
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

    public function get_link($locale, $url) {
        if($url[0] != '/' && $url[0] != 'h')
            return $url;
        $parsed = parse_url($url);
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host = isset($parsed['host']) ? $parsed['host'] : '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = isset($parsed['path']) ? $parsed['path'] : '/';
        $query    = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        $meta = json_decode($this->meta, true);
        $link = 'javascript:void(0);';
        $localeDetection = $meta['localeDetection'];
        if($this->subdir)
            $localeDetection = 'subdir';
        if($localeDetection == 'custom' && empty($meta['customUrls']))
            $localeDetection = 'querystring';

        switch($localeDetection){
            case 'custom':
                $custom_url = $meta['customUrls'][$locale];
                if ($custom_url) {
                    $scheme = $scheme == '' ? 'http://' : $scheme;
                    if(strpos($custom_url, '/') !== false){
                        // custom contains querystring
                        if(strpos($custom_url, '?') !== false)
                            return $scheme.$custom_url.$fragment;

                        // custom contains path
                        return $scheme.$custom_url.$query.$fragment;
                    }
                    // custom is only domain
                    return $scheme.$custom_url.$path.$query.$fragment;
                }
                break;
            case 'querystring':
                $query_locale = '';
                if(!isset($parsed['query']))
                    return $scheme.$host.$port.$path.'?locale='.$locale.$fragment;

                $output = array();
                parse_str(substr($query,1),$output);
                $output['locale'] = $locale;
                $query = http_build_query($output);
                return $scheme.$host.$port.$path.'?'.$query.$fragment;
            case 'subdir':
                if ($this->subdir_base != '') {
                    $path = preg_replace('/^(?:'.preg_quote($this->subdir_base, '/').')?\//', '/', $path);
                }
                $folder_keys = array_keys($this->folders);
                $prefix = '';
                if (sizeof($folder_keys) > 0) {
                    $prefix = '/'.$this->getFolder($locale);
                    $locale_keys = $folder_keys;
                } else{
                    $locale_keys = $meta['localeKeys'];
                    if($locale != $meta['original'])
                        $prefix = '/'.$locale;
                }
                $locale_regex = '('.implode('|', $locale_keys).')';
                $path = preg_replace('/^\/?'.$locale_regex.'\//', '/', $path);

                return $scheme.$host.$port.$this->subdir_base.$prefix.$path.$query.$fragment;
            case 'hash':
                $fragment = '#locale_'.$locale;
                return $scheme.$host.$port.$path.$query.$fragment;
        }
        return $url;
    }

    public function get_original(){
        if($this->meta == '')
            return null;
        $meta = json_decode($this->meta, true);
        return $meta['original'];
    }

    public function get_locales(){
        if($this->meta == '')
            return array();
        $meta = json_decode($this->meta, true);
        return $meta['localeKeys'];
    }

    private function get_locale_inner($is_bot){
        if(!empty($_SERVER['HTTP_BABLIC_LOCALE']))
            return $_SERVER['HTTP_BABLIC_LOCALE'];
		if($this->meta == '')
			return '';
        $meta = json_decode($this->meta, true);
        $auto = $meta['autoDetect'];
        $default = $meta['default'];
        $custom_urls = $meta['customUrls'];
        $locale_keys = $meta['localeKeys'];
        if(!$locale_keys)
            $locale_keys = array();
        array_push($locale_keys, $meta['original']);
        $locale_detection = $meta['localeDetection'];
		if($this->subdir)
			$locale_detection = 'subdir';
        $detected = '';
        if(!$is_bot && $auto && !empty($locale_keys)){
            $detected_lang = $this->detect_locale_from_header();
            $normalized_lang = strtolower(str_replace('-','_',$detected_lang));
            foreach ($locale_keys as &$value) {
                if ($value === $normalized_lang){
                    $detected = $value;
                    break;
                }
                if (substr($value,0,2) === substr($normalized_lang,0,2)){
                    $detected = $value;
                    break;
                }
            }
        }
        $from_cookie = $is_bot ? false : $this->detect_locale_from_cookie($locale_keys);
        $url = $this->get_current_url();
        $parsed_url = parse_url($url);
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
                preg_match('/^(?:'.preg_quote($this->subdir_base, '/').")?(\/(\w\w(_\w\w)?))(?:\/|$)/", $path, $matches);
                if ($matches) {
                    if (!empty($this->folders[$matches[2]])) {
                        $folder = $this->folders[$matches[2]];
                        array_push($locale_keys, $meta['original']);

                        return $this->getLocaleFromFolder($folder, $locale_keys);
                    }

                    return $matches[2];
                }
                if ($from_cookie) {
                    return $default;
                }
                if ($detected) {
                    return $detected;
                }

                return $default;
            case 'custom':
                foreach ($custom_urls as $key => $value) {
                    $pattern = $this->create_domain_regex($value);
                    if (preg_match($pattern, $url, $matches))
                        return $key;
                }
                return $default;
            default:
                return $from_cookie;
        }
        return;
    }

    public function get_locale() {
        if($this->_locale != '')
            return $this->_locale;
        $locale = $this->get_locale_inner(false);
        $this->_locale = $locale;
        return $locale;
    }


    private function create_domain_regex($str){
        $new_str = preg_replace("/([.?+^$[\]\\\/(){}|-])/", "\\\\$1", $str);
        return '/'.preg_replace("/\*/",'.*', $new_str).'/';
    }

    public function editor_url() {
        return $this->bablic_base . "/channels/editor?site=$this->site_id&access_token=$this->access_token";
    }

    public function remove_site(){
        $url = $this->bablic_base . "/api/v1/site/$this->site_id?access_token=$this->access_token&channel_id=$this->channel_id";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->clear_data();
        curl_close($ch);
    }

    public function handle_request($options=array()) {
		if($this->site_id == '')
			return;
        if (($this->is_bot() == false) && (empty($options['debug']) || $options['debug'] == false))
			return;
        if (!empty($options['url']))
            $this->url = $options['url'];
        else
            $this->url = $this->get_current_url();
        if ($this->ignorable($this->url))
            return;
        if (!empty($options['nocache']) && $options['nocache'] == true)
			$this->nocache = true;
        if (!empty($options['nourl']) && $options['nourl'] == true)
			$this->nourl = true;

        if($this->meta){
           $meta = json_decode($this->meta, true);
           $default = $meta['default'];
		   $locale = $this->get_locale_inner(true);
           if ($default == $locale)
			   return;
           $this->get_html_for_url($this->url);
        }
        else {
           $this->get_html_for_url($this->url);
        }
    }

    private function is_bot() {
    	if(empty($_SERVER['HTTP_USER_AGENT']))
            return false;
        $is_bot =  '/bot|crawler|baiduspider|80legs|google|facebook|twitter|seo/i';
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
		$rcode = 200;
		if(function_exists('http_response_code'))
			$rcode = http_response_code();
		else
			$rcode = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);
        if ($rcode < 200 || $rcode >= 300) return $buffer;
        if ($this->ignorable($this->get_current_url())) return $buffer;
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
        if (($html_found === false)&&($contenttype_found === 0)) return $buffer;
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
		$folder = "$tmp_dir/bablic_cache";
		if (!file_exists($folder)){
			mkdir($folder);
		}

        $filename = $this->filename_from_url($url);
        return "$folder/$filename";
    }

	public function write_buffer($ch,$fp,$len){
		$data = substr($this->_body, $this->pos, $len);
		// increment $pos
		$this->pos += strlen($data);
		// return the data to send in the request
		return $data;
	}

    private function send_to_bablic($url, $html) {
        $nourl_param = $this->nourl ? "&nourl=1" : "";
        $bablic_url = $this->bablic_seo_base . "/api/engine/seo?site=$this->site_id".$nourl_param."&url=".urlencode($url).($this->subdir ? "&ld=subdir" : "").($this->subdir_base ? "&sdb=" .urlencode($this->subdir_base) : "");
        $bablic_url .= '&folders='.urlencode(json_encode($this->folders));
	if ($this->dont_add_sub_dir_base)
		$bablic_url .= '&dasdb=1';
        $curl = curl_init($bablic_url);
		$length = strlen($html);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: text/html", "Content-Length: $length","Expect:"));
        curl_setopt($curl, CURLOPT_POST, true);
		$this->_body = $html;
		$this->pos = 0;
        curl_setopt($curl, CURLOPT_READFUNCTION, array(&$this,'write_buffer'));

        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if (($status != 200 ) && ($status != 301)) {
            return $html;
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
        if ($cached_file){
			exit;
            return;
		}
        ob_start(array(&$this, "process_buffer"));
        return;

    }

    private function read_from_cache($filename) {
        if ($this->nocache == true) return false;
		try{
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
		catch(Exception $e){
			return false;
		}
    }
}
?>
