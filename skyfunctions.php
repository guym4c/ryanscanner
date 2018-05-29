<?php

function prepareSearch($iOrigin, $iDestination, $iOutbound, $key) {
	$origin = getPlaceSuggestion($iOrigin, $key);
	$destination = getPlaceSuggestion($iDestination, $key);

	$outbound = date('Y-m-d', strtotime($iOutbound));
	return array($origin, $destination, $outbound);
}

function getFlightsFromRaw($params, $key) {
	$url = getPollURL($params[0], $params[1], $params[2], $key); 
	$url = preg_replace('/\s+/', '', $url);

	$status = 0;
	$tries = 0;
	$data = "";
	while(($status == "UpdatesPending" || $statusCode == '304') && $tries < 10) {
		$ch = curl_init("$url?apiKey=$key");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Accept: application/json',
		));
		$data = curl_exec($ch);

		if(curl_error($ch)) {
			echo curl_error($ch);
		}

		$status = json_decode($data, true)['Status'];
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		sleep(1);
		$tries++;
	}

	$data = json_decode($data, true);

	return parseSkyJson($data);
}


function getPlaces($q, $key) {
	$data = array(
		'query' => urlencode($q),
		'apikey' => $key,
	);
	$ch = curl_init('https://partners.api.skyscanner.net/apiservices/autosuggest/v1.0/GB/GBP/en-GB?' . http_build_query($data));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);

	$places = json_decode(curl_exec($ch), true);
	curl_close($ch);
	return $places;
}

function getPlaceSuggestion($q, $key) {
	return getPlaces($q, $key)['Places'][0]['PlaceId'];
}

function getBrowsePrice($params, $key) {
	$origin = $params[0];
	$destination = $params[1];
	$outbound = $params[2];

	$ch = curl_init("http://partners.api.skyscanner.net/apiservices/browsequotes/v1.0/GB/GBP/en-GB/$origin/$destination/$outbound?apiKey=$key");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Accept: application/json',
	));
	$data = curl_exec($ch);
	$quotes = json_decode(curl_exec($ch), true);
	if(curl_error($ch)) {
		echo curl_error($ch);
	}
	if(json_last_error()) {die(json_last_error());}
	curl_close($ch);
	return $quotes;
}

function getMinPrice($params, $key) {
	$quotes = getBrowsePrice($params, $key);
	$min = 10000;
	if (count($quotes['Quotes'] > 0)) {
		$min = $quotes['Quotes'][0]['MinPrice'];
	}
	return $min;
}

function getPollURL($fromID, $toID, $date, $key) {
	$data = array(
		'country' 			=> 'UK',
		'currency' 			=> 'GBP',
		'locale' 			=> 'en-GB',
		'originPlace'   	=> $fromID,
		'destinationPlace'  => $toID,
		'outboundDate'		=> $date,
		'adults'			=> 1,
		'apiKey'			=> $key,
	);
	$user = $_SERVER['REMOTE_ADDR'];

	$ch = curl_init('https://partners.api.skyscanner.net/apiservices/pricing/v1.0');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_NOBODY, true);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/x-www-form-urlencoded',
		"X-Forwarded-For: $user", 
		'Accept: application/json',
	));
	$data = curl_exec($ch);
	if(curl_error($ch)) {
		echo curl_error($ch);
	}
	curl_close($ch);
	return parseHeaders($data)['Location'];
}

function parseHeaders($data) {
	$data = explode("\n", $data);
	$headers = array();
	$headers['status'] = $data[0];
	array_shift($data);
	// $data= array_slice($data, 0, -2);
	foreach($data as $part){
	    $middle = explode(":", $part);
	    $key = trim($middle[0]);
	    $headers[$key] = '';
	    for($i = 1; $i < count($middle); $i++) {
	    	if($i > 1) {
	    		$headers[$key] .= ":";
	    	}
	    	$headers[$key] .= $middle[$i];
	    }
	}
	return $headers;
}

function parseSkyJson($data) {
	$flights = array();
	foreach($data['Itineraries'] as $itin) {
		$leg = getLeg($data, $itin['OutboundLegId']);
		if(!count($leg['Stops'])) {
			$carrier = getSkyDatapoint($data['Carriers'], $leg['Carriers'][0]);
			$code = $carrier['Code'] . $leg['FlightNumbers'][0]['FlightNumber'];
			$agent = getSkyDatapoint($data['Agents'], $itin['PricingOptions'][0]['Agents'][0]);
			$from = getSkyDatapoint($data['Places'], $leg['OriginStation']);
			$to = getSkyDatapoint($data['Places'], $leg['DestinationStation']);
			$flights[$code] = array(
				'Depart'		=> $leg['Departure'],
				'From'			=> $from['Name'] . " (" . $from['Code'] . ")",
				'Duration'		=> $leg['Duration'],
				'Arrive'		=> $leg['Arrival'],
				'To'			=> $to['Name'] . " (" . $to['Code'] . ")",
				'LegId'			=> $leg['Id'],
				'Carrier'		=> $carrier['Name'],
				'CarrierImage'	=> $carrier['ImageUrl'],
				'CarrierId'		=> $carrier['Id'],
				'Flight'		=> $code,
				'Price'			=> $itin['PricingOptions'][0]['Price'],
				'Agent'			=> $agent['Name'],
				'AgentImage'	=> $agent['ImageUrl'],
				'AgentId'		=> $agent['Id'],
				'Deeplink'		=> $itin['PricingOptions'][0]['DeeplinkUrl'],
			);
		}
	}
	return $flights;
}

function getSkyDatapoint($data, $id) {
	foreach($data as $datum) {
		if($datum['Id'] == $id) {
			return $datum;
		}
	}
	return false;
}

?>
