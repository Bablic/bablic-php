<?php

/*
 * Plugin Name: Bablic_Seo_SDK
 * Plugin URI: https://www.bablic.com/docs#Bablic_Seo_SDK_PHP
 * Description: Integrates your site with Bablic localization cloud service directly from your server to solve SEO issues.
 * Version: 0.1
 * Author: Erez Hochman
 * Author URI: https://www.bablic.com
 * License: GPLv3
 * Copyright 2016 Bablic
 */

class Bablic {
    private $site_id = '';

    public function Bablic($options) {
        $this->site_id = $options['site_id'];
    }

    private function filename_from_url($url) {
        return md5($url);
    }

    private function full_path_from_url($url) {
        $tmp_dir = sys_get_temp_dir();
        $filename = $this->filename_from_url($url);
        return "$tmp_dir/$filename";
    }

    public function send_to_bablic($url, $html,$save_flag) {
        $curl = curl_init("http://dev.bablic.com/api/engine/seo?site=$this->site_id&url=$url");
        $content = json_encode(array('html'=> $html));
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
        
        $json_response = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ( $status != 200 ) {
            die("Error: curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
        }

        curl_close($curl);
        $response = json_decode($json_response);
        
        if ($save_flag === true) {
            $this->save_html($json_response, $this->full_path_from_url($url));
        }
        return htmlspecialchars($json_response);
    }

    private function save_html($content, $filename) {
        $file = fopen($filename, "w") or die("Unable to open file!");
        fwrite($file, $content);
        fclose($file);
    }

    public function get_html_for_url($url) {
        $res = null;
        $cached_file = $this->read_from_cache($this->full_path_from_url($url));
        if ($cached_file != false){
            $res = $cached_file['html'];
        } else {
            $res = $this->send_to_bablic(urlencode($url),'', true);
        }
        return $res;
        
    }

    private function read_from_cache($filename) {
        $html_file = file_exists($filename);
        if ($html_file) {
            $html = readfile($filename);
            return array("html"=>$html);
        } else {
            return false;
        }
    }

}

$Bablic_Seo = new Bablic(array('site_id' => '[site id]'));
$result = $Bablic_Seo->get_html_for_url('[url from your site]');
echo "result is $result";
?>
