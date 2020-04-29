<?php

class Process{

        /**
         * 定義每個site使用的AML API
         * 目前可用的是djapi cddsapi
         */
        private $_SiteAPI = array(
                'INDON'=>'djapi',
                'VIETN'=>'djapi',
                'PHLNS'=>'djapi',
                'THAIL'=>'djapi'
        );

        public function __construct(){

        }

        public function amlapi($paras){

                if( property_exists($paras ,'nation_code') ){
                        $api_mapping = $this->_SiteAPI[$paras->nation_code];
                }else{
                        $api_mapping = 'default';
                }

                switch($api_mapping){
                        case 'djapi':
                                $response = $this->_djapi($paras);
                        break;

                        case 'cddsapi':
                                $response = $this->_cddsapi($paras);
                        break;

                        case 'default':
                                $response = $this->_cddsapi($paras);
                        break;
                }
                return $response;
        }


        private function _djapi($paras){

                $testing_url    = 'https://djrc.api.test.dowjones.com/v1/search/name';
                $otherfilter    = '&record-type=P&search-type=precise&exclude-deceased=true&hits-from=1&hits-to=11';
                $token          = ' Basic MTgvRVVJQVBJOmRvd2pvbmVz';

                //$url = $testing_url . '?name=' . $paras->name . '&filter-region=' . $paras->nation_code ;
                $url = $testing_url . '?name=' . urlencode($paras->name) ;
                $url .= (property_exists( $paras , 'nation_code' ))?'&filter-region=' . $paras->nation_code:'';
                $url .= (property_exists( $paras , 'birthday' ))?'&date-of-birth=' . $paras->birthday:'';
                $url .= $otherfilter;

                $curlArr = array('url'=>$url , 'method'=>'GET' , 'header'=>array( 'Authorization:'.$token ) );
                $response = $this->exeCURL($curlArr);
                

                //return $response;
                $xml = simplexml_load_string($response , null , LIBXML_NOCDATA );
                $json = json_encode($xml);
                return $this->_djprocess(json_decode($json));
        }

/*
        function : return in format
        input : DJ response json

*/
        private function _djprocess($tmpObj){
                $riskArr = array();
                $score = 0;
                if(json_decode($tmpObj->head->{'total-hits'}) == '0'  ){
                        return $score;
                }

                $targetCata = array('BL', 'PEP' , 'RCA' , 'NN'  );
                $subNN = array('SI','AM','SOC','BRD','SI-LT');
                //$subSI = array('SI-LT');
                foreach($tmpObj->body->match as $v){

                        if((float)$v->score > $score  ){
                                $score =(float)$v->score;
                        }
                        if( gettype($v->payload->{'risk-icons'}->{'risk-icon'}) =='string'  ){
                                $arisk = $v->payload->{'risk-icons'}->{'risk-icon'};
                                if(in_array($arisk , $subNN )){
                                        $arisk = 'NN';
                                }
                                if(in_array($arisk , $targetCata) && (!in_array($arisk , $riskArr ))  ){
                                        array_push($riskArr , $arisk);
                                }
                        }else{
                                foreach( $v->payload->{'risk-icons'}->{'risk-icon'} as $arisk ){
                                        if(in_array($arisk , $subNN)){
                                                $arisk = 'NN';
                                        }
                                        if(in_array($arisk , $targetCata) && (!in_array($arisk , $riskArr ))  ){
                                                array_push($riskArr, $arisk );
                                        }
                                }
                        }
                }
                $score =(round(100*$score));
                return array('score'=>$score,'risk'=>$riskArr,'raw'=>$tmpObj->body);
        }


        /**
         * execute CURL
         *  
         */
        public function exeCURL($curlArr){
                
                $curl = curl_init();

                curl_setopt_array($curl, array(
                        CURLOPT_URL =>$curlArr['url'] ,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => $curlArr['method'],
                        CURLOPT_HTTPHEADER => $curlArr['header'],
                ));
                if(isset($curlArr['body']) && $curlArr['method'] =='POST' ){//如果有傳送body
                        curl_setopt($curl ,CURLOPT_POSTFIELDS , $curlArr['body'] );
                }
                
                $response = curl_exec($curl);
                curl_close($curl);
                return $response;
        }


        /**
         * 
         */
        private function _cddsapi($paras){

                $tmp_name = $paras->name;
                $nameArr = explode(" " , $tmp_name);

                $xml_body = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
                <soap12:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap12=\"http://www.w3.org/2003/05/soap-envelope\">
                <soap12:Body>
                <CheckName xmlns=\"http://cdds.lu/\">
                <Nom>{$nameArr[0]}</Nom>
                <Prenom>{$nameArr[1]}</Prenom>
                <IsPerson>true</IsPerson>
                <DateFrom>2000-01-01</DateFrom>
                <Probabilite>100</Probabilite>
                <Code>aaa</Code>
                </CheckName>
                </soap12:Body>
                </soap12:Envelope>";
                
                $curlArr = array(
                        'url'=>'https://testapi.aml-check.com/v4/namecheck.asmx',
                        'method'=>'POST',
                        'header'=>array('Content-Type: application/soap+xml; charset=utf-8'),
                        'body'=>$xml_body
                );
                $response = $this->exeCURL($curlArr);
                $res = $this->_cddsprocess($response);
                return $res;
        }

        /**
         * 
         */
        private function _cddsprocess($response){
                $riskArr = array();
                $score = 0;
                $targetCata = array('Sanction','Interpol','Peps');

                $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $response);
                $xml = new SimpleXMLElement($response);
                $body = $xml->xpath('//soapBody')[0];
                $resArr = json_decode(json_encode((array)$body), TRUE);
                //print_r($resArr['CheckNameResponse']['CheckNameResult']['Hit']);
                if(!isset($resArr['CheckNameResponse']['CheckNameResult']['Hit'])){
                        return '0';
                }
                foreach($resArr['CheckNameResponse']['CheckNameResult']['Hit'] as $v){
                        if( in_array( $v['HitType'],$targetCata) &&  (!in_array($v['HitType'],$riskArr)) ){
                                array_push($riskArr , $v['HitType']);
                        }
                        if( (int)$v['Score'] > $score ){
                                $score = (int)$v['Score'];
                        }
                }

                return array('score'=>$score,'risk'=>$riskArr,'raw'=>$resArr['CheckNameResponse']['CheckNameResult']['Hit']);
        }


}

?>
