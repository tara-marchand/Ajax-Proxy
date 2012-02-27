<?php

include "../api_keys.php";

// add domains here to prevent proxy chaining by nefarious people; default allows all domains
$domain_whitelist = array('69.38.220.155', 'localhost', 'tmarchand.com');
$is_domain_valid = true;

if (sizeof($domain_whitelist)) {
	$domain = preg_replace("/^www\./", "", $_SERVER['HTTP_HOST']);
	// this attempts to prevent proxy chaining
	$is_xmlhttprequest = array_key_exists('HTTP_X_REQUESTED_WITH', $_SERVER) && 'XMLHttpRequest' === $_SERVER['HTTP_X_REQUESTED_WITH'];
	$is_domain_valid = $is_xmlhttprequest && in_array($domain, $domain_whitelist);
}

if ($is_domain_valid) {
	
    // params that shouldn't be passed along in request
    $params_to_exclude = array('charles', 'provider', 'service');

	// get the URL to be proxied; is it a POST or a GET?
	$is_post = array_key_exists('url', $_POST);
	$is_json = false;
	if (strpos($_SERVER['QUERY_STRING'],'json')) {
		$is_json = true;
	}

    $url = ($is_post) ? $_POST['url'] : $_GET['url'];
	$headers = '';
	$mime_type = '';
    $provider = "";
    $service = "";

	// possible url params: url, rss, cachetime
	if ($is_post) {
		if (array_key_exists('headers', $_POST)) {$headers = $_POST['headers'];}
		if (array_key_exists('mimeType', $_POST)) {$mime_type = $_POST['mimeType'];}
	} elseif ($is_json) {
		if (array_key_exists('headers', $_GET)) {$headers = $_GET['headers'];}
		$mime_type = 'application/json';
	} else {
		if (array_key_exists('headers', $_GET)) {$headers = $_GET['headers'];}
		if (array_key_exists('mimeType', $_GET)) {$mime_type = $_GET['mimeType'];}
	}

    // if it's a POST, put the POST data in the body
	if ($is_post) {
        // start the Curl session
        $session = curl_init($url);

        // use Charles as a local proxy?
        if (($domain == "localhost" || $domain == "127.0.0.1") && (array_key_exists("charles", $_POST)) && ($_POST["charles"] == true)) {
            curl_setopt($session, CURLOPT_PROXY, "127.0.0.1");
            curl_setopt($session, CURLOPT_PROXYPORT, 8888);
        }

		$post_vars = '';
		while ($element = current($_POST)) {
            if (!in_array(key($_POST), $params_to_exclude)) {
                $post_vars .= key($_POST).'='.$element.'&';
            }
            if (array_key_exists("provider", $_POST) && array_key_exists("service", $_POST)) {
                $path = $apis[$_POST["provider"]][$_POST["service"]];
                $post_vars .= $path["key"] . "=" . $path["value"] . "&";
            }
			next($_POST);
        }

		curl_setopt ($session, CURLOPT_POST, true);
		curl_setopt ($session, CURLOPT_POSTFIELDS, $post_vars);
    // if it's a GET, append the data to the URL
    } else {
        $query_string = array();
		while ($element = current($_GET)) {
            if ((key($_GET) != "url") && (in_array(key($_GET), $params_to_exclude) == false)) {
                $query_string[key($_GET)] = $element;
            }
            if (key($_GET) == "provider") {
                $provider = $element;
            }
            if (key($_GET) == "service") {
                $service = $element;
            }
            next($_GET);
        }
        if ($provider != "" && $service != "") {
            $query_string[$keys[$_GET["provider"]][$_GET["service"]]["key"]] = $keys[$_GET["provider"]][$_GET["service"]]["value"];
        }
        $url .= "?" . http_build_query($query_string);

        // start the curl session
        $session = curl_init($url);

        // use Charles as a local proxy?
        if (($domain == "localhost" || $domain == "127.0.0.1") && (array_key_exists("charles", $_GET)) && ($_GET["charles"] == true)) {
            curl_setopt($session, CURLOPT_PROXY, "127.0.0.1");
            curl_setopt($session, CURLOPT_PROXYPORT, 8888);
        }
    }

	// don't return HTTP headers; do return the contents of the call
	curl_setopt($session, CURLOPT_HEADER, ($headers == "true") ? true : false);
	curl_setopt($session, CURLOPT_FOLLOWLOCATION, true);
	// prevents an accidental or intentional DoS attack
	curl_setopt($session, CURLOPT_MAXREDIRS, 2);
	//curl_setopt($ch, CURLOPT_TIMEOUT, 4);
	curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

	// make the call
	$response = curl_exec($session);
    $headers = curl_getinfo($response);

	if ($mime_type != "") {
		// the web service returns XML; set the Content-Type appropriately
		header("Content-Type: ". $mime_type);
    }
	
    header("Content-Type: application/json");
    echo $response;
	curl_close($session);

}

?>
