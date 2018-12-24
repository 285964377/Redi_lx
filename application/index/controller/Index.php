<?php

namespace app\index\controller;

use think\cache\driver\Redis;
use think\Controller;
use think\Db;

class Index extends Controller
{

    const defaultCache = "-1";//默认缓存
    const forbiddenExpire = 3600;//incr后，重新设置过期时间,一般为一天
    const forbiddenLimit = 6;//设置 非命中最大值，超过这个数 直接不能获取 内容

    public function _initialize()
    {
        if ($this->isForbidden()) {
            $this->error('你IP非法访问次数过多');
        }


    }

    public function index()
    {
        return '<style type="text/css">*{ padding: 0; margin: 0; } .think_default_text{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }</style><div style="padding: 24px 48px;"> <h1>:)</h1><p> ThinkPHP V5<br/><span style="font-size:30px">十年磨一剑 - 为API开发设计的高性能框架</span></p><span style="font-size:22px;">[ V5.0 版本由 <a href="http://www.qiniu.com" target="qiniu">七牛云</a> 独家赞助发布 ]</span></div><script type="text/javascript" src="https://tajs.qq.com/stats?sId=9347272" charset="UTF-8"></script><script type="text/javascript" src="https://e.topthink.com/Public/static/client.js"></script><think id="ad_bd568ce7058a1091"></think>';
    }

    public function register()
    {

        return $this->fetch();
    }

    /**  Redis 防穿透DOM
     *
     * @param $id
     */
    public function redis_lx($id)
    {
        //得到参数
        $news_id = $id;
        if (!$news_id) die('参数错误');
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        //从Redis取出ID
        $news = $redis->get('news' . $news_id);
        $setIncr = false;
        //如果没有
        if (!$news) {

            //从数据库读取
            $data = Db::table('article')->where('id', $news_id)->find();
            //数据库也没的话
            if (!$data) {
                //处理上方注释问题 如果数据库也没用数据那么设置为默认缓存
                $data = self::defaultCache;
                $redis->set('news' . $news_id, $data, 5);
                //处理禁封IP逻辑
                $this->incrForbidden('p' . $this->getIP(), 2);//每次加2次
                $setIncr = true;//标识设置为true,防止下面再一次被加分
            } else {
                //存入时 js格式
                $data = json_encode($data, true);
                $redis->set('news' . $news_id, $data, 30);//存如缓存
                echo "处理逻辑";
                //  echo json_encode($data, true);
            }
        } else {
            //缓存拿出数据
            $data = $redis->get('news' . $news_id);
            echo "OK";
            // echo json_encode($data, true);
        }
        //缓存穿透 设置空建 每次统一建 +5s 生命周期 Redis 则不会保存恶意多余的空键了
        if ($data == self::defaultCache) {
            !$setIncr && $this->incrForbidden('p' . $this->getIP(), 1); //命中 默认缓存 给指定的ip增加1分
            //给予原来缓存增长5秒时间
            $redis->expire('news' . $news_id, 20);
            echo "error!";
        }

    }

    public function getIP()
    { //获取客户端IP的函数

        if (isset($_SERVER)) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $realip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $realip = $_SERVER['HTTP_CLIENT_IP'];
            } else {
                $realip = $_SERVER['REMOTE_ADDR'];
            }
        } else {
            if (getenv("HTTP_X_FORWARDED_FOR")) {
                $realip = getenv("HTTP_X_FORWARDED_FOR");
            } elseif (getenv("HTTP_CLIENT_IP")) {
                $realip = getenv("HTTP_CLIENT_IP");
            } else {
                $realip = getenv("REMOTE_ADDR");
            }
        }

        return $realip;
    }

    //给指定的key 加入分数
    public function incrForbidden(String $key, int $score = 1)
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $re = $redis->incr($key, $score);//加对应分
        $redis->expire($key, self::forbiddenExpire);//设置过期时间
        return $re;

    }

    //是否已经禁止了
    protected function isForbidden()
    {
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $getNews = $redis->get('p' . $this->getIP());
        if ($getNews && intval($getNews) >= self::forbiddenLimit) {
            return true;
        }
        return false;
    }

}
