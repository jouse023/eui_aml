<?php

class Process{

public function __construct(){

}

public function djapi($paras){

        $testing_url    = 'https://djrc.api.test.dowjones.com/v1/search/name';
        $otherfilter    = '&record-type=P&search-type=precise&exclude-deceased=true&hits-from=1&hits-to=11';
        $token          = ' Basic MTgvRVVJQVBJOmRvd2pvbmVz';

        //$url = $testing_url . '?name=' . $paras->name . '&filter-region=' . $paras->nation_code ;
        $url = $testing_url . '?name=' . urlencode($paras->name) ;
        $url .= (property_exists( $paras , 'nation_code' ))?'&filter-region=' . $paras->nation_code:'';
        $url .= (property_exists( $paras , 'birthday' ))?'&date-of-birth=' . $paras->birthday:'';
        $url .= $otherfilter;

        $curl = curl_init();

        curl_setopt_array($curl, array(
                CURLOPT_URL =>$url ,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                        "Authorization:".$token
                ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        //return $response;
        $xml = simplexml_load_string($response , null , LIBXML_NOCDATA );
        $json = json_encode($xml);
        return $this->processBody(json_decode($json));
}

/*
        function : return in format
        input : DJ response json

*/
public function processBody($tmpObj){
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


}

?>}
