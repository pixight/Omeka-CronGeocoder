<?php 

//the gps coordinates are encoded for europeana4D lat,long,0

$elementAdresse = "Coverage"; // here the dublin core field name that contains the address of the item
$elementGPS = "Coordonnees Lat&Long"; //here the name of your field that contains the coordinates
$elementAdresse_set_id = 1; //the element set id for the Dublin core field address
$elementGPS_set_id = 11; //the element set id for the ArchitectureAnalyse element linked to the field which contains the gps coordinates
$increment = 5; //no more than 5 request to google geocoder service by minute


include_once('db-connexion.php');
$result = $mysqli->query("SELECT * FROM `omeka_options` WHERE `name` LIKE 'cron_gps_lastnum'");

//see http://www.cylman.com/geocoder-une-adresse-en-php-obtenir-sa-latitude-et-sa-longitude_qr29.html for source 
function getXmlCoordsFromAdress($address)
{
    $coords=array();
    $base_url="http://maps.googleapis.com/maps/api/geocode/xml?";
    // ajouter &region=FR si ambiguité (lieu de la requete pris par défaut)
    $request_url = $base_url . "address=" . urlencode($address).'&sensor=false';
    $xml = simplexml_load_file($request_url) or die("url not loading");
    //print_r($xml);
    $coords['lat']=$coords['lon']='';
    $coords['status'] = $xml->status ;
    if($coords['status']=='OK')
    {
        $coords['lat'] = $xml->result->geometry->location->lat ;
        $coords['lon'] = $xml->result->geometry->location->lng ;
    }
    return $coords;
}




//on récup le dernier
if($result){
	$row = $result->fetch_assoc();
	$cron_lastnum = intval($row['value']);


	$result = $mysqli->query("SELECT COUNT(id) as compte FROM `omeka_element_texts` WHERE `element_id` = (SELECT `id` FROM `omeka_elements` WHERE `name` LIKE '".$elementAdresse."' AND `element_set_id` = ".$elementAdresse_set_id.")");
	if($result){
		$row = $result->fetch_assoc();
		$count = $row['compte'];
		
		if($cron_lastnum > $count){
			$cron_lastnum = 0;
		}

		$result = $mysqli->query("SELECT * FROM `omeka_element_texts` WHERE `element_id` = 
			(SELECT id FROM `omeka_elements` WHERE name LIKE '".$elementAdresse."' AND `element_set_id` = ".$elementAdresse_set_id.")
			ORDER BY  `omeka_element_texts`.`id` ASC
			LIMIT ".$cron_lastnum.",".$increment);
		if($result){
                    //on recherche l'id de l'élément gps
                    $resultforid = $mysqli->query("SELECT * FROM `omeka_elements` WHERE name LIKE '".$elementGPS."' AND `element_set_id` = ".$elementGPS_set_id);
                    if($resultforid){
                        $rowforid = $resultforid->fetch_assoc();
                        $element_gps_id = $rowforid['id'];

			while($row = $result->fetch_assoc()){


                                //on récupère le nom de la ville 
                                $adresse = $row['text'];
                                //on récupère l'id de l'item à mettre à jour
                                $itemid = $row['record_id'];
                                
                                
				/* GEOCODE SELON LA VILLE RETOURNEE 
				****************/
                                
                                
				$result2 = $mysqli->query("SELECT * FROM `omeka_element_texts` WHERE `element_id` = 
				".$element_gps_id." 
                                AND
                                `record_id` = ".$itemid);
                                
                                $coord_id = NULL;
                                if($result2){
                                    while($row2 = $result2->fetch_assoc()){
                                        $coord_id = $row2['id'];
                                        $coord = $row2['text'];
                                    }                                    
                                }
                                
                                $coords=getXmlCoordsFromAdress($adresse);
                                $coords_to_record = $coords['lat'].",".$coords['lon'].",0";

                                if($coords['status'] == "OK"){
                                    if($coord_id == NULL){ //on crée une nouvelle entrée coordonnée pour l'item
                                        $result3 = $mysqli->query("INSERT INTO `omeka_element_texts` (`id`,`record_id`,`record_type`,`element_id`,`html`,`text`) VALUES (NULL, ".$itemid.",'Item',".$element_gps_id.",0,'".$coords_to_record."')");
                                        
                                    }else{ //sinon on modifie les coordonnées déjà enregistrée à postériorie
                                        if($coords_to_record != $coord){
                                            $result3 = $mysqli->query("UPDATE `omeka_element_texts` SET `text`='".$coords_to_record."' WHERE `id`=".$coord_id);
                                        
                                            
                                        }                                         
                                    }
                                }
			}
			//une fois fini les 5, on passera au 5 suivant
			$cron_lastnum += $increment;
			$result = $mysqli->query("UPDATE `omeka_options` SET `value`=".$cron_lastnum." WHERE `name` LIKE 'cron_gps_lastnum'");
                    }
		}
	}
}
$mysqli->close();
?>