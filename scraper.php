<?php
try {
    // open or create data.sqlite database
    $file_db = new PDO('sqlite:data.sqlite');
    $file_db->setAttribute(PDO::ATTR_ERRMODE, 
                           PDO::ERRMODE_EXCEPTION);
    $file_db->exec("CREATE TABLE IF NOT EXISTS data (
                    name TEXT, 
                    location TEXT, 
                    website TEXT,
                    lat REAL, 
                    lon REAL,
                    rating TEXT,
                    contact TEXT)");
    // copy that database to memory so we can remove entries that do not exist anymore in the table
    $mem_db = new PDO('sqlite::memory:');
    $mem_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $mem_db->exec('ATTACH "data.sqlite" as filedb');
    $mem_db->exec('CREATE TABLE data AS SELECT * FROM filedb.data');
    $mem_db->exec('DETACH filedb');
}
catch(PDOException $e) {
    // Print PDOException message
    die ($e->getMessage());
}


require 'scraperwiki.php';
$html = scraperWiki::scrape("http://wiki.fablab.is/wiki/Portal:Labs");       
require 'scraperwiki/simple_html_dom.php';  
$dom = new simple_html_dom();
$dom->load($html);
$i = 0;
$notLocated = array();
foreach($dom->find("#content .wikitable tr") as $data)
{
    //if($i++ > 3) break;

    $tds = $data->find("td");
    if(count($tds) == 0) continue;

    $country = trim($tds[1]->plaintext);
    $city = trim($tds[2]->plaintext);
    $combinedLocation = $country.", ". $city;

    //figure out if this location exists in the db already, and if so. remove from the memoryDB
    $stmt = $file_db->prepare("select * from data where name LIKE :name");
    $stmt->bindParam(':name', $name, PDO::PARAM_STR);
    echo ($stmt->execute());
    
    if (count($stmt->fetchall())>0) {
        echo ("location: ".$combinedLocation." already in database\n");
        $stmt = $mem_db->prepare("delete from data where name LIKE :name");
        $stmt->bindParam(':name', $combinedLocation, PDO::PARAM_STR);
        $stmt->execute();
        continue;
    }
    echo ("location: ".$name." not yet in database, lets add\n");
    
    $combinedLocationQuery = strip_tags($combinedLocation);
    $combinedLocationQuery = htmlentities($combinedLocationQuery, ENT_QUOTES);
    
    $combinedLocationQuery = urlencode($combinedLocationQuery);
    $locationName = trim(strip_tags($tds[3]->plaintext));
    $website = $tds[4]->plaintext;
    $rating = (count($tds) >= 6)? $tds[5]->plaintext : "";
    $contact = (count($tds) >= 7)? $tds[6]->plaintext : "";

    //echo "$locationName\n";

    $lat = "";
    $lng = "";
    //$geocode_url = 'http://open.mapquestapi.com/nominatim/v1/search?format=json&q=';
    $geocode_url = "http://where.yahooapis.com/v1/places.q('";
    $app_id = "')?format=JSON&appid=DX4mM4PV34ESO96yg70UGL5nu87SZ.gLXnubndwBjFvVp6_6LlnRfyd7Co_4s_W1q3se1LE-";
    //print("    geocode_url: ".$geocode_url.$combinedLocationQuery.$app_id."\n");
    $geoResult = file_get_contents($geocode_url.$combinedLocationQuery.$app_id);
    $geoJSON = json_decode($geoResult);
    ///print $geoJSON->{'places'}->{'count'};
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
        print("geocode_url: ".$geocode_url.$combinedLocationQuery.$app_id."\n");
        $notLocated[] = "$locationName ($combinedLocation) ($combinedLocationQuery)";
        continue;
    }

    $fablab = array(
        'name' => $locationName,        
        'location' => $combinedLocation,
        'website' => $website,
        'lat' => $lat,
        'lon' => $lng,
        'rating' => $rating,
        'contact' => $contact
    );
    $insert = "INSERT INTO data (name, location, website, lat, lon, rating, contact) 
                VALUES (:name, :location, :website, :lat, :lon, :rating, :contact)";
    $stmt = $file_db->prepare($insert);
    $stmt->execute($fablab);
    

    sleep((1000+rand(0,3000))/2000);

}

$stmt = $mem_db->prepare("select * from data");
$stmt->execute();
echo "stored locations no longer in table: ".count($stmt->fetchall())."\n";
echo "unable to locate" .count($notLocated)." locations\n";
if (count($notLocated)>0) {
    $notLocatedString = implode("\n",$notLocated);
    print($notLocatedString);
}


$file_db=null;
$mem_db=null;

?>
