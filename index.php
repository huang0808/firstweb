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

?>