<?php

/*
 * Plugin Name: Bablic_Seo_SDK
 * Plugin URI: https://github.com/Bablic/bablic-php/blob/master/Bablic_Sdk.php
 * Description: Integrates your site with Bablic localization cloud service directly from your server to solve SEO issues.
 * Version: 0.2
 * Author: Erez Hochman
 * Author URI: https://www.bablic.com
 * License: GPLv3
 * Copyright 2016 Bablic
 */

$is_google_bot =  '/bot|crawler|baiduspider|80legs|mediapartners-google|adsbot-google/i';
$is_ignorable = '/\.(js|css|jpg|jpeg|png|mp3|avi|mpeg|bmp|wav|pdf|doc|xml|docx|xlsx|xls|json|kml|svg|eot|woff|woff2)/';

class Bablic {
    private $site_id = '';
    private $save_flag = true;
    private $done = false;
    private $url = '';

    public function Bablic($options) {
        $this->site_id = $options['site_id'];
        $this->url = $this->get_current_url();
        $this->get_html_for_url($this->url);
    }
   
    private function is_bot() {
        $is_bot =  '/bot|crawler|baiduspider|facebookexternalhit|Twitterbot|80legs|mediapartners-google|adsbot-google/i';
        if(preg_match($is_bot, $_SERVER['HTTP_USER_AGENT'], $matches))
            return true;
        return false;
    }

    private function get_current_url() {
        $protocol = 'http';
        if ($_SERVER['SERVER_PORT'] == 443 || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on'))
            $protocol .= 's';
        return "$protocol://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }

    public function process_buffer($buffer) {
        foreach (headers_list() as &$value) {
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
        $bablic_url = "https://www.bablic.com/api/engine/seo?site=$this->site_id&url=".urlencode($url);
        $curl = curl_init($bablic_url);
        $content = json_encode(array('html'=> $html));
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
        
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
