<?php
/**
 * Created by PhpStorm.
 * User: macrovve
 * Date: 12/13/18
 * Time: 1:26 AM
 */

namespace App\Models;


class Geohash
{
    static private $bits = array(16, 8, 4, 2, 1);
    static private $base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
    static private $neighbors = array(
        'top' => array('even' => 'bc01fg45238967deuvhjyznpkmstqrwx',),
        'bottom' => array('even' => '238967debc01fg45kmstqrwxuvhjyznp',),
        'right' => array('even' => 'p0r21436x8zb9dcf5h7kjnmqesgutwvy',),
        'left' => array('even' => '14365h7k9dcfesgujnmqp0r2twvyx8zb',),
    );
    static private $borders = array(
        'top' => array('even' => 'bcfguvyz'),
        'bottom' => array('even' => '0145hjnp'),
        'right' => array('even' => 'prxz'),
        'left' => array('even' => '028b'),
    );

    /**
     * Geohash encode
     * @param   float $latitude
     * @param   float $longtitude
     * @return  string
     */
    static public function encode($latitude, $longtitude){
        /***
        eq('xpssc0', Geohash::encode(43.025, 141.377));
        eq('xn76urx4dzxy', Geohash::encode(35.6813177190391, 139.7668218612671));
         */
        $is_even = true;
        $bit = 0;
        $ch = 0;
        $precision = min((max(strlen(strstr($latitude, '.')), strlen(strstr($longtitude, '.'))) - 1) * 2, 12);
        $geohash = '';

        $lat = array(-90.0, 90.0);
        $lon = array(-180.0, 180.0);

        while(strlen($geohash) < $precision){
            if($is_even){
                $mid = array_sum($lon) / 2;
                if($longtitude > $mid){
                    $ch |= self::$bits[$bit];
                    $lon[0] = $mid;
                } else {
                    $lon[1] = $mid;
                }
            } else {
                $mid = array_sum($lat) / 2;
                if($latitude > $mid){
                    $ch |= self::$bits[$bit];
                    $lat[0] = $mid;
                } else {
                    $lat[1] = $mid;
                }
            }
            $is_even = !$is_even;
            if($bit < 4){
                $bit++;
            } else {
                $geohash .= self::$base32{$ch};
                $bit = 0;
                $ch = 0;
            }
        }
        return $geohash;
    }

    /**
     * Geohash decode
     * @param   string $geohash
     * @return  array
     */
    static public function decode($geohash){
        /***
        list($latitude, $longtitude) = Geohash::decode('xpssc0');
        eq(array(43.0224609375, 43.027954101562, 43.025207519531), $latitude);
        eq(array(141.3720703125, 141.38305664062, 141.37756347656), $longtitude);
         */
        $is_even = true;
        $lat = array(-90.0, 90.0);
        $lon = array(-180.0, 180.0);
        $lat_err = 90.0;
        $lon_err = 180.0;
        for($i=0; $i<strlen($geohash); $i++){
            $c = $geohash{$i};
            $cd = stripos(self::$base32, $c);
            for($j=0; $j<5; $j++){
                $mask = self::$bits[$j];
                if($is_even){
                    $lon_err /= 2;
                    self::refine_interval($lon, $cd, $mask);
                } else {
                    $lat_err /= 2;
                    self::refine_interval($lat, $cd, $mask);
                }
                $is_even = !$is_even;
            }
        }
        $lat[2] = ($lat[0] + $lat[1]) / 2;
        $lon[2] = ($lon[0] + $lon[1]) / 2;

        return array($lat, $lon);
    }

    /**
     * adjacent
     */
    static public function adjacent($hash, $dir){
        /***
        eq('xne', Geohash::adjacent('xn7', 'top'));
        eq('xnk', Geohash::adjacent('xn7', 'right'));
        eq('xn5', Geohash::adjacent('xn7', 'bottom'));
        eq('xn6', Geohash::adjacent('xn7', 'left'));
         */
        $hash = strtolower($hash);
        $last = substr($hash, -1);
        // $type = (strlen($hash) % 2)? 'odd': 'even';
        $type = 'even'; //FIXME
        $base = substr($hash, 0, strlen($hash) - 1);
        if(strpos(self::$borders[$dir][$type], $last) !== false){
            $base = self::adjacent($base, $dir);
        }
        return $base. self::$base32[strpos(self::$neighbors[$dir][$type], $last)];
    }

    static private function refine_interval(&$interval, $cd, $mask){
        $interval[($cd & $mask)? 0: 1] = ($interval[0] + $interval[1]) / 2;
    }

    public static function vincentyGreatCircleDistance(
        $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
    {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $lonDelta = $lonTo - $lonFrom;
        $a = pow(cos($latTo) * sin($lonDelta), 2) +
            pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
        $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

        $angle = atan2(sqrt($a), $b);
        return $angle * $earthRadius;
    }

    static public function checkDistanceValid($note,$latitude,$longitude,$radius)
    {
        $distance=self::vincentyGreatCircleDistance(
            $note->latitude,
            $note->longitude,
            $latitude,
            $longitude);
        if($distance<=$note->radius && $distance<=$radius)
            return true;
        return false;
    }
}
