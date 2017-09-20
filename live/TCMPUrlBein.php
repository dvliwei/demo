<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2017/9/20
 * Time: 下午2:22
 */
namespace live;
class TCMPUrlBein{

    public $redis ;

    public function __construct()
    {
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6379);
        //echo "Connection to server sucessfully";
        //设置 redis 字符串数据
        $this->redis->set("tutorial-name", "Redis tutorial");
        // 获取存储的数据并输出
        //echo "Stored string in redis:: " . $this->redis->get("tutorial-name");
    }

    public function  getM3u8Url($url=false){
        $host =  $this->get_host($url);
        $redis_key = 'live_html_'.$host.md5($url);
        $html = $this->redis->get($redis_key);
        if(!$html){
            $header =  array(
                "cache-control: no-cache",
                //"host: www.mipsplayer.com",
                //"postman-token: 559ae20f-1adb-0b66-768e-d691f807ae2b",
                //"referer:http://kora-online.tv/home/iframe/1048.html",
                //"user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.91 Safari/537.36"
            );
            $html = $this->curl_get($url,$header);
            $this->redis->set($redis_key, $html);
        }
        if($html){
            $reg= '|channel=\'([^\']+)\', [a-z]+=\'([^\']+).*.src=\'([^\']+)|i';
            if(preg_match($reg,$html,$match)){
                $data = new \stdClass();
                $channel = $match[1];
                $e = $match[2];
                $src = $match[3];
                $data->m3u8_url = $this->get_url_by_js($src,$channel,$e,$url);
                $data->url = $url;
            }
            return $data;
        }
    }

    public function get_url_by_js($src,$channel,$e,$refere_url){
        $host =  $this->get_host($src);
        $redis_key = 'live_html_'.$host.md5($src);
        $body = $this->redis->get($redis_key);
        if(!$body){
            $header =  array(
                "cache-control: no-cache",
                //"host: www.mipsplayer.com",
                "postman-token: 9c08ce07-c6b3-4b57-3c00-393e09d43a16",
                "referer:".$refere_url,
                //"user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.91 Safari/537.36"
            );
            $body = $this->curl_get($src,$header);
            $this->redis->set($redis_key, $body);
        }
        $src_reg='|src=(http:\/\/[^\']+)|';
        if(preg_match($src_reg,$body,$matches)){
            $url_host = $matches[1];
        }
        $embedded_reg='|embedded = "([^"]+)|i';
        if(preg_match($embedded_reg,$body,$matches)){
            $embedded = $matches[1];
        }
        if($url_host && $embedded && $channel && $e){
            $url = $url_host.$embedded.'/'.$channel."/".$e."/640/480";
            $host =  $this->get_host($url);
            $redis_key = 'live_html_'.$host.md5($url);
            $body = $this->redis->get($redis_key);
            if(!$body){
                $header =  array(
                    "cache-control: no-cache",
                    //"host: www.mipsplayer.com",
                    //"postman-token: 559ae20f-1adb-0b66-768e-d691f807ae2b",
                    "referer:".$refere_url,
                    //"user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.91 Safari/537.36"
                );
                $body = $this->curl_get($url,$header);
                $this->redis->set($redis_key, $body);
            }
            $pk='';
            $pk_reg='|enableVideo\("([^"]+)|i';
            if(preg_match($pk_reg,$body,$matches)){
                $pk = $matches[1];
            }
            $pk_reg='|hlsUrl \+ \("([^"]+)|i';
            if(preg_match($pk_reg,$body,$matches)){
                $pk = $matches[1];
            }

            $uu_reg='|hlsUrl = "http:\/\/" \+ \w+ \+ "(:[^"]+)|i';
            if(preg_match($uu_reg,$body,$matches)){
                $hlsUrl = $matches[1];
            }

            $uu_reg='|src", "http:\/\/" \+ \w+ \+ "(:[^"]+)|i';
            if(preg_match($uu_reg,$body,$matches)){
                $hlsUrl = $matches[1];
            }

            $redirect_reg='|ajax\(\{url: "([^"]+)|i';
            if(preg_match($redirect_reg,$body,$matches)){
                $redirect_url = $matches[1];
                $header =  array(
                    "cache-control: no-cache",
                    //"host: www.mipsplayer.com",
                    //"postman-token: 559ae20f-1adb-0b66-768e-d691f807ae2b",
                    "referer:".$url,
                    //"user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.91 Safari/537.36"
                );
                $src = $this->curl_get($redirect_url,$header);
                $attr = explode('=',$src);
                $ea = $attr[1];
            }
            if($hlsUrl && $ea ){
                if($pk){
                    $m3u8_url = 'http://'.$ea.$hlsUrl.$pk;
                }else{
                    $m3u8_url = 'http://'.$ea.$hlsUrl;
                }

            }
            return  $m3u8_url;
        }


    }


    protected function  get_host($url=''){
        preg_match('|://(?P<host>[^/]+)/?|', $url, $matches);
        if(isset($matches['host'])) return strtolower($matches['host']);
    }


    protected function get_iframe($html){
        $reg='|data-mainsrvr="[0-9]+" data-clink="([^"]+)|i';
        if(preg_match_all($reg,$html,$matches) ){
            foreach ( $matches[1] as $i=>$id){
                $url[] = "http://kora-online.tv/home/iframe/".$id.".html";
            }
            return $url;
        }

    }


    protected  function curl_get($url,$header=[],$referer='') {
        if(!$header){
            $header[] = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.91 Safari/537.36 ";
            $header[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
            $header[] = "Accept-Charset: GB2312,utf-8;q=0.7,*;q=0.7";
            $header[] = "Accept-Encoding: gzip,deflate";
            $header[] = "host:kora-online.tv";
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch,CURLOPT_HEADER,1); //将头文件的信息作为数据流输出
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        //curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; zh-CN; rv:1.9.2) Gecko/20100115 Firefox/3.6" );
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        //curl_setopt($ch,CURLOPT_REFERER,$referer);
        $r = curl_exec($ch);
        curl_close($ch);
        return $r;


    }
}