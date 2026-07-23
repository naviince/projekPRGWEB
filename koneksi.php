<?php
$serverName = "LAPTOP-FB8R0UH0";
$serverName = "ELVINA-PARAMITA";
$serverName = "SATYAA\SATYASERVER";

$connectionOptions = array(
    "Database" => "SpotLight",
    "TrustServerCertificate" => true
);
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}
?>