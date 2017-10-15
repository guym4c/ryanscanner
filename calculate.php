<?php

require 'skyfunctions.php';
require 'keys.php';
require 'bases.dat.php';

$directs = getFlightsFromRaw(prepareSearch($_POST['origin'], $_POST['destination'], $_POST['outbound'], $skyKey), $skyKey);
$minDirect = reset($directs)['Price'];

$quotesOrigin = array();
$hacksOrigin = array();
$hacksDest = array();
$airline = $testyjet;
foreach($airline as $base) {
	$params = prepareSearch($_POST['origin'], $base, $_POST['outbound'], $skyKey);
	$min = getMinPrice($params, $skyKey);
	if($min < $minDirect) {
		$hacksOrigin = array_merge($hacksOrigin, getFlightsFromRaw($params, $skyKey));
		$hacksDest = array_merge($hacksDest, getFlightsFromRaw(prepareSearch($base, $_POST['destination'], $_POST['outbound'], $skyKey), $skyKey));
	}
}

?>

<!doctype html>
<html>
	<head>
		<meta charset="utf-8">
		<link rel="stylesheet" href="style.css">
	</head>
	<body>
		<div class="content">
			<?php
			$datasets = array($directs, $hacksOrigin, $hacksDest);
			$headings = array('Direct flights', 'Connecting flights from your origin', 'Connecting flights to your destination');
			$subs = array('These flights are non-stop', 'Choose one flight from this list, and one from below.', 'We recommend about two hours for a connection, but it is entirely at your own risk.');
			$i = 0;
			foreach($datasets as $flights):?>
			<h1><?php echo $headings[$i];?></h1>
			<p><?php echo $subs[$i]; $i++;?></p>
			<?php foreach($flights as $flight): 
			$departTime = date('H:i', strtotime($flight['Depart']));
			$arriveTime = date('H:i', strtotime($flight['Arrive']));
			
			?>

			<div class="flight">
				<div>
					<div class="est light"></div>
					<h2 class="price"><?php echo $flight['Price'];?></h2>
				</div>
				<div>
					<img src="<?php echo $flight['CarrierImage'];?>">
				</div>
				<div>
					<h3><?php echo $flight['From'];?></h3>
					<p title="<?php echo $flight['Depart'];?>"><?php echo $departTime;?></p>
				</div>
				<div class="light center">
					<p title="<?php echo $flight['CarrierId'];?>"><?php echo $flight['Carrier'] . " " . $flight['Flight'];?></p>
					<p class="duration" title="<?php echo $flight['LegId'];?>"><?php echo $flight['Duration'];?></p>
				</div>
				<div>
					<h3><?php echo $flight['To'];?></h3>
					<p title="<?php echo $flight['Arrive'];?>"><?php echo $arriveTime;?></p>
				</div>
				<div>
					<img src="<?php echo $flight['AgentImage'];?>">
				</div>
				<div>
					<h2><a class="deeplink" href="<?php echo $flight['Deeplink'];?>"></a></h2>
					<p title="<?php echo $flight['AgentId'];?>" class="agent"><?php echo $flight['Agent'];?></p>
				</div>
			</div>
			<?php endforeach;
			endforeach;?>
		</div>
		<img src="skyscanner.png">
	</body>
</html>
