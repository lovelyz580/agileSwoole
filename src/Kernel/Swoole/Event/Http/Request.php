<?php


namespace Kernel\Swoole\Event\Http;

use Kernel\AgileCore as Core;
use Kernel\Swoole\Event\Event;
use Kernel\Swoole\Event\EventTrait;

class Request implements Event
{
        use EventTrait;
        /* @var  \swoole_http_server $server*/

        protected $server;
        protected $request;
        protected $response;

        public function __construct(\swoole_http_server $server)
        {
                $this->server = $server;
        }

        public function doEvent(\swoole_http_request $request, \swoole_http_response $response)
        {
                if(isset($request->server['request_uri']) and $request->server['request_uri'] == '/favicon.ico') {
                        $response->end(json_encode(['code'=>0]));
                        return;
                }
                $rawData = json_decode($request->rawContent(), true);
                $post = [];
                if(is_string($request->post)) {
                       parse_str($request->post,$post);
                }else if(is_array($request->post)){
                        $post = $request->post;
                }
                if(is_array($rawData)) {
                        if(!is_array($post)) {
                                $post = [];
                        }
                        $_POST = array_merge($post,$rawData);
                }else{
                        $_POST = $post;
                }
                if(class_exists('\Swoole\Coroutine')){
                        \Swoole\Coroutine::create(function () use($response,$request){
                                $this->dispath($request,$response);
                        });
                }else{
                        $this->dispath($request,$response);
                }

        }

        public function dispath(\swoole_http_request $request, \swoole_http_response $response)
        {
                try {

                        $data = ['code'=>0,'response'=> Core::getInstant()->get('route')->dispatch(
                                $request->server['request_uri'],strtolower($request->server['request_method'])
                        )];
                }catch (\Exception $exception) {
                        $data = ['code'=>$exception->getCode()>0?$exception->getCode():1, 'response'=>$exception->getMessage()];
                }
                if(isset($data['response']['view'])) {
                        extract( $data['response']);
                        ob_start();
                        include($data['response']['view']); // PHP will be processed
                        $content = ob_get_contents();
                        @ob_end_clean();
                } else {
                        $content = json_encode($data);
                }
                $response->end($content);
        }

        public function doClosure()
        {
                if($this->callback != null) {
                        $this->params = [$this->request, $this->response];
                        return call_user_func_array($this->callback, $this->params);
                }
                return ['code'=>0];
        }
}