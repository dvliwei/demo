<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2017/9/20
 * Time: 下午2:22
 */
namespace live;
class TCMPUrl{

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
            $html = $this->curl_get($url);
            $this->redis->set($redis_key, $html);
        }
        $urls=[];
        if($html){
            $iframe_urls = $this->get_iframe($html);
            foreach ($iframe_urls as $key=>$url){
                $data = new \stdClass();
                $host =  $this->get_host($url);
                $redis_key = 'live_html_'.$host.md5($url);
                $body = $this->redis->get($redis_key);
                if(!$body){
                    $body = $this->curl_get($url);
                    $this->redis->set($redis_key, $body);
                }
                $reg= '|channel=\'([^\']+)\', [a-z]+=\'([^\']+).*.src=\'([^\']+)|i';
                if(preg_match($reg,$body,$match)){
                    $channel = $match[1];
                    $e = $match[2];
                    $data->m3u8_url = $this->get_url_by_js($channel,$e,$url);
                    $data->ifram_url = $url;
                }
                $urls[] = $data;
            }
            return $urls;
        }
    }

    public function get_url_by_js($channel,$e,$refere_url){
        $url = "http://www.mipsplayer.com/hembedplayer/".$channel."/".$e."/640/480";
        $header =  array(
            "cache-control: no-cache",
            "host: www.mipsplayer.com",
            "postman-token: 150a788f-1f38-c225-b517-e1576a7d8fd3",
            "referer:".$refere_url,
            "user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.91 Safari/537.36"
        );
        $host =  $this->get_host($url);
        $redis_key = 'live_html_'.$host.md5($url);
        $body = $this->redis->get($redis_key);
        if(!$body){
            $body = $this->curl_get($url,$header);
            $this->redis->set($redis_key, $body);
        }
        $reg = '|enableVideo\("([^"]+)|i';
        if(preg_match($reg,$body,$matches)){
            $pk = $matches[1];
        }

        $reg='|url: "(http://cdn[^,]+)|i';
        if(preg_match($reg,$body,$matches)){
            $url = $matches[1];
            $url = str_replace('" + ','',$url);
            $str = $this->curl_get($url);
            $attr = explode('=',$str);
            $ea =$attr[1];
            $hlsUrl = "http://".$ea.":8088/live/sdsdsdsdsdsd/playlist.m3u8?id=260402&pk=".$pk;
            return $hlsUrl;
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