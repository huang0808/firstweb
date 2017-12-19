<?php
echo " This is get editor detail informaton API";
// get editor detail information

function api_geteditordetail($detail,$start=0, $size = 20, $has_photo = false) {
    $solr_path = 'http://'.variable_get('host', 'XX').':'.variable_get ( 'port', '8080' ).variable_get ( 'editor', '/solr/editor' );
    $q = array();
    $detail = str_replace('freelance', '', strtolower($detail));
    if (strstr($detail, ' ')) {
        $detail = explode(' ',$detail);
        foreach ($detail as $item) {
            if (!empty($item))
                $q[] = ':*'.strtolower($item).'*';
        }
        $params ['q'] = implode(" OR ",$q);
    } else {
        $params ['q'] = ':*'.strtolower($detail).'*';
    }
    $params ['wt'] = 'phps';
    $params ['start'] = $start;
    $params ['rows'] = $size;
    //$params ['debugQuery'] = 'on';
    $params ['sort'] = 'score desc';
    $params ['fl'] = 'id,code,photo,last_name,first_name,forstext,fors,forscode,featured_editor_text_'.variable_get('region', 'english').',marketing_text_'.variable_get('region', 'english').',all_qualifications,score';
    $params ['fq'] = 'marketing_text_'.variable_get('region', 'english').':["" TO *] AND last_name:["" TO *] AND first_name:["" TO *] AND fors:["" TO *] AND credentialed_in_house:false'; // 'NOT code:["" TO *] AND ' removed
    $params ['defType'] = 'edismax';
    $params ['qf'] = 'last_name^10 first_name^8 all_qualifications^3';
    watchdog('params',var_export($params, true));
    $solr_query = $solr_path . '/select/?' . http_build_query ( $params, null, '&' );
    watchdog('query',var_export($solr_query, true));
    $ch =  curl_init($solr_query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $str = curl_exec($ch);

    $response = unserialize($str);

    if ($response)
        return $response['response'];
    else
        return null;
}
/*
****
****
****/

/* get job information by soap client*/


$create_query_result = sugar_call($soap_client, 'get_entry_list', $params);


//create soap client
$soap_client = create_soap_client();

        global $sessionid;
        $sessionid = r2_login($soap_client);
$params = array(
            'session'=>$sessionid,
            'query' =>$r2_query,
            'module_name' => 'job',
            'order_by' => 'date_entered',
            'offset' => 0,
            'select_fields' => array('id','name','date_entered'),
            'deleted' => 0
        );
function r2_login($soap_client) {
    // define method arguments
    $user_name = variable_get('user','demo');
    $user_password = variable_get('passwd','');
    $args = array(    
        'user_auth' => array(   
            'user_name'=>$user_name,
            'password'=>md5($user_password),
            'version'=>'1.0'
        ),
        'application_name'=>'SoapTest'
    );

    // call soap server method
    $result = sugar_call($soap_client, 'login', $args);

    $sessionid = $result['id'];
    return $sessionid;
}

function create_soap_client() {
    // include nu_soap library
    require_once(drupal_get_path('module', 'webform_sugar') .'/lib/nusoap/nusoap.php');
    require_once(drupal_get_path('module', 'webform_sugar') .'/lib/nusoap/class.wsdlcache.php');

    // define wsdl path
    $domain4sugar = variable_get('sugar_url','');
    $wsdl_path = $domain4sugar.'soap.php?wsdl';

    $cache = new nusoap_wsdlcache('/tmp', 86400);
    $wsdl = $cache->get($wsdl_path);
    if(is_null($wsdl))
    {
      $wsdl = new wsdl($wsdl_path, '', '', '', '', 5);
      $cache->put($wsdl);
    }

    // create new soap client instance
    try {
        $soap_client = new nusoap_client($wsdl,'wsdl','','','','');
    }
    catch( Exception $e ) {
        if( $soap_client->fault || $soap_client->getError() ) {
            watchdog( 'WR2', "SOAP connection failed" );
            return( false );
        }
    }
    if( empty($soap_client) || $soap_client->fault || $soap_client->getError() ) {
        watchdog( 'WR2', "SOAP create connection failed: ".$soap_client->getError() );
        return( false );
    }

    $soap_client->decodeUTF8(false);
    $soap_client->timeout = 1800;
    $soap_client->response_timeout = 1800;
    return $soap_client;
}


function sugar_call( &$soapclient, $method, $params )
{
    global $sessionid;
    $try = 1;
    $error_email = variable_get('email', 'demo');
    //watchdog('error',$error_email);
    while( true ) {
        $res = $soapclient->call( $method, $params );
        $errmsg = $soapclient->getError();
        //watchdog('errorss',$errmsg);
        // Error happened
        if( $errmsg ) {
            watchdog( 'WR2', "SOAP $method error: $errmsg" );
            if( ( stripos( $errmsg, "timed out" ) !== false ) && $try < 7 ) {
                watchdog( 'WR2', "Try call $method again: $try" );
                $try++;
                continue;
            }
            if( stripos( $errmsg, "Response not of type text/xml" ) !== false ) {
                watchdog( 'WR2', $soapclient->response );
            }
            mail($error_email, 'WR2 SOAP ERROR failure | ','Function: '.$method.' | Parameters: '.print_r($params, true).' | Return:'.print_r($res, true));
            return( false );
        }
        // Need to ReLogin
        if( ((preg_match('/get_|set_entry|login/', $method) && $res['error']['name']=='Invalid Login') || (preg_match('/set_relationship/', $method) && $res['name']=='Invalid Login')) || empty($soapclient)  ) {
            if ( $try < 7 ) {
                //$soapclient = create_soap_client();
                watchdog( 'WR2', "Soap Session LOST when trying to call $method : $try, Re-Login" );
                $sessionid = r2_login($soapclient); 
                $try++;
                continue;
            }

            mail($error_email, 'WR2 SOAP LOGIN failure | ','Function: '.$method.' | Parameters: '.print_r($params, true).' | Return:'.print_r($res, true));
        }
        break;
    }
    // verbose DEBUG
    //if (empty($soapclient)) watchdog('WR2', 'soapclient is EMPTY!');
    //if (preg_match('/set_/', $method)) watchdog('WR2', print_r($res,true)); 
    //watchdog('WR2', print_r($res,true));
    return( $res );
} 

?>