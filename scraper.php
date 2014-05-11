<?php
require 'scraperwiki.php';

$html = scraperWiki::scrape("http://wiki.fablab.is/wiki/Portal:Labs");       
require 'scraperwiki/simple_html_dom.php';  
$dom = new simple_html_dom();
$dom->load($html);
$i = 0;
$notLocated = array();
foreach($dom->find("#content .wikitable tr") as $data)
{
    /*if($i >= 0)
    {
    echo("i: $i \n");*/
    $tds = $data->find("td");
    if(count($tds) == 0) continue;
    
    $country = trim($tds[1]->plaintext);
    $city = trim($tds[2]->plaintext);
    $combinedLocation = $country.", ". $city;
    //print("combinedLocation: ".$combinedLocation);
    //$combinedLocation = str_replace(";"," ",$combinedLocation);
    $combinedLocationQuery = strip_tags($combinedLocation);
    $combinedLocationQuery = htmlentities($combinedLocationQuery, ENT_QUOTES);
    
    //$combinedLocationQuery = preg_replace('/^ | $|  |\r|\n/i',"",$combinedLocationQuery);
    //$combinedLocationQuery = preg_replace('/[,;]/i',"",$combinedLocationQuery);    
    $combinedLocationQuery = urlencode($combinedLocationQuery);
    //print(">    combinedLocation: ".$combinedLocation."\n");
    $locationName = trim(strip_tags($tds[3]->plaintext));
    $website = $tds[4]->plaintext;
    $rating = (count($tds) >= 6)? $tds[5]->plaintext : "";
    $contact = (count($tds) >= 7)? $tds[6]->plaintext : "";
    
    $lat = "";
    $lng = "";
    //echo "$locationName\n";
    $geocode_url = "http://where.yahooapis.com/v1/places.q('";
    $app_id = "')?format=JSON&appid=DX4mM4PV34ESO96yg70UGL5nu87SZ.gLXnubndwBjFvVp6_6LlnRfyd7Co_4s_W1q3se1LE-";
    //$geocode_url = 'http://open.mapquestapi.com/nominatim/v1/search?format=json&q=';
    print("    geocode_url: ".$geocode_url.$combinedLocationQuery.$app_id."\n");
    $geoResult = file_get_contents($geocode_url.$combinedLocationQuery.$app_id);
    //$geoResult = utf8_encode($geoResult); 
    $geoJSON = json_decode($geoResult);
    ///print $geoJSON->{'places'}->{'count'};
    //var_dump($geoJSON, true);
    //print("    responce: ".$geoJSON."\n");
    if($geoJSON->{'places'}->{'count'} > 0)
    {
        $plObj = $geoJSON->{'places'}->{'place'}[0];
        $place = $plObj->name;
        /*print("\nplace: \n");
          print_r($place);*/
        $lat = $plObj->centroid->latitude;
        $lng = $plObj->centroid->longitude;
        print($i." located ".$locationName." (".$lat." x ".$lng.")\n");
    }
    else
    {
        echo "Can't locate: $locationName ($combinedLocation) ($combinedLocationQuery)\n";
        $notLocated[] = "$locationName ($combinedLocation) ($combinedLocationQuery)";
    }

    $fablab = array(
        'name' => $locationName,        
        'location' => $combinedLocation,
        'website' => $website,
        'lat' => $lat,
        'lng' => $lng,
        'rating' => $rating,
        'contact' => $contact
    );

    scraperwiki::save(array('name','location'), $fablab);
    
    //sleep((1000+rand(0,5000))/1000);

    $i++;
    if($i > 3) break;
}
print("Can't locate:\n");
$notLocatedString = implode("\n",$notLocated);
print($notLocatedString);
?>
