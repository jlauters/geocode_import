<?php

// load config file
require 'config.php';

// load db_connection class
require 'mysql_connect.php';

if(!mysql_db::factory()->connect($config)) {
    error_log('db connection error .. exiting script');
    die();
}

$create = <<<EOSQL
CREATE TABLE IF NOT EXISTS `zipcode_lat_lng` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `zipcode` varchar(255) DEFAULT NULL,
    `lat` float DEFAULT NULL,
    `lng` float DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
EOSQL;

mysql_db::factory()->query($create);

// Upload Import File
if(isset($_FILES['file'])) {
    if(0 === $_FILES['file']['error']) {
 
        // check all of our acceptable .csv MIME types
        $mimes = array('text/csv', 'application/vnd.ms-excel', 'text/plain', 'text/tsv');
        if(in_array($_FILES['file']['type'], $mimes)) {
            move_uploaded_file($_FILES['file']['tmp_name'], $_FILES['file']['name']);

            /* clean up line endings */
            $file = file_get_contents($_FILES['file']['name']);
            $file = str_replace("\r", "\n", $file);
            file_put_contents($_FILES['file']['name'], $file);

            parse_csv($_FILES['file']['name']);

            // remove the uploaded file after we're done
            unlink($_FILES['file']['name']);
        } else {
            error_log('file is not of type .csv');
            exit;
        }

    } else {
        error_log('Error with file submission');
        exit;
    }
}

/* function parse_csv( $filename )
 * opens input file
 * you will provide your own custom parse
 * inputs zipcode / lat / lng into table
 */
function parse_csv($filename) {

    if(file_exists($filename)) {
        if(FALSE !== ($handle = fopen($filename, 'r'))) {
            while(($data = fgetcsv($handle)) !== FALSE) {

                /* PARSE CSV DATA HERE */

                /* END CSV DATA PARSE */

                // set from $data
                $zipcode = intval(00000);
                $lat = $lng = 0.0;

                $geocode = check_extant_geocode($zipcode);
                if(empty($geocode['lat']) || empty($geocode['lng'])) {

                    $geo_json = file_get_contents('http://maps.google.com/maps/api/geocode/json?components=postal_code:'.$zipcode.'&sensor=false');
                    if($geo_json) {
                        $geo = json_decode($geo_json, true);
                        if(isset($geo['results'][0])) {
                            $lat = $geo['results'][0]['geometry']['location']['lat'];
                            $lng = $geo['results'][0]['geometry']['location']['lng'];
                        } else {
                            error_log('API CALL LIMIT REACHED ... EXITING SCRIPT');
                            exit;
                        }
                    }
                   
                }

                $zipcode_lat_lng_row = array(
                    'zipcode' => $zipcode
                   ,'lat'     => $lat
                   ,'lng'     => $lng
                );

                mysql_db::factory()->insert('zipcode_lat_lng', $zipcode_lat_lng_row);
            } 
        }
    }
}

/* function check_extant_geocode( $zipcode )
 * lookup function to prevent duplicate
 * geocode API calls
 */
function check_extant_geocode($zipcode) {

    $return_array = array('lat' => 0.0, 'lng' => 0.0);
    if($zipcode) {
        $sql = <<<EOSQL
SELECT lat, lng 
FROM zipcode_lat_lng 
WHERE zipcode = {$zipcode}
EOSQL;

        $row = mysql_db::factory()->query($sql);
        if(!empty($row)) {
            $return_array['lat'] = $row[0]['lat'];
            $return_array['lng'] = $row[0]['lng'];
        }
    }

    return $return_array;
}
