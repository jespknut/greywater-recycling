<?php


$csvheaders .= 'Start date;End date;Step size;CT volume;HT volume;Collect from;Collect rooms;HW reuse;HW reuse rooms;CW reuse;CW reuse rooms;CW reuse threshold;Treatment threshold;Treatment capacity;';

$csvheaders .= 'baseline HW;baseline CW;treated volume;Washing machine CW;Washing machine HW;Shower CW;Shower HW;WC CW;WC HW;Sink CW;Sink HW;Kitchen CW;Kithcen HW;HW reuse;CW reuse;CT overflow;HT overflow;municiopal HW;municipal CW;HW reduction;CW reduction;WC reduction;WW reduction;HW deficiency;CW deficiency;Collected CW;Collected HW;Treated volume;Energy expenditure'."\n";


$simulation_start = "2019-09-01 00:00:00";
$simulation_end = "2019-11-01 00:00:00";

$simulation_start_time  = $timeCounter = strtotime($simulation_start);
$simulation_end_time = strtotime($simulation_end);

	$rw_collection_area = 400; //m2
	$rw_collection_coefficient = 0.8;

unset($baseLineCase);unset($superEvents);unset($event_array);

$step = 10; //minutes

$events  = $db->get_results("SELECT a.timestamp,a.ID,SUM(a.value) AS value,a.type,a.room_type,a.room_number,a.attached_to,c.super_event_id FROM `tbl_events` a
LEFT JOIN tbl_event_relations b ON a.ID=b.event_id
LEFT JOIN tbl_super_events c ON c.super_event_id = b.super_event_id
WHERE a.timestamp>'$simulation_start' AND a.timestamp<'$simulation_end'
GROUP BY c.super_event_id, a.type");

$rainfall  = $db->get_results("SELECT * FROM tbl_rainfall WHERE date>'$simulation_start' AND date<'$simulation_end' ORDER BY ID ASC");

foreach($events AS $row) {
	
	$superEvents[$row->super_event_id]['meta'] = array("timestamp"=>$row->timestamp,"room_number"=>$row->room_number,"room_type"=>$row->room_type,"type"=>$row->type,"attached_to"=>$row->attached_to);
	if($row->room_number>1500 OR $row->room_number==1004) {
		if($row->type=='cold water consumption') {
			$superEvents[$row->super_event_id]['cw_value'] = round($row->value,4);
			if(!$superEvents[$row->super_event_id]['hw_value']) $superEvents[$row->super_event_id]['hw_value'] = 0;
			$baseLineCase['cw'] = round($baseLineCase['cw'],4) + round($row->value,4);
			$baseLineCase[$row->attached_to]['cw'] = round($baseLineCase[$row->attached_to]['cw'],4) + round($row->value,4);
			
		} else {
			$superEvents[$row->super_event_id]['hw_value'] = round($row->value,4);
			if(!$superEvents[$row->super_event_id]['cw_value']) $superEvents[$row->super_event_id]['cw_value'] = 0;
			$baseLineCase['hw'] = round($baseLineCase['hw'],4) + round($row->value,4);
			$baseLineCase[$row->attached_to]['hw'] = round($baseLineCase[$row->attached_to]['hw'],4) + round($row->value,4);
		}
	}	
}

echo '<pre>';
//print_r($baseLineCase);
echo '</pre>';

foreach($superEvents AS $superID => $innerA) {
	//echo $innerA['meta']['timestamp'].'.<br>';
	$epoch = floor((strtotime($innerA['meta']['timestamp'])-$simulation_start_time)/60/$step); //define timeslot
	if($innerA['meta']['room_number']>1999 OR $innerA['meta']['room_number']==1004) {
		$event_array[$epoch][] = array("timestamp"=>$innerA['meta']['timestamp'],"cw_value"=>round($innerA['cw_value'],4),"hw_value"=>round($innerA['hw_value'],4),"room_number"=>$innerA['meta']['room_number'],"room_type"=>$innerA['meta']['room_type'],"type"=>$innerA['meta']['type'],"attached_to"=>$innerA['meta']['attached_to']);
	}
}

foreach($rainfall AS $row) {
	//echo $innerA['meta']['timestamp'].'.<br>';
	if($row->value>0) {
		$rw_volume = $row->value * $rw_collection_area * $rw_collection_coefficient / 1000;
	} else {
		$rw_volume = 0;
	}
	$epoch = floor((strtotime($row->date)-$simulation_start_time)/60/$step); //define timeslot
	$rainfall_array[$epoch][] = array("timestamp"=>$row->date,"value"=>round($rw_volume,5));
	//$rainfall_sum = $rainfall_sum + $rw_volume;
}

foreach ($rainfall_array AS $inner) {
	foreach ($inner AS $inner2) {
		$rainfall_sum = $rainfall_sum + $inner2['value'];
	}
}

//echo $rainfall_sum.' <-- ';


$filename = 'B_Sink_WC_reuse_'.date("Y-m-d H:i").'.csv';
$filename = 'B_Sink_Shower_reuse_'.date("Y-m-d H:i").'.csv';
//$filename = 'A_sink_shower_wc_reuse_'.date("Y-m-d H:i").'.csv';
$fp = fopen(ABSPATH.'plugins/admin-panel/'.$filename,"a+");
fwrite ($fp,$csvheaders);

//SINK SHOWER
	for($ct=0.05;$ct<=0.3;$ct+=0.05) {
		for($ht=0.05;$ht<=0.3;$ht+=0.05) {
			for($tc=0.001;$tc<=0.005;$tc+=0.001) {
				//for($cwrt=0;$cwrt<=0;$cwrt+=0) {	
					echo $ct.",".$ht.','.$tc.','.$trtr.'<br>';	
					$dataStringArray = run_sim($baseLineCase,$simulation_start,$simulation_end,$simulation_end_time,$event_array,$ct,$ht,$tc,0,1,$step,$rainfall_array);	
					fwrite ($fp,implode(";",$dataStringArray)."\n");
				//}
			}
		}
	}


/*
	


//SINK WC
for($ct=0.02;$ct<=0.1;$ct+=0.02) {
		for($ht=0.02;$ht<=0.1;$ht+=0.02) {
			for($tc=0.001;$tc<=0.006;$tc+=0.001) {
				//for($cwrt=0;$cwrt<=0;$cwrt+=0) {	
					echo $ct.",".$ht.','.$tc.','.$trtr.'<br>';	
					$dataStringArray = run_sim($baseLineCase,$simulation_start,$simulation_end,$simulation_end_time,$event_array,$ct,$ht,$tc,0,1,$step,$rainfall_array);	
					fwrite ($fp,implode(";",$dataStringArray)."\n");
				//}
			}
		}
	}

	//SINK SHOWER WC
	for($ct=0.1;$ct<=0.31;$ct+=0.1) {
		for($ht=0.1;$ht<=0.31;$ht+=0.1) {
			for($tc=0.002;$tc<=0.010;$tc+=0.002) {
				for($cwrt=0;$cwrt<=0.8;$cwrt+=0.2) {	
					echo $ct.",".$ht.','.$tc.','.$trtr.'<br>';	
					$dataStringArray = run_sim($baseLineCase,$simulation_start,$simulation_end,$simulation_end_time,$event_array,$ct,$ht,$tc,$cwrt,1,$step,$rainfall_array);	
					fwrite ($fp,implode(";",$dataStringArray)."\n");
				}
			}
		}
	}


	
	*/
	


fclose($fp);	

function run_sim($baseLineCase,$simulation_start,$simulation_end,$simulation_end_time,$event_array,$ct,$ht,$tc,$cwrt,$trtr,$step=10,$rainfall_array) {
	
	echo '<pre>';
	//print_r($rainfall_array);
echo '</pre>';
	
	$collection_tank['current_level'] = 0; //m3
	$holding_tank['current_level'] = 0; //m3
	
	$treatment_energy = 0.661; //kWh per cbm in the treatment process
	$cw_energy = 1; //kWh per cbm for reusing as CHW
	$hw_energy = (15.9+1-55); //kWh per cbm for reusing as HW
	$currentEpoch = 0;
	$simulation_start_time  = $timeCounter = strtotime($simulation_start);
	
	$collection_tank['volume'] = $ct; //m3
	$holding_tank['volume'] = $ht; //m3
	
	$treatment_capacity = $tc; //m3 per minute
	$cw_reuse_threshold = $cwrt; //AT WHAT HOLDING TANK FILL LEVEL SHOULD WATER BE REUSED FOR WC
	$treatment_threshold = $trtr; //WHEN HOLDING TANK IS BELOW THIS FILL RATIO, TREAT WATER FROM COLLECTION TANK
	
	//RAINWATER PARAMETERS
	$rw_tank['volume'] = 2; // RAINWATER TANK m3
	$rw_tank['current_level'] = 0; //m3
		
	//COMMON PARAMETERS
	$collect_rw = FALSE;
	$collect_from_room = explode(",","RWC,WC,WC gemensamt,Boenderum,Tvättstudio / Utställ");
	$resue_in_room = explode(",","RWC,WC,WC gemensamt,Boenderum,,Tvättstudio / Utställ");
	$resue_cw_in_room = explode(",","RWC,WC,WC gemensamt,Boenderum,Tvättstudio / Utställ");
	
	//$collect_from = explode(",","Tvättställ,Dusch,Vatten Tvättmaskin");
	//$collect_from_room = explode(",","WC,WC gemensamt,Boenderum,Tvättstudio / Utställ");
	//$resue_as_hw = explode(",","Tvättställ,Dusch");
	//$resue_as_cw = explode(",","Vatten WC stol,Vatten Tvättmaskin");
	
	// SINK WC 
	$collect_from = explode(",","Tvättställ");
	$resue_as_hw = array();
	$resue_as_cw = explode(",","Vatten WC stol");
	
	
	// SINK SHOWER 
	$collect_from = explode(",","Tvättställ,Dusch");
	$resue_as_hw = explode(",","Tvättställ,Dusch");
	$resue_as_cw = array();
	
	/*
	// SINK SHOWER WC
	$collect_from = explode(",","Tvättställ,Dusch");
	$resue_as_hw = explode(",","Tvättställ,Dusch");
	$resue_as_cw = explode(",","Vatten WC stol");
	*/
	
	unset($dataStringArray);unset($totals);unset($record);

	for($timeCounter = strtotime($simulation_start);$timeCounter < $simulation_end_time;$timeCounter=$timeCounter+($step*60)) {
		$currentEpoch++;	
		
		$numEvents = count($event_array[$currentEpoch]);
		$record[$currentEpoch]['num_events'] = $numEvents;
		$record[$currentEpoch]['timestamp'] = date("Y-m-d H:i:s",$timeCounter);
		
		if($numEvents>0) {
			foreach($event_array[$currentEpoch] AS $n=>$innerA) {
				
				$record[$currentEpoch]['original_hw_demand'] = $record[$currentEpoch]['original_hw_demand'] + $innerA['hw_value'];
				$record[$currentEpoch]['original_cw_demand'] = $record[$currentEpoch]['original_cw_demand'] + $innerA['cw_value'];
					
				//TREATMENT
				for($i=0;$i<=floor($step/$numEvents);$i++) {
					if(($collection_tank['current_level'] - $treatment_capacity)<=0 
					OR ($holding_tank['current_level']/$holding_tank['volume']>$treatment_threshold) 
					OR (($holding_tank['current_level']+$treatment_capacity)>$holding_tank['volume']) 
					OR ($holding_tank['current_level']>$holding_tank['volume'])) break;
					$treatedVolume = ($treatment_capacity);
					$collection_tank['current_level'] = $collection_tank['current_level'] - $treatedVolume;
					$holding_tank['current_level'] = $holding_tank['current_level'] + $treatedVolume;
					$record[$currentEpoch]['treated_volume'] = $record[$currentEpoch]['treated_volume'] + $treatedVolume;
					$record[$currentEpoch]['energy_expenditure'] = $record[$currentEpoch]['energy_expenditure'] + ($treatedVolume*$treatment_energy);
				}
				
				
				//SUPPLY HW
				if(in_array($innerA['attached_to'],$resue_as_hw) AND in_array($innerA['room_type'],$resue_in_room) AND $innerA['hw_value']>0) {
					//echo $innerA['attached_to'].$innerA['room_type'].'; ';
					
					
						// THere is simultaneous demand for cold water, but it will be proportionally less since feed hotwater has lower temperature. We simulate this by decreasing CW demand by 80% and increasing the recycled HW by the same corresponding amount.
						$cwShare = $innerA['cw_value']/($innerA['hw_value']+$innerA['cw_value']);
						$tempHW = $innerA['hw_value'];
						
					
					if($holding_tank['current_level']>0) {
						
						if($innerA['cw_value']>0) {
							$innerA['hw_value'] = round($innerA['hw_value']*1.2,3);	//... AND MORE HW
							$innerA['cw_value'] = round($innerA['cw_value']-($innerA['hw_value']-$tempHW),3); //HW WITH LOWER TEMP MEAN WE USE LESS CW TO COOL
							if($innerA['cw_value']<0) { //MAKE SURE THAT CW CAN NEVER BE <0
								$innerA['hw_value']=$innerA['hw_value']+$innerA['cw_value'];
								$innerA['cw_value']=0;
							}
							$record[$currentEpoch]['adjusted_hw_cw_per_event'][] = $innerA['hw_value'].','.$innerA['cw_value'];
						}
						
						if($holding_tank['current_level']-$innerA['hw_value']>0) { //ALL HW FROM REUSE
							$reused = $innerA['hw_value'];
							$municipal = 0;
						} else { //NOT ALL HW FROM REUSE
							
							if($innerA['cw_value']>0) {
								$HWdecrease = round(($innerA['hw_value']-$holding_tank['current_level'])-(($innerA['hw_value']-$holding_tank['current_level'])*(5/6)),3);
								$municipal = round(($innerA['hw_value']-$holding_tank['current_level'])-$HWdecrease,3);
								$innerA['hw_value'] = $innerA['hw_value']-$HWdecrease;
								$innerA['cw_value'] = round($innerA['cw_value'] + $HWdecrease,3);
							} else {
								$municipal = round(($innerA['hw_value']-$holding_tank['current_level']),3);
							}
							
							$reused = $holding_tank['current_level'];
							
							//$totals['hw_deficiency'] = $totals['hw_deficiency'] + $municipal;
							$record[$currentEpoch]['hw_deficiency'] = round($municipal,4);
						}
						$record[$currentEpoch]['reused_hw'] = $record[$currentEpoch]['reused_hw'] + $reused;
						$record[$currentEpoch]['energy_expenditure'] = $record[$currentEpoch]['energy_expenditure'] + ($reused*$hw_energy);
						$record[$currentEpoch]['municipal_hw'] = $record[$currentEpoch]['municipal_hw'] + $municipal;
						$holding_tank['current_level'] = $holding_tank['current_level'] - $reused;
					
					} else {
						$record[$currentEpoch]['municipal_hw'] = $record[$currentEpoch]['municipal_hw'] + $innerA['hw_value'];
						//$totals['hw_deficiency'] = $totals['hw_deficiency'] + $innerA['hw_value'];
						$record[$currentEpoch]['hw_deficiency'] = round($innerA['hw_value'],4);
					}	
				} else { //SUPPLY ALL HW FROM MUNICIPAL
									
					//echo $innerA['attached_to'].$innerA['room_type'].'; ';
					$record[$currentEpoch]['municipal_hw'] = $record[$currentEpoch]['municipal_hw'] + $innerA['hw_value'];
					
					//VALIDATION CHECK
					if($record[$currentEpoch]['municipal_hw']<0) moratorium($record,$event_array,$currentEpoch,'Municipal HW error');
				}
				
				
				//CW REUSE
				if(in_array($innerA['attached_to'],$resue_as_cw) AND in_array($innerA['room_type'],$resue_cw_in_room) AND ($holding_tank['current_level']/$holding_tank['volume']>$cw_reuse_threshold)) {
					
					$thisBalance = $innerA['cw_value'];$initialHT =$holding_tank['current_level'];
					
					if($collect_rw AND $rw_tank['current_level']>0) {
						if($rw_tank['current_level']>=$thisBalance) {
							$reused = $thisBalance;
							$rw_tank['current_level'] = $rw_tank['current_level'] - $thisBalance;
							$thisBalance = 0;
						} else {
							$thisBalance = $thisBalance - $rw_tank['current_level'];
							$reused = $rw_tank['current_level'];
							
							$rw_tank['current_level'] = 0;
						}
						$record[$currentEpoch]['reused_rw'] = round($record[$currentEpoch]['reused_rw'],4) + round($reused,4);
						$record[$currentEpoch]['rw_deficiency'] = $thisBalance;
					}
					
					if($thisBalance>0 AND $holding_tank['current_level']>0) {
						if($holding_tank['current_level']-$thisBalance>=0) { //IF WE HAVE MORE TREATED WATER THAN NEEDED
							$reused =+ $thisBalance;
							$holding_tank['current_level'] = $holding_tank['current_level'] - $thisBalance;
							$thisBalance = 0;
						} else {
							$thisBalance = $thisBalance - $holding_tank['current_level'];
							$reused = $reused + $holding_tank['current_level'];
							
							$holding_tank['current_level'] = 0;
						}
					}
					
					//VALIDATION CHECK
					if($thisBalance<0) moratorium($record,$event_array,$currentEpoch,'Balanace error: '.$thisBalance.' ['.$innerA['cw_value'].'/'.$initialHT.']');
					
					
					
					$record[$currentEpoch]['cw_deficiency'] = $record[$currentEpoch]['cw_deficiency'] + $thisBalance;
					
					$municipal = $innerA['cw_value'] - $reused;
					
					$record[$currentEpoch]['reused_cw'] = round($record[$currentEpoch]['reused_cw'],4) + round($reused,4);
					$record[$currentEpoch]['energy_expenditure'] = $record[$currentEpoch]['energy_expenditure'] + ($reused*$cw_energy);
					$record[$currentEpoch]['municipal_cw'] = $record[$currentEpoch]['municipal_cw'] + $municipal;
					$record[$currentEpoch]['municipal_cw_per_event'][] = $municipal;
								
					if($record[$currentEpoch]['municipal_cw']<0) moratorium($record,$event_array,$currentEpoch,'Municipal CW error:'.$municipal.'='.$innerA['cw_value'].'-'.$reused);
					
				} else {
					if(in_array($innerA['attached_to'],$resue_as_cw) AND in_array($innerA['room_type'],$resue_cw_in_room)) {
						//$totals['cw_deficiency'] = $totals['cw_deficiency'] + $innerA['cw_value'];
						$record[$currentEpoch]['cw_deficiency'] = $innerA['cw_value'];
					}
					//IF NO REUSE CASE SUPPLY BY MUNICIPAL
					$record[$currentEpoch]['municipal_cw'] = $record[$currentEpoch]['municipal_cw'] + $innerA['cw_value'];
					$record[$currentEpoch]['municipal_cw_per_event'][] = $innerA['cw_value'];
				}
				unset($reused);
				
				
				//COLLECTION		
				if(in_array($innerA['attached_to'],$collect_from) AND in_array($innerA['room_type'],$collect_from_room)) {
					$collection_tank['current_level'] = $collection_tank['current_level'] + ($innerA['hw_value'] + $innerA['cw_value']);
					//$totals['collected_cw']=round($totals['collected_cw'],4)+round($innerA['cw_value'],4);
					//$totals['collected_hw']=round($totals['collected_hw'],4)+round($innerA['hw_value'],4);
					$record[$currentEpoch]['collected_cw'] = $record[$currentEpoch]['collected_cw'] + round($innerA['cw_value'],4);
					$record[$currentEpoch]['collected_hw'] = $record[$currentEpoch]['collected_hw'] + round($innerA['hw_value'],4);
				} else {
					$record[$currentEpoch]['wastewater'] = $record[$currentEpoch]['wastewater'] + ($innerA['hw_value'] + $innerA['cw_value']);
					$record[$currentEpoch]['wastewater_per_event'][] = ($innerA['hw_value'] + $innerA['cw_value']);
				}
					
			} // END EVENT LOOP
		
	
		} else { //No events, but we still treat any CT water
			//TREATMENT
			for($i=0;$i<intval($step);$i++) {
				if(($collection_tank['current_level'] - $treatment_capacity)<=0 
					OR ($holding_tank['current_level']/$holding_tank['volume']>$treatment_threshold) 
					OR (($holding_tank['current_level']+$treatment_capacity)>$holding_tank['volume'])
					OR ($holding_tank['current_level']>$holding_tank['volume'])) break;
				$collection_tank['current_level'] = $collection_tank['current_level'] - $treatment_capacity;
				$holding_tank['current_level'] = $holding_tank['current_level'] + $treatment_capacity;
				$record[$currentEpoch]['treated_volume'] = $record[$currentEpoch]['treated_volume'] + $treatment_capacity;
				$record[$currentEpoch]['energy_expenditure'] = $record[$currentEpoch]['energy_expenditure'] + ($treatment_capacity*$treatment_energy);
			}
			
		}
		//READJUST HW SO THAT VOLUMES AGREES
			$deltaW = $record[$currentEpoch]['original_hw_demand'] + $record[$currentEpoch]['original_cw_demand'] - $innerA['hw_value'] - $innerA['cw_value'];
			if($deltaW != 0) {
				$redistW = ($deltaW*1000)/2;
				$record[$currentEpoch]['adjusted_hw_demand'] = $innerA['hw_value'] = $innerA['hw_value'] + (floor($redistW)/1000);
				$record[$currentEpoch]['adjusted_cw_demand'] = $innerA['cw_value'] = $innerA['cw_value'] + (ceil($redistW)/1000);
			}
		
		// RW COLLECTION		
			if($collect_rw AND is_array($rainfall_array[$currentEpoch])) {
				foreach($rainfall_array[$currentEpoch] AS $a=>$innerA) {
					$rw_tank['current_level'] = $rw_tank['current_level'] + $innerA['value'];
					//$totals['collected_rw']=round($totals['collected_rw'],4)+round($innerA['value'],4);
					$record[$currentEpoch]['collected_rw'] = ($record[$currentEpoch]['collected_rw'] + $innerA['value']);
					$rwtotal = $rwtotal +  $innerA['value'];
				}
			}
		
		
		//CHECK TANKS FOR OVERFLOW
			if($collection_tank['current_level']>$collection_tank['volume']) {
				$record[$currentEpoch]['ct_overflow'] = ($collection_tank['current_level']-$collection_tank['volume']);
				$collection_tank['current_level'] = $collection_tank['volume'];
			} 
			if($holding_tank['current_level']>$holding_tank['volume']) {
				$record[$currentEpoch]['ht_overflow'] = ($holding_tank['current_level']-$holding_tank['volume']);
				$holding_tank['current_level'] = $holding_tank['volume'];
			}
			if($rw_tank['current_level']>$rw_tank['volume']) {
				$record[$currentEpoch]['rw_overflow'] = ($rw_tank['current_level']-$rw_tank['volume']);
				$rw_tank['current_level'] = $rw_tank['volume'];
			} 			
		
		$record[$currentEpoch]['ht_level'] = ($holding_tank['current_level']);
		$record[$currentEpoch]['ct_level'] = round($collection_tank['current_level'],4);
		$record[$currentEpoch]['rw_level'] = round($rw_tank['current_level'],4);
		
		//VALIDATE EPOCH 
		
		if($record[$currentEpoch]['municipal_cw']<0) moratorium($record,$event_array,$currentEpoch,'Municipal CW error');
		
		$balance = round($record[$currentEpoch]['municipal_hw'] + $record[$currentEpoch]['municipal_cw'] + $record[$currentEpoch]['reused_cw'] + $record[$currentEpoch]['reused_hw'] - $record[$currentEpoch]['collected_hw'] - $record[$currentEpoch]['collected_cw'] - $record[$currentEpoch]['wastewater'],3);
		
		$calc = $record[$currentEpoch]['municipal_hw']."+".$record[$currentEpoch]['municipal_cw']."+".$record[$currentEpoch]['reused_cw']."+".$record[$currentEpoch]['reused_hw']."-".$record[$currentEpoch]['collected_hw']."-".$record[$currentEpoch]['collected_cw']."-".$record[$currentEpoch]['wastewater'];
		
		if($balance!=0 OR $collection_tank['current_level']<0) {
			moratorium($record,$event_array,$currentEpoch,'Validation error: '.$calc.' = '.$balance.' | HWdecrease: '.$HWdecrease);
		}
		
		
		if(round(($record[$currentEpoch]['treated_volume']-$record[($currentEpoch-1)]['ct_level']),3)>round($record[$currentEpoch]['collected_cw']+$record[$currentEpoch]['collected_hw'],3)) moratorium($record,$event_array,$currentEpoch,'Reuse error: '.$record[$currentEpoch]['treated_volume'].'-'.$record[($currentEpoch-1)]['ct_level'].'>'.$record[$currentEpoch]['collected_cw'].'+'.$record[$currentEpoch]['collected_hw']);
		
		
		
	}

	//$keys = array_keys_multi($record);
	foreach($record AS $n=>$row) {
		foreach($row AS $key=>$value) {
			$keys[$key] = $key;
		}
	}


	foreach($record AS $n=>$row) {
		$prevEpoch = $n-1;
		foreach($keys AS $key) {
			if(!is_array($row[$key])) {
				$aggregatedRecord[$n][$key] = $aggregatedRecord[$prevEpoch][$key]+$row[$key];
				$totals[$key] = round($totals[$key]+$row[$key],4);
			}
		}
	}

	//echo '##'.$rwtotal.'##';

	echo '<h1>Greywater reuse simulator</h1>';
	echo '<h3>Parameters</h3>';
	/*
	echo '<table class="table table-sm table-hover">';
		echo '<tr>
				<th>Simulation data period</th>
				<th>Resolution (min)</th>
				<th>Collection tank volume (m<sup>3</sup>)</th>
				<th>Holding tank volume (m<sup>3</sup>)</th>
				
			</tr>
			<tr>
				<td>'.date("Y-m-d H",strtotime($simulation_start)).'-'.date("Y-m-d H",$simulation_end_time).'</td>
				<td>'.$step.'</td>
				<td>'.$collection_tank['volume'].'</td>
				<td>'.$holding_tank['volume'].'</td>
				
			</tr>
			<tr>			
				<th>Collection</th>
				<th>Reuse HW</th>
				<th>Reuse CW</th>
				<th>Treatment capacity (m<sup>3</sup> min<sup>-1</sup>)</th>
			</tr>
			<tr>			
				<td>'.implode(", ",$collect_from).'<br>'.implode(", ",$collect_from_room).'</td>
				<td>'.implode(", ",$resue_as_hw).'<br>'.implode(", ",$resue_in_room).'</td>
				<td>'.implode(", ",$resue_as_cw).'<br>'.implode(", ",$resue_cw_in_room).'</td>
				<td>'.$treatment_capacity.'</td>
			</tr>
			</table>';


	

	echo '<h3>Output</h3>';
	echo '<table class="table table-sm table-hover">';
		echo '<tr>
				<th>Baseline HW (m<sup>3</sup>)</th>
				<th>Baseline CW (m<sup>3</sup>)</th>
				<th>Baseline WW (m<sup>3</sup>)</th>
				<th>Baseline Washing machine (m<sup>3</sup>)</th>		
			</tr>
			<tr>
				<td>'.$baseLineCase['hw'].'</td>
				<td>'.$baseLineCase['cw'].'</td>
				<td>'.($baseLineCase['hw']+$baseLineCase['cw']).'</td>
				<td>'.$baseLineCase['Vatten Tvättmaskin']['cw'].'/'.$baseLineCase['Vatten Tvättmaskin']['hw'].'</td>
			</tr>
			<tr>
				<th>Baseline Shower (m<sup>3</sup>)</th>
				<th>Baseline WC (m<sup>3</sup>)</th>
				<th>Baseline Sink (m<sup>3</sup>)</th>
				<th>Baseline Kitchen (m<sup>3</sup>)</th>		
			</tr>
			<tr>
				<td>'.$baseLineCase['Dusch']['cw'].'/'.$baseLineCase['Dusch']['hw'].'</td>
				<td>'.$baseLineCase['Vatten WC stol']['cw'].'/'.$baseLineCase['Vatten WC stol']['hw'].'</td>
				<td>'.$baseLineCase['Tvättställ']['cw'].'/'.$baseLineCase['Tvättställ']['hw'].'</td>
				<td>'.$baseLineCase['Tappvatten Kök']['cw'].'/'.$baseLineCase['Tappvatten Kök']['hw'].'</td>
			</tr>
			<tr>			
				<th>Reused HW (m<sup>3</sup>)</th>
				<th>Reused CW (m<sup>3</sup>)</th>			
				<th>CT overflow (m<sup>3</sup>)</th>
				<th>HT overflow (m<sup>3</sup>)</th>
			</tr>
			<tr>			
				<td>'.$totals['reused_hw'].'</td>
				<td>'.$totals['reused_cw'].'</td>
				<td>'.$totals['ct_overflow'].'</td>
				<td>'.$totals['ht_overflow'].'</td>
			</tr>
			<tr>
				<th>HW reduction (%)</th>
				<th>CW reduction (%)</th>			
				<th>WC reduction (%)</th>
				<th>WW reduction (%)</th>
			</tr>
			<tr>
				<td>'.round((1-($totals['municipal_hw']/$baseLineCase['hw']))*100,1).'%</td>
				<td>'.round((1-($totals['municipal_cw']/$baseLineCase['cw']))*100,1).'%</td>
				<td>'.round((($totals['reused_cw']/$baseLineCase['Vatten WC stol']['cw']))*100,1).'%</td>
				<td>'.round((1-(($totals['municipal_hw']+$totals['municipal_cw']+$totals['reused_rw'])/($baseLineCase['hw']+$baseLineCase['cw'])))*100,1).'%</td>		
			</tr>
			<tr>
				<th>HW deficiency (m<sup>3</sup>)</th>
				<th>CW deficiency (m<sup>3</sup>)</th>			
				<th>Treated volume (m<sup>3</sup>)</th>
				<th>Energy expenditure (kWh)</th>
			</tr>
			<tr>
				<td>'.$totals['hw_deficiency'].'</td>
				<td>'.round($totals['cw_deficiency'],3).'</td>
				<td>'.$totals['treated_volume'].'</td>
				<td>'.round($totals['energy_expenditure'],3).'</td>		
			</tr>
			<tr>
				<th>Collected CW (m<sup>3</sup>)</th>
				<th>Collected HW (m<sup>3</sup>)</th>			
				<th>Collected RW (m<sup>3</sup>)</th>
				<th>RW overflow</th>
			</tr>
			<tr>
				<td>'.$totals['collected_cw'].'</td>
				<td>'.round($totals['collected_hw'],3).'</td>
				<td>'.$totals['collected_rw'].'</td>
				<td>'.$totals['rw_overflow'].'</td>		
			</tr>
			
			<tr>
				<th>Reused RW (m<sup>3</sup>)</th>
				<th>RW deficiency (m<sup>3</sup>)</th>			
				<th></th>
				<th></th>
			</tr>
			<tr>
				<td>'.$totals['reused_rw'].'</td>
				<td>'.round($totals['rw_deficiency'],3).'</td>
				<td></td>
				<td></td>		
			</tr>			
		</table>';*/
	
	$dataStringArray[]=$simulation_start;$dataStringArray[]=$simulation_end;$dataStringArray[]=$step;$dataStringArray[]=$collection_tank['volume'];$dataStringArray[]=$holding_tank['volume'];

	$dataStringArray[]=implode(", ",$collect_from);$dataStringArray[]=implode(", ",$collect_from_room);$dataStringArray[]=implode(", ",$resue_as_hw);$dataStringArray[]=implode(", ",$resue_in_room);$dataStringArray[]=implode(", ",$resue_as_cw);$dataStringArray[]=implode(", ",$resue_cw_in_room);$dataStringArray[]=$cw_reuse_threshold;$dataStringArray[]=$treatment_threshold;$dataStringArray[]=$treatment_capacity;
			
	$dataStringArray[]=$baseLineCase['hw'];$dataStringArray[]=$baseLineCase['cw'];$dataStringArray[]=$totals['treated_volume'];
	$dataStringArray[]=$baseLineCase['Vatten Tvättmaskin']['cw'];$dataStringArray[]=$baseLineCase['Vatten Tvättmaskin']['hw'];
	$dataStringArray[]=$baseLineCase['Dusch']['cw'];$dataStringArray[]=$baseLineCase['Dusch']['hw'];
	$dataStringArray[]=$baseLineCase['Vatten WC stol']['cw'];$dataStringArray[]=$baseLineCase['Vatten WC stol']['hw'];
	$dataStringArray[]=$baseLineCase['Tvättställ']['cw'];$dataStringArray[]=$baseLineCase['Tvättställ']['hw'];
	$dataStringArray[]=$baseLineCase['Tappvatten Kök']['cw'];$dataStringArray[]=$baseLineCase['Tappvatten Kök']['hw'];
	$dataStringArray[]=$totals['reused_hw'];$dataStringArray[]=$totals['reused_cw'];$dataStringArray[]=$totals['ct_overflow'];$dataStringArray[]=$totals['ht_overflow'];
	
	$dataStringArray[]= round($totals['municipal_hw'],3);
	$dataStringArray[]= round($totals['municipal_cw'],3);
	
	$dataStringArray[]= round((1-($totals['municipal_hw']/$baseLineCase['hw'])),3);
	$dataStringArray[]= round((1-($totals['municipal_cw']/$baseLineCase['cw'])),3);
	$dataStringArray[]= round((1-($totals['reused_cw']/$baseLineCase['Vatten WC stol']['cw'])),3);
	$dataStringArray[]= round((1-($totals['wastewater']+$totals['ct_overflow']+$totals['ht_overflow'])/($baseLineCase['hw']+$baseLineCase['cw'])),3);
	$dataStringArray[]=$totals['hw_deficiency'];
	$dataStringArray[]=round($totals['cw_deficiency'],3);
	$dataStringArray[]=$totals['collected_cw'];
	$dataStringArray[]=round($totals['collected_hw'],3);
	$dataStringArray[]=round($totals['treated_volume'],3);
	$dataStringArray[]=round($totals['energy_expenditure'],3);
	
	//output_sim_table($record);
	
	return $dataStringArray;

}


function output_sim_table($record) {
	echo '<h2>Data</h2><table class="table">';
		echo '<tr>
				<th>Epoch</th>
				<th>Timestamp</th>
				<th>Num events</th>
				<th>municipal_hw</th>
				<th>municipal_cw</th>
				<th>reused_hw</th>
				<th>reused_cw</th>
				<th>reused_rw</th>
				<th>wastewater</th>
				<th>ht_level</th>
				<th>ct_level</th>
				<th>rw_level</th>
				<th>treated_volume</th>
				<th>collected_rw</th>
				<th>ht_overflow</th>
				<th>ct_overflow</th>
			</tr>';
	foreach($record AS $n=>$row) {
		echo '<tr>
				<td>'.$n.'</td>
				<td>'.$row['timestamp'].'</td>
				<td>'.$row['num_events'].'</td>
				<td>'.$row['municipal_hw'].'</td>
				<td>'.$row['municipal_cw'].'</td>
				<td>'.$row['reused_hw'].'</td>
				<td>'.$row['reused_cw'].'</td>
				<td>'.$row['reused_rw'].'</td>
				<td>'.$row['wastewater'].'</td>
				<td>'.$row['ht_level'].'</td>
				<td>'.$row['ct_level'].'</td>
				<td>'.$row['rw_level'].'</td>
				<td>'.$row['treated_volume'].'</td>
				<td>'.$row['collected_rw'].'</td>
				<td>'.$row['ht_overflow'].'</td>
				<td>'.$row['ct_overflow'].'</td>
			
			</tr>';
	}
	echo '</table>';
}


function array_keys_multi(array $array)
{
    $keys = array();

    foreach ($array as $key => $value) {
        $keys[$key] = $key;

        if (is_array($value)) {
            $keys = array_merge($keys, array_keys_multi($value));
        }
    }

    return $keys;
}

function moratorium($record,$event_array,$currentEpoch,$customcode) {
	echo '<pre>';
	foreach ($record[$currentEpoch] AS $key=>$value) {
		echo $key.': '.$value.'<br>';
	}
	print_r ($record[$currentEpoch]['wastewater_per_event']);
	print_r ($record[$currentEpoch]['municipal_cw_per_event']);
	print_r ($event_array[$currentEpoch]);
	die ($customcode);
}
