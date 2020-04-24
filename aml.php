<?php
/*
input parameters
name        :person name(must) ,
nation_code :INDO(must),
birthday    :person`s birthday(selected)

*/
function deliver_response($response){
        // Define HTTP responses
        $http_response_code = array(
                100 => 'Continue',
                101 => 'Switching Protocols',
                200 => 'OK',
                201 => 'Created',
                202 => 'Accepted',
                203 => 'Non-Authoritative Information',
                204 => 'No Content',
                205 => 'Reset Content',
                206 => 'Partial Content',
                300 => 'Multiple Choices',
                301 => 'Moved Permanently',
                302 => 'Found',
                303 => 'See Other',
                304 => 'Not Modified',
                305 => 'Use Proxy',
                306 => '(Unused)',
                307 => 'Temporary Redirect',
                400 => 'Bad Request',
                401 => 'Unauthorized',
                402 => 'Payment Required',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                406 => 'Not Acceptable',
                407 => 'Proxy Authentication Required',
                408 => 'Request Timeout',
                409 => 'Conflict',
                410 => 'Gone',
                411 => 'Length Required',
                412 => 'Precondition Failed',
                413 => 'Request Entity Too Large',
                414 => 'Request-URI Too Long',
                415 => 'Unsupported Media Type',
                416 => 'Requested Range Not Satisfiable',
                417 => 'Expectation Failed',
                500 => 'Internal Server Error',
                501 => 'Not Implemented',
                502 => 'Bad Gateway',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout',
                505 => 'HTTP Version Not Supported'
                );

        // Set HTTP Response
        header('HTTP/1.1 '.$response['status'].' '.$http_response_code[ $response['status'] ]);
        // Set HTTP Response Content Type
        header('Content-Type: application/json; charset=utf-8');
        // Format data into a JSON response
        $json_response = json_encode($response['data']);
        // Deliver formatted data
        echo $json_response;

        exit;
}


// Set default HTTP response of 'Not Found'
$response['status'] = 404;
$response['data'] = NULL;

$url_array = explode('/', $_SERVER['REQUEST_URI']);

// get the action (resource, collection)
$action = $url_array[0];
// get the method
$method = $_SERVER['REQUEST_METHOD'];
require_once('process.php');
//進入處理邏輯
if($method == 'POST'){
        $nationArr = array('INDON', 'VIETN','PHLNS','THAIL');
        $json = file_get_contents('php://input');
        $paras = json_decode($json);

        //check json format & 參數
        //if(!(property_exists($paras,'name') && property_exists($paras,'nation_code'))){
        if(!property_exists($paras,'name') ){
                $response['status'] = 500;
                $response['data'] = NULL ;
        }else{
                //check paras format
                if(property_exists($paras ,'nation_code')){
                        if(!in_array($paras->nation_code ,$nationArr )){
                                $response['status']=500;
                                $response['data']=NULL;
                                deliver_response($response);
                        }
                }

                if(property_exists($paras , 'birthday')){
                        if(!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$paras->birthday)){
                                $response['status'] = 500;
                                $response['data'] = NULL;
                                deliver_response($response);
                        }
                }
                if(gettype($paras->name) !='string' ){
                        $response['status'] = 500;
                        $response['data'] = NULL;
                        deliver_response($response);
                }


                //AML API 查詢
                $aml = new Process();
                $api_res = $aml->djapi($paras);
                $response['status'] = 200;
                $response['data'] = $api_res ;
        }
}else{
        $reponse['data'] = NULL;
        $response['status'] = 404;
}

deliver_response($response);




?>                        
